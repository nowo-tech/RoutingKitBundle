# Security

## Attack surface

- Twig CRUD panel under a configurable prefix (default `/_routing`) — **must be protected** by the application firewall.
- Filesystem JSON storage under `var/routing_kit/` — ensure web server cannot serve `var/`.
- Route loader overwrites app routes by name — treat panel write access as high privilege.

## Mitigations

- No secrets in config examples.
- Input validation on path placeholders via `RoutePathValidator` + `#[Routable]` constraints.
- CSRF: applications should add CSRF tokens when hardening the panel (v1 forms are intentionally minimal).

## Release security checklist (12.4.1)

| Item | Status |
| --- | --- |
| SECURITY.md (this file) | yes |
| `.env` in `.gitignore` | yes |
| No secrets in repo | yes |
| Safe config/recipe | yes |
| Input validation | path + params validated |
| Output escaping | Twig auto-escape |
| `composer audit` | run before release |
| No-secret logs | cache invalidation only |
| Cryptography | N/A |
| Permissions / exposure | document panel firewall |
| Limits / DoS | storage file size app-managed |

See also [`.github/SECURITY.md`](../.github/SECURITY.md).
