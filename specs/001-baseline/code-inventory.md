# Code inventory — Routing Kit Bundle (`src/`)

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/routing-kit-bundle`  
**Last audited**: 2026-10-28  
**Coverage summary**: **80/80** files under `src/` mapped (see [Coverage summary](#coverage-summary)). PHPUnit line coverage target 100% of included `src/` PHP (optional SeoKit bridge files may be excluded from the coverage denominator when SeoKit is not installed).

Every production file under `src/` maps to one or more baseline `FR-*` requirements.

## Bundle and dependency injection

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `NowoRoutingKitBundle.php` | Registers extension and compiler passes. | FR-14, FR-16 |
| `DependencyInjection/Configuration.php` | Defines the `nowo_routing_kit` configuration tree. | FR-07, FR-08, FR-09, FR-12, FR-16 |
| `DependencyInjection/RoutingKitExtension.php` | Wires storage, locales, loader, panel, subscribers. | FR-06–FR-10, FR-12, FR-16, FR-19 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Registers Twig namespace; app overrides win. | FR-14 |
| `DependencyInjection/Compiler/SeoKitBridgePass.php` | Decorates SeoKit path builder when enabled. | FR-16 |
| `DependencyInjection/Compiler/PanelAccessGuardPass.php` | Wires panel access / token storage at compile time. | FR-08 |
| `DependencyInjection/Compiler/CsrfProtectionPass.php` | Fails the container build when the panel is enabled without form CSRF protection. | FR-17, FR-21 |

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
| `Locale/ConfiguredLocales.php` | Normalizes `locales` (list, container parameter or env var). | FR-07 |

## Model / enums

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Model/RoutePathDefinition.php` | Path row value object + array serialization. | FR-01, FR-02 |
| `Model/CanonicalStyle.php` | `without_prefix` / `with_prefix`. | FR-11 |
| `Model/AliasMode.php` | `redirect` / `alias`. | FR-11 |
| `Model/TrailingSlashStyle.php` | Trailing slash behaviours. | FR-13 |
| `Model/UrlRedirect.php` | URL redirect row (source, target, status, hits, note). | 002 REQ-REDIR-001 |

## Storage

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Storage/RoutePathStorageInterface.php` | Persistence contract. | FR-06 |
| `Storage/FilesystemRoutePathStorage.php` | JSON filesystem storage (`LOCK_SH` reads / `LOCK_EX` writes). | FR-01, FR-06, FR-19 |
| `Storage/UrlRedirectStorageInterface.php` | URL redirect persistence contract. | 002 REQ-REDIR-001 |
| `Storage/FilesystemUrlRedirectStorage.php` | JSON redirect storage with `flock`, atomic rename, fail-closed parsing. | 002 REQ-REDIR-001 |

## Routing

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Routing/PublicPathResolver.php` | Prefixed/unprefixed/canonical/alias paths + fallback (+ optional in-memory index). | FR-02, FR-03, FR-11, FR-13 |
| `Routing/SafePublicPath.php` | Rejects unsafe public path strings. | FR-05 |
| `Routing/DbRouteLoader.php` | `nowo_routing_kit` route loader (`ResetInterface`). | FR-02, FR-10, FR-19 |
| `Routing/RouteCacheInvalidator.php` | Clears/warms router cache; route-table version + in-process router reset. | FR-09, FR-19 |
| `Routing/DefaultLocaleRouter.php` | Decorates `router.default`: the default locale never appears in generated URLs. | FR-20 |

## Events and subscribers

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Event/RoutePathsChangedEvent.php` | Dispatched on save/delete. | FR-15 |
| `EventSubscriber/CanonicalRedirectSubscriber.php` | Redirects non-canonical twins / slash variants. | FR-11, FR-13 |
| `EventSubscriber/RootRedirectSubscriber.php` | Optional `/` redirect. | FR-12 |
| `EventSubscriber/RoutePathAuditSubscriber.php` | Logs path mutations when a security token exists. | FR-08 |
| `EventSubscriber/RouteTableFreshnessSubscriber.php` | Rebuilds this worker's router when the route-table version changes. | FR-09, FR-19 |
| `EventSubscriber/DefaultLocalePrefixRedirectSubscriber.php` | `GET /{default}/…` → 301 to the bare URL of the same page. | FR-20 |
| `EventSubscriber/UrlRedirectSubscriber.php` | `kernel.request` URL redirect listener (priority 33). | 002 REQ-REDIR-005 |

## Security

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Security/PanelAccessGuard.php` | Panel gate. | FR-08 |
| `Security/RoutingKitAccessCheckerInterface.php` | Access-checker contract. | FR-08 |
| `Security/ConfigurableRoutingKitAccessChecker.php` | Role-based checker. | FR-08 |
| `Security/AllowAllRoutingKitAccessChecker.php` | Unauthenticated allow. | FR-08 |

