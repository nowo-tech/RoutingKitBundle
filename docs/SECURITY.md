# Security

## Table of contents

- [Scope](#scope)
- [Attack surface](#attack-surface)
- [Threat model](#threat-model)
- [Built-in controls (1.1.4+)](#built-in-controls-114)
- [Logging](#logging)
- [Dependencies](#dependencies)
- [Application checklist](#application-checklist)
- [Release security checklist (12.4.1)](#release-security-checklist-1241)

## Scope

RoutingKit lets operators change public route paths per locale (stored in `var/routing_kit/` JSON), issue canonical / root redirects and, optionally, manage URL redirects from a Twig admin panel. It does not authenticate users (Symfony Security does), does not render public pages and does not expose an API beyond the panel and export/import.

## Attack surface

- Twig CRUD panel under `web_ui.path_prefix` (default `/_routing`).
- Optional controller override (disabled by default; allowlist = `#[Routable]` discovery only).
- Filesystem JSON under `var/routing_kit/` — must not be web-accessible.
- DB loader registers `{name}.{locale}` with `_canonical_route`.
- Canonical / root redirect subscribers (open-redirect hardened).
- Optional operator URL redirects (`url_redirects.enabled`, off by default): panel section `{path_prefix}/redirects` and a `kernel.request` listener.

## Threat model

| Threat | Where | Risk |
| --- | --- | --- |
| Open redirect | Canonical / root / URL redirects, stored paths | A stored path or redirect target such as `//evil.test` or `/\evil.test` sends visitors off-site |
| Route hijacking | Panel saves, import, DB loader | A path that shadows another route, an admin prefix or a locale prefix; a controller override pointing to an arbitrary controller |
| Privilege / authz | Panel under `web_ui.path_prefix` | Non-admins editing routes or redirects; misconfigured empty roles |
| CSRF | Panel forms, delete and export actions | Cross-site POSTs changing routes or exporting the table |
| Tampered import | JSON import | Forged or modified exports injecting routes or overrides |
| DoS | Import, list, storage | Huge payloads, unbounded rows, redirect loops / chains |
| Storage integrity | `var/routing_kit/*.json` | Corrupt or concurrently written files; web-exposed `var/` leaking the table |
| Header / path injection | Stored paths | CR/LF, null bytes, encoded separators reaching `Location` headers |

## Built-in controls (1.1.4+)

| Control | Behaviour |
| --- | --- |
| CSRF | **Required** (`symfony/security-csrf`). Invalid/missing token → 403 / form error (fail-closed). `CsrfProtectionPass` refuses to compile the container when the panel is registered without `security.csrf.token_manager` + `form.type_extension.csrf`, or with `framework.form.csrf_protection: false` (action forms such as `RoutingPanelActionType` declare their own CSRF options and would otherwise accept tokenless POSTs). |
| `security.access_roles` | Default `[ROLE_ADMIN]` via `RoutingKitAccessCheckerInterface` / `ConfigurableRoutingKitAccessChecker`. **Empty list is fail-closed (deny).** Use `allow_unauthenticated: true` (dev/demo only) or a custom `access_checker` to relax. `PanelAccessGuard` always runs the checker unless `allow_unauthenticated`. |
| `panel.role` | BC alias for `security.access_roles` (`null` → empty roles). Prefer `security.access_roles`. |
| Route / locale allowlist | Saves **and imports** must use a `#[Routable]` route name and a configured locale. |
| Controller override | Off by default; when on, only discovery controllers are accepted. Loader ignores stored overrides when off. |
| Path safety | Rejects `//…`, schemes, C0/DEL controls, tabs, encoded `//` / `\` / CR-LF / null (`%2f%2f`, `%5c`, `%0d`, `%00`, …), `..` segments; redirects only to safe targets. |
| Loader defense | `DbRouteLoader` skips unsafe stored paths; controller overrides must match the `#[Routable]` allowlist even when override is enabled. |
| Panel ids | Definition ids limited to `[A-Za-z0-9_.-]+` (routes + manager). |
| Import size | Raw import JSON capped at 1 MiB (413 when larger). |
| `root_home_path` | Validated at config compile time with `SafePublicPath`. |
| Trailing slash | Applies only to paths managed by that definition (not site-wide). |
| `enabled: false` | Unregisters panel, DB loader, and redirect subscribers. |
| Conflicts | `reject_conflicts` blocks colliding public paths (incl. locale fallbacks and disabled rows). |
| Max rows | `panel.max_definitions` (default 500); enforced on import too. |
| Audit | `RoutePathAuditSubscriber` logs saves/deletes with user id when a token exists. |
| Export/import | HMAC-SHA256 signed JSON; **import** reuses manager validation; controller stripped unless override enabled; `replace_all` is atomic; signing key ≥32 chars. |
| Export HTTP | POST + CSRF only. |
| `path_prefix` | Restricted to `/[A-Za-z0-9/_-]+`. |
| Storage | Corrupt JSON fails closed; exclusive lock on mutations. |
| List pagination | `panel.list_page_size` (default 50) on the index; storage hard-capped by `max_definitions`. |
| URL redirects (opt-in) | Same panel guard + CSRF on create / edit / delete (delete via the UiKit confirm dialog or the no-JS confirmation page, no inline JS). Sources: protected prefixes (incl. `/{locale}/…` and `web_ui.path_prefix`) and live pages refused; loops, chains, duplicates refused. Targets: internal paths without `//`, `\`, `%5c`, `%2f`, controls, or absolute `http(s)://host` URLs only — re-checked at request time for rows written outside the panel. Visitor query strings never forwarded to external hosts. GET/HEAD main requests only. |
| `security.access_roles: []` / `panel.role: null` | Fail-closed deny in-bundle. Prefer explicit roles; demos may use `allow_unauthenticated`. Still firewall the prefix. |

## Logging

- `RoutePathAuditSubscriber` logs every save / delete at `info`: action, route name, locale, path, definition id and the user identifier of the security token (`anonymous` without one). No request bodies, tokens or session ids.
- `RouteCacheInvalidator` logs cache invalidation and cross-worker resets at `info`, and a `warning` when the version file cannot be written or a router cannot be rebuilt in-process.
- Redirect / canonical subscribers log a `warning` with the exception message only when storage or the cache is unavailable; the visitor URL and query string are not logged.

## Dependencies

- Runtime dependencies are Symfony components (`routing`, `http-kernel`, `form`, `security-core`, `security-csrf`, …) plus `nowo-tech/ui-kit-bundle` and `nowo-tech/form-kit-bundle`; Doctrine is not required (storage is JSON under `var/routing_kit/`; hosts may implement `RoutePathStorageInterface` on Doctrine).
- `composer audit` runs before every release and Dependabot alerts are triaged; security fixes ship as patch releases and are listed under *Security* in [CHANGELOG](CHANGELOG.md).

## Application checklist

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/_routing, roles: ROLE_ADMIN }
```

```yaml
nowo_routing_kit:
    security:
        access_roles: [ROLE_ADMIN]
    panel:
        allow_controller_override: false
```

1. Firewall the panel prefix.
2. Keep `var/` (and `paths.json` / `.lock`) off the web root.
3. Prefer `allow_controller_override: false`.
4. Use a signing key ≥32 characters (`panel.export_signing_key` or a strong `kernel.secret`) before export/import.
5. Treat panel operators as routing admins (SeoKit bridge inherits storage paths).

## Release security checklist (12.4.1)

| Item | Status |
| --- | --- |
| SECURITY.md (this file) | yes |
| `.env` in `.gitignore` | yes |
| No secrets in repo | yes |
| Safe config/recipe | yes (`ROLE_ADMIN` + access_control example) |
| Input validation | path safety + params + allowlists |
| Output escaping | Twig auto-escape |
| `composer audit` | run before release |
| CSRF | fail-closed |
| Permissions / exposure | firewall + in-bundle role (empty roles fail closed) + UNSAFE banner when `allow_unauthenticated: true` |
| Limits / DoS | `max_definitions` + import payload ≤ 1 MiB |
| Storage integrity | corrupt JSON fail-closed + flock |
| Export HMAC | key length ≥32 |
| AI security audit (REQ-SEC-004) | Grade **Pass (good)** / risk **Low** (re-audit 2026-09-28). Recorded in the Nowo monorepo `BUNDLES_SECURITY_ANALYSIS.md`. |

See also [`.github/SECURITY.md`](../.github/SECURITY.md).
