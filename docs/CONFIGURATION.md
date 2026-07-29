# Configuration

Root key: `nowo_routing_kit`.

| Key | Default | Description |
| --- | --- | --- |
| `enabled` | `true` | Master switch — when `false`, panel, DB loader and redirect subscribers are not registered |
| `default_locale` | `en` | Locale published without prefix when style allows |
| `locales` | `[en]` | All locales |
| `locale_provider` | `null` | Service id for `LocaleProviderInterface` (overrides YAML locales) |
| `storage.paths_file` | `%kernel.project_dir%/var/routing_kit/paths.json` | JSON storage path |
| `storage.path_storage` | `null` | Custom `RoutePathStorageInterface` service id (must implement `replaceAll`) |
| `discovery.scan_dirs` | `[%kernel.project_dir%/src/Controller]` | Controllers scanned for `#[Routable]` |
| `panel.enabled` | `true` | Twig CRUD panel |
| `panel.path_prefix` | `/_routing` | Panel URL prefix (`/^/[A-Za-z0-9/_-]+$/`) |
| `panel.role` | `ROLE_ADMIN` | **Deprecated BC** alias for `security.access_roles` (`null` → empty roles) |
| `panel.allow_controller_override` | `false` | Allow selecting a discovery controller override (never free-form) |
| `panel.max_definitions` | `500` | Hard cap on stored path rows (REQ-PERF-001) |
| `panel.list_page_size` | `50` | Panel index page size (REQ-PERF-001) |
| `panel.reject_conflicts` | `true` | Block saves that collide on public paths |
| `panel.export_signing_key` | `null` | HMAC key for signed export/import (`null` → `%kernel.secret%`; min **32** chars when set) |
| `web_ui.enabled` | `true` | REQ-UI-001 chrome globals |
| `web_ui.layout_template` | `@NowoRoutingKitBundle/panel/layout.html.twig` | Host apps SHOULD set their project layout |
| `web_ui.css_framework` | `custom` | `bootstrap` / `bootstrap4` / `bootstrap5` / `tailwind` / `foundation` / `custom` / `tabler` / `none` |
| `web_ui.icon_set` | `none` | `bootstrap-icons` / `tabler-icons` / `ux_icon` / `svg_inline` / `none` |
| `security.access_roles` | `[ROLE_ADMIN]` | At least one role required; **empty** = no in-bundle gate (still firewall the prefix) |
| `security.access_checker` | `null` | Reserved for a custom checker service id |
| `security.allow_unauthenticated` | `false` | DEV/DEMO only — skip in-bundle role check |
| `redirects.canonical_enabled` | `true` | Redirect non-canonical twins |
| `redirects.canonical_status` | `301` | Redirect status |
| `redirects.root_enabled` | `false` | Redirect `/` to default-locale home |
| `redirects.root_canonical_style` | `without_prefix` | `without_prefix` \| `with_prefix` |
| `redirects.root_home_path` | `/` | Home path segment (must be a safe public path) |
| `auto_invalidate_cache` | `true` | Clear router cache after CRUD |
| `register_unprefixed_default` | `true` | Register unprefixed routes for default locale |
| `seo_kit_bridge` | `true` | Decorate SeoKit `SeoPathBuilderInterface` when present |

### Host app UI integration (REQ-UI-001)

```yaml
nowo_routing_kit:
    web_ui:
        layout_template: 'base.html.twig'   # project layout
        css_framework: bootstrap5
        icon_set: bootstrap-icons
    security:
        access_roles: [ROLE_ADMIN]
```

Panel templates extend `layout|default(nowo_routing_kit_layout_template)` and use semantic `nowo-ui-*` classes.

### Private access (REQ-UI-002)

Prefer `security.access_roles`. Legacy `panel.role: ROLE_X` still maps to `access_roles: [ROLE_X]`; `panel.role: null` maps to `access_roles: []`. Always add host `access_control` for `panel.path_prefix`.

See `src/Resources/config/packages/nowo_routing_kit.yaml` for a full example.
