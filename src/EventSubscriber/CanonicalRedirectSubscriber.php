<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\EventSubscriber;

use Nowo\RoutingKitBundle\Event\RoutePathsChangedEvent;
use Nowo\RoutingKitBundle\Locale\LocaleProviderInterface;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Model\TrailingSlashStyle;
use Nowo\RoutingKitBundle\Routing\PublicPathResolver;
use Nowo\RoutingKitBundle\Routing\RouteCacheInvalidator;
use Nowo\RoutingKitBundle\Routing\SafePublicPath;
use Nowo\RoutingKitBundle\Storage\RoutePathStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

use function rtrim;

/**
 * Redirects non-canonical locale/path twins and trailing-slash variants when configured.
 *
 * Only safe methods (GET/HEAD) are redirected, and the query string is carried over to the target.
 * The resolved path table is memoized for the current request and, when a cache pool is configured,
 * cached under the current route-table version ({@see RouteCacheInvalidator}) so the JSON storage is
 * not read (and locked) on every request. Writes through the panel / RoutePathManager drop the entry.
 */
final class CanonicalRedirectSubscriber implements EventSubscriberInterface, ResetInterface
{
    public const CACHE_KEY = 'nowo_routing_kit.canonical_table.v1';

    public const DEFAULT_CACHE_TTL = 3600;

    /** @var list<array{canonical: string, alias: string, redirectAlias: bool, slash: string}>|null */
    private ?array $table = null; // @igor-ignore - Per-request memo cleared by reset() (kernel.reset) and on RoutePathsChangedEvent.

    public function __construct(
        private readonly RoutePathStorageInterface $storage,
        private readonly PublicPathResolver $paths,
        private readonly LocaleProviderInterface $locales,
        private readonly bool $enabled = true,
        private readonly int $redirectStatus = 301,
        private readonly ?CacheInterface $cache = null,
        private readonly ?RouteCacheInvalidator $routeTable = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly int $cacheTtl = self::DEFAULT_CACHE_TTL,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST         => ['onKernelRequest', 33],
            RoutePathsChangedEvent::class => 'onRoutePathsChanged',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$this->enabled || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        // A 301/308 on POST/PUT/DELETE loses or replays the body: only redirect safe methods.
        if (!$request->isMethodCacheable()) {
            return;
        }

        $pathInfo = $request->getPathInfo();

        foreach ($this->table() as $entry) {
            $canonical = $entry['canonical'];
            $alias     = $entry['alias'];

            if ($entry['redirectAlias']
                && $this->pathEquals($pathInfo, $alias)
                && !$this->pathEquals($pathInfo, $canonical)
                && SafePublicPath::isSafeRedirectTarget($canonical)
            ) {
                $event->setResponse(new RedirectResponse($this->withQuery($canonical, $request), $this->redirectStatus));

                return;
            }

            if (!$this->isManagedPath($pathInfo, $canonical, $alias)) {
                continue;
            }

            $slashTarget = $this->trailingSlashTarget($pathInfo, TrailingSlashStyle::from($entry['slash']));
            if ($slashTarget !== null
                && $slashTarget !== $pathInfo
                && SafePublicPath::isSafeRedirectTarget($slashTarget)
            ) {
                $event->setResponse(new RedirectResponse($this->withQuery($slashTarget, $request), $this->redirectStatus));

                return;
            }
        }
    }

    public function onRoutePathsChanged(): void
    {
        $this->table = null;
        if (!$this->cache instanceof CacheInterface) {
            return;
        }

        try {
            $this->cache->delete($this->cacheKey());
        } catch (Throwable $exception) {
            $this->logger?->warning('Canonical redirect cache not cleared: {message}', ['message' => $exception->getMessage()]);
        }
    }

    public function reset(): void
    {
        $this->table = null;
    }

    /**
     * @return list<array{canonical: string, alias: string, redirectAlias: bool, slash: string}>
     */
    private function table(): array
    {
        if ($this->table !== null) {
            return $this->table;
        }

        try {
            if ($this->cache instanceof CacheInterface) {
                /** @var list<array{canonical: string, alias: string, redirectAlias: bool, slash: string}> $table */
                $table = $this->cache->get($this->cacheKey(), function (ItemInterface $item): array {
                    $item->expiresAfter($this->cacheTtl);

                    return $this->buildTable();
                });
            } else {
                $table = $this->buildTable();
            }
        } catch (Throwable $exception) {
            $this->logger?->warning('Canonical redirects unavailable: {message}', ['message' => $exception->getMessage()]);
            $table = [];
        }

        return $this->table = $table;
    }

    /**
     * @return list<array{canonical: string, alias: string, redirectAlias: bool, slash: string}>
     */
    private function buildTable(): array
    {
        $all   = $this->storage->all();
        $index = PublicPathResolver::indexDefinitions($all);
        $table = [];

        foreach ($all as $stored) {
            if (!$stored->enabled) {
                continue;
            }

            foreach ($this->locales->getLocales() as $locale) {
                $definition = $this->paths->resolveDefinition($stored->routeName, $locale, $index);
                if (!$definition instanceof RoutePathDefinition) {
                    continue;
                }

                $table[] = [
                    'canonical'     => $this->paths->canonicalPath($definition),
                    'alias'         => $this->paths->aliasPath($definition),
                    'redirectAlias' => $this->paths->shouldRedirectAlias($definition),
                    'slash'         => $definition->trailingSlash->value,
                ];
            }
        }

        return $table;
    }

    private function cacheKey(): string
    {
        return self::CACHE_KEY . '.' . ($this->routeTable?->currentVersion() ?? '0');
    }

    private function withQuery(string $target, Request $request): string
    {
        $query = $request->getQueryString();

        return $query !== null && $query !== '' ? $target . '?' . $query : $target;
    }

    private function isManagedPath(string $pathInfo, string $canonical, string $alias): bool
    {
        return $this->pathEquals($pathInfo, $canonical)
            || $this->pathEquals($pathInfo, $alias)
            || $this->pathEquals($this->stripTrailingSlash($pathInfo), $this->stripTrailingSlash($canonical))
            || $this->pathEquals($this->stripTrailingSlash($pathInfo), $this->stripTrailingSlash($alias));
    }

    private function pathEquals(string $a, string $b): bool
    {
        return rtrim($a, '/') === rtrim($b, '/') || $a === $b;
    }

    private function stripTrailingSlash(string $path): string
    {
        if ($path === '/') {
            return '/';
        }

        return rtrim($path, '/') ?: '/';
    }

    private function trailingSlashTarget(string $pathInfo, TrailingSlashStyle $style): ?string
    {
        if ($pathInfo === '/') {
            return null;
        }

        return match ($style) {
            TrailingSlashStyle::RedirectToOmit => str_ends_with($pathInfo, '/') ? rtrim($pathInfo, '/') : null,
            TrailingSlashStyle::RedirectToKeep => str_ends_with($pathInfo, '/') ? null : $pathInfo . '/',
            default                            => null,
        };
    }
}
