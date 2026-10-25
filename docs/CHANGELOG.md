# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.10.0] - 2026-10-18](#1100---2026-10-18)
- [[1.9.3] - 2026-10-18](#193---2026-10-18)
- [[1.9.2] - 2026-10-18](#192---2026-10-18)
- [[1.9.1] - 2026-10-18](#191---2026-10-18)
- [[1.9.0] - 2026-10-18](#190---2026-10-18)
- [[1.8.1] - 2026-10-13](#181---2026-10-13)
- [[1.8.0] - 2026-10-13](#180---2026-10-13)
- [[1.7.2] - 2026-10-13](#172---2026-10-13)
- [[1.7.1] - 2026-10-13](#171---2026-10-13)
- [[1.7.0] - 2026-10-13](#170---2026-10-13)
- [[1.6.0] - 2026-10-09](#160---2026-10-09)
- [[1.5.1] - 2026-10-09](#151---2026-10-09)
- [[1.5.0] - 2026-09-28](#150---2026-09-28)
- [[1.4.6] - 2026-09-27](#146---2026-09-27)
- [[1.4.5] - 2026-09-25](#145---2026-09-25)
- [[1.4.4] - 2026-08-24](#144---2026-08-24)
- [[1.4.3] - 2026-08-20](#143---2026-08-20)
- [[1.4.2] - 2026-08-19](#142---2026-08-19)
- [[1.4.1] - 2026-08-18](#141---2026-08-18)
- [[1.4.0] - 2026-08-12](#140---2026-08-12)
- [[1.3.1] - 2026-08-07](#131---2026-08-07)
- [[1.3.0] - 2026-08-05](#130---2026-08-05)
- [[1.2.0] - 2026-08-04](#120---2026-08-04)
- [[1.1.9] - 2026-08-03](#119---2026-08-03)
- [[1.1.8] - 2026-08-01](#118---2026-08-01)
- [[1.1.7] - 2026-08-01](#117---2026-08-01)
- [[1.1.6] - 2026-07-30](#116---2026-07-30)
- [[1.1.5] - 2026-07-29](#115---2026-07-29)
- [[1.1.4] - 2026-07-26](#114---2026-07-26)
- [[1.1.3] - 2026-07-26](#113---2026-07-26)
- [[1.1.2] - 2026-07-26](#112---2026-07-26)
- [[1.1.1] - 2026-07-26](#111---2026-07-26)
- [[1.1.0] - 2026-07-26](#110---2026-07-26)
- [[1.0.3] - 2026-07-26](#103---2026-07-26)
- [[1.0.2] - 2026-07-26](#102---2026-07-26)
- [[1.0.1] - 2026-07-26](#101---2026-07-26)
- [[1.0.0] - 2026-07-26](#100---2026-07-26)

## [Unreleased]

### Changed

- Developer tooling: `make release-check` runs the full REQ-MAKE-002 chain with `ensure-up` first (then the Cursor co-author, open-PR and Twig `extra` gates, composer-sync, cs-fix, cs-check, rector-dry, phpstan, igor, translations, coverage-check and the demo release-check).
- `panel/_import_form.html.twig` renders the import form body through the rendered-aware child loop after the full-width `payload_json` row (the `replace_all` checkbox and the CSRF token are now rendered by the loop; same markup, field names and form theme) (REQ-TWIG-003 / REQ-TWIG-005).
- Docs: USAGE states the Symfony Form / child-loop rule for panel forms and that host overrides and the demo must not reintroduce raw `<form>` / `<input>` (REQ-TWIG-005 rule 9).

### Added

- Developer tooling: `make check-no-raw-html-form` (REQ-TWIG-005) fails on raw `<form>` / `<input>` / `<textarea>` / `<select>` in bundle views and demo templates (only the form theme `panel/_form_theme.html.twig` is allowlisted) and runs in `make release-check` after the Twig `extra` gate.

## [1.10.0] - 2026-10-18

Opt-in default locale without URL prefix for every route that has a bare twin. See [UPGRADING](UPGRADING.md#to-1100).

### Added

- `default_locale_unprefixed` (off by default; `true` or `{enabled, twin_suffix, redirect_prefixed, redirect_status}`):
  - `Routing\DefaultLocaleRouter` decorates `router.default`: generating a route for the default locale (explicit `_locale` or the request context's) returns its bare twin `<route><twin_suffix>` (default `_unlocalized`, the AuthKit / SiteBackup convention, e.g. `nowo_auth_kit_login_unlocalized` = `/login`) when it exists, so every link, redirect, form action and e-mail URL drops the default-locale prefix, whoever generates it. Routes without a twin and other locales are unchanged; missing twins are memoized (worker-safe, cleared with the router state by `RouteCacheInvalidator`).
  - `EventSubscriber\DefaultLocalePrefixRedirectSubscriber` (`redirect_prefixed`, default `true`): `GET` / `HEAD` `/{default}/…` of a route with a twin answers `redirect_status` (default 301) to the bare URL, query string kept; POST is never redirected.
  - Works with `register_unprefixed_default` (RoutingKit's stored routes already use the bare path for the default locale). Extracted from Symfony Beacon.

### Changed

- `RouteCacheInvalidator` unwraps `DefaultLocaleRouter` to reset the decorated Symfony router (and clears its twin memo).
- `phpunit.xml.dist`: `memory_limit` 512M (several real kernels in the Integration suite).

## [1.9.3] - 2026-10-18

Locale lists accept %parameters% and env vars (no compile error with a parameter). See [UPGRADING](UPGRADING.md#to-193).

### Fixed

- `locales` given as a container parameter or env var (`'%app.locales%'`, `'%kernel.enabled_locales%'`,
  `'%env(json:APP_LOCALES)%'`) no longer breaks the container build ("Expected array, but got string"): `prepend()`
  processed the whole raw host config, where the parameter is still a string (and `kernel.enabled_locales` is not
  defined yet), and the node only accepted a literal list. `prepend()` now reads only the `web_ui` / `panel` keys,
  `locales` accepts a list or a parameter / env placeholder, and an env var is read at runtime by
  `ConfigurableLocaleProvider` (routes are registered per resolved locale). See
  [CONFIGURATION](CONFIGURATION.md#locales-from-a-parameter-or-env-var).

## [1.9.2] - 2026-10-18

Requires UiKit ^2.2; controller choices not translated (no missing message). See [UPGRADING](UPGRADING.md#to-192).

### Changed

- Requires `nowo-tech/ui-kit-bundle` **^2.2** (default-theme polish: centred confirm dialog with a close button and a
  solid destructive button, table header hierarchy, visible actions column header, tinted inline code). See
  [UPGRADING](UPGRADING.md#unreleased).
- The redirect status notice passes its message through the `flashes` partial's `flash_messages` input (UiKit 2.2;
  the 2.0 `flashes` input is deprecated and ignored inside page scaffolds).
- The deprecated 1.x layout bridge (`panel/_legacy_layout.html.twig`) prints the UiKit runtimes through the UiKit
  `scripts` partial (with the CSP nonce) instead of its own list (modal, confirm, dialog); demo host layout likewise.

### Fixed

- **Missing messages (profiler):** with `panel.allow_controller_override`, the controller select looked the
  controller FQCNs up as messages of `NowoRoutingKitBundle`. The choices are literals now
  (`choice_translation_domain: false`) and "— discovery default —" is the select placeholder (`panel.form.controller_empty`,
  value `''` as before). The literal placeholders (`/about`, JSON) and locale / route choices were already fixed in
  1.9.0. A contract test renders every panel page (plus the path form with the controller override) with a
  `DataCollectorTranslator` and asserts no missing message.

## [1.9.1] - 2026-10-18

UiKit icon row actions, consistent Yes/No badges and code cells, import in a dialog by default, confirm titles name the item; row-count caption hidden by default. See [UPGRADING](UPGRADING.md#to-191).

Panel polish after the UiKit page-contract migration (visual review of 1.9.0). See [UPGRADING](UPGRADING.md#to-191).

### Changed

- **`web_ui.import_display` defaults to `modal`** (was `inline`): the signed JSON import opens in the UiKit `dialog` from the "Import" page action instead of a card under the paths table; the dialog footer holds Cancel / Import (bound to the form with `form="routing-kit-import-form"`). Set `import_display: inline` to keep the card.
- **`web_ui.show_row_count` defaults to `false`** (was `true`): the "N row(s)" `<caption>` duplicated the pagination summary ("Showing 1–3 of 3"). Set it to `true` to keep the caption.
- Row actions (Edit, Delete) are UiKit icon actions (`Action::icon` `edit` / `delete`, display from `nowo_ui_kit.row_actions_display`; the label stays as `aria-label` / `title` and a visually hidden span). The edit forms' delete action shows the trash icon with its label.
- Delete confirmations name the item: `panel.delete_title` "Delete path %path% (%locale%)?" and `redirects.delete_title` "Delete redirect %source%?" (dialog, full-page confirm and document title); the messages (`panel.delete_confirm_message`, `redirects.delete_confirm`) now describe the effect instead of repeating the item (7 locales).
- Route names, paths and redirect source / target cells use the UiKit `code` slot (`<code class="nowo-ui-code">`), so the monospace cells share size and baseline.
- Import form: full-width textarea (`input_full` slot), labels in sentence case ("Signed JSON payload", "Replace all existing rows"), checkbox as a UiKit check label through the new bundle form theme `@NowoRoutingKitBundle/panel/_form_theme.html.twig` (`PanelView::FORM_THEME`), also applied to the route path and redirect forms (`FormPage::formThemes`).
- Demo: `nowo_ui_kit.icon_set: svg_inline` (icon row actions without a font), default import dialog, host CSS keeps `input_full` full width and leaves `.nowo-ui-check` labels inline; e2e covers the import dialog, icon row actions, the enabled badge, the code cells and the confirm title; screenshots refreshed (paths list, import dialog, redirect delete confirm).

### Fixed

- Enabled badge text: the paths list printed "yes" / "no" while the redirects list printed "Yes" / "No"; both lists now use `panel.yes` / `panel.no`, capitalised in every shipped locale (en, es, de, fr, it, nl, pt).
- The paths list intro (`panel.subtitle`) ended with a literal "/{_locale}/…" and read as truncated; rewritten as a complete sentence in every locale.

## [1.9.0] - 2026-10-18

Panel pages on the UiKit 2.1 standard page contract (REQ-UI-003): list, form and confirm scaffolds, area nav, native import dialog, no-JS delete confirm pages; deprecated panel/web_ui keys bridged. See [UPGRADING](UPGRADING.md#to-190).

Requires `nowo-tech/ui-kit-bundle` **^2.1**.

### Added

- Panel pages on the UiKit page scaffolds: route paths and URL redirects lists → `page/list`, route path and redirect forms → `page/form`, route path and redirect delete fallbacks → `page/confirm`, built from UiKit view models (`ListPage`, `FormPage`, `ConfirmPage`, `Action`, `Confirm`, `Pagination`…). Pages, data blocks and context: [docs/TEMPLATES.md](TEMPLATES.md).
- `Nowo\RoutingKitBundle\Panel\PanelView`: renders the panel pages (per-page template, layout override, role-gate flag) and builds the section tabs (`PageHeader::tabs`, UiKit `AreaNav`); also a UiKit `AreaNavProviderInterface` for area `routing_kit` (`nowo_ui_area_nav('routing_kit')`, DashboardMenu).
- `web_ui.path_prefix` (canonical URL prefix, default `/_routing`) and `web_ui.templates.<page>` (`index`, `form`, `delete`, `redirects_index`, `redirects_form`, `redirects_delete`).
- Deleting a path or redirect from a list (and from the edit forms' danger zone) opens the UiKit confirm `<dialog>`; the route path edit form gains a delete action. Without JavaScript a `<noscript>` link (`Action::fallbackHref`) opens the full-page confirmation; new `GET {path_prefix}/delete/{id}` confirmation page for route paths (`panel/delete.html.twig`; the POST stays CSRF-protected).
- Translation keys `panel.delete_title`, `panel.delete_confirm_message`, `panel.import_placeholder`, `panel.form.path_placeholder`, `redirects.form.source_placeholder`, `redirects.form.target_placeholder` (7 locales).
- Page contract tests (`PageContractTestCase`, English and German), BC tests for the deprecated keys and the theme bridge.
- Demo: host layout bridge (`nowo_ui_kit.layouts.admin`) and theme tokens, Playwright e2e (`make -C demo/symfony8 test-e2e`) and WebP screenshots (`make -C demo/symfony8 demo-screenshots`, `docs/images/demo/`).

### Changed

- `web_ui.layout_template` defaults to `null` (UiKit `nowo_ui_kit.layouts.admin`) and must render the UiKit canonical blocks; a 1.x layout that only renders `body` is wrapped by `panel/_legacy_layout.html.twig` (deprecated).
- The section nav is the UiKit area navigation (`PageHeader::tabs`, rendered by the `page_header` partial through the `tabs` partial), the role-gate warning and the redirect status go through the `flash_warning` slot / `flashes` partial, the inline import renders in a UiKit card and the modal import in the framework-free UiKit `dialog` partial (`js/nowo-ui-dialog.js`; also loaded by the legacy layout bridge).
- Form-level and save errors of the route path form are form errors (`form_errors`); the conflict preview renders in the `flash_warning` slot.
- Route names and locales in the route path form are no longer passed through the translator.
- `web_ui.css_framework` / `icon_set` are seeded into `nowo_ui_kit` only when set (they defaulted to `custom` / `none` and forced them on UiKit when the host did not configure UiKit).
- Dev: PHPUnit 11.

### Deprecated

- `panel.enabled` → `web_ui.enabled`; `panel.path_prefix` → `web_ui.path_prefix`; `panel.role` → `security.access_roles`.
- `web_ui.classes` and `web_ui.partials` (`PanelChrome`): bridged into `nowo_ui_kit.theme.bundles.NowoRoutingKitBundle`.
- `web_ui.css_framework`, `web_ui.icon_set` (set them on `nowo_ui_kit`), `web_ui.ui_kit_assets`, `web_ui.main_landmark`, `web_ui.delete_confirm_attr` (no effect).
- A `web_ui.layout_template` that does not render the UiKit canonical blocks.
- Twig globals `nowo_routing_kit_*` (no panel template reads them except the legacy layout bridge).

Since the UiKit 2.0-based state of this branch: UiKit **^2.1**; section tabs moved from the local `panel/_nav.html.twig` include into `PageHeader::tabs` (`AreaNav`; context variable `nav` removed); import modal `modal_shell` → `dialog`; no-JS `fallbackHref` on every delete action and the new route path confirm page; the contract tests no longer allow-list the `modal_shell` classes.

### Removed

- `panel/layout.html.twig` (standalone HTML layout), `panel/base.html.twig`, `panel/_modal_shell.html.twig`, `panel/_nav.html.twig`, the inline `onsubmit="return confirm(…)"` handler and the `rk_*` page blocks / slot blocks (`rk_page_header_in_layout`, `rk_title_suffix`). See the block mapping in UPGRADING.

## [1.8.1] - 2026-10-13

### Changed

- `docs/SECURITY.md`: the release checklist no longer says the UNSAFE banner shows when the role is null (empty roles fail closed); it shows when `security.allow_unauthenticated: true`, as shipped in 1.8.0.

## [1.8.0] - 2026-10-13

### Added

- **`redirects.canonical_cache_pool`** (default `cache.app`, `null` = no pool): the resolved canonical/alias path table is cached under the current route-table version and memoized per request (reset via `kernel.reset`), instead of reading and locking the JSON storage and resolving every row × locale on every main request. Panel / `RoutePathManager` writes (`RoutePathsChangedEvent`) drop the cached table; `RouteCacheInvalidator::currentVersion()` exposes the shared route-table version.

### Fixed

- Canonical and trailing-slash redirects now keep the query string (`utm_*`, pagination, …) on the `Location` target.
- Canonical and trailing-slash redirects only apply to safe methods (`GET`/`HEAD`): a 301 on `POST`/`PUT`/`PATCH`/`DELETE` no longer drops the request body. Unsafe requests do not touch the path storage.
- `panel.role_gate_warning` (UNSAFE banner) was never shown: the panels now pass `security.allow_unauthenticated` as the role-gate flag (definitions panel and redirects panel). The banner text in all 7 locales no longer claims that `security.access_roles: []` / `panel.role: null` disables the gate (an empty list fails closed); `docs/CONFIGURATION.md` and `docs/DEMO-FRANKENPHP.md` aligned.
- Storage failures in the redirect form are logged (`logger`, error level) and shown as the generic translated `redirects.error.save_failed` message instead of the raw exception text (file paths, SQL).

### Changed

- Symfony floor aligned to **7.4** for every component (`symfony/event-dispatcher`, `finder`, `routing`, `security-csrf`, `yaml` and the dev `asset`, `framework-bundle`, `messenger`, `twig-bundle` still allowed `^7.0`); CI matrix drops the untested-floor `7.0` cell.

## [1.7.2] - 2026-10-13

### Added

- **`web_ui.main_landmark`** (bool, default `true`): set `false` when the host layout already renders its own `<main>`. The page wrapper of `panel/index`, `panel/form`, `panel/redirects/index`, `panel/redirects/form` and `panel/redirects/delete` then renders as a `<div>` with the same classes (e.g. `nowo-ui-confirm__panel` on the delete page), avoiding nested `main` landmarks. Exposed as the Twig global `nowo_routing_kit_main_landmark`. Default output is byte-identical.

### Changed

- Dev lock: `rector/rector` 2.5.7 → 2.7.0 (2.5.7 crashed with phpstan 2.3.1, so `make rector-dry` failed on main).

## [1.7.1] - 2026-10-13

### Dependencies

- PHPStan group (3 packages) bumped by Dependabot (development lock; #13).
- `igor-php/igor-php` 0.9.7 → 0.10.1 (require-dev constraint + development lock; #14).
- `vincentlanglet/twig-cs-fixer` 3.14.0 → 4.1.1 (require-dev constraint + development lock; #15).

### Changed

- Repository: `.twig-cs-fixer.cache` is now git-ignored.

## [1.7.0] - 2026-10-13

Admin chrome hooks (spec [`003-admin-chrome-hooks`](../specs/003-admin-chrome-hooks/spec.md)): hosts can theme the panel without copying pages. Default markup is unchanged (snapshot-tested); default English strings are unchanged.

### Added

- `web_ui.classes`: allow-listed class map (buttons, row actions, page header, table cells, badges, empty row, forms, alerts, import). Twig global `nowo_routing_kit_classes`.
- `web_ui.partials`: `nav`, `pagination`, `action_inner` (e.g. UiKit `_action_inner` for `row_actions_display`), `modal_shell`. Twig global `nowo_routing_kit_partials`.
- `web_ui.ui_kit_assets`, `web_ui.import_display` (`inline` | `modal`, with `panel/_modal_shell.html.twig` and the `modal-routing-import` id), `web_ui.show_row_count`, `web_ui.primary_action_last`, `web_ui.delete_confirm_attr` (CSP-friendly delete confirmation, e.g. a Stimulus controller).
- Twig blocks on every panel page (slot blocks `rk_page_title`, `rk_page_intro`, `rk_page_actions`, `rk_back_link`; page blocks `rk_page_header`, `rk_results`, `rk_table`, `rk_row`, `rk_enabled_cell`, `rk_row_actions`, `rk_empty_state`, `rk_pagination`, `rk_import`, `rk_form*`…) and layout contract variables `rk_page_header_in_layout`, `rk_title_suffix`.
- `@!NowoRoutingKitBundle/...` Twig namespace (always the bundle copy) so an app override can extend the original page and redefine blocks only.
- Translation keys `panel.export`, `panel.import`, `panel.import_title`, `panel.import_payload`, `panel.import_replace_all`, `panel.count`, `panel.yes`, `panel.no`, `panel.delete_confirm`, `panel.col.actions`, `panel.role_gate_warning` in all 7 locales; render tests for the default output and every hook; catalogue parity test.

### Changed

- No hard-coded English left in panel templates ("Export", "Import", "Import (signed JSON)", "row(s)", "yes"/"no", "Delete?", the role-gate warning); the import form labels use translation keys. English output is identical.
- `panel.*` strings translated in de, fr, it, nl, pt (they were English).
- Development: `composer.json` pins `config.platform.php` to 8.2.0 so the committed lock stays installable on the minimum PHP; CI overrides the platform per matrix cell.
- Dev lock: `nowo-tech/form-kit-bundle` v2.8.0, `nowo-tech/ui-kit-bundle` v1.12.0 (`make composer-sync`).
- Dev lock re-resolved for PHP 8.2 (Symfony components 8.1.x -> 7.4.x, `symfony/polyfill-php83` added, `symfony/polyfill-deepclone` dropped; `nowo-tech/form-kit-bundle` v2.7.0, `nowo-tech/ui-kit-bundle` v1.10.0).

## [1.6.0] - 2026-10-09

### Added

- **Operator URL redirect manager** (opt-in, `url_redirects.enabled: false` by default; REQ-REDIR-001…008, [docs/REDIRECTS.md](REDIRECTS.md)):
  - `UrlRedirectSubscriber` (`kernel.request`, priority 33, before routing): main GET/HEAD requests; skips protected paths and live pages; visitor query string only for internal targets; unsafe stored targets ignored.
  - `UrlRedirect` model (`normalizePath()` percent-decodes, `isInternalTarget()`, `isExternalTarget()`), `UrlRedirectStorageInterface` + `FilesystemUrlRedirectStorage` (JSON, locked, fail-closed); `url_redirects.storage` for a host (e.g. Doctrine) implementation.
  - `UrlRedirectMatcher`: enabled-redirect map cached in `url_redirects.cache_pool` (`cache.app`, TTL `cache_ttl` 3600 s), invalidated by panel writes, `ResetInterface` for worker mode; failures degrade to "no redirect".
  - `ProtectedPaths` with configurable `protected_prefixes` / `protected_starts` (locale-prefix aware via `LocaleProviderInterface`; `panel.path_prefix` always protected).
  - `LivePublicPaths` + tagged `LivePathCheckerInterface` (`nowo_routing_kit.live_path_checker`, autoconfigured) so retired slug URLs can be redirected while live pages always win.
  - Hit counter (`hits`, `lastHitAt`): synchronous, or async through Messenger (`hits.message_bus`, `RecordRedirectHit` + handler).
  - Panel section `{panel.path_prefix}/redirects` (`UrlRedirectPanelController`, `UrlRedirectType`, `UrlRedirectValidator`): list with pagination, create / edit / delete with CSRF, delete confirmation page without inline JavaScript, protected / live / loop / chain / duplicate checks, audit by user identifier. Routes | Redirects switcher in both panel sections.
  - Translations (`redirects.*`, `nav.*`) in en, es, fr, de, it, pt, nl.
- `PanelAccessGuard::userIdentifier()`; Twig global `nowo_routing_kit_url_redirects_enabled`.

### Dev

- `symfony/messenger` added to `require-dev`; kernel integration test (`tests/Integration/Redirect`) for the panel and listener; `igor.json` excludes `src/Model` (value objects, not services).

## [1.5.1] - 2026-10-09

### Dependencies

- Symfony group bumps (Dependabot) and lockfile refresh: Symfony 8.1.8, `twig/twig` 3.30.0, `nowo-tech/form-kit-bundle` 2.6.1, `nowo-tech/ui-kit-bundle` 1.9.1; dev `nowo-tech/phpstan-frankenphp` 1.2.3, `phpunit/phpunit` 10.5.66.
- Demo (`demo/symfony8`): regenerated `config/reference.php`.

## [1.5.0] - 2026-09-28

### Security

- Empty `security.access_roles` is fail-closed via the access checker; empty roles no longer disable the in-bundle gate (`roleGateDisabled` is not used to open access).

## [1.4.6] - 2026-09-27

### Added

- **REQ-CS-008:** `igor-php/igor-php` (require-dev only), root `igor.json`, Composer/`Makefile` `igor` target, and `release-check` wiring for FrankenPHP worker-state audit.

### Changed

- **Worker safety (Igor):** justified `// @igor-ignore` annotations and/or `ResetInterface` / request-scoped fixes so `make igor` passes on package `src/`.

[1.5.1]: https://github.com/nowo-tech/RoutingKitBundle/releases/tag/v1.5.1
[1.5.0]: https://github.com/nowo-tech/RoutingKitBundle/releases/tag/v1.5.0
[1.4.6]: https://github.com/nowo-tech/RoutingKitBundle/releases/tag/v1.4.6

## [1.4.5] - 2026-09-25

### Fixed

- **FrankenPHP / long-running workers (kernel not reset):** path edits, imports, deletes and "clear cache" in the panel now reach every worker without a restart. `RouteCacheInvalidator::invalidate()` writes a route-table version file (`%kernel.cache_dir%/nowo_routing_kit_route_table.version`), drops the router's memoized collection / matcher / generator (and the per-thread compiled-routes cache) before `warmUp()`, so the rebuild is effective in the editing worker. The new `RouteTableFreshnessSubscriber` (`kernel.request`, priority 4096, main requests) calls `RouteCacheInvalidator::refreshIfStale()` so other workers rebuild their router on their next request. `DbRouteLoader` implements `ResetInterface` so its duplicate-import guard allows the reload. Correctness does **not** depend on Symfony `services_resetter` / `kernel.reset`.
- **Performance:** `FilesystemRoutePathStorage` reads take a shared lock (`LOCK_SH`) instead of an exclusive one; `CanonicalRedirectSubscriber` and `DbRouteLoader` load the storage once and resolve default-locale fallbacks from memory (`PublicPathResolver::resolveDefinition()` accepts an optional index built by `PublicPathResolver::indexDefinitions()`).
- PHPStan (0 errors at level 8) now also analyses `SeoKitBridgePass`.

### Documentation

- [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md) (scenario B: no kernel reset); [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md) / [UPGRADING.md](UPGRADING.md) / baseline FR-19.

## [1.4.4] - 2026-08-24

### Changed

- **Docs:** PHP-FIG PSR evaluation (REQ-CS-007).

### Notes

- **No API or configuration changes** for integrators unless noted above.

[1.4.4]: https://github.com/nowo-tech/RoutingKitBundle/releases/tag/v1.4.4

## [1.4.3] - 2026-08-20

### Security

- **Flex recipe `when@prod`:** disable `panel.allow_controller_override`, keep `ROLE_ADMIN`; ship `security_nowo_routing_kit.yaml` (`access_control` for `/_routing`). Prefer **`^1.4.3`**.

[1.4.3]: https://github.com/nowo-tech/RoutingKitBundle/releases/tag/v1.4.3

## [1.4.2] - 2026-08-19

### Security

- **CI:** run `composer audit --locked` after dependency install (REQ-SEC / P3).

[1.4.2]: https://github.com/nowo-tech/RoutingKitBundle/releases/tag/v1.4.2

## [1.4.1] - 2026-08-18

### Changed

- **Demos:** pin `nowo-tech/hot-reload-bundle` to `^1.4` with FrankenPHP Mercure/`hot_reload` (`dev`/`test` only).

[1.4.1]: https://github.com/nowo-tech/RoutingKitBundle/releases/tag/v1.4.1

## [1.4.0] - 2026-08-12

### Changed

- **REQ-TWIG-005:** replace the panel index raw Twig `<form>` markup (export, clear-cache, import, **delete**) with Symfony Form Types rendered through `form_*`. Import uses FormKit `routing_kit`; toolbar/delete actions share `RoutingPanelActionType` + panel CSRF token id. Host overrides should use `export_form` / `clear_cache_form` / `import_form` / `delete_forms` (no raw HTML `<form>`, no Twig `constant()` for CSRF). Index no longer passes `csrf_token`.

### Tests

- Add unit coverage for the new panel action/import form types and update controller/form-factory support so the index/actions keep working through Symfony forms.

[1.4.0]: https://github.com/nowo-tech/RoutingKitBundle/releases/tag/v1.4.0

## [1.3.1] - 2026-08-07

### Fixed

- **CI / Composer:** regenerate `composer.lock` content-hash after the v1.3.0 `composer.json` changes (`form-kit-bundle`, `symfony/form`, UiKit constraint, suggest). `composer validate --strict` on the default branch is green again (REQ-CI-003).

### Tests

- Cover `prependUiKitDefaults` (seed / host override / non-array config bags) and FormKit/UiKit skip paths; cover invalid routable/locale/controller choice filtering in `RoutePathDefinitionType` (100% line coverage).

[1.3.1]: https://github.com/nowo-tech/RoutingKitBundle/releases/tag/v1.3.1

## [1.3.0] - 2026-08-05

### Added

- **FormKitBundle:** depend on [`nowo-tech/form-kit-bundle`](https://github.com/nowo-tech/FormKitBundle) `^2.2`. Panel path row form uses `RoutePathDefinitionType` with `FormOptionsTrait` + profile `routing_kit` (`#[FormKitConfig]`). Extension prepends that profile with `auto_help` / `auto_placeholder` false (and default `css_framework: bootstrap`) when the host has not defined them.
- `NowoRoutingKitBundle::TRANSLATION_DOMAIN` constant for shared translation domain wiring.
- Unit coverage for `RoutingKitExtension::prependFormKitDefaults` (routing_kit profile seed + host override guards).

### Changed

- **Panel form:** `RoutingPanelController::form()` builds/handles a Symfony form (CSRF via form component); `panel/form.html.twig` renders `form_start` / `form_row` / `form_end` with FormKit UiKit attrs.
- **Panel index:** list pagination uses `@NowoUiKitBundle/partials/_pagination.html.twig` instead of hand-rolled Previous/Next links.

[1.3.0]: https://github.com/nowo-tech/RoutingKitBundle/releases/tag/v1.3.0

## [1.2.0] - 2026-08-04

### Added
- **REQ-TWIG-004:** require `twig/extra-bundle` + `twig/string-extra`; `make check-twig-extra` in `release-check`; demos register `TwigExtraBundle`.
- **Twig-CS-Fixer:** `vincentlanglet/twig-cs-fixer`, `.twig-cs-fixer.php`, `composer twig:lint` / `twig:fix`.

### Changed

- **REQ-UI-001-kit:** Panel `nowo-ui.css` now loads from UiKit (`asset('css/nowo-ui.css', 'nowo_ui_kit')`). Removed the forked `src/Resources/public/css/nowo-ui.css`. Requires `nowo-tech/ui-kit-bundle` `^1.4`. Extension seeds `nowo_ui_kit` defaults from `web_ui.css_framework` / `icon_set` when the host has not configured UiKit. `panel/base.html.twig` imports `@NowoUiKitBundle/macros/ui.html.twig` (panel index primary action via `ui.btn`).
- **UiKit:** Panel templates use `ui.btn` / `ui.row_actions` macros with `nowo_routing_kit_css_framework` for secondary/row/form actions instead of hand-rolled `nowo-ui-btn` classes.

[1.2.0]: https://github.com/nowo-tech/RoutingKitBundle/releases/tag/v1.2.0

## [1.1.9] - 2026-08-03

### Added

- `RoutingKitAccessCheckerInterface` with `ConfigurableRoutingKitAccessChecker` (role gate) and `AllowAllRoutingKitAccessChecker` (`security.allow_unauthenticated`) — REQ-UI-002.
- Compile-time guard: panel enabled + `allow_unauthenticated: false` requires SecurityBundle.

### Changed

- `PanelAccessGuard` delegates to the access checker and uses `security.token_storage` (wired by `PanelAccessGuardPass`) instead of injecting `AuthorizationChecker` directly.
- `security.access_checker` accepts a custom service id implementing `RoutingKitAccessCheckerInterface` (`null` = configurable by `access_roles`).
- CI: bump `actions/stale` from v10 to v11.
- Dev dependency: `nowo-tech/phpstan-frankenphp` 1.0.3.

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) / [SECURITY.md](SECURITY.md) / [UPGRADING.md](UPGRADING.md) for the checker contract.

## [1.1.8] - 2026-08-01

### Fixed

- `PanelAccessGuard`: wire `security.authorization_checker` in a compiler pass (`PanelAccessGuardPass`). Extension-time `hasDefinition`/`hasAlias` often misses the SecurityBundle service, leaving the guard with a null checker and AccessDenied on `/_routing` when `security.access_roles` is non-empty.

### Changed

- Suggest / require-dev `symfony/asset` so the `nowo_routing_kit` named package can register when FrameworkBundle is present (demo Symfony 8 requires `symfony/asset`).

### Documentation

- [UPGRADING.md](UPGRADING.md) sections **To 1.1.8** and **To 1.1.7**; SECURITY notes compile-time guard wiring.

## [1.1.7] - 2026-08-01

### Added

- `src/Resources/public/css/nowo-ui.css`: tokenized panel stylesheet with `--nowo-ui-*` CSS variables extracted from the former inline styles (buttons, table, forms, muted, errors, pagination, actions). Tokens align with DashboardMenuBundle semantics where applicable.
- Named Symfony asset package `nowo_routing_kit` (base path `/bundles/noworoutingkit`) registered via `PrependExtensionInterface` in `RoutingKitExtension`, mirroring the DashboardMenuBundle pattern. Run `assets:install` to expose the CSS to host apps.
- `src/Resources/views/panel/base.html.twig`: intermediate Twig template that extends the configured `layout_template` and injects `nowo-ui.css` via the `stylesheets` block so host shells receive the CSS through `{{ parent() }}`.

### Changed

- `panel/index.html.twig` and `panel/form.html.twig` now extend `@NowoRoutingKitBundle/panel/base.html.twig` instead of `layout|default(nowo_routing_kit_layout_template)` directly.
- `panel/layout.html.twig` (demo root shell): inline `<style>` block replaced by `<link>` to `asset('css/nowo-ui.css', 'nowo_routing_kit')`, keeping the demo self-contained without duplicating CSS.

### Documentation

- CONFIGURATION: `css_framework: custom` recommended host path; `--nowo-ui-*` token override guide; Bootstrap 5 example retained as an alternative.
- INSTALLATION: new **Assets** section (`assets:install`, `nowo_routing_kit` package, host layout integration with `css_framework: custom`).

## [1.1.6] - 2026-07-30

### Documentation

- README: section order (Requirements / Configuration / Usage / Development / Documentation) aligned with org checklist (REQ-DOCS-019).
- USAGE: panel security (`security.access_roles`), pagination, host `web_ui.layout_template` path; Twig override freeze rule + procedure (REQ-TWIG-001).
- Panel demo layout: clarify root shell has no `{{ parent() }}` (pages do not reopen asset blocks).

## [1.1.5] - 2026-07-29

### Added

- `make check-open-prs` / `demo-smoke`; `release-check` fails on unresolved GitHub PRs (REQ-REL-003 / REQ-MAKE-002).
- `SYMFONY_DEPRECATIONS_HELPER=max[direct]=0` in PHPUnit + CI (REQ-SF-005).
- Panel `#[Route]` attributes (REQ-SF-004); `web_ui.*` + `security.access_roles` (REQ-UI-001 / REQ-UI-002).
- `RoutingKitTwigExtension` globals for panel layout / CSS / icon set.
- Panel index pagination via `panel.list_page_size` (REQ-PERF-001).

### Changed

- `panel.role` is a BC alias for `security.access_roles` (documented in UPGRADING / CONFIGURATION / SECURITY).
- Panel templates use `nowo-ui-*` markup hooks for host theming.

### Documentation

- [UPGRADING.md](UPGRADING.md) section **To 1.1.5**; CONFIGURATION / SECURITY updated for the UI/security contract.

## [1.1.4] - 2026-07-26

### Security

- `SafePublicPath` rejects additional control characters, encoded bypasses (`%5c`, `%00`, `%0d`/`%0a`, `%252f%252f`), and `..` segments.
- `redirects.root_home_path` is validated at config compile time with `SafePublicPath`.
- `DbRouteLoader` skips unsafe stored paths and ignores non-allowlisted controller overrides even when `allow_controller_override` is true.
- Panel `{id}` requirements and manager saves accept only `[A-Za-z0-9_.-]+`.
- Import rejects payloads larger than 1 MiB (HTTP 413); unexpected import errors no longer echo internal exception messages.

### Docs

- Expanded [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md) (dev/prod, `FRANKENPHP_MODE`, troubleshooting).
- Added table of contents to USAGE, UPGRADING, CHANGELOG, and GITHUB_CI (REQ-DOCS-005).
- Demo Symfony 8: `config/packages/dev/twig.yaml` (`cache: false`); longer local `APP_SECRET` in `.env.example` / Compose fallback.

### Changed

- Dev dependency: pin `phpstan/phpstan` to `^2.0 <2.2.6` (Rector incompatibility with PHPStan 2.2.6).

### Compatibility

- PHP `>=8.2`, `<8.6`; Symfony `^7.4 || ^8.0` (CI minors **7.4**, **8.0**, **8.1**).
- **BC notes (fail-closed):** previously accepted unsafe public paths / `root_home_path` values, non-allowlisted stored controllers (with override on), and non `[A-Za-z0-9_.-]+` definition ids are now rejected or ignored.

## [1.1.3] - 2026-07-26

### Docs

- Aligned SECURITY / UPGRADING / USAGE with **1.1.2** behaviour (controls header `1.1.2+`, migration table, signing-key checklist, `replaceAll` note, config comment).

## [1.1.2] - 2026-07-26

### Security

- Filesystem storage fails closed on corrupt JSON (no silent wipe on next save).
- Exclusive `flock` on `{paths}.lock` for read-modify-write mutations.
- Export/import signing key must be ≥32 characters (`panel.export_signing_key` validated; runtime check also covers `kernel.secret`).
- Panel shows an **UNSAFE** banner when `panel.role` is null; demo home page warns similarly.

### Compatibility

- PHP `>=8.2`, `<8.6`; Symfony `^7.4 || ^8.0` (CI minors **7.4**, **8.0**, **8.1**).
- **BC notes:** weak signing keys (<32 chars) now fail export/import; corrupt `paths.json` throws instead of loading as empty.

## [1.1.1] - 2026-07-26

### Security

- Import goes through `RoutePathManager` (validator, allowlists, conflicts, `max_definitions`); stored `_controller` is stripped when `allow_controller_override` is false.
- `DbRouteLoader` ignores stored controller overrides unless `allow_controller_override` is true (defense in depth).
- `replace_all` import uses atomic `RoutePathStorageInterface::replaceAll()`.
- Export is **POST + CSRF** only (no GET download link).
- `panel.path_prefix` must match `^/[A-Za-z0-9/_-]+$` (blocks `javascript:` / `//`).
- Conflict detector includes default-locale fallback paths, trailing-slash variants, and disabled rows.
- `SafePublicPath` also rejects tab and `%2f%2f`.

### Changed

- Custom `RoutePathStorageInterface` implementations must add `replaceAll(array $definitions): array`.

### Compatibility

- PHP `>=8.2`, `<8.6`; Symfony `^7.4 || ^8.0` (CI minors **7.4**, **8.0**, **8.1**).
- **BC notes:** export is POST-only; custom storages need `replaceAll()`; invalid `path_prefix` values are rejected at compile time.

## [1.1.0] - 2026-07-26

### Security

- CSRF is **fail-closed** and `symfony/security-csrf` is a hard requirement.
- `panel.role` (default `ROLE_ADMIN`) gates the panel via `AuthorizationCheckerInterface`.
- `route_name` must be `#[Routable]`; `locale` must be configured; free-form `_controller` overrides are disabled by default (discovery allowlist only).
- Path safety rejects `//…` / schemes / control characters; redirect subscribers only emit safe `Location` targets.
- Trailing-slash redirects apply only to paths managed by that definition (fixes global redirect bug).
- `enabled: false` unregisters panel, DB loader, and redirect subscribers.

### Added

- Conflict detection / rejection (`panel.reject_conflicts`), `panel.max_definitions`.
- Signed export/import (HMAC) and conflict preview endpoint.
- `RoutePathAuditSubscriber` (logs user when a security token is present).
- Recipe `access_control` example and panel security defaults.

### Compatibility

- PHP `>=8.2`, `<8.6`; Symfony `^7.4 || ^8.0` (CI minors **7.4**, **8.0**, **8.1**).
- **BC notes:** CSRF manager required; `panel.role` defaults to `ROLE_ADMIN` (set `null` to restore pre-1.1 behaviour without in-bundle gate); controller override field removed unless `allow_controller_override: true`.

## [1.0.3] - 2026-07-26

### Fixed

- Restored Symfony **8.x** constraints (`|| ^8.0`) again after a post-`v1.0.2` CI code-style job had narrowed several `require` entries to `^7.4` only (that job now restores `composer.json` / `composer.lock` before committing).

### Changed

- Documented real panel security posture (firewall required, CSRF when available, controller-override privilege).
- Clarified that the DB loader registers `{name}.{locale}` with `_canonical_route` (import last so locale paths compete correctly with app routes).
- Fixed Keep a Changelog compare links for **1.0.2** / **Unreleased**.

### Compatibility

- PHP `>=8.2`, `<8.6`; Symfony `^7.4 || ^8.0` (CI minors **7.4**, **8.0**, **8.1**).

## [1.0.2] - 2026-07-26

### Fixed

- Restored Symfony **8.x** constraints in `composer.json` (`|| ^8.0`) that were dropped in 1.0.1, so the package installs on Symfony 8.0 / 8.1 again.
- `DbRouteLoader` now registers `{name}.{locale}` routes with `_canonical_route`, so `generate('route', ['_locale' => 'en'])` and Twig `path()` resolve correctly.

### Changed

- Default-locale paths are unprefixed when `register_unprefixed_default` is true; other locales use `/{locale}{path}` (no bare `{name}` duplicate without canonical metadata).

### Compatibility

- PHP `>=8.2`, `<8.6`; Symfony `^7.4 || ^8.0` (CI minors **7.4**, **8.0**, **8.1**).

## [1.0.1] - 2026-07-26

### Removed

- Optional `label` argument on `#[Routable]`. The CRUD route selector shows the Symfony route **`name`** only.

### Changed

- Panel create/edit `<select>` options use `route_name` as the visible label (no separate display name).
- Docs and demo controllers no longer pass `label:` to `#[Routable]`.
- Minor Rector / CS cleanups so `make release-check` (including `rector-dry`) stays green.

### Compatibility

- Unchanged: PHP `>=8.2`, `<8.6`; Symfony `^7.4 || ^8.0` (CI minors **7.4**, **8.0**, **8.1**).
- **BC note:** remove any `label:` named arguments from `#[Routable(...)]` (see [UPGRADING.md](UPGRADING.md)).

## [1.0.0] - 2026-07-26

First stable release of **Routing Kit Bundle**.

### Added

#### Locale routing

- Pluggable path storage (`RoutePathStorageInterface`); default filesystem JSON under `var/routing_kit/paths.json`.
- One path row per `(routeName, locale)` with fallback to the **default locale** path when a locale row is missing.
- Paths stored **without** `{_locale}`; the loader always builds `/{_locale}/…`.
- Public styles: `canonical_style` (`without_prefix` / `with_prefix`), `alias_mode` (`redirect` / `alias`), trailing-slash variants.
- Dual access: `/foo` and `/{locale}/foo` with admin-defined canonical URL.
- Optional root `/` redirect to the default-locale home.
- Symfony route loader `type: nowo_routing_kit` (import **after** app routes so DB/locale paths take effect).

#### Controllers & validation

- PHP attributes `#[Routable]` and `RouteParam` (required/optional, requirement regex, enum, type).
- Discovery of offerable controllers for the CRUD panel.
- Path placeholder validation against declared params on save.

#### Admin panel

- Twig CRUD under configurable prefix (default `/_routing`): list / create / edit / delete.
- CSRF protection when `CsrfTokenManagerInterface` is available.
- Automatic router-cache invalidation after save/delete, plus manual **Clear routing cache**.

#### Locales & SeoKit

- Locales from YAML (`default_locale` + `locales`) or a custom `LocaleProviderInterface`.
- Optional SeoKit bridge (`seo_kit_bridge`): decorates `SeoPathBuilderInterface::pagePath` when SeoKit is installed.
- `RoutingKitSeoPathProvider` for apps/SeoKit integrations.

#### Packaging & quality

- Flex recipe under `.symfony/recipe/nowo-tech/routing-kit-bundle`.
- Panel translations for **en / es / it / fr / pt / de / nl** (`NowoRoutingKitBundle`).
- FrankenPHP demo (`demo/symfony8`, port **8058**, `FRANKENPHP_MODE` classic/worker).
- Spec Kit baseline (`specs/001-baseline/`), Engram/Cursor rules, GitHub workflows.
- PHPUnit **100%** line coverage of included `src/` PHP.

### Compatibility

- **PHP** `>=8.2` and `<8.6` (Symfony 8.x requires PHP 8.4+).
- **Symfony** `^7.4 || ^8.0` (CI minors: **7.4**, **8.0**, **8.1**).
- Twig for the CRUD panel templates.

[Unreleased]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.9.3...HEAD
[1.9.3]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.9.2...v1.9.3
[1.9.2]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.9.1...v1.9.2
[1.9.1]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.9.0...v1.9.1
[1.9.0]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.8.1...v1.9.0
[1.8.1]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.8.0...v1.8.1
[1.8.0]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.7.2...v1.8.0
[1.7.2]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.7.1...v1.7.2
[1.7.1]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.7.0...v1.7.1
[1.7.0]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.6.0...v1.7.0
[1.6.0]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.4.5...v1.6.0
[1.4.5]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.4.4...v1.4.5
[1.4.4]: https://github.com/nowo-tech/RoutingKitBundle/releases/tag/v1.4.4
[1.4.3]: https://github.com/nowo-tech/RoutingKitBundle/releases/tag/v1.4.3
[1.4.2]: https://github.com/nowo-tech/RoutingKitBundle/releases/tag/v1.4.2
[1.4.1]: https://github.com/nowo-tech/RoutingKitBundle/releases/tag/v1.4.1
[1.4.0]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.3.1...v1.4.0
[1.3.1]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.3.0...v1.3.1
[1.3.0]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.1.9...v1.2.0
[1.1.9]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.1.8...v1.1.9
[1.1.8]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.1.7...v1.1.8
[1.1.7]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.1.6...v1.1.7
[1.1.6]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.1.5...v1.1.6
[1.1.5]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.1.4...v1.1.5
[1.1.4]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.1.3...v1.1.4
[1.1.3]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.1.2...v1.1.3
[1.1.2]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.0.3...v1.1.0
[1.0.3]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/nowo-tech/RoutingKitBundle/releases/tag/v1.0.0
