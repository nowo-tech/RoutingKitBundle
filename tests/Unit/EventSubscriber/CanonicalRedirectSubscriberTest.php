<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\EventSubscriber;

use Nowo\RoutingKitBundle\Event\RoutePathsChangedEvent;
use Nowo\RoutingKitBundle\EventSubscriber\CanonicalRedirectSubscriber;
use Nowo\RoutingKitBundle\Locale\ConfigurableLocaleProvider;
use Nowo\RoutingKitBundle\Model\AliasMode;
use Nowo\RoutingKitBundle\Model\CanonicalStyle;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Model\TrailingSlashStyle;
use Nowo\RoutingKitBundle\Routing\PublicPathResolver;
use Nowo\RoutingKitBundle\Routing\RouteCacheInvalidator;
use Nowo\RoutingKitBundle\Storage\FilesystemRoutePathStorage;
use Nowo\RoutingKitBundle\Storage\RoutePathStorageInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Cache\CacheInterface;

final class CanonicalRedirectSubscriberTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/rk_canonical_' . uniqid('', true) . '.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testSubscribedEventsConfiguration(): void
    {
        self::assertSame([
            'kernel.request'              => ['onKernelRequest', 33],
            RoutePathsChangedEvent::class => 'onRoutePathsChanged',
        ], CanonicalRedirectSubscriber::getSubscribedEvents());
    }

    public function testDoesNothingWhenDisabled(): void
    {
        $subscriber = $this->createSubscriber(
            [new RoutePathDefinition('app_about', 'en', '/about')],
            enabled: false,
        );

        $event = $this->createEvent('/en/about');
        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testDoesNothingForSubRequests(): void
    {
        $subscriber = $this->createSubscriber(
            [new RoutePathDefinition('app_about', 'en', '/about')],
        );

        $event = $this->createEvent('/en/about', HttpKernelInterface::SUB_REQUEST);
        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testRedirectsAliasPathToCanonicalPath(): void
    {
        $subscriber = $this->createSubscriber([
            new RoutePathDefinition(
                routeName: 'app_about',
                locale: 'en',
                path: '/about',
                canonicalStyle: CanonicalStyle::WithoutPrefix,
                aliasMode: AliasMode::Redirect,
            ),
        ], redirectStatus: 308);

        $event = $this->createEvent('/en/about');
        $subscriber->onKernelRequest($event);

        self::assertNotNull($event->getResponse());
        self::assertSame('/about', $event->getResponse()->headers->get('Location'));
        self::assertSame(308, $event->getResponse()->getStatusCode());
    }

    public function testRedirectsTrailingSlashVariant(): void
    {
        $subscriber = $this->createSubscriber([
            new RoutePathDefinition(
                routeName: 'app_about',
                locale: 'en',
                path: '/about',
                canonicalStyle: CanonicalStyle::WithoutPrefix,
                trailingSlash: TrailingSlashStyle::RedirectToKeep,
                aliasMode: AliasMode::Alias,
            ),
        ]);

        $event = $this->createEvent('/about');
        $subscriber->onKernelRequest($event);

        self::assertNotNull($event->getResponse());
        self::assertSame('/about/', $event->getResponse()->headers->get('Location'));
        self::assertSame(301, $event->getResponse()->getStatusCode());
    }

    public function testSkipsDisabledDefinitionsAndMissingResolvedLocales(): void
    {
        $subscriber = $this->createSubscriber([
            new RoutePathDefinition(
                routeName: 'app_disabled',
                locale: 'en',
                path: '/disabled',
                enabled: false,
            ),
            new RoutePathDefinition(
                routeName: 'app_spanish_only',
                locale: 'es',
                path: '/solo-es',
            ),
        ]);

        $event = $this->createEvent('/solo-es');
        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testDoesNotRedirectRootPathForTrailingSlashRules(): void
    {
        $subscriber = $this->createSubscriber([
            new RoutePathDefinition(
                routeName: 'app_home',
                locale: 'en',
                path: '/',
                trailingSlash: TrailingSlashStyle::RedirectToKeep,
                aliasMode: AliasMode::Alias,
            ),
        ]);

        $event = $this->createEvent('/');
        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testDoesNotRedirectUnrelatedPathsForTrailingSlashRules(): void
    {
        $subscriber = $this->createSubscriber([
            new RoutePathDefinition(
                routeName: 'app_about',
                locale: 'en',
                path: '/about',
                canonicalStyle: CanonicalStyle::WithoutPrefix,
                trailingSlash: TrailingSlashStyle::RedirectToKeep,
                aliasMode: AliasMode::Alias,
            ),
        ]);

        $event = $this->createEvent('/totally-unrelated');
        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testStripTrailingSlashOnRootViaManagedPathCheck(): void
    {
        $subscriber = $this->createSubscriber([
            new RoutePathDefinition(
                routeName: 'app_about',
                locale: 'en',
                path: '/about',
                trailingSlash: TrailingSlashStyle::RedirectToKeep,
                aliasMode: AliasMode::Alias,
            ),
        ]);
        $event = $this->createEvent('/');
        $subscriber->onKernelRequest($event);
        self::assertNull($event->getResponse());
    }

    public function testAliasRedirectKeepsQueryString(): void
    {
        $subscriber = $this->createSubscriber([$this->redirectingAbout()]);

        $event = $this->createEvent('/en/about?utm_source=news&page=2');
        $subscriber->onKernelRequest($event);

        self::assertSame('/about?page=2&utm_source=news', $event->getResponse()?->headers->get('Location'));
    }

    public function testTrailingSlashRedirectKeepsQueryString(): void
    {
        $subscriber = $this->createSubscriber([
            new RoutePathDefinition(
                routeName: 'app_about',
                locale: 'en',
                path: '/about',
                canonicalStyle: CanonicalStyle::WithoutPrefix,
                trailingSlash: TrailingSlashStyle::RedirectToOmit,
                aliasMode: AliasMode::Alias,
            ),
        ]);

        $event = $this->createEvent('/about/?page=3');
        $subscriber->onKernelRequest($event);

        self::assertSame('/about?page=3', $event->getResponse()?->headers->get('Location'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeMethodProvider(): iterable
    {
        yield 'POST' => ['POST'];
        yield 'PUT' => ['PUT'];
        yield 'PATCH' => ['PATCH'];
        yield 'DELETE' => ['DELETE'];
    }

    #[DataProvider('unsafeMethodProvider')]
    public function testDoesNotRedirectUnsafeMethods(string $method): void
    {
        $storage    = $this->countingStorage([$this->redirectingAbout()]);
        $subscriber = $this->subscriberFor($storage);

        $event = $this->createEvent('/en/about', method: $method);
        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
        self::assertSame(0, $storage->reads, 'Unsafe methods must not touch the storage.');
    }

    public function testRedirectsHeadRequests(): void
    {
        $subscriber = $this->createSubscriber([$this->redirectingAbout()]);

        $event = $this->createEvent('/en/about', method: 'HEAD');
        $subscriber->onKernelRequest($event);

        self::assertSame('/about', $event->getResponse()?->headers->get('Location'));
    }

    public function testTableIsMemoizedUntilReset(): void
    {
        $storage    = $this->countingStorage([$this->redirectingAbout()]);
        $subscriber = $this->subscriberFor($storage);

        $subscriber->onKernelRequest($this->createEvent('/en/about'));
        $subscriber->onKernelRequest($this->createEvent('/other'));
        self::assertSame(1, $storage->reads);

        $subscriber->reset();
        $subscriber->onKernelRequest($this->createEvent('/other'));
        self::assertSame(2, $storage->reads);
    }

    public function testCachePoolAvoidsStorageReadsAcrossInstances(): void
    {
        $storage = $this->countingStorage([$this->redirectingAbout()]);
        $cache   = new ArrayAdapter();

        $first = $this->subscriberFor($storage, $cache);
        $first->onKernelRequest($this->createEvent('/other'));

        $second = $this->subscriberFor($storage, $cache);
        $event  = $this->createEvent('/en/about');
        $second->onKernelRequest($event);

        self::assertSame(1, $storage->reads);
        self::assertSame('/about', $event->getResponse()?->headers->get('Location'));
    }

    public function testRoutePathsChangedDropsMemoAndCachedTable(): void
    {
        $storage    = $this->countingStorage([$this->redirectingAbout()]);
        $cache      = new ArrayAdapter();
        $subscriber = $this->subscriberFor($storage, $cache);

        $subscriber->onKernelRequest($this->createEvent('/other'));
        $storage->inner->save(new RoutePathDefinition(
            routeName: 'app_contact',
            locale: 'en',
            path: '/contact',
            canonicalStyle: CanonicalStyle::WithoutPrefix,
            aliasMode: AliasMode::Redirect,
        ));
        $subscriber->onRoutePathsChanged();

        $other = $this->subscriberFor($storage, $cache);
        $event = $this->createEvent('/en/contact');
        $other->onKernelRequest($event);

        self::assertSame(2, $storage->reads);
        self::assertSame('/contact', $event->getResponse()?->headers->get('Location'));
    }

    public function testCacheKeyFollowsRouteTableVersion(): void
    {
        $cacheDir = sys_get_temp_dir() . '/rk_canonical_cache_' . uniqid('', true);
        mkdir($cacheDir);
        try {
            $routeTable = new RouteCacheInvalidator($this->createMock(RouterInterface::class), $cacheDir);
            $storage    = $this->countingStorage([$this->redirectingAbout()]);
            $cache      = new ArrayAdapter();

            $this->subscriberFor($storage, $cache, $routeTable)->onKernelRequest($this->createEvent('/other'));
            $this->subscriberFor($storage, $cache, $routeTable)->onKernelRequest($this->createEvent('/other'));
            self::assertSame(1, $storage->reads);

            file_put_contents($routeTable->getVersionFile(), "abc123\n");
            self::assertSame('abc123', $routeTable->currentVersion());

            $this->subscriberFor($storage, $cache, $routeTable)->onKernelRequest($this->createEvent('/other'));
            self::assertSame(2, $storage->reads);
        } finally {
            array_map('unlink', glob($cacheDir . '/*') ?: []);
            rmdir($cacheDir);
        }
    }

    public function testStorageFailureDegradesToNoRedirectAndLogs(): void
    {
        $storage = $this->createMock(RoutePathStorageInterface::class);
        $storage->method('all')->willThrowException(new RuntimeException('disk /secret/path unreadable'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with('Canonical redirects unavailable: {message}');

        $locales    = new ConfigurableLocaleProvider('en', ['en', 'es']);
        $subscriber = new CanonicalRedirectSubscriber($storage, new PublicPathResolver($storage, $locales), $locales, logger: $logger);

        $event = $this->createEvent('/en/about');
        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testRoutePathsChangedWithoutCacheAndWithFailingCache(): void
    {
        $storage = $this->countingStorage([$this->redirectingAbout()]);
        $this->subscriberFor($storage)->onRoutePathsChanged();

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('delete')->willThrowException(new RuntimeException('pool down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with('Canonical redirect cache not cleared: {message}');

        $locales    = new ConfigurableLocaleProvider('en', ['en', 'es']);
        $subscriber = new CanonicalRedirectSubscriber($storage, new PublicPathResolver($storage, $locales), $locales, cache: $cache, logger: $logger);
        $subscriber->onRoutePathsChanged();
    }

    private function redirectingAbout(): RoutePathDefinition
    {
        return new RoutePathDefinition(
            routeName: 'app_about',
            locale: 'en',
            path: '/about',
            canonicalStyle: CanonicalStyle::WithoutPrefix,
            aliasMode: AliasMode::Redirect,
        );
    }

    /**
     * @param list<RoutePathDefinition> $definitions
     */
    private function countingStorage(array $definitions): CountingRoutePathStorage
    {
        $storage = new FilesystemRoutePathStorage($this->file);
        foreach ($definitions as $definition) {
            $storage->save($definition);
        }

        return new CountingRoutePathStorage($storage);
    }

    private function subscriberFor(RoutePathStorageInterface $storage, ?CacheInterface $cache = null, ?RouteCacheInvalidator $routeTable = null): CanonicalRedirectSubscriber
    {
        $locales = new ConfigurableLocaleProvider('en', ['en', 'es']);

        return new CanonicalRedirectSubscriber(
            $storage,
            new PublicPathResolver($storage, $locales),
            $locales,
            cache: $cache,
            routeTable: $routeTable,
        );
    }

    /**
     * @param list<RoutePathDefinition> $definitions
     */
    private function createSubscriber(array $definitions, bool $enabled = true, int $redirectStatus = 301): CanonicalRedirectSubscriber
    {
        $storage = new FilesystemRoutePathStorage($this->file);
        foreach ($definitions as $definition) {
            $storage->save($definition);
        }

        $locales = new ConfigurableLocaleProvider('en', ['en', 'es']);

        return new CanonicalRedirectSubscriber(
            $storage,
            new PublicPathResolver($storage, $locales),
            $locales,
            enabled: $enabled,
            redirectStatus: $redirectStatus,
        );
    }

    private function createEvent(string $path, int $requestType = HttpKernelInterface::MAIN_REQUEST, string $method = 'GET'): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new RequestEvent($kernel, Request::create($path, $method), $requestType);
    }
}

/**
 * Delegating storage that counts full reads.
 */
final class CountingRoutePathStorage implements RoutePathStorageInterface
{
    public int $reads = 0;

    public function __construct(public readonly FilesystemRoutePathStorage $inner)
    {
    }

    public function all(): array
    {
        ++$this->reads;

        return $this->inner->all();
    }

    public function find(string $routeName, string $locale): ?RoutePathDefinition
    {
        return $this->inner->find($routeName, $locale);
    }

    public function findById(string $id): ?RoutePathDefinition
    {
        return $this->inner->findById($id);
    }

    public function findByRouteName(string $routeName): array
    {
        return $this->inner->findByRouteName($routeName);
    }

    public function save(RoutePathDefinition $definition): RoutePathDefinition
    {
        return $this->inner->save($definition);
    }

    public function delete(string $id): void
    {
        $this->inner->delete($id);
    }

    public function replaceAll(array $definitions): array
    {
        return $this->inner->replaceAll($definitions);
    }
}
