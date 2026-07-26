# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.0.3...HEAD
[1.0.3]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/nowo-tech/RoutingKitBundle/releases/tag/v1.0.0
