# Templates

Admin panel pages of RoutingKitBundle on the UiKit 2.1 page contract (REQ-UI-003). The controllers
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
| Route paths list | `panel/index.html.twig` | `page/list` | `ListPage` (header: `panel.title`, `panel.subtitle`, actions New path (create), Export (POST + CSRF form), Clear routing cache (POST + CSRF form), Import (opens the import dialog through `data-nowo-ui-dialog-open`, `data-testid="routing-kit-import-open"`, `import_display: modal`, the default); `primary_action_last` moves New last; columns: routeName, locale, path, canonicalStyle (priority 3), enabled (priority 2); rowActions: edit (icon `edit`), delete (icon `delete`, POST + CSRF form, `Confirm` titled `panel.delete_title` "Delete path %path% (%locale%)?", `fallbackHref` to the confirm page) — display from `nowo_ui_kit.row_actions_display`; `Pagination` (route `nowo_routing_kit_panel`); `EmptyState`; caption "N row(s)" only with `show_row_count: true` (default false); attributes `data-testid="routing-kit-definitions-table"`) + `import_form`, `import_display`, `import_modal_id` | `nowo_ui_list_row` (`code` slot on route / path, `panel.yes` / `panel.no` badge), `nowo_ui_list_after` (inline import card), `nowo_ui_modals` (import dialog through the UiKit `dialog` partial, size `lg`, `data-testid="routing-kit-import-dialog"`, Cancel / Import in the dialog `footer`) |
| Route path form | `panel/form.html.twig` | `page/form` | `FormPage` (title create / edit, intro `panel.form_hint`, submit / cancel, `deleteAction` on edit (icon `delete`, POST + CSRF, `Confirm`, `fallbackHref`); `formThemes` `[PanelView::FORM_THEME]`; attributes `data-testid="routing-kit-definition-form"`) + `definition` (`RoutePathDefinition` or null), `conflicts` (preview messages) | `nowo_ui_form_body` (form errors + conflict preview in the `flash_warning` slot, then the default child loop) |
| URL redirects list | `panel/redirects/index.html.twig` | `page/list` | `ListPage` (header: `redirects.title`, `redirects.intro`, action New redirect (`data-testid="redirect-new"`); columns: sourcePath, targetUrl, statusCode, enabled, hits (end, priority 2), note (priority 3); rowActions: edit (icon `edit`), delete (icon `delete`, POST + CSRF form, `Confirm` titled `redirects.delete_title` "Delete redirect %source%?", `fallbackHref`, `data-testid="redirect-delete"`); `Pagination` (route `nowo_routing_kit_redirects`); `EmptyState`; caption "N redirect(s)" only with `show_row_count: true`; attributes `data-testid="redirect-table"`) + `status` (`created` / `saved` / `deleted` / null) | `nowo_ui_list_row` (`code` slot on source / target, `panel.yes` / `panel.no` badge) |
| URL redirect form | `panel/redirects/form.html.twig` | `page/form` | `FormPage` (title create / edit, intro `redirects.form_hint`, submit / cancel, `deleteAction` on edit (icon `delete`, `fallbackHref`); `formThemes` `[PanelView::FORM_THEME]`; attributes `data-testid="routing-kit-redirect-form-panel"`) + `redirect` (`UrlRedirect` or null) | `nowo_ui_form_body` (form-level errors, then the default child loop) |
| Delete route path (no-JS fallback) | `panel/delete.html.twig` | `page/confirm` | `ConfirmPage` (header `panel.delete_title` with `%path%` / `%locale%`; `Confirm` with `panel.delete_confirm_message`; POST action with the panel CSRF form; cancel to the list; attributes `data-testid="routing-kit-delete-form"`) + `definition` | — |
| Delete URL redirect (no-JS fallback) | `panel/redirects/delete.html.twig` | `page/confirm` | `ConfirmPage` (header `redirects.delete_title` with `%source%`; `Confirm` with `redirects.delete_confirm`; POST action with the per-redirect CSRF form; cancel to the list; attributes `data-testid="redirect-delete-form"`) + `redirect` | — |

