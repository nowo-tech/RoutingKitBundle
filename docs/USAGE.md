# Usage

## Table of contents

- [Mark offerable controllers](#mark-offerable-controllers)
- [Path rows](#path-rows)
- [Panel](#panel)
- [SeoKitBundle](#seokitbundle)
- [Custom storage / locales](#custom-storage--locales)
- [Overriding templates (REQ-TWIG-001)](#overriding-templates-req-twig-001)

## Mark offerable controllers

```php
use Nowo\RoutingKitBundle\Attribute\Routable;
use Nowo\RoutingKitBundle\Attribute\RouteParam;

final class BlogController
{
    #[Routable(
        name: 'app_blog_show',
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

Loaded Symfony routes are named `{route_name}.{locale}` with `_canonical_route` set to `route_name`. Prefer:

```twig
{{ path('app_blog_show', { _locale: 'en', slug: 'hello' }) }}
```

## Panel

- List / create / edit / delete at `/_routing` (configurable `panel.path_prefix`).
- **Clear routing cache** button; saves/deletes also invalidate when `auto_invalidate_cache: true`.
- Signed **Export** / **Import** (HMAC): export is POST + CSRF; import validates rows like panel saves (allowlists, conflicts, max rows). Signing key must be ≥32 characters (`panel.export_signing_key` or `kernel.secret`).
- Index list paginates with `panel.list_page_size` (default 50).
- Private access: prefer `security.access_roles` (e.g. `[ROLE_ADMIN]`); `panel.role` remains a BC alias. Empty roles / `allow_unauthenticated: true` show an **UNSAFE** banner (demo / trusted networks only). Also firewall the path prefix in the host app.
- Host chrome without forking pages: set `web_ui.layout_template` to the project layout (and optional `web_ui.css_framework`). See [CONFIGURATION.md](CONFIGURATION.md#host-app-ui-integration-req-ui-001).

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

Implement `LocaleProviderInterface` and `RoutePathStorageInterface` (including `replaceAll()` since 1.1.1).

## Overriding templates (REQ-TWIG-001)

Twig namespace: **`NowoRoutingKitBundle`**. Application files under `templates/bundles/NowoRoutingKitBundle/` **always win**.

**Freeze rule:** a full-file override hides vendor updates for that `<subpath>` until you delete or merge it. Prefer `web_ui.layout_template` / `web_ui.css_framework` (or a one-file bridge) over copying list/form pages so package upgrades keep shipping UI fixes.

| Subpath | Purpose |
| --- | --- |
| `panel/layout.html.twig` | Default demo shell (`web_ui.layout_template` default) |
| `panel/index.html.twig` | List |
| `panel/form.html.twig` | Create/edit |

**Procedure:** copy the vendor file to `templates/bundles/NowoRoutingKitBundle/<subpath>`, then `php bin/console cache:clear` if needed.
