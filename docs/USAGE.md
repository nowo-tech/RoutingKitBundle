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

- List / create / edit / delete at `/_routing` (configurable `panel.path_prefix`).
- **Clear routing cache** button; saves/deletes also invalidate when `auto_invalidate_cache: true`.
- Signed **Export** / **Import** (HMAC): export is POST + CSRF; import validates rows like panel saves (allowlists, conflicts, max rows). Signing key must be ≥32 characters (`panel.export_signing_key` or `kernel.secret`).
- Toolbar, import, and per-row delete actions use Symfony Form Types rendered with `form_start` / `form_row` / `form_end` (FormKit profile `routing_kit` for visible fields). Context keys: `export_form`, `clear_cache_form`, `import_form`, `delete_forms[id]`. Host overrides should keep using form rendering instead of raw HTML `<form>`.
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

## URL redirects

Opt-in (`url_redirects.enabled: true`): operators redirect retired public URLs to a new path or URL from `{panel.path_prefix}/redirects`. Slug routes need a `LivePathCheckerInterface` so deleted content can be redirected. Full guide: [REDIRECTS.md](REDIRECTS.md).

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

**Freeze rule:** a full-file override hides vendor updates for that `<subpath>` until you delete or merge it. Prefer `web_ui.layout_template` / `web_ui.css_framework` / `web_ui.classes` / `web_ui.partials` (see [Customising the admin chrome](#customising-the-admin-chrome)) over copying list/form pages; if you must override, `{% extends '@!NowoRoutingKitBundle/<subpath>' %}` and redefine only blocks (1.7.0+).

| Subpath | Purpose |
| --- | --- |
| `panel/layout.html.twig` | Default demo shell (`web_ui.layout_template` default) |
| `panel/index.html.twig` | List |
| `panel/form.html.twig` | Create/edit |
| `panel/_nav.html.twig` | Routes / Redirects switcher (prefer `web_ui.partials.nav`) |
| `panel/_modal_shell.html.twig` | Import modal shell when `import_display: modal` (prefer `web_ui.partials.modal_shell`) |
| `panel/redirects/*.html.twig` | Redirect list / form / delete confirmation |

**Procedure:** copy the vendor file to `templates/bundles/NowoRoutingKitBundle/<subpath>`, then `php bin/console cache:clear` if needed.

## Customising the admin chrome

Since **1.7.0** (spec [`003-admin-chrome-hooks`](../specs/003-admin-chrome-hooks/spec.md)) a host can repaint the panel **without copying pages**. Everything is opt-in: with no option set the markup is the 1.6 markup (snapshot-tested). Behaviour stays in the bundle: routes, form variable names (`export_form`, `clear_cache_form`, `import_form`, `delete_forms`), CSRF, modal id `modal-routing-import`.

Pick the lightest tool:

1. **Class map** (`web_ui.classes`) — buttons, row actions, table, badges, empty row, forms, alerts, import.
2. **Options** — `ui_kit_assets: false`, `import_display: modal`, `show_row_count: false`, `primary_action_last: true`, `delete_confirm_attr` (CSP-friendly confirmation instead of the inline `onsubmit`).
3. **Partial paths** (`web_ui.partials`) — `nav`, `pagination`, `action_inner`, `modal_shell`.
4. **Layout slots** — your `layout_template` renders `block('rk_page_title')`, `block('rk_page_intro')`, `block('rk_page_actions')`, `block('rk_back_link')` and sets `rk_page_header_in_layout` / `rk_title_suffix`.
5. **Block overrides** — an app override extends `@!NowoRoutingKitBundle/...` and redefines one block.

### Class map keys

| Group | Keys (default when unset) |
|-------|---------------------------|
| Buttons | `btn_primary`, `btn_secondary`, `btn_danger`, `btn_link` (`ui.btn(variant, null, css_framework)`), `inline_form` (export / clear-cache forms; default `style="display:inline"`) |
| Row actions | `action_edit`, `action_delete` (`ui.action()`), `row_actions` (`ui.row_actions()` + `nowo-ui-row-actions--text`), `delete_form` (`nowo-ui-row-actions__form`) |
| Page | `page_header`, `page_title`, `page_intro`, `toolbar`, `count`, `flash`, `muted`, `empty`, `badge_yes`, `badge_no` |
| Tables | `results` (wraps count + table + pagination in `<section data-testid="routing-kit-definitions-table">` / `routing-kit-redirects-table`; none by default), `table_wrap`, `table`, `thead_row`, `th`, `th_actions` (also prints the translated "Actions" header), `row`, `td`, `td_actions` |
| Forms | `form_panel` (wraps the form in `<section data-testid="routing-kit-definition-form">`), `form_heading` / `form_hint` (heading inside the panel when the page header is in the layout), `alert` (adds `role="alert"`), `form`, `form_actions` |
| Import | `import_title`, `import_form` (inline), `modal_form`, `modal_form_body`, `modal_actions` (modal) |

### Partials

| Key | Default | Receives |
|-----|---------|----------|
| `nav` | `panel/_nav.html.twig` | `current` (`routes` / `redirects`), `path_prefix` |
| `pagination` | `@NowoUiKitBundle/partials/_pagination.html.twig` | `pagination` (`page`, `per_page`, `total`, `total_pages`), `item_count`, `route`, `route_params`, `framework` |
| `action_inner` | text label | `display` (`nowo_ui_kit_row_actions_display`), `icon_name` (`edit`, `delete`), `label` |
| `modal_shell` | `panel/_modal_shell.html.twig` | `modal_id` (`modal-routing-import`), `modal_title`; blocks `rk_modal_body`, `rk_modal_footer` |

### Layout contract

| Variable | Effect |
|----------|--------|
| `rk_page_header_in_layout` | Pages skip their `<header>`; forms print the title + hint inside `form_panel` instead. |
| `rk_title_suffix` | Appended to every page `title` block. |

Slot blocks are definitions the stock layout never renders: `rk_page_title`, `rk_page_intro` (all pages), `rk_page_actions` (lists), `rk_back_link` (forms). Guard with `is defined` in a shared layout.

### Blocks

| Template | Blocks |
|----------|--------|
| `panel/index` | `rk_nav`, `rk_page_header`, `rk_results`, `rk_count`, `rk_table`, `rk_table_head`, `rk_row`, `rk_enabled_cell`, `rk_row_actions`, `rk_empty_state`, `rk_pagination`, `rk_import` |
| `panel/form` | `rk_page_header`, `rk_form`, `rk_form_heading`, `rk_form_errors`, `rk_form_fields`, `rk_form_actions` |
| `panel/redirects/index` | `rk_nav`, `rk_page_header`, `rk_status`, `rk_results`, `rk_count`, `rk_table`, `rk_row`, `rk_row_actions`, `rk_empty_state`, `rk_pagination` |
| `panel/redirects/form` | `rk_nav`, `rk_page_header`, `rk_form`, `rk_form_heading`, `rk_form_fields`, `rk_form_actions` |
| `panel/redirects/delete` | `rk_nav`, `rk_confirm` |

### Example (Tailwind admin, no page copies)

```yaml
# config/packages/nowo_routing_kit.yaml
nowo_routing_kit:
    web_ui:
        layout_template: 'admin/routing_layout.html.twig'
        css_framework: tailwind
        ui_kit_assets: false          # the admin layout loads nowo-ui.css + its modal JS
        import_display: modal
        show_row_count: false
        primary_action_last: true
        delete_confirm_attr:
            data-controller: confirm-submit
            data-action: 'submit->confirm-submit#confirm'
            data-confirm-submit-message-value: '%message%'
        classes:
            btn_primary: 'btn-primary no-underline'
            btn_secondary: 'btn-ghost'
            btn_link: 'btn-ghost no-underline'
            inline_form: 'inline'
            action_edit: 'btn-ghost btn-icon no-underline'
            action_delete: 'btn-danger btn-icon'
            delete_form: 'inline'
            row_actions: 'inline-flex gap-1 nowo-ui-row-actions nowo-ui-row-actions--icon'
            results: 'panel overflow-x-auto'
            table: 'data-table w-full text-sm'
            th: 'px-2 py-2 font-medium'
            th_actions: 'w-0 px-2 py-2 text-end'
            td: 'px-2 py-3'
            td_actions: 'w-0 px-2 py-3 text-end whitespace-nowrap'
            badge_yes: 'badge badge--ok'
            badge_no: 'badge'
            form_panel: 'panel'
            form_heading: 'mb-1 text-base font-semibold'
            alert: 'alert alert--danger'
            modal_form: 'modal-panel'
            modal_form_body: 'modal-content'
            modal_actions: 'modal-actions'
        partials:
            pagination: 'admin/_pagination.html.twig'
            action_inner: '@NowoUiKitBundle/partials/_action_inner.html.twig'
            modal_shell: 'admin/_dialog_shell.html.twig'
```

```twig
{# templates/admin/routing_layout.html.twig #}
{% extends 'admin/base.html.twig' %}
{% set rk_page_header_in_layout = true %}
{% set rk_title_suffix = ' — ' ~ brand_name %}
{% set admin_actions %}
    {% if block('rk_back_link') is defined %}{{ block('rk_back_link') }}{% endif %}
    {% if block('rk_page_actions') is defined %}{{ block('rk_page_actions') }}{% endif %}
{% endset %}
```

```twig
{# templates/admin/_dialog_shell.html.twig — keep modal_id and "{modal_id}-label" #}
<dialog class="nowo-ui-modal admin-dialog" id="{{ modal_id }}" aria-labelledby="{{ modal_id }}-label">
    <header><h2 id="{{ modal_id }}-label">{{ modal_title }}</h2></header>
    {% block rk_modal_body %}{% endblock %}
</dialog>
```

One structural change left? Extend the original and redefine a single block:

```twig
{# templates/bundles/NowoRoutingKitBundle/panel/index.html.twig #}
{% extends '@!NowoRoutingKitBundle/panel/index.html.twig' %}
{% block rk_enabled_cell %}{{ row.enabled ? '✓' : '—' }}{% endblock %}
```

