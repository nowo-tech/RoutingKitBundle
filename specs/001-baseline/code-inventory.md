# Code inventory — Routing Kit Bundle (`src/`)

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/routing-kit-bundle`  
**Last audited**: 2026-11-25 (1.20.1)  
**Coverage summary**: **94/94** files under `src/` mapped (see [Coverage summary](#coverage-summary)). PHPUnit line coverage target 100% of included `src/` PHP (optional SeoKit bridge files may be excluded from the coverage denominator when SeoKit is not installed).

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
| `Storage/FileLock.php` | Bounded `flock()` (non-blocking retries until `storage.lock_timeout`). | FR-24 |
| `Storage/StorageLockTimeoutException.php` | Lock wait ran out (`TransientStorageFailure`). | FR-24 |
| `Concurrency/StorageWriteLedger.php` | Monotonic count of durable store writes (CONFLICT_002 detection). | FR-24 |

## Routing

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Routing/PublicPathResolver.php` | Prefixed/unprefixed/canonical/alias paths + fallback (+ optional in-memory index). | FR-02, FR-03, FR-11, FR-13 |
| `Routing/SafePublicPath.php` | Rejects unsafe public path strings. | FR-05 |
| `Routing/DbRouteLoader.php` | `nowo_routing_kit` route loader (`ResetInterface`). | FR-02, FR-10, FR-19 |
| `Routing/RouteCacheInvalidator.php` | Clears/warms router cache; route-table version + in-process router reset. | FR-09, FR-19 |
| `Routing/PublicPathResolverInterface.php` | Query service interface of the resolver (1.17.0); the bundle's services type against it. | FR-02, FR-03, FR-23 |
| `Routing/DefaultLocaleRouter.php` | Decorates `router.default`: the default locale never appears in generated URLs. | FR-20 |

