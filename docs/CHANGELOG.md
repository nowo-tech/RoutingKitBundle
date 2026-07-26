# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
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

[Unreleased]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.1.3...HEAD
[1.1.3]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.1.2...v1.1.3
[1.1.2]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.0.3...v1.1.0
[1.0.3]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/nowo-tech/RoutingKitBundle/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/nowo-tech/RoutingKitBundle/releases/tag/v1.0.0
