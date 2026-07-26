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
| `panel.role` | `ROLE_ADMIN` | In-bundle `AuthorizationChecker` gate (`null` = disabled; still firewall the prefix) |
| `panel.allow_controller_override` | `false` | Allow selecting a discovery controller override (never free-form) |
| `panel.max_definitions` | `500` | Soft cap on stored path rows |
| `panel.reject_conflicts` | `true` | Block saves that collide on public paths |
| `panel.export_signing_key` | `null` | HMAC key for signed export/import (`null` → `%kernel.secret%`; min **32** chars when set or when used) |
| `redirects.canonical_enabled` | `true` | Redirect non-canonical twins |
| `redirects.canonical_status` | `301` | Redirect status |
| `redirects.root_enabled` | `false` | Redirect `/` to default-locale home |
| `redirects.root_canonical_style` | `without_prefix` | `without_prefix` \| `with_prefix` |
| `redirects.root_home_path` | `/` | Home path segment (must be a safe public path) |
| `auto_invalidate_cache` | `true` | Clear router cache after CRUD |
| `register_unprefixed_default` | `true` | Register unprefixed routes for default locale |
| `seo_kit_bridge` | `true` | Decorate SeoKit `SeoPathBuilderInterface` when present |

See `src/Resources/config/packages/nowo_routing_kit.yaml` for a full example.
