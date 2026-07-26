# Code inventory — Routing Kit Bundle (`src/`)

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/routing-kit-bundle`  
**Last audited**: 2026-07-26  
**Coverage summary**: PHPUnit line coverage target 100% of included `src/` PHP (optional SeoKit bridge files may be excluded from the coverage denominator when SeoKit is not installed).

This is a 100% inventory of production files under `src/`: PHP, YAML configuration, Twig views, and translations. Every file maps to one or more baseline `FR-*` requirements.

## Summary

| Category | Files |
| --- | ---: |
| Bundle and dependency injection | 5 |
| Attribute, discovery, validation | 4 |
| Locale | 2 |
| Model / enums | 4 |
| Storage | 2 |
| Routing | 3 |
| Events and subscribers | 3 |
| Service | 1 |
| Controller | 1 |
| Seo | 2 |
| YAML configuration | 3 |
| Twig views | 3 |
| Translations | 7 |
| **Total production files under `src/`** | **40/40** |

## Bundle and dependency injection

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `NowoRoutingKitBundle.php` | Registers extension and compiler passes. | FR-14, FR-16 |
| `DependencyInjection/Configuration.php` | Defines the `nowo_routing_kit` configuration tree. | FR-07, FR-08, FR-09, FR-12, FR-16 |
| `DependencyInjection/RoutingKitExtension.php` | Wires storage, locales, loader, panel, subscribers. | FR-06–FR-10, FR-12, FR-16 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Registers Twig namespace; app overrides win. | FR-14 |
| `DependencyInjection/Compiler/SeoKitBridgePass.php` | Decorates SeoKit path builder when enabled. | FR-16 |

## Attribute, discovery, validation

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Attribute/Routable.php` | Marks offerable controller actions. | FR-04 |
| `Attribute/RouteParam.php` | Declares path param constraints. | FR-04, FR-05 |
| `Discovery/RoutableControllerDiscovery.php` | Scans controllers for `#[Routable]`. | FR-04 |
| `Validation/RoutePathValidator.php` | Validates stored paths and param values. | FR-05 |

## Locale

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Locale/LocaleProviderInterface.php` | Locale list + default contract. | FR-07 |
| `Locale/ConfigurableLocaleProvider.php` | YAML-backed locale provider. | FR-07 |

## Model / enums

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Model/RoutePathDefinition.php` | Path row value object + array serialization. | FR-01, FR-02 |
| `Model/CanonicalStyle.php` | `without_prefix` / `with_prefix`. | FR-11 |
| `Model/AliasMode.php` | `redirect` / `alias`. | FR-11 |
| `Model/TrailingSlashStyle.php` | Trailing slash behaviours. | FR-13 |

## Storage

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Storage/RoutePathStorageInterface.php` | Persistence contract. | FR-06 |
| `Storage/FilesystemRoutePathStorage.php` | JSON filesystem storage. | FR-01, FR-06 |

## Routing

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Routing/PublicPathResolver.php` | Prefixed/unprefixed/canonical/alias paths + fallback. | FR-02, FR-03, FR-11, FR-13 |
| `Routing/DbRouteLoader.php` | `nowo_routing_kit` route loader. | FR-02, FR-10 |
| `Routing/RouteCacheInvalidator.php` | Clears/warms router cache. | FR-09 |

## Events and subscribers

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Event/RoutePathsChangedEvent.php` | Dispatched on save/delete. | FR-15 |
| `EventSubscriber/CanonicalRedirectSubscriber.php` | Redirects non-canonical twins / slash variants. | FR-11, FR-13 |
| `EventSubscriber/RootRedirectSubscriber.php` | Optional `/` redirect. | FR-12 |

## Service and controller

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Service/RoutePathManager.php` | Validates, persists, invalidates cache, dispatches events. | FR-05, FR-08, FR-09, FR-15 |
| `Controller/RoutingPanelController.php` | Twig CRUD + CSRF + clear cache. | FR-08, FR-09, FR-17 |

## Seo

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Seo/RoutingKitSeoPathProvider.php` | Canonical page paths for SeoKit/apps. | FR-16 |
| `Seo/RoutingKitSeoPathBuilderDecorator.php` | Decorates SeoKit `SeoPathBuilderInterface`. | FR-16 |

## YAML configuration

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Service definitions. | FR-06–FR-10 |
| `Resources/config/routes.yaml` | Panel routes. | FR-08 |
| `Resources/config/packages/nowo_routing_kit.yaml` | Default config reference. | FR-07, FR-12, FR-16 |

## Twig views

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Resources/views/panel/layout.html.twig` | Panel chrome. | FR-08, FR-14 |
| `Resources/views/panel/index.html.twig` | Path list + clear cache. | FR-08, FR-09, FR-17 |
| `Resources/views/panel/form.html.twig` | Create/edit form. | FR-08, FR-17 |

## Translations

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Resources/translations/NowoRoutingKitBundle.{en,es,it,fr,pt,de,nl}.yaml` | Panel catalogue (7 locales). | FR-18 |