## Events and subscribers

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Event/RoutePathsChangedEvent.php` | Dispatched on save/delete (deprecated since 1.17.0, removed in 2.0). | FR-15 |
| `Event/RoutePathDefinitionChangedEvent.php` | Plain-PHP twin dispatched for every saved / imported / deleted row (ids and scalars, 1.17.0). | FR-15, FR-23 |
| `Event/UrlRedirectChangedEvent.php` | Dispatched after every redirect create / update / delete (1.15.0). | 002 REQ-REDIR-009 |
| `EventSubscriber/CanonicalRedirectSubscriber.php` | Redirects non-canonical twins / slash variants. | FR-11, FR-13 |
| `EventSubscriber/RootRedirectSubscriber.php` | Optional `/` redirect. | FR-12 |
| `EventSubscriber/RoutePathAuditSubscriber.php` | Logs path mutations when a security token exists. | FR-08 |
| `EventSubscriber/RouteTableFreshnessSubscriber.php` | Rebuilds this worker's router when the route-table version changes. | FR-09, FR-19 |
| `EventSubscriber/DefaultLocalePrefixRedirectSubscriber.php` | `GET /{default}/…` → 301 to the bare URL of the same page. | FR-20 |
| `EventSubscriber/UrlRedirectSubscriber.php` | `kernel.request` URL redirect listener (priority 33). | 002 REQ-REDIR-005 |
| `EventSubscriber/ConcurrencyConflictSubscriber.php` | Admin-route lock timeouts → 409 + `X-Conflict-Code` (CONFLICT_001 / CONFLICT_002). | FR-24 |

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
| `Service/RoutePathManagerInterface.php` | Application service interface for route paths (1.17.0). | FR-15, FR-23 |
| `Service/RoutePathConflictDetector.php` | Detects colliding public paths. | FR-05 |
| `Service/RoutePathImportExport.php` | Signed export/import. | FR-08 |
| `Controller/RoutingPanelController.php` | Twig CRUD + CSRF + clear cache. | FR-08, FR-09, FR-17 |
| `Twig/RoutingKitTwigExtension.php` | Panel Twig globals (1.x; deprecated since 1.9). | FR-08, FR-14 |
| `Panel/PanelView.php` | Renders panel pages on the UiKit page contract (template per page, layout, nav, role-gate flag) — spec 004. | FR-08, FR-14 |
| `Panel/PanelChrome.php` | Deprecated 1.x class / partial maps and their bridge into `nowo_ui_kit.theme.bundles` — spec 004. | FR-14 |
| `Panel/HeaderActionsPlacement.php` | `web_ui.header_actions` enum (`buttons` default, `page_actions`): header actions as buttons or as items of UiKit's page-actions menu (UiKit 2.21+); stateless (1.19.0). | FR-25 |
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
| `Resources/views/panel/index.html.twig` | Route paths list on the UiKit `page/list` scaffold: row cells, inline import card, import dialog block, panel notices ([docs/TEMPLATES.md](../../docs/TEMPLATES.md)). | FR-08, FR-09, FR-14, FR-25, 004 FR-001 |
| `Resources/views/panel/form.html.twig` | Route path create / edit form on `page/form`: form errors and the conflict preview before the child loop. | FR-08, FR-14, 004 FR-001 |
| `Resources/views/panel/delete.html.twig` | Route path delete: full-page confirmation on `page/confirm`, the no-JS fallback of the list / edit confirm dialog (POST deletes, CSRF-protected). | FR-08, FR-17, 004 FR-001 |
| `Resources/views/panel/_notices.html.twig` | Panel notices after the host flashes: role-gate warning when `security.allow_unauthenticated` is true, redirect status message through the UiKit `flashes` partial. | FR-08, FR-17, 004 FR-003 |
| `Resources/views/panel/_import_form.html.twig` | Signed JSON import form: body in the UiKit `dialog` partial (Cancel first, then Import via `form=`) or inline in a card; child-loop rendering. | FR-08, FR-17, 004 FR-003 |
| `Resources/views/panel/_form_theme.html.twig` | Bundle form theme (`PanelView::FORM_THEME`): checkbox rows as UiKit check labels; the one REQ-TWIG-005 raw-`<input>` exception. | FR-14, 004 FR-007 |
| `Resources/views/panel/_legacy_layout.html.twig` | Deprecated BC bridge (1.9, removed in 2.0): wraps a 1.x `web_ui.layout_template` that renders `body` instead of the UiKit canonical blocks; selected by `PanelView` when the layout has no `nowo_ui_content` block. | FR-14, 004 FR-005 |
| `Resources/views/panel/redirects/index.html.twig` | URL redirects list on `page/list` (source, target, status, hits cells; delete through the UiKit confirm dialog; `?status=` notice). | FR-22, 002 REQ-REDIR-007, 004 FR-001 |
| `Resources/views/panel/redirects/form.html.twig` | URL redirect create / edit form on `page/form` (form-level errors before the child loop). | FR-22, 002 REQ-REDIR-007, 004 FR-001 |
| `Resources/views/panel/redirects/delete.html.twig` | URL redirect delete: full-page confirmation on `page/confirm`, no-JS fallback of the list dialog. | FR-22, 002 REQ-REDIR-007, 004 FR-001 |

## URL redirects (`src/Redirect/`, since 1.6.0)

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Redirect/ProtectedPaths.php` | Refuses `/`, protected prefixes / starts (also behind a locale segment). | 002 REQ-REDIR-002 |
| `Redirect/LivePathCheckerInterface.php` | Tagged checkers answering whether a matched route is a live page. | 002 REQ-REDIR-003 |
| `Redirect/LivePublicPaths.php` | Router match + checkers: is the source a live page. | 002 REQ-REDIR-003 |
| `Redirect/UrlRedirectMatcher.php` | Cached enabled map, per-request memo, invalidation, hit recording. | 002 REQ-REDIR-004, 002 REQ-REDIR-008 |
| `Redirect/UrlRedirectValidator.php` | Source / target / status validation, protected / live / loop / chain / duplicate checks. | 002 REQ-REDIR-006 |
| `Redirect/UrlRedirectMatcherInterface.php` | Query service interface of the matcher (1.17.0). | 002 REQ-REDIR-004, FR-23 |
| `Redirect/UrlRedirectManagerInterface.php` | Application service interface for redirects: `validate()`, `save()`, `delete()` (1.17.0). | 002 REQ-REDIR-009 |
| `Redirect/UrlRedirectManager.php` | Default application service: validates, writes through the storage, invalidates the matcher, dispatches `UrlRedirectChangedEvent`. | 002 REQ-REDIR-009 |
| `Redirect/UrlRedirectData.php` | Input DTO of the application service (source, target, status, enabled, note). | 002 REQ-REDIR-009 |
| `Redirect/InvalidUrlRedirectException.php` | Refused write with per-field translation keys; nothing written. | 002 REQ-REDIR-009 |
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

Audit command: `git ls-files src | wc -l` (2026-11-25).

| Category | Files | Mapped |
| --- | ---: | ---: |
| Bundle and dependency injection | 7 | 7 |
| Attribute, discovery, validation | 4 | 4 |
| Locale | 3 | 3 |
| Model / enums | 5 | 5 |
| Storage (with `Concurrency/`) | 7 | 7 |
| Routing | 6 | 6 |
| Events and subscribers | 10 | 10 |
| Security | 4 | 4 |
| Form | 4 | 4 |
| Service and controller | 10 | 10 |
| Seo | 2 | 2 |
| YAML configuration | 3 | 3 |
| Twig views | 10 | 10 |
| URL redirects (`src/Redirect/`, since 1.6.0) | 12 | 12 |
| Translations | 7 | 7 |
| **Total production files (`git ls-files src`)** | **94** | **94** |
