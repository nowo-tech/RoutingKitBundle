# Upgrading

## Table of contents

- [Unreleased](#unreleased)
- [To 1.9.3](#to-193)
- [To 1.9.2](#to-192)
- [To 1.9.1](#to-191)
- [To 1.9.0](#to-190)
  - [Configuration](#configuration)
  - [Template contract](#template-contract)
- [To 1.8.1](#to-181)
- [To 1.8.0](#to-180)
- [To 1.7.2](#to-172)
- [To 1.7.1](#to-171)
- [To 1.7.0](#to-170)
- [To 1.6.0](#to-160)
- [To 1.5.1](#to-151)
- [To 1.5.0](#to-150)
- [To 1.4.6](#to-146)
- [To 1.4.5](#to-145)
- [To 1.4.3](#to-143)
- [To 1.4.2](#to-142)
- [To 1.4.1](#to-141)
- [To 1.4.0](#to-140)
  - [Host Twig overrides](#host-twig-overrides)
- [To 1.3.1](#to-131)
- [To 1.3.0](#to-130)
- [To 1.2.0](#to-120)
  - [UiKit composition](#uikit-composition)
  - [Twig Extra Bundle (REQ-TWIG-004)](#twig-extra-bundle-req-twig-004)
  - [Twig-CS-Fixer (maintainers)](#twig-cs-fixer-maintainers)
- [To 1.1.9](#to-119)
  - [Install / update](#install--update)
  - [Behaviour / security](#behaviour--security)
  - [Breaking / migration](#breaking--migration)
- [To 1.1.8](#to-118)
  - [Install / update](#install--update-1)
- [To 1.1.7](#to-117)
  - [Install / update](#install--update-2)
- [To 1.1.6](#to-116)
  - [Install / update](#install--update-3)
- [To 1.1.5](#to-115)
  - [Install / update](#install--update-4)
  - [Behaviour / security](#behaviour--security-1)
  - [Breaking / migration](#breaking--migration-1)
- [To 1.1.4](#to-114)
  - [Install / update](#install--update-5)
  - [Behaviour / security](#behaviour--security-2)
  - [Breaking / migration](#breaking--migration-2)
- [To 1.1.3](#to-113)
- [To 1.1.2](#to-112)
  - [Install / update](#install--update-6)
  - [Behaviour / security](#behaviour--security-3)
  - [Breaking / migration](#breaking--migration-3)
- [To 1.1.1](#to-111)
  - [Install / update](#install--update-7)
  - [Behaviour / security](#behaviour--security-4)
  - [Breaking / migration](#breaking--migration-4)
- [To 1.1.0](#to-110)
  - [Requirements](#requirements)
  - [Install / update](#install--update-8)
  - [Breaking / migration](#breaking--migration-5)
  - [Behaviour notes](#behaviour-notes)
- [To 1.0.3](#to-103)
  - [Requirements](#requirements-1)
  - [Install / update](#install--update-9)
  - [Behavior notes](#behavior-notes)
  - [Breaking changes](#breaking-changes)
- [To 1.0.2](#to-102)
  - [Requirements](#requirements-2)
  - [Install / update](#install--update-10)
  - [Behavior notes](#behavior-notes-1)
  - [Breaking changes](#breaking-changes-1)
- [To 1.0.1](#to-101)
  - [Requirements](#requirements-3)
  - [Install / update](#install--update-11)
  - [Breaking changes](#breaking-changes-2)
  - [Behaviour notes (non-breaking)](#behaviour-notes-non-breaking)
- [To 1.0.0](#to-100)
  - [Requirements](#requirements-4)
  - [Install](#install)
  - [Routes (order matters)](#routes-order-matters)
  - [Suggested first-time config](#suggested-first-time-config)
  - [Security](#security)
  - [SeoKit](#seokit)
  - [Storage](#storage)

## Unreleased

## To 1.9.3

From **1.9.2**:

```bash
composer update nowo-tech/routing-kit-bundle
```

## To 1.9.2

From **1.9.1**:

```bash
composer update nowo-tech/routing-kit-bundle
```

Requires UiKit **^2.2** (`composer require nowo-tech/ui-kit-bundle:^2.2`). No configuration change. What hosts may
notice (UiKit UPGRADING "Template contract (2.2)"): the delete confirm dialog is centred, has a close button and a
solid red confirm button (`btn_danger_solid`: a host theme that styled `btn_danger` for confirms sets it too); table
headers are small uppercase labels and the actions column has a visible "Actions" header; host layouts with their own
UiKit script list should include `{{ include(nowo_ui_partial('scripts')) }}` instead. Overrides of
`panel/_notices.html.twig` that pass `flashes:` to the `flashes` partial switch to `flash_messages:`.

## To 1.9.1

From **1.9.0**:

```bash
composer update nowo-tech/routing-kit-bundle
```

From **1.9.0** (no BC break in the PHP API; two configuration defaults and some labels change):

- **Import dialog by default.** `web_ui.import_display` now defaults to `modal`: the signed JSON import is a UiKit
  `dialog` opened from the "Import" page action (the layout must load `js/nowo-ui-dialog.js`; the UiKit document
  layout does). To keep the card under the paths table, set `nowo_routing_kit.web_ui.import_display: inline`.
- **No row-count caption by default.** `web_ui.show_row_count` now defaults to `false` (the pagination summary already
  shows the count). Set it to `true` to keep the "N row(s)" caption.
- **Icon row actions.** Edit / Delete carry `Action::icon` and follow `nowo_ui_kit.row_actions_display` (default
  `icon`). With `nowo_ui_kit.icon_set: none` the icon is the label text; use `svg_inline` (no font), a font icon set, or
  `row_actions_display: text` / `icon_text`.
- **Confirm titles name the item.** `panel.delete_title` takes `%path%` / `%locale%` and `redirects.delete_title`
  takes `%source%`; the messages `panel.delete_confirm_message` / `redirects.delete_confirm` were reworded. Host
  translation overrides of these keys should add the placeholders.
- **Labels.** `panel.yes` / `panel.no` are capitalised and used by both lists (`redirects.yes` / `redirects.no` are no
  longer rendered by the bundle templates); `panel.subtitle`, `panel.import_payload` and `panel.import_replace_all`
  were reworded.
- **Template contract.** `panel/_import_form.html.twig` takes `part` (`form` | `actions`) and renders the dialog
  actions in the dialog footer; the form has `id="routing-kit-import-form"`. The panel forms use the bundle form theme
  `@NowoRoutingKitBundle/panel/_form_theme.html.twig` (`checkbox_row` as a UiKit `.nowo-ui-check` label) on top of
  the host form themes; a host `checkbox_row` override for these forms must now live in a theme passed after it (or
  override the bundle template). `<code>` cells carry the `code` slot classes.

## To 1.9.0

From **1.8.1**:

```bash
composer update nowo-tech/routing-kit-bundle
```

From **1.8.1** — the admin panel follows the UiKit 2 page contract (REQ-UI-003). Configuration keeps working
(deprecated keys are mapped); page template overrides written for 1.x must be migrated.

```bash
composer require nowo-tech/ui-kit-bundle:^2.1
composer update nowo-tech/routing-kit-bundle
php bin/console cache:clear
```

1. Point `nowo_ui_kit.layouts.admin` at your admin layout (or a one-file bridge into your base layout) that renders the
   UiKit canonical blocks (`nowo_ui_document_title`, `nowo_ui_head`, `nowo_ui_styles`, `nowo_ui_navigation`,
   `nowo_ui_page_header`, `nowo_ui_flashes`, `nowo_ui_content`, `nowo_ui_modals`, `nowo_ui_scripts`), loads
   `nowo-ui.css` + `nowo_ui_tokens()` and the UiKit scripts (`nowo-ui-confirm.js` for the delete dialogs,
   `nowo-ui-dialog.js` for the modal import). A host `page_header` partial override must render `header.tabs`
   (`nowo_ui_partial('tabs')`, see UiKit PAGE_CONTRACT "Area navigation"), or the Routes | Redirects tabs disappear.
   Example: UiKit `docs/PAGE_CONTRACT.md#example-host-integration`, or this
   bundle's `demo/symfony8/templates/layouts/admin.html.twig`.
2. Remove `nowo_routing_kit.web_ui.layout_template` (or keep it only to give the panel a different layout — it must
   render the same blocks).
3. Move the deprecated keys below to their replacements and delete page copies under
   `templates/bundles/NowoRoutingKitBundle/`.

### Configuration

| 1.x key | 1.9 | Action |
| --- | --- | --- |
| `panel.enabled` | deprecated alias | `web_ui.enabled` (the canonical key wins when both are set; `web_ui.enabled: false` now really disables the panel) |
| `panel.path_prefix` | deprecated alias | `web_ui.path_prefix`. Container parameter `nowo.routing_kit.panel.path_prefix` keeps the value (new: `nowo.routing_kit.web_ui.path_prefix`) |
| `panel.role` | deprecated alias | `security.access_roles`. No longer filled from `access_roles` in the processed config; parameter `nowo.routing_kit.panel.role` = first access role |
| `web_ui.layout_template` | default `null` | `null` = `nowo_ui_kit.layouts.admin`. The 1.x default `@NowoRoutingKitBundle/panel/layout.html.twig` (removed) maps to `null`. A layout without `nowo_ui_content` is wrapped by `panel/_legacy_layout.html.twig` (fills `title`, `stylesheets`, `body`, `javascripts`; deprecation logged at runtime) |
| `web_ui.css_framework`, `web_ui.icon_set` | deprecated | Set `nowo_ui_kit.css_framework` / `icon_set`. When set here and not on UiKit they are still seeded into UiKit. **Changed:** without them, RoutingKit no longer forces `custom` / `none` on UiKit (UiKit defaults apply: `bootstrap5` / `bootstrap-icons`) |
| `web_ui.classes` | deprecated, bridged | Prepended as `nowo_ui_kit.theme.bundles.NowoRoutingKitBundle.classes` (table below), each value with `framework_defaults: false` (a 1.x value replaced the whole class list). Your own `theme.bundles.NowoRoutingKitBundle` config wins |
| `web_ui.partials` | deprecated, bridged | `nav` → `theme.bundles.NowoRoutingKitBundle.partials.tabs` (receives the UiKit `tabs` input: `items` with `current`, `aria_label`, `attr`, `region`; the 1.x `current` / `path_prefix` variables are no longer passed), `pagination` → `partials.pagination` (1.x input unchanged). `action_inner` and `modal_shell` are ignored: use `nowo_ui_kit.row_actions_display` / `Action` display modes and a UiKit `dialog` override (input `id`, `title`, `body`, `size`, `attr`) for the import dialog |
| `web_ui.ui_kit_assets` | deprecated | The layout loads UiKit assets; only the legacy layout bridge reads it |
| `web_ui.main_landmark` | deprecated, no effect | The layout owns `<main>` |
| `web_ui.delete_confirm_attr` | deprecated, no effect | Deletes open the UiKit confirm `<dialog>` (CSP-safe); remove your Stimulus confirm controller from the panel |
| `web_ui.import_display`, `show_row_count`, `primary_action_last` | kept | Same meaning |

`web_ui.classes` → UiKit slot:

| 1.x key | Slot | 1.x key | Slot |
| --- | --- | --- | --- |
| `btn_primary` / `btn_secondary` / `btn_danger` / `btn_link` | same name | `table_wrap`, `table` | same name |
| `action_edit` | `action_neutral` (row actions without an explicit variant) | `thead_row` | `table_head_row` |
| `action_delete` | `action_danger` | `th` | `table_head_cell` |
| `row_actions` | `row_actions` | `th_actions` | `table_actions_head` |
| `page_header`, `page_title`, `page_intro`, `toolbar` | same name | `row` | `table_row` |
| `flash` | `flash_success` | `td` | `table_cell` |
| `alert` | `flash_error` | `td_actions` | `table_actions_cell` |
| `muted`, `empty` | same name | `form_panel`, `form`, `form_actions` | same name |
| `badge_yes` / `badge_no` | `badge_success` / `badge_neutral` | `import_title` | `card_title` |
| `modal_form_body` | `modal_body` | `delete_form`, `inline_form`, `count`, `results`, `form_heading`, `form_hint`, `import_form`, `modal_form`, `modal_actions` | dropped (no such region; the count is the table caption, wrappers come from the scaffold) |

### Template contract

What hosts may notice:

- **Pages → scaffolds.** `panel/index` and `panel/redirects/index` extend `@NowoUiKit/page/list.html.twig`,
  `panel/form` and `panel/redirects/form` extend `page/form`, `panel/redirects/delete` extends `page/confirm`. Each page
  receives `page` (UiKit view model) plus the context listed in [TEMPLATES.md](TEMPLATES.md). New page
  `panel/delete.html.twig` (`page/confirm`, `web_ui.templates.delete`): the no-JS route path delete confirmation. The 1.x variables
  `definitions`, `redirects`, `export_form`, `clear_cache_form`, `delete_forms`, `errors`, `page` (number), `pages`,
  `total`, `page_size`, `item_count` and `form` (now `page.form`) are gone; `import_form`, `path_prefix`, `status`,
  `definition`, `conflicts`, `redirect`, `role_gate_disabled` remain.
- **Removed templates:** `panel/layout.html.twig`, `panel/base.html.twig`, `panel/_modal_shell.html.twig`. New:
  `panel/_notices.html.twig`, `panel/_import_form.html.twig`, `panel/_legacy_layout.html.twig` (deprecated);
  `panel/_nav.html.twig` is removed: the section tabs are `page.header.tabs` (UiKit `AreaNav`), rendered by the
  `page_header` partial (region `area-nav`); the context variable `nav` is gone (`nav_current` stays). Other menus can
  reuse the navigation with `nowo_ui_area_nav('routing_kit', {current: …})`.
- **Blocks:** the `rk_*` blocks are gone. Overrides move to the UiKit data blocks (or to the theme):

  | 1.x block | 1.9 |
  | --- | --- |
  | `rk_row`, `rk_enabled_cell` | `nowo_ui_list_row` (cells only; `row`, `row_key`, `loop`, `page` in context) |
  | `rk_table`, `rk_table_head`, `rk_results`, `rk_count`, `rk_pagination`, `rk_empty_state`, `rk_row_actions` | scaffold regions: theme slots, `theme.partials` (`pagination`, `empty`, `row_actions`) or a `page/list` scaffold override; header cells `nowo_ui_list_head` |
  | `rk_import` | `nowo_ui_list_after` (inline card) / `nowo_ui_modals` (modal) |
  | `rk_page_header`, `rk_page_title`, `rk_page_intro`, `rk_page_actions`, `rk_back_link` | `nowo_ui_page_header` (the `page_header` partial; title / intro / actions come from `page.header`) |
  | `rk_nav` | `page.header.tabs` (rendered by the `page_header` partial; theme the `tabs` partial / slots) |
  | `rk_status` | `nowo_ui_flashes` |
  | `rk_form`, `rk_form_heading`, `rk_form_errors`, `rk_form_fields`, `rk_form_actions` | `nowo_ui_form_body` (call `{{ parent() }}` for the remaining rows), `nowo_ui_form_aside`; submit / cancel from `page.submitLabel` / `page.cancelHref` |
  | `rk_confirm` | none (`page/confirm` scaffold; `confirm_*` slots) |
  | `title` | `nowo_ui_document_title` |

  `rk_page_header_in_layout` and `rk_title_suffix` have no effect: the layout decides where `nowo_ui_page_header`
  renders and how `nowo_ui_document_title` is suffixed.
- **Deletes:** route path and redirect deletes from the lists are UiKit `Action`s with a `Confirm` (toggle button +
  `<dialog>` rendered once after the table, `nowo-ui-confirm.js`); no inline `confirm()`. The edit forms get a danger
  zone with the same delete. Without JavaScript a `<noscript>` link (`Action::fallbackHref`, class
  `nowo-ui-action__fallback`) opens the full-page confirmation: `GET {path_prefix}/redirects/{id}/delete` (kept) and
  the new `GET {path_prefix}/delete/{id}` for route paths (the route accepted only POST before); both delete on POST
  with the CSRF token.
- **Import modal** (`web_ui.import_display: modal`): the UiKit `dialog` partial (native `<dialog>`, no Bootstrap
  classes) opened by the header action's `data-nowo-ui-dialog-open`; Cancel uses `data-nowo-ui-dialog-close`.
  Host layouts that list UiKit scripts themselves add `js/nowo-ui-dialog.js`.
- **Markup / test ids:** kept `data-testid` values: `routing-kit-definitions-table`, `routing-kit-definition-form`,
  `routing-kit-redirect-form-panel` (now on the scaffold root, always present), `redirect-table` (scaffold root, was the
  `<table>`), `redirect-new`, `redirect-delete` (dialog toggle button, was a link), `redirect-delete-form` (confirm page
  root, was the `<form>`), `redirect-status`. New: `routing-kit-nav-routes`, `routing-kit-nav-redirects`,
  `routing-kit-role-gate-warning`, `routing-kit-import-form`, `routing-kit-import-open`, `routing-kit-import-dialog`,
  `routing-kit-delete-form`, `routing-kit-conflicts`. Scaffold regions carry
  `data-nowo-ui-region`.
- **Count line:** "N row(s)" / "N redirect(s)" is the table `<caption>` (hidden when the list is empty; the empty
  state shows instead).
- **Classes:** markup carries `nowo-ui-*` classes plus the theme slot classes for `nowo_ui_kit.css_framework`; the
  `routing_kit` FormKit profile (field classes) is unchanged.
- **Translations:** new keys `panel.delete_title`, `panel.delete_confirm_message`, `panel.import_placeholder`,
  `panel.form.path_placeholder`, `redirects.form.source_placeholder`, `redirects.form.target_placeholder` — add them if
  you override the `NowoRoutingKitBundle` domain. `panel.delete_confirm` is no longer used.

## To 1.8.1

From **1.8.0** — CHANGELOG link references and SECURITY.md checklist corrected.

```bash
composer update nowo-tech/routing-kit-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.8.0

From **1.7.2** — redirects keep the query string and skip unsafe methods; canonical table cached; Symfony >=7.4.

```bash
composer update nowo-tech/routing-kit-bundle
```

- **Symfony 7.4+** is now required for every `symfony/*` dependency (some components still allowed `^7.0`). Symfony 7.0–7.3 hosts must upgrade to 7.4 LTS first.
- Canonical / trailing-slash redirects only fire for `GET`/`HEAD` and keep the query string. If you relied on `POST` to an alias path being redirected, post to the canonical path instead.
- The canonical path table is cached in `cache.app` by default (key follows the route-table version; panel writes clear it). Set `redirects.canonical_cache_pool: ~` to read the storage once per request instead, e.g. if you edit the JSON / storage outside the panel with `auto_invalidate_cache: false` (otherwise changes show up after at most one hour or a `cache:clear`).
- With `security.allow_unauthenticated: true` the panel now shows the UNSAFE banner, as documented.
- New translation key `redirects.error.save_failed`: add it if you override the `NowoRoutingKitBundle` domain.

## To 1.7.2

From **1.7.1** — no breaking changes; default markup is unchanged.

```bash
composer update nowo-tech/routing-kit-bundle
```

- Optional: if your `web_ui.layout_template` wraps the content block in its own `<main>`, set `web_ui.main_landmark: false` so the panel pages render a `<div>` wrapper instead of a nested `<main>`.

## To 1.7.1

From **1.7.0** — dependency refresh and repository maintenance.

```bash
composer update nowo-tech/routing-kit-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.7.0

No breaking changes; with no new option the panel renders as in 1.6 (English strings included).

```bash
composer update nowo-tech/routing-kit-bundle
php bin/console cache:clear
```

- Copies of `panel/base.html.twig`, `panel/index.html.twig` or `panel/form.html.twig` made only to change classes, move actions into your header, show the import in a modal or replace the inline `confirm()` can be deleted: use `web_ui.classes`, `web_ui.partials`, `web_ui.ui_kit_assets`, `web_ui.import_display`, `web_ui.show_row_count`, `web_ui.primary_action_last`, `web_ui.delete_confirm_attr` and a layout that renders the slot blocks (see [USAGE — Customising the admin chrome](USAGE.md#customising-the-admin-chrome)).
- A remaining override can extend the original: `{% extends '@!NowoRoutingKitBundle/panel/index.html.twig' %}` and redefine blocks only.
- Host translation overrides for `panel.export`, `panel.import`, `panel.yes`… keep working (application catalogues win); you can drop the ones that match the bundle now.
- The import form labels are now the keys `panel.import_payload` / `panel.import_replace_all` (same English text).

## To 1.6.0

No breaking changes. The new operator URL redirect manager is **off by default**; existing apps need no change.

To adopt it:

```yaml
nowo_routing_kit:
    url_redirects:
        enabled: true
```

- Default storage is a JSON file (`var/routing_kit/redirects.json`, not web-accessible). For a database, implement `UrlRedirectStorageInterface` in the host, set `url_redirects.storage`, and generate the migration in the host (`doctrine:migrations:diff`); the bundle ships no entity. See [REDIRECTS.md](REDIRECTS.md#storage).
- Register a `LivePathCheckerInterface` for slug routes (`/blog/{slug}`…) so retired posts can be redirected.
- Review `url_redirects.protected_prefixes` / `protected_starts`: setting either **replaces** the defaults; keep your back-office and auth prefixes.
- Optional async hit counting: `url_redirects.hits.message_bus` + route `Nowo\RoutingKitBundle\Redirect\Message\RecordRedirectHit` to an async transport.
- Panel templates that override `panel/index.html.twig` may include `@NowoRoutingKitBundle/panel/_nav.html.twig` (`{current: 'routes', path_prefix: path_prefix}`) to show the Routes | Redirects switcher.

## To 1.5.1

From **1.5.0** — dependency updates only.

```bash
composer update nowo-tech/routing-kit-bundle
```

No breaking changes. **No application upgrade steps.**

## To 1.5.0

From **1.4.6** — `access_roles` fail-closed.

```bash
composer update nowo-tech/routing-kit-bundle
php bin/console cache:clear
```

- Empty `security.access_roles` / `panel.role: null` no longer disables the in-bundle gate. Set at least one role, a custom checker, or demo-only `allow_unauthenticated`.

## To 1.4.6

From **1.4.5** — REQ-CS-008 Igor FrankenPHP worker audit (igor-php require-dev, igor.json, make igor).

```bash
composer update nowo-tech/routing-kit-bundle
php bin/console cache:clear
```

- No application upgrade steps for require-dev Igor tooling (REQ-CS-008). Consumers do not pull `igor-php/igor-php` transitively.

## To 1.4.5

No configuration changes. Compatible with FrankenPHP **worker** mode when the kernel is **not** reset between requests (`services_resetter` / `kernel.reset` optional). Panel changes are applied by every worker that shares `%kernel.cache_dir%` on its next main request; restarting workers after editing paths is no longer needed. Notes:

- The route-table version is stored in `%kernel.cache_dir%/nowo_routing_kit_route_table.version`. Workers that do not share the same cache directory (several hosts / containers with a shared storage file) still need a restart or a cache clear on each host.
- In-process rebuild supports Symfony's FrameworkBundle router and any router service implementing `Symfony\Contracts\Service\ResetInterface`. With another router decorator, a warning is logged and workers must be restarted as before.
- `RouteCacheInvalidator` has a new optional constructor argument `$routeLoader` (wired automatically) and a new public method `refreshIfStale()`.
- See [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md) and [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md).

```bash
composer require nowo-tech/routing-kit-bundle:^1.4.5
php bin/console cache:clear
```

## To 1.4.3

Review Flex `when@prod` (`allow_controller_override: false`) and `security_nowo_routing_kit.yaml`. Prefer **`^1.4.3`**.

```bash
composer update nowo-tech/routing-kit-bundle
php bin/console cache:clear
```

## To 1.4.2

No application upgrade steps.

```bash
composer update nowo-tech/routing-kit-bundle
```

## To 1.4.1

No application upgrade steps. **Demos only:** Hot Reload Bundle `^1.4` (FrankenPHP Mercure/`hot_reload`, `dev`/`test`).

```bash
composer update nowo-tech/routing-kit-bundle
php bin/console cache:clear
```

## To 1.4.0

From **1.3.1** — panel index actions (export, clear-cache, import, delete) are Symfony forms (`form_*`).

```bash
composer update nowo-tech/routing-kit-bundle
php bin/console cache:clear
```

### Host Twig overrides

If you override `panel/index.html.twig`, stop using raw HTML `<form>` / Twig `constant(...::CSRF_TOKEN_ID)` / a bare `csrf_token` string. Use the controller context instead:

| Action | Context |
| --- | --- |
| Export | `export_form` |
| Clear cache | `clear_cache_form` |
| Import | `import_form` |
| Delete (per row) | `delete_forms[row.id]` |

`csrf_token` is no longer passed to the index template.

## To 1.3.1

From **1.3.0** — maintainer/CI fix only (`composer.lock` content-hash). No host migration.

```bash
composer update nowo-tech/routing-kit-bundle
```

## To 1.3.0

From **1.2.0** — FormKit panel form + UiKit pagination.

```bash
composer update nowo-tech/routing-kit-bundle
php bin/console cache:clear
php bin/console assets:install --symlink --relative public
```

Requires `nowo-tech/form-kit-bundle` `^2.2` and `symfony/form`. Panel create/edit uses a Symfony form (`RoutePathDefinitionType` + FormKit profile `routing_kit`). Pagination on the panel index uses UiKit `_pagination`.

## To 1.2.0

From **1.1.9** — UiKit, Twig Extra (REQ-TWIG-004), Twig-CS-Fixer.

```bash
composer update nowo-tech/routing-kit-bundle
php bin/console cache:clear
```

### UiKit composition

Panel `nowo-ui.css` now comes from **UiKitBundle** (`asset('css/nowo-ui.css', 'nowo_ui_kit')`). Hosts that linked `nowo_routing_kit` for that stylesheet must switch package (or rely on `panel/base.html.twig`). Require `nowo-tech/ui-kit-bundle` `^1.4` and run `assets:install`. Panel templates import `@NowoUiKitBundle/macros/ui.html.twig` for primary toolbar actions (`ui.btn` on index).

### Twig Extra Bundle (REQ-TWIG-004)

Hosts that render this bundle's Twig templates must install:

```bash
composer require twig/extra-bundle twig/string-extra
```

and enable `Twig\Extra\TwigExtraBundle\TwigExtraBundle`. Flex recipes usually register it automatically.

### Twig-CS-Fixer (maintainers)

Package maintainers: `composer twig:lint` / `composer twig:fix` use `.twig-cs-fixer.php` over `src/` (and `templates/` when present).

## To 1.1.9

Panel access aligns with REQ-UI-002 (`access_roles` / `access_checker` / `allow_unauthenticated`). Defaults keep `[ROLE_ADMIN]` behaviour.

### Install / update

```bash
composer update nowo-tech/routing-kit-bundle
php bin/console cache:clear
```

### Behaviour / security

- Default gate uses `ConfigurableRoutingKitAccessChecker` + `security.access_roles`.
- Custom checker: set `security.access_checker` to a service implementing `RoutingKitAccessCheckerInterface`.
- `allow_unauthenticated: true` registers `AllowAllRoutingKitAccessChecker` (demos only).
- With the panel enabled and `allow_unauthenticated: false`, the host must register SecurityBundle (compile-time `LogicException` otherwise).
- `PanelAccessGuardPass` wires `security.token_storage` at compile time (unchanged need for a firewall on the panel prefix).

### Breaking / migration

No YAML key renames. If you subclassed or constructed `PanelAccessGuard` manually with `(AuthorizationChecker, roles)`, switch to the checker + token storage constructor (apps using DI only need no code changes).

## To 1.1.8

Bugfix: `PanelAccessGuard` now receives `security.authorization_checker` via `PanelAccessGuardPass` (compiler pass). Extension-time wiring often missed SecurityBundle’s service and denied `/_routing` when `security.access_roles` was non-empty.

### Install / update

```bash
composer require nowo-tech/routing-kit-bundle:^1.1.8
php bin/console cache:clear
```

No YAML or public API changes. Clear the container cache so the new compiler pass runs.

Hosts that use the `nowo_routing_kit` asset package need `symfony/asset` (usually already present via FrameworkBundle). The bundle suggests it; the Symfony 8 demo requires it explicitly.

---

## To 1.1.7

Panel CSS extracted to the `nowo_routing_kit` asset package (`css/nowo-ui.css`) for `css_framework: custom` hosts (e.g. Tailwind). See [CONFIGURATION.md](CONFIGURATION.md) and [INSTALLATION.md](INSTALLATION.md) **Assets**.

### Install / update

```bash
composer require nowo-tech/routing-kit-bundle:^1.1.7
php bin/console assets:install
php bin/console cache:clear
```

Requires `symfony/asset` for the named package registration (see **To 1.1.8** note).

Hosts using `web_ui.css_framework: custom` should ensure the host layout exposes a `stylesheets` block so `@NowoRoutingKitBundle/panel/base.html.twig` can inject `nowo-ui.css` via `{{ parent() }}`.

---

## To 1.1.6

Documentation polish only (README section order, USAGE Twig override / `web_ui` guidance). **No config or API migration.**

### Install / update

```bash
composer require nowo-tech/routing-kit-bundle:^1.1.6
php bin/console cache:clear
```

Hosts that already use `web_ui.layout_template` / `security.access_roles` from **1.1.5** need no further changes.

---

## To 1.1.5

Compliance remedia (2026-07-29): open-PR gate, zero direct deprecations, attribute panel routes, UI/security contract, list pagination.

### Install / update

```bash
composer require nowo-tech/routing-kit-bundle:^1.1.5
php bin/console cache:clear
```

### Behaviour / security

- Panel routes use PHP `#[Route]` attributes (imported with `panel.path_prefix`).
- Canonical private access: `security.access_roles` (+ optional `allow_unauthenticated` for demos). `panel.role` remains a BC alias.
- Panel look-and-feel: `web_ui.layout_template` / `css_framework` / `icon_set` + `nowo-ui-*` markup.
- Index list paginates with `panel.list_page_size` (default 50); storage still hard-capped by `panel.max_definitions`.

### Breaking / migration

| Topic | Before | After |
| --- | --- | --- |
| Preferred panel ACL | `panel.role` | `security.access_roles` (`panel.role` still works) |
| Demo open panel | `panel.role: null` | same, or `security.access_roles: []` / `allow_unauthenticated: true` |
| Host layout | Fixed bundle layout | Set `web_ui.layout_template` to the project layout |

---

## To 1.1.4

Security hardening follow-up (path safety, loader defense, panel ids, import limits) plus FrankenPHP / TOC docs.

### Install / update

```bash
composer require nowo-tech/routing-kit-bundle:^1.1.4
php bin/console cache:clear
```

### Behaviour / security

- Stored / redirect paths reject more open-redirect and injection shapes (C0/DEL controls, encoded `\` / null / CR-LF, double-encoded `//`, `..` segments).
- `redirects.root_home_path` must already be a safe absolute public path at compile time.
- With `allow_controller_override: true`, `DbRouteLoader` still applies only the `#[Routable]` discovery controller for that route (tampered storage cannot inject another controller).
- Definition `id` values must match `[A-Za-z0-9_.-]+` (panel routes and saves/imports).
- Panel import raw JSON is capped at **1 MiB**.

### Breaking / migration

| Topic | Before (1.1.3) | 1.1.4 |
| --- | --- | --- |
| Path / `root_home_path` edge cases | Some encoded / control / `..` shapes accepted | Rejected by `SafePublicPath` / config validation |
| Stored controller override (override on) | Loader could honor any stored string | Only discovery allowlist for that route name |
| Definition ids | Mostly unrestricted (`[^/]+` in panel routes) | `[A-Za-z0-9_.-]+` only |
| Huge import payloads | Limited only by `max_definitions` after decode | Rejected at **1 MiB** before decode |

Default `uniqid('rk_', true)` ids remain valid. Re-save or clean any hand-edited `paths.json` rows with unsafe paths or exotic ids before upgrade.

---

## To 1.1.3

Documentation alignment with 1.1.2 behaviour. No code or config changes.

```bash
composer require nowo-tech/routing-kit-bundle:^1.1.3
```

---

## To 1.1.2

Storage / HMAC / demo hardening follow-up.

### Install / update

```bash
composer require nowo-tech/routing-kit-bundle:^1.1.2
php bin/console cache:clear
```

### Behaviour / security

- Corrupt `paths.json` throws instead of returning an empty set (prevents accidental wipe).
- Filesystem storage uses an exclusive lock file (`paths.json.lock`) during mutations.
- Signing keys shorter than **32** characters are rejected (config + runtime). Strengthen `kernel.secret` or set `panel.export_signing_key`.
- UI warning when `panel.role` is null.

### Breaking / migration

| Topic | Before (1.1.1) | 1.1.2 |
| --- | --- | --- |
| Corrupt `paths.json` | Loaded as empty → next save could wipe | Throws; fix or restore the file |
| Signing key | Any length | ≥ **32** characters |
| Concurrent writes | Last-write-wins without lock | Exclusive flock on `.lock` |
| `panel.role: null` | Silent | Panel (+ demo) shows **UNSAFE** banner |

Ensure `APP_SECRET` / `panel.export_signing_key` is at least 32 characters before using export/import. Backup `paths.json` before upgrade if it might be corrupt.

---

## To 1.1.1

Import/export hardening follow-up to 1.1.0.

### Install / update

```bash
composer require nowo-tech/routing-kit-bundle:^1.1.1
php bin/console cache:clear
```

### Behaviour / security

- Signed **import** now runs through `RoutePathManager` (same allowlists, conflicts, and `max_definitions` as panel saves). Invalid rows fail the whole import.
- With `allow_controller_override: false` (default), imported `controller` fields are stripped and the DB loader ignores any leftover stored override.
- **Export** is POST + CSRF only (update any bookmarks/scripts that used GET `/_routing/export`).
- Invalid `panel.path_prefix` values (e.g. `javascript:…`) are rejected at config compile time.
- Conflict detection covers default-locale fallback paths, trailing-slash variants, and disabled rows.

### Breaking / migration

| Topic | Before (1.1.0) | 1.1.1 |
| --- | --- | --- |
| Export | GET or POST | **POST + CSRF** only |
| Custom storage | `save` / `delete` | Must implement `replaceAll(array $definitions): array` |
| `path_prefix` | Any non-empty scalar | Must match `^/[A-Za-z0-9/_-]+$` |
| Import | HMAC only; wrote storage directly | HMAC + full manager validation |

No YAML key renames. Prefer a dedicated `panel.export_signing_key` over a weak `kernel.secret`.

---

## To 1.1.0

Security hardening release: CSRF fail-closed, panel role gate, allowlists, path safety, trailing-slash fix, and signed export/import.

### Requirements

Same PHP/Symfony ranges as 1.0.x, plus **`symfony/security-csrf`** (now required).

### Install / update

```bash
composer require nowo-tech/routing-kit-bundle:^1.1
php bin/console cache:clear
```

### Breaking / migration

| Topic | Before | 1.1.0 |
| --- | --- | --- |
| CSRF | Optional (fail-open without manager) | Required; invalid token rejected |
| `panel.role` | n/a | Default `ROLE_ADMIN` (needs Security Bundle checker, or set `null`) |
| Controller override | Free-form text field | Off by default; allowlist only when enabled |
| `route_name` / `locale` | Weakly validated | Must be `#[Routable]` + configured locale |
| Paths | Only “starts with `/`” | Rejects `//`, schemes, control chars |

Recommended app security:

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/_routing, roles: ROLE_ADMIN }

# config/packages/nowo_routing_kit.yaml
nowo_routing_kit:
    panel:
        role: ROLE_ADMIN
        allow_controller_override: false
```

Demo apps without Security Bundle should set `panel.role: null` **and** keep the panel off public networks.

### Behaviour notes

- Export/import uses HMAC (`panel.export_signing_key` or `kernel.secret`).
- `enabled: false` fully disables panel + loader + redirect subscribers.

---

## To 1.0.3

Patch release: restores Symfony 8 Composer constraints again (narrowed on `main` after `v1.0.2` by the CI code-style job) and refreshes security / routing docs.

### Requirements

Same as 1.0.2:

- **PHP** `>=8.2` and `<8.6`. Symfony **8.x** requires **PHP 8.4+**.
- **Symfony** `^7.4 || ^8.0` (CI minors: **7.4**, **8.0**, **8.1**).
- **Twig** for the routing CRUD panel templates.

### Install / update

```bash
composer require nowo-tech/routing-kit-bundle:^1.0.3
php bin/console cache:clear
```

If Composer previously resolved only Symfony 7 packages because of the narrowed constraints on `main` between `v1.0.2` and this tag, re-run update so Symfony 8 apps pick up `|| ^8.0`.

### Behavior notes

- Unchanged from 1.0.2: routes are `{name}.{locale}` with `_canonical_route`.
- Protect `/_routing` (or `panel.path_prefix`) with a firewall — the bundle does not ship authentication. See [SECURITY.md](SECURITY.md).

### Breaking changes

None.

---

## To 1.0.2

Patch release: restores Symfony 8 Composer constraints (broken in 1.0.1) and fixes locale URL generation via `_canonical_route`.

### Requirements

Same as 1.0.0 / 1.0.1:

- **PHP** `>=8.2` and `<8.6`. Symfony **8.x** requires **PHP 8.4+**.
- **Symfony** `^7.4 || ^8.0` (CI minors: **7.4**, **8.0**, **8.1**).
- **Twig** for the routing CRUD panel templates.

### Install / update

```bash
composer require nowo-tech/routing-kit-bundle:^1.0.2
php bin/console cache:clear
```

### Behavior notes

- Loaded routes are named `{route}.{locale}` with `_canonical_route` set. Prefer `path('app_home', { _locale: 'en' })` / `generate('app_home', ['_locale' => 'en'])`.
- With `register_unprefixed_default: true`, the default locale has no `/{locale}` prefix; other locales keep the prefix.

### Breaking changes

None beyond 1.0.1 (`#[Routable]` without `label`).

---

## To 1.0.1

Patch release: the CRUD panel lists routes by **`name`** only; the optional `label` argument on `#[Routable]` is removed.

### Requirements

Same as 1.0.0:

- **PHP** `>=8.2` and `<8.6`. Symfony **8.x** requires **PHP 8.4+**.
- **Symfony** `^7.4 || ^8.0` (CI minors: **7.4**, **8.0**, **8.1**).
- **Twig** for the routing CRUD panel templates.

### Install / update

```bash
composer require nowo-tech/routing-kit-bundle:^1.0
php bin/console cache:clear
```

### Breaking changes

| Topic | Before (1.0.0) | 1.0.1 |
| --- | --- | --- |
| `#[Routable]` | Optional `label: '…'` for panel display | Constructor is `name` + `params` only |
| Panel `<select>` | Could show `label` when set | Always shows `route_name` |

If you used `label:`, drop it:

```php
// Before
#[Routable(name: 'app_about', label: 'About')]

// After
#[Routable(name: 'app_about')]
```

Leaving `label:` causes a PHP error (`Unknown named parameter $label`).

### Behaviour notes (non-breaking)

Storage rows, loader behaviour, config keys, and SeoKit bridge are unchanged.

---

## To 1.0.0

First public release. No prior Packagist versions — install fresh.

### Requirements

- **PHP** `>=8.2` and `<8.6`. Symfony **8.x** requires **PHP 8.4+**.
- **Symfony** `^7.4 || ^8.0` (CI minors: **7.4**, **8.0**, **8.1**).
- **Twig** for the routing CRUD panel templates.

### Install

```bash
composer require nowo-tech/routing-kit-bundle:^1.0
```

With Flex, the recipe registers the bundle and copies config. Without Flex, see [INSTALLATION.md](INSTALLATION.md).

### Routes (order matters)

```yaml
# config/routes.yaml
nowo_routing_kit_panel:
    resource: '@NowoRoutingKitBundle/Resources/config/routes.yaml'

# … your application routes …

nowo_routing_kit_db:
    resource: .
    type: nowo_routing_kit
```

Import the DB loader **last** so RoutingKit locale routes (`{name}.{locale}` with `_canonical_route`) are registered after attribute routes.

### Suggested first-time config

```yaml
nowo_routing_kit:
    default_locale: en
    locales: [en, es]
    panel:
        path_prefix: /_routing
    redirects:
        canonical_enabled: true
        root_enabled: false
    seo_kit_bridge: true
```

Mark offerable controllers with `#[Routable]` / `RouteParam`, then open `/_routing` to create path rows.

### Security

Protect `/_routing` (or your `panel.path_prefix`) with a Symfony firewall — the bundle does not ship authentication. See [SECURITY.md](SECURITY.md).

### SeoKit

Requires a SeoKit build that exposes `SeoPathBuilderInterface`. Keep `default_locale` / `locales` aligned between both bundles when `seo_kit_bridge: true`.

### Storage

Default path file: `%kernel.project_dir%/var/routing_kit/paths.json`. Ensure `var/` is not web-accessible. Swap storage via `storage.path_storage` / `LocaleProviderInterface` as needed.
