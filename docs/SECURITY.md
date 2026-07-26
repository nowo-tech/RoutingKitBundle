# Security

## Attack surface

- Twig CRUD panel under a configurable prefix (default `/_routing`) — **must be protected** by the application firewall (`access_control` / `IsGranted`). The bundle does **not** ship authentication or authorization.
- Optional **controller override** field on path rows is high privilege: a panel writer can point a public path at any Symfony `_controller` callable. Restrict panel access accordingly (or leave the field empty and rely on `#[Routable]` discovery).
- Filesystem JSON storage under `var/routing_kit/` — ensure the web server cannot serve `var/`.
- DB loader registers `{name}.{locale}` routes with `_canonical_route` (import **after** app routes). Treat panel write access as routing-admin privilege.

## Mitigations

- No secrets in config examples.
- Input validation on path placeholders via `RoutePathValidator` + `#[Routable]` constraints (paths must start with `/`; placeholders must match declared params).
- CSRF: when `CsrfTokenManagerInterface` is available, panel POSTs validate token `nowo_routing_kit_panel`. Install `symfony/security-csrf` (or Security Bundle) in production apps; without a manager, CSRF checks are skipped.
- Twig auto-escaping on panel templates.
- Delete and clear-cache actions accept **POST** only.

## Application checklist

1. Firewall / role gate on `panel.path_prefix` (e.g. `ROLE_ADMIN`).
2. Ensure `var/` is not web-accessible.
3. Prefer leaving the panel controller override empty unless you need it.
4. Keep `seo_kit_bridge` aligned with trusted panel operators (canonical paths come from storage).

## Release security checklist (12.4.1)

| Item | Status |
| --- | --- |
| SECURITY.md (this file) | yes |
| `.env` in `.gitignore` | yes |
| No secrets in repo | yes |
| Safe config/recipe | yes (panel still enabled by default — protect in app) |
| Input validation | path + params validated |
| Output escaping | Twig auto-escape |
| `composer audit` | run before release |
| No-secret logs | cache invalidation only |
| Cryptography | N/A |
| Permissions / exposure | document panel firewall |
| Limits / DoS | storage file size app-managed |

See also [`.github/SECURITY.md`](../.github/SECURITY.md).
