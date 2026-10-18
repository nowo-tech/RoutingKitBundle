# Configuration

Root key: `nowo_routing_kit`.

| Key | Default | Description |
| --- | --- | --- |
| `enabled` | `true` | Master switch — when `false`, panel, DB loader and redirect subscribers are not registered |
| `default_locale` | `en` | Locale published without prefix when style allows |
| `locales` | `[en]` | All locales (must include `default_locale`). A list, or a parameter / env var holding one — see [Locales from a parameter or env var](#locales-from-a-parameter-or-env-var) |
| `locale_provider` | `null` | Service id for `LocaleProviderInterface` (overrides YAML locales) |
| `storage.paths_file` | `%kernel.project_dir%/var/routing_kit/paths.json` | JSON storage path |
| `storage.path_storage` | `null` | Custom `RoutePathStorageInterface` service id (must implement `replaceAll`) |
| `discovery.scan_dirs` | `[%kernel.project_dir%/src/Controller]` | Controllers scanned for `#[Routable]` |
| `panel.allow_controller_override` | `false` | Allow selecting a discovery controller override (never free-form) |
| `panel.max_definitions` | `500` | Hard cap on stored path rows (REQ-PERF-001) |
| `panel.list_page_size` | `50` | Panel lists page size (REQ-PERF-001) |
| `panel.reject_conflicts` | `true` | Block saves that collide on public paths |
| `panel.export_signing_key` | `null` | HMAC key for signed export/import (`null` → `%kernel.secret%`; min **32** chars when set) |
| `web_ui.enabled` | `true` | Register the admin panel (paths, redirects, import / export). Replaces `panel.enabled` |
| `web_ui.path_prefix` | `/_routing` | Panel URL prefix (`/^/[A-Za-z0-9/_-]+$/`). Replaces `panel.path_prefix` |
| `web_ui.layout_template` | `null` | Layout the panel scaffolds extend instead of `nowo_ui_kit.layouts.admin` (`null` = UiKit layout). It must render the UiKit canonical blocks; a 1.x layout that only renders `body` is wrapped by a deprecated bridge |
| `web_ui.templates.<page>` | `{}` | Per-page template override: `index`, `form`, `redirects_index`, `redirects_form`, `redirects_delete` ([TEMPLATES.md](TEMPLATES.md#per-page-overrides)) |
| `web_ui.import_display` | `modal` | Signed JSON import: `modal` (UiKit `dialog` `#modal-routing-import`, opened from the "Import" page action; needs `js/nowo-ui-dialog.js`, loaded by the UiKit document layout; default since 1.9.1) or `inline` (card under the paths table) |
| `web_ui.show_row_count` | `false` | "N row(s)" caption of the tables (default `false` since 1.9.1: the pagination summary already shows the count) |
| `web_ui.primary_action_last` | `false` | Render "New path" after Export / Clear cache / Import |
| `security.access_roles` | `[ROLE_ADMIN]` | At least one role required; **empty** = fail-closed (every panel request is denied). Still firewall the prefix |
| `security.access_checker` | `null` | Custom `RoutingKitAccessCheckerInterface` service id (`null` = Configurable by roles) |
| `security.allow_unauthenticated` | `false` | DEV/DEMO only — skip in-bundle role check (AllowAll) and show the **UNSAFE** banner on every panel page; panel requires SecurityBundle when `false` |
| `redirects.canonical_enabled` | `true` | Redirect non-canonical twins |
| `redirects.canonical_status` | `301` | Redirect status. Only `GET`/`HEAD` are redirected; the query string is kept |
| `redirects.canonical_cache_pool` | `cache.app` | Cache pool for the resolved canonical/alias path table, keyed by the route-table version (`null` = storage read once per request) |
| `redirects.root_enabled` | `false` | Redirect `/` to default-locale home |
| `redirects.root_canonical_style` | `without_prefix` | `without_prefix` \| `with_prefix` |
| `redirects.root_home_path` | `/` | Home path segment (must be a safe public path) |
| `url_redirects.enabled` | `false` | Opt-in operator URL redirects (listener at priority 33 + panel section `{path_prefix}/redirects`). See [REDIRECTS.md](REDIRECTS.md) |
| `url_redirects.storage` | `null` | Custom `UrlRedirectStorageInterface` service id (`null` = JSON file) |
| `url_redirects.file` | `%kernel.project_dir%/var/routing_kit/redirects.json` | JSON file for the default storage |
| `url_redirects.cache_pool` | `cache.app` | Cache pool for the enabled-redirect map (`null` = storage read once per request) |
| `url_redirects.cache_ttl` | `3600` | Seconds; bounds staleness after writes made outside the panel |
| `url_redirects.protected_prefixes` | `/admin`, `/login`, `/logout`, `/register`, `/reset-password`, `/api`, `/health`, `/build`, `/bundles`, `/media`, `/.well-known` | Whole-segment prefixes never redirected (also behind `/{locale}`); replaces defaults; `web_ui.path_prefix` always added |
| `url_redirects.protected_starts` | `/_`, `/robots.txt`, `/sitemap`, `/favicon` | Raw string prefixes never redirected; replaces defaults |
| `url_redirects.hits.enabled` | `true` | Count hits / last hit |
| `url_redirects.hits.message_bus` | `null` | Messenger bus id for async hit counting (`null` = synchronous write) |
| `auto_invalidate_cache` | `true` | Clear router cache after CRUD |
| `register_unprefixed_default` | `true` | Register unprefixed routes for default locale |
| `seo_kit_bridge` | `true` | Decorate SeoKit `SeoPathBuilderInterface` when present |

### Locales from a parameter or env var

Keep one locale list in the host and pass it to the bundle:

```yaml
parameters:
    app.locales: ['en', 'es']

nowo_routing_kit:
    default_locale: en
    locales: '%app.locales%'            # or '%kernel.enabled_locales%' (framework.enabled_locales)
    # locales: '%env(json:APP_LOCALES)%' # APP_LOCALES='["en","es"]' (or '%env(csv:APP_LOCALES)%' with APP_LOCALES=en,es)
```

Parameters are resolved when the container is built (`nowo.routing_kit.locales` holds the resolved list). An env var
is read at runtime by `ConfigurableLocaleProvider` (the route loader registers `{route}.{locale}` for each env locale
when the route cache is built; clear the cache after changing it). Values are trimmed and de-duplicated.

### Deprecated keys (removed in 2.0)

Still accepted in 1.9 (a deprecation is logged when set); see [UPGRADING.md](UPGRADING.md#to-190).

| Key | Replacement |
| --- | --- |
| `panel.enabled` | `web_ui.enabled` (the canonical key wins when both are set) |
| `panel.path_prefix` | `web_ui.path_prefix` (the canonical key wins when both are set) |
| `panel.role` | `security.access_roles` (`null` → `[]`, fail-closed) |
| `web_ui.classes` | `nowo_ui_kit.theme.classes` (all bundles) or `nowo_ui_kit.theme.bundles.NowoRoutingKitBundle.classes`; bridged automatically (slot table in UPGRADING) |
| `web_ui.partials` | `nowo_ui_kit.theme.bundles.NowoRoutingKitBundle.partials`; `nav` → `tabs` and `pagination` are bridged, `action_inner` / `modal_shell` are ignored |
| `web_ui.css_framework`, `web_ui.icon_set` | `nowo_ui_kit.css_framework` / `icon_set` (still seeded into UiKit when set here and not in `nowo_ui_kit`) |
| `web_ui.ui_kit_assets` | The layout loads the UiKit assets (only the 1.x layout bridge reads it) |
| `web_ui.main_landmark` | No effect: the layout owns `<main>` |
| `web_ui.delete_confirm_attr` | No effect: deletes use the UiKit confirm dialog (CSP-safe) |

### Host app UI integration (REQ-UI-003)

The panel pages extend the UiKit page scaffolds (`page/list`, `page/form`, `page/confirm`) and render inside the
layout configured **once** for every Nowo bundle:

```yaml
nowo_ui_kit:
    css_framework: bootstrap5            # CSS stack and row-action display come from UiKit
    layouts:
        admin: 'layouts/admin.html.twig' # renders the canonical nowo_ui_* blocks
    theme:
        tokens: { primary: '#0b5fff', radius: '0.5rem' }
        classes: { table: 'table-sm' }   # one class map for all bundles
        bundles:
            NowoRoutingKitBundle: { classes: { table: 'table-sm table-striped' } }   # optional exception

nowo_routing_kit:
    web_ui:
        path_prefix: /_routing
        # layout_template: ~   # only to give the panel a different layout than nowo_ui_kit.layouts.admin
    security:
        access_roles: [ROLE_ADMIN]
```

Layout bridge, slot vocabulary and tokens: UiKit `docs/PAGE_CONTRACT.md` and `docs/THEME_SLOTS.md`. Pages, view
models and data blocks of this bundle: [TEMPLATES.md](TEMPLATES.md). The demo (`demo/symfony8`) ships a one-file
bridge (`templates/layouts/admin.html.twig`) and `config/packages/nowo_ui_kit.yaml`.

Form fields use the FormKit profile `routing_kit` (seeded when the host does not define it; override
`nowo_form_kit.profiles.routing_kit` to change field classes).

### Private access (REQ-UI-002)

Prefer `security.access_roles`. Legacy `panel.role: ROLE_X` still maps to `access_roles: [ROLE_X]`; `panel.role: null` maps to `access_roles: []` (fail-closed: the panel denies everyone). Always add host `access_control` for `web_ui.path_prefix`.

See `src/Resources/config/packages/nowo_routing_kit.yaml` for a full example.
