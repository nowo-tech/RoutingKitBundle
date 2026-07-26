# Routing Kit Bundle — Baseline product specification

**Package**: `nowo-tech/routing-kit-bundle`  
**Last audited**: 2026-07-26  
**Inventory**: [`code-inventory.md`](code-inventory.md)

## Overview

Routing Kit Bundle provides **DB-driven (pluggable) locale paths** for Symfony: dual `/foo` and `/{locale}/foo` access, admin-defined **canonical** URLs, a Twig CRUD panel, route-cache invalidation, and optional **SeoKit** path bridging.

## Functional requirements

| ID | Requirement |
| --- | --- |
| FR-01 | Path definitions are stored as one row per `(routeName, locale)`. |
| FR-02 | Stored paths never include `{_locale}`; the loader always applies `/{_locale}/…`. |
| FR-03 | Missing locale rows fall back to the **default locale** path for that route name. |
| FR-04 | `#[Routable]` marks offerable controller actions with name, params, and constraints. |
| FR-05 | CRUD validates path placeholders against `#[Routable]` declarations. |
| FR-06 | Default **filesystem JSON** storage; replaceable via `RoutePathStorageInterface`. |
| FR-07 | Locales come from YAML or a custom `LocaleProviderInterface`. |
| FR-08 | Twig **panel** lists/creates/edits/deletes paths and can clear the router cache. |
| FR-09 | Save/delete can auto-invalidate the router cache; panel also exposes a manual button. |
| FR-10 | `type: nowo_routing_kit` loader registers/overwrites routes when imported last. |
| FR-11 | Canonical style (`without_prefix` / `with_prefix`) and alias mode (`redirect` / `alias`). |
| FR-12 | Optional root `/` redirect to the default-locale home. |
| FR-13 | Trailing-slash styles including redirect-to-omit / redirect-to-keep. |
| FR-14 | Bundle Twig namespace `NowoRoutingKitBundle`; app overrides win (REQ-TWIG-001). |
| FR-15 | Path mutations dispatch `RoutePathsChangedEvent`. |
| FR-16 | Optional SeoKit bridge decorates `SeoPathBuilderInterface::pagePath`. |
| FR-17 | Panel POST actions validate CSRF when `CsrfTokenManagerInterface` is available. |
| FR-18 | Seven minimum translation locales for the panel domain `NowoRoutingKitBundle`. |

## User scenarios

### US-01 — Operator creates a path

**Given** a controller marked `#[Routable(name: 'app_about')]`  
**When** the operator saves locale `es` with path `/sobre-nosotros` and `with_prefix`  
**Then** `/es/sobre-nosotros` is registered and canonical for that locale.

### US-02 — Default locale without prefix

**Given** locale `en` is default and canonical style is `without_prefix`  
**When** path `/about` is saved  
**Then** both `/about` and `/en/about` are reachable; canonical is `/about` when alias mode is `redirect`.

### US-03 — Locale fallback

**Given** only the default-locale row exists for `app_about`  
**When** a visitor requests the Spanish twin  
**Then** the default path segment is reused under `/es/…`.

### US-04 — Cache clear

**Given** paths were changed in the panel  
**When** auto-invalidate is enabled (or the operator clicks clear cache)  
**Then** the Symfony router cache is cleared/warmed.

### US-05 — SeoKit bridge

**Given** SeoKitBundle is installed and `seo_kit_bridge: true`  
**When** SeoKit resolves `pagePath(route, locale)`  
**Then** RoutingKit canonical paths take precedence when present.

## Out of scope

- Multisite / host-based routing.
- Shipping FrankenPHP as a runtime dependency.
- Mandatory Doctrine ORM (storage is pluggable).

## Success criteria

| ID | Criterion |
| --- | --- |
| SC-01 | PHPUnit covers production PHP under `src/` (optional SeoKit bridge excluded when not installed). |
| SC-02 | PHPStan level 8 passes with `nowo-tech/phpstan-frankenphp` rulesets. |
| SC-03 | Demo FrankenPHP app boots; panel and sample `#[Routable]` pages smoke-test. |
| SC-04 | `code-inventory.md` maps 100% of production files under `src/` to one or more `FR-*` IDs. |

## Validation

```bash
make qa
make phpstan
make test-coverage
```

## Traceability

- FR-01 … FR-03, FR-11 … FR-13: `tests/Unit/Routing/*`
- FR-04 … FR-05: `tests/Unit/Validation/*`, `tests/Unit/Discovery/*`
- FR-06: `tests/Unit/Storage/*`
- FR-07: `tests/Unit/Locale/*`
- FR-08 … FR-09, FR-17: `tests/Unit/Controller/*`, `tests/Unit/Service/*`
- FR-10: `tests/Unit/Routing/DbRouteLoaderTest.php`
- FR-14: `tests/Unit/DependencyInjection/Compiler/TwigPathsPassTest.php`
- FR-15: `tests/Unit/Service/RoutePathManagerTest.php`
- FR-16: `tests/Unit/Seo/*` (when SeoKit present) / coverage exclude otherwise
