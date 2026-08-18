# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
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

[Unreleased]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.4.1...HEAD
[1.4.0]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.3.1...v1.4.0
[1.3.1]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.3.0...v1.3.1
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
