<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\EventSubscriber;

use Nowo\RoutingKitBundle\EventSubscriber\CanonicalRedirectSubscriber;
use Nowo\RoutingKitBundle\Locale\ConfigurableLocaleProvider;
use Nowo\RoutingKitBundle\Model\AliasMode;
use Nowo\RoutingKitBundle\Model\CanonicalStyle;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Model\TrailingSlashStyle;
use Nowo\RoutingKitBundle\Routing\PublicPathResolver;
use Nowo\RoutingKitBundle\Storage\FilesystemRoutePathStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

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
            'kernel.request' => ['onKernelRequest', 33],
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

    private function createEvent(string $path, int $requestType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new RequestEvent($kernel, Request::create($path), $requestType);
    }
}
