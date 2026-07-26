# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
- Symfony route loader `type: nowo_routing_kit` (import **after** app routes so DB paths overwrite).

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

[Unreleased]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/nowo-tech/RoutingKitBundle/releases/tag/v1.0.0
