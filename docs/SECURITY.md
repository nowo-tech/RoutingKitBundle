# Security

## Attack surface

- Twig CRUD panel under `panel.path_prefix` (default `/_routing`).
- Optional controller override (disabled by default; allowlist = `#[Routable]` discovery only).
- Filesystem JSON under `var/routing_kit/` — must not be web-accessible.
- DB loader registers `{name}.{locale}` with `_canonical_route`.
- Canonical / root redirect subscribers (open-redirect hardened).

## Built-in controls (1.1+)

| Control | Behaviour |
| --- | --- |
| CSRF | **Required** (`symfony/security-csrf`). Invalid/missing token → 403 / form error (fail-closed). |
| `panel.role` | Default `ROLE_ADMIN` via `AuthorizationCheckerInterface`. Set `null` only if the firewall alone is enough. |
| Route / locale allowlist | Saves must use a `#[Routable]` route name and a configured locale. |
| Controller override | Off by default; when on, only discovery controllers are accepted. |
| Path safety | Rejects `//…`, schemes, control characters; redirects only to safe targets. |
| Trailing slash | Applies only to paths managed by that definition (not site-wide). |
| `enabled: false` | Unregisters panel, DB loader, and redirect subscribers. |
| Conflicts | `reject_conflicts` blocks colliding public paths. |
| Max rows | `panel.max_definitions` (default 500). |
| Audit | `RoutePathAuditSubscriber` logs saves/deletes with user id when a token exists. |
| Export/import | HMAC-SHA256 signed JSON (`panel.export_signing_key` or `kernel.secret`). |

## Application checklist

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/_routing, roles: ROLE_ADMIN }
```

```yaml
nowo_routing_kit:
    panel:
        role: ROLE_ADMIN
        allow_controller_override: false
```

1. Firewall the panel prefix.
2. Keep `var/` off the web root.
3. Prefer `allow_controller_override: false`.
4. Treat panel operators as routing admins (SeoKit bridge inherits storage paths).

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
| Permissions / exposure | firewall + in-bundle role |
| Limits / DoS | `max_definitions` |

See also [`.github/SECURITY.md`](../.github/SECURITY.md).