Every page passes `bundle: 'NowoRoutingKitBundle'` (theme exception `nowo_ui_kit.theme.bundles.NowoRoutingKitBundle`)
and `layout: web_ui.layout_template` (null = `nowo_ui_layout.admin`). Labels are `NowoRoutingKitBundle` translation keys
(`PanelView::label()`). The enabled column uses the slots `badge_success` / `badge_neutral` (text `panel.yes` / `panel.no` in both lists);
cells use `table_cell`, `table_cell_muted`, `muted`, and `code` for route names, paths and redirect source / target. Every header carries the section tabs as `PageHeader::tabs` (`View\AreaNav` from
`PanelView::nav()`: Routes | Redirects, `aria-label` `nav.label`, items with `data-testid="routing-kit-nav-routes|redirects"`;
null when `url_redirects.enabled` is false), rendered by the UiKit `page_header` partial through the `tabs` partial
(region `area-nav`). The same navigation is published to the UiKit area-nav registry as area `routing_kit`
(`nowo_ui_area_nav('routing_kit', {current: …})`, `PanelView` implements `AreaNavProviderInterface`).
Deletes from the lists and the edit forms open the UiKit confirm `<dialog>`; without JavaScript the `<noscript>` link
(`Action::fallbackHref`) opens the full-page confirmation: `GET {path_prefix}/delete/{id}` (route paths) and
`GET {path_prefix}/redirects/{id}/delete` (redirects); both POST with CSRF.

## Shared page context

`PanelView::render()` adds to every page:

| Variable | Content |
| --- | --- |
| `path_prefix` | `web_ui.path_prefix` |
| `nav_current` | Current section: `routes` or `redirects` (the tabs themselves are `page.header.tabs`) |
| `role_gate_disabled` | `security.allow_unauthenticated` (UNSAFE warning) |
| `nowo_routing_kit_legacy_layout` | The 1.x layout wrapped by the legacy bridge, else null |

Every page overrides one canonical layout block with `{{ parent() }}` first: `nowo_ui_flashes` (adds the notices,
`panel/_notices.html.twig`).

## Unique pages

- (none)

## Per-page overrides

`nowo_routing_kit.web_ui.templates.<page>` (`index`, `form`, `delete`, `redirects_index`, `redirects_form`, `redirects_delete`):
a host template that extends `@!NowoRoutingKitBundle/panel/<template>` and overrides a data block (`nowo_ui_list_row`,
`nowo_ui_list_after`, `nowo_ui_form_body`, `nowo_ui_form_aside`) or one of the canonical blocks above. Prefer theming
the UiKit slots (`nowo_ui_kit.theme`) and scaffolds once for every bundle.

## Bundle partials

| Partial | Role | Renders through |
| --- | --- | --- |
| `panel/_notices.html.twig` | Role-gate warning (`flash_warning` slot, `data-testid="routing-kit-role-gate-warning"`) and the redirect status message | `nowo_ui_partial('flashes')` (`data-testid="redirect-status"`) |
| `panel/_import_form.html.twig` | Signed JSON import form (Symfony form `RoutingPanelImportType`, `id="routing-kit-import-form"`); `part: 'actions'` renders the dialog footer buttons (Cancel with `data-nowo-ui-dialog-close`, Import with `form="routing-kit-import-form"`) | `form` / `form_actions` / `btn_*` / `input_full` (textarea) slots, inside the `card` (inline) or `dialog` (modal, default) partial |
| `panel/_form_theme.html.twig` | Bundle form theme (`PanelView::FORM_THEME`): `checkbox_row` as a UiKit check label (`ui.check()`, `.nowo-ui-check`) with errors and help | `FormPage::formThemes` (path and redirect forms), `form_theme` in `_import_form` |
| `panel/_legacy_layout.html.twig` | Deprecated bridge around a 1.x `web_ui.layout_template` that renders `body` | Removed in 2.0 |

## Allowed exceptions

- (none) — the import modal uses the framework-free UiKit `dialog` partial; the contract tests declare no
  `allowedClasses()`.
