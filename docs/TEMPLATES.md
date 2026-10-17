# Templates

Admin panel pages of RoutingKitBundle on the UiKit 2.0 page contract (REQ-UI-003). The controllers
(`RoutingPanelController`, `UrlRedirectPanelController`) build the UiKit view models; `Nowo\RoutingKitBundle\Panel\PanelView`
renders them with the shared page context. The templates extend a UiKit scaffold and fill data blocks only. Contract
tests: `tests/Integration/Contract/PageContractTest.php` (English) and `PageContractGermanTest.php` (German catalogue),
both on UiKit's `PageContractTestCase`.

## Table of contents

- [Pages](#pages)
- [Shared page context](#shared-page-context)
- [Unique pages](#unique-pages)
- [Per-page overrides](#per-page-overrides)
- [Bundle partials](#bundle-partials)
- [Allowed exceptions](#allowed-exceptions)

## Pages

| Page | Template | Scaffold | View model | Data blocks filled |
| --- | --- | --- | --- | --- |
| Route paths list | `panel/index.html.twig` | `page/list` | `ListPage` (header: `panel.title`, `panel.subtitle`, actions New path (create), Export (POST + CSRF form), Clear routing cache (POST + CSRF form), Import (modal trigger, `import_display: modal` only); `primary_action_last` moves New last; columns: routeName, locale, path, canonicalStyle (priority 3), enabled (priority 2); rowActions: edit, delete (POST + CSRF form, `Confirm`); `Pagination` (route `nowo_routing_kit_panel`); `EmptyState`; caption "N row(s)" unless `show_row_count: false`; attributes `data-testid="routing-kit-definitions-table"`) + `import_form`, `import_display`, `import_modal_id` | `nowo_ui_list_row`, `nowo_ui_list_after` (inline import card), `nowo_ui_modals` (import modal through the UiKit `modal_shell` partial) |
| Route path form | `panel/form.html.twig` | `page/form` | `FormPage` (title create / edit, intro `panel.form_hint`, submit / cancel, `deleteAction` on edit (POST + CSRF, `Confirm`); attributes `data-testid="routing-kit-definition-form"`) + `definition` (`RoutePathDefinition` or null), `conflicts` (preview messages) | `nowo_ui_form_body` (form errors + conflict preview in the `flash_warning` slot, then the default child loop) |
| URL redirects list | `panel/redirects/index.html.twig` | `page/list` | `ListPage` (header: `redirects.title`, `redirects.intro`, action New redirect (`data-testid="redirect-new"`); columns: sourcePath, targetUrl, statusCode, enabled, hits (end, priority 2), note (priority 3); rowActions: edit, delete (POST + CSRF form, `Confirm`, `data-testid="redirect-delete"`); `Pagination` (route `nowo_routing_kit_redirects`); `EmptyState`; caption "N redirect(s)"; attributes `data-testid="redirect-table"`) + `status` (`created` / `saved` / `deleted` / null) | `nowo_ui_list_row` |
| URL redirect form | `panel/redirects/form.html.twig` | `page/form` | `FormPage` (title create / edit, intro `redirects.form_hint`, submit / cancel, `deleteAction` on edit; attributes `data-testid="routing-kit-redirect-form-panel"`) + `redirect` (`UrlRedirect` or null) | `nowo_ui_form_body` (form-level errors, then the default child loop) |
| Delete URL redirect (no-JS fallback) | `panel/redirects/delete.html.twig` | `page/confirm` | `ConfirmPage` (header `redirects.delete_title`; `Confirm` with `redirects.delete_confirm`; POST action with the per-redirect CSRF form; cancel to the list; attributes `data-testid="redirect-delete-form"`) + `redirect` | — |

Every page passes `bundle: 'NowoRoutingKitBundle'` (theme exception `nowo_ui_kit.theme.bundles.NowoRoutingKitBundle`)
and `layout: web_ui.layout_template` (null = `nowo_ui_layout.admin`). Labels are `NowoRoutingKitBundle` translation keys
(`PanelView::label()`). The enabled column uses the slots `badge_success` / `badge_neutral`; cells use `table_cell`,
`table_cell_muted`, `muted`. Deletes from the lists open the UiKit confirm `<dialog>`; the routes list has no
full-page fallback (POST-only route), the redirects list keeps `GET {path_prefix}/redirects/{id}/delete`.

## Shared page context

`PanelView::render()` adds to every page:

| Variable | Content |
| --- | --- |
| `path_prefix` | `web_ui.path_prefix` |
| `nav` / `nav_current` | Section switcher items (`label`: `View\Label`, `href`, `current`, `attr` with `data-testid="routing-kit-nav-routes|redirects"`) — empty when `url_redirects.enabled` is false; `routes` or `redirects` |
| `role_gate_disabled` | `security.allow_unauthenticated` (UNSAFE warning) |
| `nowo_routing_kit_legacy_layout` | The 1.x layout wrapped by the legacy bridge, else null |

Every page overrides two canonical layout blocks with `{{ parent() }}` first: `nowo_ui_page_header` (adds the section
nav, `panel/_nav.html.twig`) and `nowo_ui_flashes` (adds the notices, `panel/_notices.html.twig`).

## Unique pages

- (none)

## Per-page overrides

`nowo_routing_kit.web_ui.templates.<page>` (`index`, `form`, `redirects_index`, `redirects_form`, `redirects_delete`):
a host template that extends `@!NowoRoutingKitBundle/panel/<template>` and overrides a data block (`nowo_ui_list_row`,
`nowo_ui_list_after`, `nowo_ui_form_body`, `nowo_ui_form_aside`) or one of the canonical blocks above. Prefer theming
the UiKit slots (`nowo_ui_kit.theme`) and scaffolds once for every bundle.

## Bundle partials

| Partial | Role | Renders through |
| --- | --- | --- |
| `panel/_nav.html.twig` | Routes / Redirects switcher | `nowo_ui_partial('tabs')` (slots `tabs`, `tab`, `tab_active`; also receives the 1.x `current` / `path_prefix`) |
| `panel/_notices.html.twig` | Role-gate warning (`flash_warning` slot, `data-testid="routing-kit-role-gate-warning"`) and the redirect status message | `nowo_ui_partial('flashes')` (`data-testid="redirect-status"`) |
| `panel/_import_form.html.twig` | Signed JSON import form (Symfony form `RoutingPanelImportType`) | `form` / `form_actions` / `btn_*` slots, inside the `card` (inline) or `modal_shell` (modal) partial |
| `panel/_legacy_layout.html.twig` | Deprecated bridge around a 1.x `web_ui.layout_template` that renders `body` | Removed in 2.0 |

## Allowed exceptions

- `modal`, `fade`, `modal-dialog`, `modal-content`, `modal-header`, `modal-title`, `btn-close`, `modal-body`,
  `modal-footer` — structure classes printed by UiKit's own `modal_shell` partial for every `css_framework` (Bootstrap
  JS and `nowo-ui.css` rely on them); the UiKit contract allow-list does not include them yet. Only the import modal
  (`web_ui.import_display: modal`) renders them.
