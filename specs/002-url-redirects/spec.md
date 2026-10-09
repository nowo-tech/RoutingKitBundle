# RoutingKitBundle — URL redirect manager (002)

## Goal

Let operators retire public URLs (old site, renamed pages, deleted posts) without a deploy: an old path is answered with an HTTP redirect to a new path or an absolute URL, managed from the RoutingKit panel. Ported and generalized from an application implementation (`App\Shared\Redirect`).

## Non-goals

- Wildcard / regex sources, host-specific redirects, redirect rules by query string.
- Shipping a Doctrine entity (the bundle stays Doctrine-free; hosts plug their own storage).
- Replacing the canonical / root redirect subscribers (`redirects.*`).

## Configuration

Opt-in under `nowo_routing_kit.url_redirects` (`enabled: false` by default). Keys: `storage`, `file`, `cache_pool`, `cache_ttl`, `protected_prefixes`, `protected_starts`, `hits.enabled`, `hits.message_bus`. The node is named `url_redirects` because `redirects.*` already configures canonical / root redirects.

## Domain model — `UrlRedirect`

| Field | Description |
| --- | --- |
| `id` | Storage id (string; filesystem: `rd_` + 16 hex) |
| `sourcePath` | Normalized old path (unique) |
| `targetUrl` | Internal path or absolute `http(s)://` URL |
| `statusCode` | 301 (default), 302, 307, 308 |
| `enabled` | Soft disable |
| `hits`, `lastHitAt` | Hit counter |
| `note` | Free text (≤255) |
| `createdAt`, `updatedAt`, `createdBy`, `updatedBy` | Audit; `*By` is the panel user's identifier string (no User FK) |

Normalization (`normalizePath`): percent-decode, drop query and fragment, ensure one leading `/`, trim trailing `/` (except root), keep case.

## Requirements

- **REQ-REDIR-001 Storage.** `UrlRedirectStorageInterface` (`all`, `findById`, `findBySource`, `enabledMap`, `save`, `delete`, `recordHit`). Default `FilesystemUrlRedirectStorage`: JSON, shared/exclusive `flock`, atomic rename, corrupt JSON fails closed, duplicate source rejected. `url_redirects.storage` aliases a host service.
- **REQ-REDIR-002 Protected paths.** `ProtectedPaths` refuses `/`, whole-segment `protected_prefixes` (plus `panel.path_prefix`) and raw `protected_starts`, case-insensitively, also behind a `/{locale}` segment whose locale comes from `LocaleProviderInterface` (only when more path follows the locale). Configured lists replace the defaults.
- **REQ-REDIR-003 Live pages.** `LivePublicPaths` matches the path as a `GET` with the router (method restored afterwards). No match → not live. Match → tagged `LivePathCheckerInterface` services answer `true` / `false` / `null` (abstain) for the matched route; no answer → live; a throwing checker → live.
- **REQ-REDIR-004 Matching and cache.** `UrlRedirectMatcher` loads `enabledMap()` through `cache_pool` (TTL `cache_ttl`), memoizes per request, `invalidate()` on panel writes, `reset()` via `kernel.reset`. Cache / storage / bus failures log a warning and behave as "no redirect" / "hit not recorded".
- **REQ-REDIR-005 Request listener.** `UrlRedirectSubscriber` at `kernel.request` priority 33 (before `RouterListener`): main request, `GET`/`HEAD`; skip protected; match; ignore unsafe targets (internal-looking targets must pass `isInternalTarget`, others `isExternalTarget`); skip live paths; append the visitor query string only to internal targets without `?`; record hit; respond with the stored status.
- **REQ-REDIR-006 Validation.** `UrlRedirectValidator` (panel): source `^/[^\s?#\\]*$`, ≤255, no controls; target internal or external as above, ≤500; status in 301/302/307/308; note ≤255; then protected → live → loop (target path = source) → chain (target path is another row's source) → duplicate source. Errors are translation keys per field.
- **REQ-REDIR-007 Panel.** `UrlRedirectPanelController` under `{panel.path_prefix}`: `GET /redirects` (paginated by `panel.list_page_size`, `?status=created|saved|deleted` notice), `GET|POST /redirects/new`, `GET|POST /redirects/{id}/edit`, `GET|POST /redirects/{id}/delete` (GET = confirmation page, POST = delete). Panel guard (`security.*`); CSRF on every write (`nowo_routing_kit_url_redirect`, `nowo_routing_kit_url_redirect_delete_{id}`); 404 when the feature or the panel is disabled. Templates extend the panel base / `web_ui.layout_template`, contain no inline script or style (overrides use the `csp_nonce` request attribute). Translations in every shipped locale.
- **REQ-REDIR-008 Hits.** `hits.enabled` toggles counting. With `hits.message_bus`, a `RecordRedirectHit(sourcePath, at)` message is dispatched and `RecordRedirectHitHandler` (tagged only then) calls `recordHit`; otherwise the storage is written synchronously.

## Acceptance (tests)

- Unit: model normalization / target safety / array round trip; filesystem storage CRUD, ordering, uniqueness, hits, corrupt files; protected paths (defaults, locale prefix, custom lists); live paths (checkers, failures, context restore); matcher (cache, invalidate, bus, failures); validator rules; subscriber (query handling, HEAD/POST, sub-requests, live page wins, unsafe targets); controller (CRUD, CSRF, 404/403, audit, translated errors); DI (opt-in removal, wiring, bus/handler, panel disabled).
- Integration (`tests/Integration/Redirect`): micro-kernel with FrameworkBundle, TwigBundle, UiKit, FormKit — create through the real form, public redirect with query, case sensitivity, POST not redirected, hit counted, protected / live / slug checker / loop / chain / duplicate errors rendered, edit, delete confirmation + forged token, accented sources, live page wins over a stale row.
