# Usage

## Table of contents

- [Mark offerable controllers](#mark-offerable-controllers)
- [Path rows](#path-rows)
- [Panel](#panel)
- [SeoKitBundle](#seokitbundle)
- [URL redirects](#url-redirects)
- [Custom storage / locales](#custom-storage--locales)
- [Overriding templates (REQ-TWIG-001)](#overriding-templates-req-twig-001)
- [Customising the admin chrome](#customising-the-admin-chrome)

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

- List / create / edit / delete at `/_routing` (configurable `web_ui.path_prefix`). Pages extend the UiKit page scaffolds (REQ-UI-003): see [TEMPLATES.md](TEMPLATES.md).
- **Clear routing cache** button; saves/deletes also invalidate when `auto_invalidate_cache: true`.
- Signed **Export** / **Import** (HMAC): export is POST + CSRF; import validates rows like panel saves (allowlists, conflicts, max rows). Signing key must be ≥32 characters (`panel.export_signing_key` or `kernel.secret`).
- Toolbar, import, and per-row delete actions use Symfony Form Types (CSRF-only `RoutingPanelActionType`, `RoutingPanelImportType`; FormKit profile `routing_kit` for visible fields). Export / clear cache / delete are UiKit `Action`s carrying their CSRF form; deletes open the UiKit confirm dialog (no inline JavaScript). The import form is the `import_form` context variable.
- Index list paginates with `panel.list_page_size` (default 50).
- Private access: prefer `security.access_roles` (e.g. `[ROLE_ADMIN]`); `panel.role` is a deprecated alias. `allow_unauthenticated: true` shows an **UNSAFE** warning (demo / trusted networks only). Also firewall the path prefix in the host app.
- Host chrome without forking pages: point `nowo_ui_kit.layouts.admin` at your layout and theme `nowo_ui_kit.theme` once for every bundle. See [CONFIGURATION.md](CONFIGURATION.md#host-app-ui-integration-req-ui-003).

## SeoKitBundle

When `nowo-tech/seo-kit-bundle` is installed and `seo_kit_bridge: true` (default), RoutingKit decorates `SeoPathBuilderInterface` so `pagePath(route, locale)` returns the **canonical** public path from RoutingKit storage (fallback to SeoKit config).

Requires SeoKit with `SeoPathBuilderInterface` (RoutingKit-compatible SeoKit). Keep `default_locale` / `locales` aligned between both bundles.

You can also inject:

```php
Nowo\RoutingKitBundle\Seo\RoutingKitSeoPathProvider::pagePath($route, $locale)
```

Prefer a single route loader owner (RoutingKit) for the same pages.

## URL redirects

Opt-in (`url_redirects.enabled: true`): operators redirect retired public URLs to a new path or URL from `{web_ui.path_prefix}/redirects`. Slug routes need a `LivePathCheckerInterface` so deleted content can be redirected. Full guide: [REDIRECTS.md](REDIRECTS.md).

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

**Freeze rule:** a full-file override hides vendor updates for that `<subpath>` until you delete or merge it. Theme the UiKit scaffolds and slots instead (see [Customising the admin chrome](#customising-the-admin-chrome)); if you must change one page, set `web_ui.templates.<page>` to a template that `{% extends '@!NowoRoutingKitBundle/<subpath>' %}` and redefines one data block.

| Subpath | Purpose |
| --- | --- |
| `panel/index.html.twig` | Route paths list (`page/list`) |
| `panel/form.html.twig` | Route path create / edit (`page/form`) |
| `panel/redirects/index.html.twig`, `form.html.twig` | URL redirects list / form (`page/list`, `page/form`) |
| `panel/delete.html.twig`, `panel/redirects/delete.html.twig` | No-JS delete confirmations (`page/confirm`) |
| `panel/_notices.html.twig`, `_import_form.html.twig` | Notices, import form (see [TEMPLATES.md](TEMPLATES.md#bundle-partials)); the section tabs are `page.header.tabs` (UiKit area nav) |

**Procedure:** copy the vendor file to `templates/bundles/NowoRoutingKitBundle/<subpath>`, then `php bin/console cache:clear` if needed.

## Customising the admin chrome

Since **1.9** the panel follows the UiKit 2.1 page contract (REQ-UI-003): a host themes each **view type** once in
UiKit and the panel inherits it — no RoutingKit-specific chrome configuration is needed.

1. **Layout** — `nowo_ui_kit.layouts.admin` (a one-file bridge into your base layout that renders the canonical
   `nowo_ui_*` blocks). `web_ui.layout_template` only when the panel needs a different layout than the other bundles.
2. **Theme** — `nowo_ui_kit.theme.tokens` and `theme.classes` (slots `btn_primary`, `table`, `badge_success`,
   `action_danger`, `tabs`…) for all bundles; `theme.bundles.NowoRoutingKitBundle` for exceptions of this bundle
   only; `theme.partials` to replace a UiKit partial (`page_header`, `pagination`, `tabs`, `dialog`…).
3. **Scaffolds** — `templates/bundles/NowoUiKitBundle/page/list.html.twig` (or `form`, `confirm`) re-skins every list /
   form / confirm page of every bundle.
4. **Options** — `web_ui.import_display: inline` (card instead of the default dialog), `web_ui.show_row_count: true`,
   `web_ui.primary_action_last: true`. Row actions follow `nowo_ui_kit.row_actions_display` / `icon_set`.
5. **One page** — `web_ui.templates.<page>` with a template that extends `@!NowoRoutingKitBundle/…` and redefines a
   data block ([TEMPLATES.md](TEMPLATES.md)).

Example:

```yaml
nowo_ui_kit:
    css_framework: tailwind
    layouts:
        admin: 'admin/layout.html.twig'
    theme:
        tokens: { primary: 'var(--color-brand)', radius: '0.5rem' }
        classes:
            btn_primary: 'btn-primary'
            table: 'data-table w-full text-sm'
            action_danger: 'text-red-700'
        bundles:
            NowoRoutingKitBundle:
                classes: { table: 'data-table data-table--dense' }

nowo_routing_kit:
    web_ui:
        import_display: inline
        primary_action_last: true
```

```twig
{# templates/routing/paths.html.twig — nowo_routing_kit.web_ui.templates.index #}
{% extends '@!NowoRoutingKitBundle/panel/index.html.twig' %}
{% block nowo_ui_list_after %}{% endblock %}   {# hide the inline import card #}
```

The 1.x hooks (`web_ui.classes`, `web_ui.partials`, `ui_kit_assets`, `main_landmark`, `delete_confirm_attr`, the
`rk_*` blocks and the `rk_page_header_in_layout` / `rk_title_suffix` layout contract) are deprecated or gone; the
mapping to UiKit slots and blocks is in [UPGRADING.md](UPGRADING.md#to-190).
