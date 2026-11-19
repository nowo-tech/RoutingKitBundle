# Concurrency (REQ-RUNTIME-003)

How RoutingKit stays correct when two operators, two workers or a request and a Messenger consumer write the same
data at the same time.

## Table of contents

- [Storage model](#storage-model)
- [Write paths](#write-paths)
- [Conflict codes](#conflict-codes)
- [Host options](#host-options)
- [Host storages](#host-storages)
- [Multi-server deployments](#multi-server-deployments)
- [Worker mode](#worker-mode)
- [Residual risks](#residual-risks)
- [Tests](#tests)

## Storage model

The default stores are JSON files, not a database. They are `FilesystemRoutePathStorage` (`storage.paths_file`) and
`FilesystemUrlRedirectStorage` (`url_redirects.file`). Both work the same way:

- **Every write** takes an exclusive `flock()` on `{file}.lock`. It reads the file, re-checks the uniqueness rule and
  writes a temporary file, then renames it over the store. The rename is atomic, so a reader never sees a torn file.
- **Reads** take a shared lock. Readers do not block each other.
- **Uniqueness** is checked under the exclusive lock, never only before it:
  - one path per route + locale;
  - one redirect per source path.

  A losing writer gets a `RuntimeException` ("already exists"). The panel shows it as a form error.
- **Lock waits are bounded** (since this release). The lock is tried without blocking, again and again with a short
  growing pause (1 ms up to 50 ms), until `storage.lock_timeout` runs out (default 5 s). Then the store throws
  `StorageLockTimeoutException`. Nothing was read or written by that call. Before, the wait had no limit, so a stuck
  holder could hang every worker.
- **Corrupt JSON fails closed.** The next save does not wipe the file.

There is no database transaction and no Doctrine EntityManager. Rules 1–3 of REQ-RUNTIME-003 (transaction runner,
host EntityManager, nested transactions) do not apply to the default stores.

## Write paths

| Path | Atomic unit | Control (rule 4) | Conflict answer (rule 6) |
|---|---|---|---|
| Panel: create / edit a path (`RoutePathManager::save`) | One `save()` under the exclusive lock | Route + locale re-checked under the lock | 409 `CONFLICT_001` |
| Panel: delete a path (`RoutePathManager::delete`) | One `delete()` | Delete by id; deleting a missing id is a no-op | 409 `CONFLICT_001` |
| Panel: import, replace all (`RoutePathManager::import(..., true)`) | One `replaceAll()` | Duplicates in the payload refused under the lock | 409 `CONFLICT_001` |
| Panel: import, merge (`RoutePathManager::import(..., false)`) | One `save()` per row: **not atomic as a whole** | Each row re-checked under the lock | 409 `CONFLICT_002` once a row was saved, else `CONFLICT_001` |
| Panel: create / edit a redirect (`UrlRedirectManager::save`) | One `save()` | Source path re-checked under the lock; a losing writer gets a field error | 409 `CONFLICT_001` |
| Panel: delete a redirect (`UrlRedirectManager::delete`) | One `delete()` | Delete by id | 409 `CONFLICT_001` |
| Panel: list, form and preview pages (reads) | Shared lock | None needed | 409 `CONFLICT_001` |
| Redirect hit, synchronous (`UrlRedirectMatcher::flushHits`, at `kernel.terminate`) | One `recordHit()` | `hits + 1` under the exclusive lock: no lost increment | None: the response is already sent; a failed hit is logged and dropped |
| Redirect hit, Messenger (`RecordRedirectHitHandler`) | One `recordHit()` | As above | The failure propagates, so Messenger retries the message. Not idempotent (statistics only) |
| Host routes: redirect matching, canonical redirects, route loading (reads) | Shared lock | None needed | Never answered by the bundle (see below) |

Events (`RoutePathDefinitionChangedEvent`, `UrlRedirectChangedEvent` and the deprecated `RoutePathsChangedEvent`) are
dispatched after the store write has returned. The cache is invalidated after the write too.

## Conflict codes

A `StorageLockTimeoutException` (or any `Nowo\TransactionContract\TransientStorageFailure`, see
[Host storages](#host-storages)) reaching the kernel on one of the **bundle's own admin routes** is answered by
`ConcurrencyConflictSubscriber`:

| Situation | Status | `X-Conflict-Code` | `Retry-After` |
|---|---|---|---|
| Nothing was written in this request | 409 (or `conflict_response.status`) | `CONFLICT_001` | `conflict_response.retry_after` (default 1), or the failure's larger hint |
| The request already wrote something | 409 (or `conflict_response.status`) | `CONFLICT_002` | none: reload, do not repeat |

- **What "already wrote" means.**
  - A filesystem store wrote since the main request started. `StorageWriteLedger` is a monotonic counter; a mark is
    taken at `kernel.request` and compared at `kernel.exception`. Redirect hit counts are statistics and are not
    counted, so a leftover hit flushed at the start of a request never turns its conflict into `CONFLICT_002`.
  - Or, when the host runs nowo-tech/transaction-doctrine ≥ 1.6 with `commit_detection`, its request `CommitMark` saw
    a database commit. This covers the host's own writes earlier in the request.
- **How the answer is rendered.** It is an `HttpException`, so the host's error page renders it, in HTML or JSON. The
  headers come along: `X-Conflict-Code`, `Retry-After` for 001, and `Cache-Control: no-store`.
  - The message is generic. The lock path stays in the exception's `lockPath` property, for the logs only.
  - The header only ever carries `CONFLICT_001` or `CONFLICT_002`.
- **Only the bundle's routes are answered.** The subscriber checks that `_controller` belongs to
  `Nowo\RoutingKitBundle\Controller\`. On host routes the exception is left alone. The bundle's own host-route readers
  already fail open:
  - the redirect matcher logs and serves no redirect;
  - the canonical redirect subscriber logs and skips.
- **What is not a conflict.**
  - A duplicate route + locale or source path. It stays a form error (domain refusal).
  - I/O failures: unwritable directory, corrupt JSON, a lock that fails for another reason than "would block".
  - These are never mapped to `CONFLICT_*`.

## Host options

```yaml
nowo_routing_kit:
    storage:
        lock_timeout: 5.0        # seconds a read or write waits for {file}.lock; 0 = try once (max 60)
    conflict_response:
        status: 409              # or 503
        retry_after: 1           # seconds, sent with CONFLICT_001 only
```

`storage.lock_timeout` applies to both JSON stores (paths and `url_redirects`).

## Host storages

`storage.path_storage` and `url_redirects.storage` replace the JSON files, for example with a Doctrine repository.
Such a store owns its own concurrency. To get the same answers:

- Throw `Nowo\TransactionContract\TransientStorageFailure` (nowo-tech/transaction-contract) for a deadlock, a lock-wait
  timeout or a serialization failure. Never throw it for a unique violation or a lost connection. The admin routes then
  answer 409 + `X-Conflict-Code`, with the `Retry-After` hint you pass.
- Call `StorageWriteLedger::record()` after each commit, or run transaction-doctrine's commit detection. A later
  conflict in the same request then answers `CONFLICT_002`.
- Keep "one row per route + locale" and "one redirect per source" with a unique key. Turn the violation into the
  `RuntimeException` the interfaces document.

## Multi-server deployments

`flock()` is advisory and local to one kernel:

- **Several hosts** (several app servers, or containers on different nodes) that share the JSON files do **not**
  exclude each other. Two of them can read the same file and both rename their copy, and one update is lost.
- **Network filesystems.** Most of them either ignore `flock()` or emulate it unreliably: NFS (depending on version and
  mount options), SMB/CIFS, many FUSE mounts, and object-storage mounts. `rename()` may also not be atomic there.
- **Several workers or containers on one host,** sharing a local volume, are safe.

On more than one server, use a host storage backed by your database (`storage.path_storage`,
`url_redirects.storage`), or keep the JSON files on a single writer. The route-table freshness marker
(`RouteCacheInvalidator`) is a file too: put `kernel.cache_dir` on shared storage, or deploy the change to every node.

## Worker mode

FrankenPHP worker mode with kernel reset off is covered:

- `StorageWriteLedger` only grows, and is only read by difference against a mark kept on the request. It needs no
  reset.
- `ConcurrencyConflictSubscriber` and `FileLock` hold no state.
- The lock handle is released in a `finally`, also when the callback throws, so a failed request never leaves the lock
  held for the next request of the worker.

## Residual risks

- **Merge import is not atomic.** A merge import (`replace_all` off) saves row by row. If the lock times out half way,
  the rows already saved stay saved. The answer is `CONFLICT_002`: reload the list before importing again. Use
  "replace all" for an all-or-nothing import.
- **Synchronous hits may be dropped.** A synchronous redirect hit that meets a held lock after the response is logged
  (`Redirect hit not recorded`) and dropped. Hit counts are statistics. Use `url_redirects.hits.message_bus` to get
  retries, or turn hits off on very hot legacy URLs.
- **Messenger may count a hit twice.** A redelivered hit message counts again (no idempotency key; statistics only).
- **Read timeouts on host routes.** On host routes, a read that times out raises `StorageLockTimeoutException`
  wherever the caller does not catch it, for example `PublicPathResolver` from Twig. The host's error handling decides
  the answer. Before this release such a read waited without limit.
- **No cross-host locking.** See [Multi-server deployments](#multi-server-deployments).

## Tests

- `tests/Unit/Storage/FileLockTest.php`:
  - a held lock times out after the deadline, and not before;
  - a zero timeout tries once;
  - shared locks do not exclude each other but do exclude writers;
  - a lock held by **another process** is taken as soon as it is released.
- `tests/Unit/Storage/Filesystem*StorageTest.php`: every read and write meets a held lock with a timeout, leaves the
  file byte-identical, and counts only real writes in the ledger.
- `tests/Integration/Concurrency/FilesystemStoreRaceTest.php`: four separate PHP processes race on one store.
  - 100 concurrent hit increments are all kept.
  - 40 concurrent inserts are all kept.
  - Exactly one writer wins the same source path, and the same route + locale.
- `tests/Integration/Concurrency/StorageLockConflictKernelTest.php`, through a real kernel:
  - a held lock answers 409 + `CONFLICT_001` + `Retry-After` on the panel form and the list, and writes nothing;
  - a host route is still served;
  - a write followed by a conflict in the same request answers `CONFLICT_002` without `Retry-After`;
  - `conflict_response.status: 503` is honoured.
- `tests/Unit/EventSubscriber/ConcurrencyConflictSubscriberTest.php`: the mapping rules, including host routes, domain
  refusals and I/O errors left alone, transaction-doctrine's mark, and a cyclic exception chain.
- `tests/Integration/Redirect/UrlRedirectPanelRaceKernelTest.php`: two operators race for the same source path, and the
  loser gets a field error, not a 500.
