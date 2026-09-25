# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/routing-kit-bundle` (`symfony-bundle`) |
| Audited revision | `v1.4.5` (includes remediations on top of `468a23d`) |
| Audit date | 2026-09-25 |
| Method | Manual review of every file under `src/` (services, subscribers, controller, forms, DI extension, compiler passes, `Resources/config`) plus the Symfony `Router` code it relies on (`vendor/symfony/routing` v8.1.6) |
| **Verdict** | ✅ **Viable under scenario B** — no per-request state leaks; panel edits propagate to every worker sharing the cache directory on its next main request through a route-table version file |
| Remediation (2026-09-23) | W-01 resolved (`RouteCacheInvalidator` version file + in-process router reset, `EventSubscriber/RouteTableFreshnessSubscriber`); W-02 resolved (`LOCK_SH` reads, single storage load per request); W-03 resolved (`DbRouteLoader` implements `ResetInterface`). Tests: `tests/Unit/Routing/WorkerRouteTableSyncTest.php` (two workers, real FrameworkBundle `Router` + `DbRouteLoader`), `tests/Unit/Routing/RouteCacheInvalidatorTest.php`, `tests/Unit/Routing/DbRouteLoaderTest.php`, `tests/Unit/EventSubscriber/RouteTableFreshnessSubscriberTest.php` |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests, so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | `DbRouteLoader::$loaded` (guard, reset with the router, `ResetInterface`) and `RouteCacheInvalidator::$appliedVersion` (route-table version this worker's router was built from; intentionally worker-lifetime). No request data is stored |
| Static properties / `static` locals | ✅ | None. `SafePublicPath` and `RoutePathDefinition::fromArray()` are pure static methods |
| `ResetInterface` / `kernel.reset` coverage | ✅ | `DbRouteLoader` implements `ResetInterface`; correctness does not depend on `kernel.reset` |
| Request / user / locale captured in services | ✅ | Request comes as a method argument; the security token is read from `TokenStorageInterface` at call time |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | None used; configuration is compiled into container parameters |
| Doctrine / EntityManager | ✅ N/A | Default storage is a JSON file; no Doctrine code in the bundle |
| Output, headers, `exit`, shutdown functions | ✅ | None; responses are Symfony `Response` objects |
| Resources (files, sockets, cURL) held open | ✅ | The storage lock file handle is opened and closed inside each call (`try/finally`) |
| Memory growth across requests | ✅ | No caches or accumulating arrays in services |
| Blocking I/O and timeouts | ✅ | Reads use `LOCK_SH`; `CanonicalRedirectSubscriber` reads the storage once per main request plus one small version-file read (W-02) |
| Third-party static state | ✅ | Symfony `Router` memoization (collection, matcher, generator, per-thread compiled-routes cache) is dropped when the route-table version changes (W-01) |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker.neon` included in `phpstan.neon.dist` |

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `Storage\FilesystemRoutePathStorage` | yes | none (`readonly` file path); reads the file on every call | ✅ | ✅ |
| `Routing\DbRouteLoader` (`routing.loader`) | yes | `private bool $loaded` (guard; `reset()`) | ✅ | ✅ (W-03 resolved) |
| `Routing\RouteCacheInvalidator` | yes | applied route-table version (worker lifetime, by design) | ✅ | ✅ (W-01 resolved) |
| `EventSubscriber\RouteTableFreshnessSubscriber` | yes | none | ✅ | ✅ |
| `Routing\PublicPathResolver` | yes | none | ✅ | ✅ |
| `Discovery\RoutableControllerDiscovery` | yes | none (re-scans on every call, no cache) | ✅ | ✅ |
| `Validation\RoutePathValidator` | yes | none | ✅ | ✅ |
| `Service\RoutePathManager` | yes | none | ✅ | ✅ |
| `Service\RoutePathConflictDetector` | yes | none | ✅ | ✅ |
| `Service\RoutePathImportExport` | yes | none (`readonly` signing key) | ✅ | ✅ |
| `Locale\ConfigurableLocaleProvider` | yes | none | ✅ | ✅ |
| `Security\PanelAccessGuard` | yes | none; token read per call (`src/Security/PanelAccessGuard.php:35`) | ✅ | ✅ |
| `Security\ConfigurableRoutingKitAccessChecker` / `AllowAllRoutingKitAccessChecker` | yes | none (`final readonly` / no properties) | ✅ | ✅ |
| `EventSubscriber\CanonicalRedirectSubscriber` | yes | none | ✅ (see W-02) | ✅ (see W-02) |
| `EventSubscriber\RootRedirectSubscriber` | yes | none | ✅ | ✅ |
| `EventSubscriber\RoutePathAuditSubscriber` | yes | none; token read per event (`src/EventSubscriber/RoutePathAuditSubscriber.php:51`) | ✅ | ✅ |
| `Controller\RoutingPanelController` | yes (public) | none; forms are created per action | ✅ | ✅ |
| `Twig\RoutingKitTwigExtension` | yes | none; globals are constant config strings | ✅ | ✅ |
| `Seo\RoutingKitSeoPathProvider` / `Seo\RoutingKitSeoPathBuilderDecorator` | yes | none | ✅ | ✅ |
| 3 form types (`Form\*Type`) | yes | stateless | ✅ | ✅ |

`RoutePathDefinition` is a `readonly` value object created per call and never stored in a service. Compiler passes (`TwigPathsPass`, `SeoKitBridgePass`, `PanelAccessGuardPass`) only run at container compile time.

## Findings

### W-01 — Panel edits do not reach running workers; the route cache is deleted but not rebuilt in-process (Medium)

- **Where:** `src/Routing/RouteCacheInvalidator.php:29-58`, called from `RoutePathManager::save()` / `import()` / `delete()` / `clearCache()` (`src/Service/RoutePathManager.php:67-69`, `116-118`, `131-133`, `136-139`) and from `RoutingPanelController::import()` (`src/Controller/RoutingPanelController.php:223`).
- **Worker impact:** the invalidator deletes `url_matching_routes.php` / `url_generating_routes.php` and then calls `Router::warmUp()`. In a worker, the `router` service is shared and not resettable (no `ResetInterface`), and Symfony memoizes its state: `Router::getRouteCollection()` (`vendor/symfony/routing/Router.php:142`, `vendor/symfony/framework-bundle/Routing/Router.php:72`), `getMatcher()` (`vendor/symfony/routing/Router.php:196`) and `getGenerator()` (`:238`) return the instance built on the first request. `warmUp()` (`vendor/symfony/framework-bundle/Routing/Router.php:99-100`) only calls those getters, so in the panel request (where routing already ran) it is a no-op and the cache files stay deleted. Consequences, in both scenario A and B:
  - every running worker (including the one that handled the edit) keeps matching and generating the **old** paths: new paths return 404, removed or renamed paths keep working, and `path()` in Twig generates old URLs;
  - `CanonicalRedirectSubscriber` reads the storage fresh on every request, so it can redirect to a canonical path the stale matcher does not know, producing a 404 after a 301;
  - workers restarted at different times (crash, `max_requests`, deploy) rebuild the cache from the new file, so different workers can serve different route tables at the same time.
  This is stale data, not a cross-user leak, so it stays Medium.
- **Recommendation:** document that a path change needs a worker restart (Caddy admin API `POST /frankenphp/workers/restart` on recent FrankenPHP versions, or a container restart) and, in the bundle, either trigger that restart after invalidation or keep a generation counter in the storage file and compare it on `kernel.request` (then rebuild the router, e.g. by using a fresh `Router` instance or telling integrators to reboot the kernel). At minimum, make `warmUp()` effective by using a router whose memoized matcher/generator can be cleared.
- **Status:** Resolved — cross-worker invalidation by version key:
  - `src/Routing/RouteCacheInvalidator.php`: `invalidate()` deletes the cache files (as before), writes a random token atomically (temp file + `rename`) to `%kernel.cache_dir%/nowo_routing_kit_route_table.version`, then resets the router in-process **before** `warmUp()`, so the editing worker regenerates the cache files from the new storage. The reset drops `collection`, `matcher` and `generator` on Symfony's FrameworkBundle `Router` and removes this thread's `Router::$cache` entries for the two compiled files (used when OPcache is off); routers implementing `ResetInterface` get `reset()`; any other router logs a warning (restart still required). It also calls `DbRouteLoader::reset()`.
  - `refreshIfStale()` compares the version file with the version this worker's router was built from; on the first request of a worker it only records it.
  - New `src/EventSubscriber/RouteTableFreshnessSubscriber.php` (`kernel.request`, priority 4096, main requests only, before `RouterListener` 32 and `CanonicalRedirectSubscriber` 33) calls `refreshIfStale()`, so every worker serves the new table from its next request. Cost per request: one read of a ~32-byte file.
  - Test `WorkerRouteTableSyncTest` runs two independent worker stacks (FrameworkBundle `Router`, `DbRouteLoader`, filesystem storage, shared cache dir), seeds worker B's compiled-routes cache with the old table, and checks that after an edit in worker A, worker B matches and generates the new path on its next request and 404s the old one, without any reset.
  - Residual: (1) scope is one kernel cache directory; hosts with separate cache dirs and a shared storage file still need a cache clear/restart per host (documented in `docs/UPGRADING.md`). (2) A request already in flight in another worker during the edit finishes with the old table. In the rare case where that worker builds its matcher or generator for the first time inside the few milliseconds between the file deletion and the editing worker's `warmUp()`, it dumps the compiled file from its old collection and may overwrite the fresh one; the stale file then persists until the next panel change or `cache:clear`. Clicking "clear cache" in the panel repairs it.

### W-02 — Every main request reads the storage file repeatedly under an exclusive lock (Low)

- **Where:** `src/EventSubscriber/CanonicalRedirectSubscriber.php:50-56` calls `storage->all()` and then `PublicPathResolver::resolveDefinition()` for each stored row × each locale; `resolveDefinition()` calls `storage->find()` one or two times (`src/Routing/PublicPathResolver.php:32`, `42`). Every storage call takes `flock($handle, LOCK_EX)` and re-reads and decodes the whole JSON file (`src/Storage/FilesystemRoutePathStorage.php:149-171`, `176-212`), including pure reads.
- **Worker impact:** no state leaks, but with the default `max_definitions: 500` and several locales one request can do thousands of lock/read/decode cycles. Because reads use an exclusive lock, all worker threads serialize on the same lock file on every request, so throughput drops as the thread count grows. A slow filesystem (network volume) blocks threads with no timeout.
- **Recommendation:** use `LOCK_SH` for read-only operations, and in the subscriber load the storage once per request (e.g. `all()` once and resolve fallbacks from that in-memory list). If a cross-request cache is added, key it by file `mtime`/size so it cannot go stale in a long-lived worker.
- **Status:** Resolved — `src/Storage/FilesystemRoutePathStorage.php` uses `LOCK_SH` for `all()`, `find()`, `findById()`, `findByRouteName()` (writes keep `LOCK_EX` and atomic `rename`). `CanonicalRedirectSubscriber` and `DbRouteLoader` call `all()` once and pass an index (`PublicPathResolver::indexDefinitions()`) to `PublicPathResolver::resolveDefinition()`, so a request does one storage read instead of one per row × locale. No cross-request cache was added.

### W-03 — `DbRouteLoader` one-shot guard is never cleared (Info)

- **Where:** `src/Routing/DbRouteLoader.php:35`, `51-54`.
- **Worker impact:** `load()` throws `RuntimeException('Do not add the RoutingKit loader twice.')` on its second call in the same container. With the stock Symfony router this does not happen in a worker, because the route collection is memoized after the first load (see W-01). It would surface if a fix for W-01 re-runs `routing.loader` inside the same worker, or if integrator code loads routes twice in one process.
- **Recommendation:** if W-01 is fixed by reloading routes in-process, reset this flag (implement `ResetInterface` or clear it when the collection is rebuilt).
- **Status:** Resolved — `DbRouteLoader` implements `ResetInterface`; `RouteCacheInvalidator` calls `reset()` whenever it drops the router state (test `DbRouteLoaderTest::testResetAllowsReloadingAfterPathChange`).

No other findings. The discovery service re-scans `scan_dirs` with Finder and Reflection on every call without caching; this costs CPU on panel and route-build requests but avoids stale data. Classes autoloaded by the scan stay in worker memory, which is bounded by the size of the codebase.

## Usage recommendations in worker mode

- Panel edits, imports, deletes and "clear cache" are applied by every worker that shares the kernel cache directory on its next main request; no restart is needed. With several hosts, clear the cache or restart workers on each host.
- If the `router` service is decorated by a class that is neither Symfony's FrameworkBundle `Router` nor `ResetInterface`, the bundle logs a warning and workers must be restarted after path changes.
- In production, prefer managing paths at deploy time (edit, commit the JSON file, deploy) instead of editing live in the panel.
- Keep `max_definitions` and the number of locales modest; each main request walks all rows (W-02). Put the storage file on local disk, not on a network volume.
- Custom `RoutePathStorageInterface` or `LocaleProviderInterface` implementations must stay stateless, or cache with a freshness check (file `mtime`, DB version column) and implement `ResetInterface`, to keep the rest of this verdict.
- The demo (`demo/symfony8`) runs in worker mode by default (`FRANKENPHP_MODE=worker`, `worker { … }` block in `demo/symfony8/docker/frankenphp/Caddyfile:15`).

## Re-audit triggers

Re-run this audit when a change adds: properties or caches to the storage, resolver, discovery or subscribers; a different route-cache invalidation strategy; a Doctrine storage implementation; an event listener that keeps data between requests; or any use of `$_SERVER` / `$_ENV` at runtime.
