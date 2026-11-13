# Architecture — RoutingKitBundle

RoutingKit is an **infrastructure + presentation** bundle (REQ-ARCH-001): it owns URL data — route path rows and
operator URL redirects — and an admin panel for them. It never references your entities, needs no Doctrine mapping
and forces no base class. This page lists the public surface, the extension points and how a standard Symfony host
and a DDD / hexagonal host wire them.

## Table of contents

- [Public surface](#public-surface)
- [Ports and extension points (SPI)](#ports-and-extension-points-spi)
- [Application services](#application-services)
- [Events](#events)
- [Standard Symfony and DDD hosts](#standard-symfony-and-ddd-hosts)
- [Worker mode](#worker-mode)

## Public surface

| Kind | Types |
| --- | --- |
| Application services (interfaces) | `Service\RoutePathManagerInterface`, `Redirect\UrlRedirectManagerInterface` (since 1.16.0) |
| Query services (interfaces) | `Routing\PublicPathResolverInterface`, `Redirect\UrlRedirectMatcherInterface` (since 1.16.0) |
| Persistence ports | `Storage\RoutePathStorageInterface`, `Storage\UrlRedirectStorageInterface` |
| SPI (you implement it) | `Locale\LocaleProviderInterface`, `Redirect\LivePathCheckerInterface`, `Security\RoutingKitAccessCheckerInterface` |
| Value objects / models | `Model\RoutePathDefinition` (immutable), `Model\UrlRedirect`, `Redirect\UrlRedirectData` (since 1.16.0), enums `Model\CanonicalStyle`, `Model\TrailingSlashStyle`, `Model\AliasMode` |
| Events | `Event\RoutePathDefinitionChangedEvent` (since 1.16.0), `Event\UrlRedirectChangedEvent`; deprecated `Event\RoutePathsChangedEvent` |
| Exceptions | `Redirect\InvalidUrlRedirectException` (since 1.16.0) |

The class ids of the defaults (`RoutePathManager`, `PublicPathResolver`, `UrlRedirectMatcher`, `UrlRedirectManager`)
keep working; type your code against the interfaces.

## Ports and extension points (SPI)

| Extension point | Default | How to replace it |
| --- | --- | --- |
| `Storage\RoutePathStorageInterface` | `FilesystemRoutePathStorage` (JSON file) | `nowo_routing_kit.storage.path_storage: <service id>` |
| `Storage\UrlRedirectStorageInterface` | `FilesystemUrlRedirectStorage` (JSON file) | `nowo_routing_kit.url_redirects.storage: <service id>`; `save()` / `delete()` must commit before they return |
| `Locale\LocaleProviderInterface` | `ConfigurableLocaleProvider` | `nowo_routing_kit.locale_provider: <service id>` |
| `Redirect\LivePathCheckerInterface` | none | implement it (autoconfigured, tag `nowo_routing_kit.live_path_checker`); see [REDIRECTS.md](REDIRECTS.md) |
| `Security\RoutingKitAccessCheckerInterface` | role gate | `nowo_routing_kit.security.access_checker: <service id>` |
| `Service\RoutePathManagerInterface`, `Redirect\UrlRedirectManagerInterface`, `Routing\PublicPathResolverInterface`, `Redirect\UrlRedirectMatcherInterface` | the bundle classes | alias the interface to your service (decorate the default by injecting its class id) |
| Panel templates | UiKit pages | Twig theming and the UiKit chrome; see [USAGE.md](USAGE.md#customising-the-admin-chrome) |

None of these interfaces, the value objects or the events uses a Doctrine, HttpFoundation, Form or Security type.

## Application services

Your own code (a console command, a migration, a message handler, an import) writes through the same application
services as the panel, so it gets the same validation, limits and events:

- **Route paths** — `RoutePathManagerInterface::save()`, `import()`, `delete()`: validation, the
  `max_definitions` limit, conflict detection, route cache invalidation and the events below.
- **URL redirects** (since 1.16.0, with `url_redirects.enabled: true`) — `UrlRedirectManagerInterface`:

```php
use Nowo\RoutingKitBundle\Redirect\InvalidUrlRedirectException;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectData;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectManagerInterface;

public function __construct(private UrlRedirectManagerInterface $redirects) {}

$errors = $this->redirects->validate(new UrlRedirectData('/old-page', '/new-page'));   // field => translation key
try {
    $redirect = $this->redirects->save(new UrlRedirectData('/old-page', '/new-page', 301), actorId: 'importer');
} catch (InvalidUrlRedirectException $e) {
    $e->getErrors();   // nothing was written
}
$this->redirects->save(new UrlRedirectData('/old-page', '/other', 302), (string) $redirect->getId(), 'editor');
$this->redirects->delete((string) $redirect->getId(), 'editor');
```

`save()` validates (protected paths, live pages, loops, chains, duplicates), writes through the storage port,
invalidates the matcher cache and dispatches `UrlRedirectChangedEvent`. `$actorId` is the user identifier you want
recorded (`createdBy` / `updatedBy`) and sent in the event; the panel passes the logged-in user's identifier.

## Events

| Event | When | Payload |
| --- | --- | --- |
| `RoutePathDefinitionChangedEvent` (since 1.16.0) | every row saved, imported or deleted through `RoutePathManagerInterface`, after the storage write | `id`, `routeName`, `locale`, `path`, `enabled`, `deleted`, and the immutable `definition` |
| `UrlRedirectChangedEvent` | every redirect created, updated or deleted through `UrlRedirectManagerInterface` (panel or your code), after the storage write | `redirectId`, `sourcePath`, `action`, `actorId`, `occurredAt` |
| `RoutePathsChangedEvent` (deprecated, removed in 2.0) | right before each `RoutePathDefinitionChangedEvent`, same rows | `definition`, `deleted`; extends `Symfony\Contracts\EventDispatcher\Event` |

Both current events are plain PHP (no framework base class) and carry ids and scalars. A replace-all import sends the
deletions first, then the saved rows; key projections by `id` / `redirectId`. A listener that throws on a redirect event
is logged and the write stands.

## Standard Symfony and DDD hosts

**Standard Symfony host.** Enable the bundle, import its routes and open `/_routing`. The JSON file storages, the
configured locales and the role gate work with no code. Listen to the events with `#[AsEventListener]` when you need
an audit.

**DDD / hexagonal host.**

- Implement the storage ports in your infrastructure layer (for example over a DBAL table or your own repository)
  and set `storage.path_storage` / `url_redirects.storage`. The ports exchange the bundle's own models; map them to
  your rows in the adapter. Who changed a row is a user identifier string, never a user entity.
- Implement `LivePathCheckerInterface` over your domain port (for example "is this product published?") and
  `LocaleProviderInterface` / `RoutingKitAccessCheckerInterface` when your locales or access rules live elsewhere.
- Call `RoutePathManagerInterface` / `UrlRedirectManagerInterface` from your application layer (a command handler
  or an anti-corruption adapter), never the storages directly, so validation and events are not skipped.
- Translate `RoutePathDefinitionChangedEvent` / `UrlRedirectChangedEvent` into your own events in an infrastructure
  listener; do not pass them into the domain.

```yaml
# config/packages/nowo_routing_kit.yaml
nowo_routing_kit:
  storage:
    path_storage: App\Infrastructure\Routing\DbalRoutePathStorage
  url_redirects:
    enabled: true
    storage: App\Infrastructure\Routing\DbalUrlRedirectStorage

# config/services.yaml — decorate an application service
services:
  App\Infrastructure\Routing\AuditedRoutePathManager:
    arguments: { $inner: '@Nowo\RoutingKitBundle\Service\RoutePathManager' }
  Nowo\RoutingKitBundle\Service\RoutePathManagerInterface: '@App\Infrastructure\Routing\AuditedRoutePathManager'
```

## Worker mode

The services keep no per-request state: the redirect matcher drops its memoised map at the request boundary and on
`kernel.reset`, and writes its hit buffer on `kernel.terminate`. The application services are stateless. Safe in a FrankenPHP worker with the kernel not reset; see
[FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).
