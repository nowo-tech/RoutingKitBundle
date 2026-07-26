# Usage

## Mark offerable controllers

```php
use Nowo\RoutingKitBundle\Attribute\Routable;
use Nowo\RoutingKitBundle\Attribute\RouteParam;

final class BlogController
{
    #[Routable(
        name: 'app_blog_show',
        label: 'Blog post',
        params: [
            new RouteParam('slug', required: true, requirement: '[a-z0-9-]+'),
        ],
    )]
    public function show(string $slug): Response
    {
        // …
    }
}
```

The CRUD panel only lists `#[Routable]` actions. On save, path placeholders are validated against declared params (required, requirement regex, enum).

## Path rows

Each row is `(route_name, locale)`:

- **path** — always without `{_locale}` (e.g. `/about`, `/blog/{slug}`).
- **canonical_style** — `without_prefix` (`/about`) or `with_prefix` (`/en/about`).
- **alias_mode** — `redirect` (non-canonical → canonical) or `alias` (both 200).
- **trailing_slash** — `omit` / `keep` / `redirect_to_omit` / `redirect_to_keep`.

If locale `es` has no row, the **default locale** path is reused under `/es/…`.

## Panel

- List / create / edit / delete at `/_routing` (configurable).
- **Clear routing cache** button; saves/deletes also invalidate when `auto_invalidate_cache: true`.

## SeoKitBundle

When `nowo-tech/seo-kit-bundle` is installed and `seo_kit_bridge: true` (default), RoutingKit decorates `SeoPathBuilderInterface` so `pagePath(route, locale)` returns the **canonical** public path from RoutingKit storage (fallback to SeoKit config).

Requires SeoKit with `SeoPathBuilderInterface` (RoutingKit-compatible SeoKit). Keep `default_locale` / `locales` aligned between both bundles.

You can also inject:

```php
Nowo\RoutingKitBundle\Seo\RoutingKitSeoPathProvider::pagePath($route, $locale)
```

Prefer a single route loader owner (RoutingKit) for the same pages.

## Custom storage / locales

```yaml
nowo_routing_kit:
    locale_provider: App\Locale\DatabaseLocaleProvider
    storage:
        path_storage: App\Routing\DoctrineRoutePathStorage
```

Implement `LocaleProviderInterface` and `RoutePathStorageInterface`.

## Overriding templates (REQ-TWIG-001)

| Subpath | Purpose |
| --- | --- |
| `panel/layout.html.twig` | Panel chrome |
| `panel/index.html.twig` | List |
| `panel/form.html.twig` | Create/edit |

Copy to `templates/bundles/NowoRoutingKitBundle/<subpath>`.