## Form

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Form/RoutePathDefinitionType.php` | Create/edit path form. | FR-08, FR-17 |
| `Form/RoutingPanelActionType.php` | Export / clear-cache / delete CSRF forms. | FR-08, FR-17 |
| `Form/RoutingPanelImportType.php` | Import form. | FR-08, FR-17 |
| `Form/UrlRedirectType.php` | Create/edit URL redirect form (CSRF). | 002 REQ-REDIR-006, 002 REQ-REDIR-007 |

## Service and controller

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Service/RoutePathManager.php` | Validates, persists, invalidates cache, dispatches events. | FR-05, FR-08, FR-09, FR-15 |
| `Service/RoutePathConflictDetector.php` | Detects colliding public paths. | FR-05 |
| `Service/RoutePathImportExport.php` | Signed export/import. | FR-08 |
| `Controller/RoutingPanelController.php` | Twig CRUD + CSRF + clear cache. | FR-08, FR-09, FR-17 |
| `Twig/RoutingKitTwigExtension.php` | Panel Twig globals (1.x; deprecated since 1.9). | FR-08, FR-14 |
| `Panel/PanelView.php` | Renders panel pages on the UiKit page contract (template per page, layout, nav, role-gate flag) — spec 004. | FR-08, FR-14 |
| `Panel/PanelChrome.php` | Deprecated 1.x class / partial maps and their bridge into `nowo_ui_kit.theme.bundles` — spec 004. | FR-14 |
| `Controller/UrlRedirectPanelController.php` | URL redirects panel (list, new, edit, delete with confirmation). | 002 REQ-REDIR-007 |

## Seo

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Seo/RoutingKitSeoPathProvider.php` | Canonical page paths for SeoKit/apps. | FR-16 |
| `Seo/RoutingKitSeoPathBuilderDecorator.php` | Decorates SeoKit `SeoPathBuilderInterface`. | FR-16 |

## YAML configuration

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Service definitions. | FR-06–FR-10, FR-19 |
| `Resources/config/routes.yaml` | Panel routes. | FR-08 |
| `Resources/config/packages/nowo_routing_kit.yaml` | Default config reference. | FR-07, FR-12, FR-16 |

## Twig views

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Resources/views/panel/*` | Panel pages extending the UiKit scaffolds (`page/list`, `page/form`, `page/confirm`) + nav / notices / import partials ([docs/TEMPLATES.md](../../docs/TEMPLATES.md)). | FR-08, FR-09, FR-14, FR-17 |

## URL redirects (`src/Redirect/`, since 1.6.0)

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Redirect/ProtectedPaths.php` | Refuses `/`, protected prefixes / starts (also behind a locale segment). | 002 REQ-REDIR-002 |
| `Redirect/LivePathCheckerInterface.php` | Tagged checkers answering whether a matched route is a live page. | 002 REQ-REDIR-003 |
| `Redirect/LivePublicPaths.php` | Router match + checkers: is the source a live page. | 002 REQ-REDIR-003 |
| `Redirect/UrlRedirectMatcher.php` | Cached enabled map, per-request memo, invalidation, hit recording. | 002 REQ-REDIR-004, 002 REQ-REDIR-008 |
| `Redirect/UrlRedirectValidator.php` | Source / target / status validation, protected / live / loop / chain / duplicate checks. | 002 REQ-REDIR-006 |
| `Redirect/Message/RecordRedirectHit.php` | Messenger message for asynchronous hit counting. | 002 REQ-REDIR-008 |
| `Redirect/Message/RecordRedirectHitHandler.php` | Handler writing the hit through the storage. | 002 REQ-REDIR-008 |

## Translations

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Resources/translations/NowoRoutingKitBundle.en.yaml` | Panel catalogue (English). | FR-18 |
| `Resources/translations/NowoRoutingKitBundle.es.yaml` | Panel catalogue (Spanish). | FR-18 |
| `Resources/translations/NowoRoutingKitBundle.it.yaml` | Panel catalogue (Italian). | FR-18 |
| `Resources/translations/NowoRoutingKitBundle.fr.yaml` | Panel catalogue (French). | FR-18 |
| `Resources/translations/NowoRoutingKitBundle.pt.yaml` | Panel catalogue (Portuguese). | FR-18 |
| `Resources/translations/NowoRoutingKitBundle.de.yaml` | Panel catalogue (German). | FR-18 |
| `Resources/translations/NowoRoutingKitBundle.nl.yaml` | Panel catalogue (Dutch). | FR-18 |

## Coverage summary

Audit command: `git ls-files src | wc -l` (2026-10-28).

| Category | Files | Mapped |
| --- | ---: | ---: |
| Bundle and dependency injection | 7 | 7 |
| Attribute, discovery, validation | 4 | 4 |
| Locale | 3 | 3 |
| Model / enums | 5 | 5 |
| Storage | 4 | 4 |
| Routing | 5 | 5 |
| Events and subscribers | 7 | 7 |
| Security | 4 | 4 |
| Form | 4 | 4 |
| Service and controller | 8 | 8 |
| Seo | 2 | 2 |
| YAML configuration | 3 | 3 |
| Twig views | 10 | 10 |
| URL redirects (`src/Redirect/`, since 1.6.0) | 7 | 7 |
| Translations | 7 | 7 |
| **Total production files (`git ls-files src`)** | **80** | **80** |
