# Configuration

Root key: `nowo_routing_kit`.

| Key | Default | Description |
| --- | --- | --- |
| `enabled` | `true` | Master switch |
| `default_locale` | `en` | Locale published without prefix when style allows |
| `locales` | `[en]` | All locales |
| `locale_provider` | `null` | Service id for `LocaleProviderInterface` (overrides YAML locales) |
| `storage.paths_file` | `%kernel.project_dir%/var/routing_kit/paths.json` | JSON storage path |
| `storage.path_storage` | `null` | Custom `RoutePathStorageInterface` service id |
| `discovery.scan_dirs` | `[%kernel.project_dir%/src/Controller]` | Controllers scanned for `#[Routable]` |
| `panel.enabled` | `true` | Twig CRUD panel |
| `panel.path_prefix` | `/_routing` | Panel URL prefix |
| `redirects.canonical_enabled` | `true` | Redirect non-canonical twins |
| `redirects.canonical_status` | `301` | Redirect status |
| `redirects.root_enabled` | `false` | Redirect `/` to default-locale home |
| `redirects.root_canonical_style` | `without_prefix` | `without_prefix` \| `with_prefix` |
| `redirects.root_home_path` | `/` | Home path segment |
| `auto_invalidate_cache` | `true` | Clear router cache after CRUD |
| `register_unprefixed_default` | `true` | Register unprefixed routes for default locale |
| `seo_kit_bridge` | `true` | Decorate SeoKit `SeoPathBuilderInterface` when present |

See `src/Resources/config/packages/nowo_routing_kit.yaml` for a full example.
