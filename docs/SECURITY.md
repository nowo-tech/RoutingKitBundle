# Security

## Attack surface

- Twig CRUD panel under `panel.path_prefix` (default `/_routing`).
- Optional controller override (disabled by default; allowlist = `#[Routable]` discovery only).
- Filesystem JSON under `var/routing_kit/` — must not be web-accessible.
- DB loader registers `{name}.{locale}` with `_canonical_route`.
- Canonical / root redirect subscribers (open-redirect hardened).

## Built-in controls (1.1.4+)

| Control | Behaviour |
| --- | --- |
| CSRF | **Required** (`symfony/security-csrf`). Invalid/missing token → 403 / form error (fail-closed). |
| `security.access_roles` | Default `[ROLE_ADMIN]` via `RoutingKitAccessCheckerInterface` / `ConfigurableRoutingKitAccessChecker`. Empty list / `allow_unauthenticated: true` disables the in-bundle gate (firewall still required). `PanelAccessGuardPass` wires `security.token_storage` at compile time. |
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
| `security.access_roles: []` / `panel.role: null` | Allowed but panel shows UNSAFE banner; still firewall the prefix. |

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
| Permissions / exposure | firewall + in-bundle role + UNSAFE banner if role null |
| Limits / DoS | `max_definitions` + import payload ≤ 1 MiB |
| Storage integrity | corrupt JSON fail-closed + flock |
| Export HMAC | key length ≥32 |

See also [`.github/SECURITY.md`](../.github/SECURITY.md).
