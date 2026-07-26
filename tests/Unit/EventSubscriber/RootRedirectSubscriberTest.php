<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\EventSubscriber;

use Nowo\RoutingKitBundle\EventSubscriber\RootRedirectSubscriber;
use Nowo\RoutingKitBundle\Locale\LocaleProviderInterface;
use Nowo\RoutingKitBundle\Model\CanonicalStyle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class RootRedirectSubscriberTest extends TestCase
{
    public function testSubscribedEventsConfiguration(): void
    {
        self::assertSame([
            'kernel.request' => ['onKernelRequest', 34],
        ], RootRedirectSubscriber::getSubscribedEvents());
    }

    public function testDoesNothingWhenDisabled(): void
    {
        $subscriber = new RootRedirectSubscriber(
            $this->createLocales(),
            enabled: false,
            homeCanonicalStyle: CanonicalStyle::WithPrefix,
        );

        $event = $this->createEvent('/');
        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testDoesNothingForSubRequests(): void
    {
        $subscriber = new RootRedirectSubscriber(
            $this->createLocales(),
            enabled: true,
            homeCanonicalStyle: CanonicalStyle::WithPrefix,
        );

        $event = $this->createEvent('/', HttpKernelInterface::SUB_REQUEST);
        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testDoesNothingOutsideRootPath(): void
    {
        $subscriber = new RootRedirectSubscriber(
            $this->createLocales(),
            enabled: true,
            homeCanonicalStyle: CanonicalStyle::WithPrefix,
        );

        $event = $this->createEvent('/about');
        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testDoesNothingWhenDefaultHomeAlreadyLivesAtRoot(): void
    {
        $subscriber = new RootRedirectSubscriber(
            $this->createLocales(),
            enabled: true,
            homeCanonicalStyle: CanonicalStyle::WithoutPrefix,
            homePath: '/',
        );

        $event = $this->createEvent('/');
        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testRedirectsRootToPrefixedHome(): void
    {
        $subscriber = new RootRedirectSubscriber(
            $this->createLocales(),
            enabled: true,
            homeCanonicalStyle: CanonicalStyle::WithPrefix,
            homePath: '/home',
            redirectStatus: 307,
        );

        $event = $this->createEvent('/');
        $subscriber->onKernelRequest($event);

        self::assertNotNull($event->getResponse());
        self::assertSame('/en/home', $event->getResponse()->headers->get('Location'));
        self::assertSame(307, $event->getResponse()->getStatusCode());
    }

    public function testRedirectsRootToUnprefixedCustomHome(): void
    {
        $subscriber = new RootRedirectSubscriber(
            $this->createLocales(),
            enabled: true,
            homeCanonicalStyle: CanonicalStyle::WithoutPrefix,
            homePath: '/landing',
        );

        $event = $this->createEvent('/');
        $subscriber->onKernelRequest($event);

        self::assertSame('/landing', $event->getResponse()?->headers->get('Location'));
    }

    public function testSkipsUnsafeHomePath(): void
    {
        $subscriber = new RootRedirectSubscriber(
            $this->createLocales(),
            enabled: true,
            homeCanonicalStyle: CanonicalStyle::WithoutPrefix,
            homePath: '//evil.example',
        );
        $event = $this->createEvent('/');
        $subscriber->onKernelRequest($event);
        self::assertNull($event->getResponse());
    }

    public function testSkipsWhenPrefixedTargetIsUnsafe(): void
    {
        $locales = $this->createMock(LocaleProviderInterface::class);
        $locales->method('getDefaultLocale')->willReturn('http:');

        $subscriber = new RootRedirectSubscriber(
            $locales,
            enabled: true,
            homeCanonicalStyle: CanonicalStyle::WithPrefix,
            homePath: '/home',
        );
        $event = $this->createEvent('/');
        $subscriber->onKernelRequest($event);
        self::assertNull($event->getResponse());
    }

    private function createEvent(string $path, int $requestType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new RequestEvent($kernel, Request::create($path), $requestType);
    }

    private function createLocales(): LocaleProviderInterface
    {
        $locales = $this->createMock(LocaleProviderInterface::class);
        $locales->method('getDefaultLocale')->willReturn('en');

        return $locales;
    }
}
