<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\EventSubscriber;

use Nowo\RoutingKitBundle\EventSubscriber\UrlRedirectSubscriber;
use Nowo\RoutingKitBundle\Locale\ConfigurableLocaleProvider;
use Nowo\RoutingKitBundle\Model\UrlRedirect;
use Nowo\RoutingKitBundle\Redirect\LivePublicPaths;
use Nowo\RoutingKitBundle\Redirect\ProtectedPaths;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectMatcher;
use Nowo\RoutingKitBundle\Storage\FilesystemUrlRedirectStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class UrlRedirectSubscriberTest extends TestCase
{
    private string $file;

    private FilesystemUrlRedirectStorage $storage;

    private UrlRedirectSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->file    = sys_get_temp_dir() . '/rk_redirect_sub_' . uniqid('', true) . '.json';
        $this->storage = new FilesystemUrlRedirectStorage($this->file);
        foreach ([
            ['/tratamientos/Unas', '/contact', 301],
            ['/podología', '/contact', 308],
            ['/with-query', '/target?keep=1', 301],
            ['/external', 'https://example.com/x', 302],
            ['/contacto', '/blog', 301],
            ['/unsafe', '/\\evil.example', 301],
            ['/js', 'javascript:alert(1)', 301],
            ['/admin/legacy', '/x', 301],
        ] as [$source, $target, $status]) {
            $this->storage->save((new UrlRedirect())->setSourcePath($source)->setTargetUrl($target)->setStatusCode($status));
        }

        $routes = new RouteCollection();
        $routes->add('contacto', new Route('/contacto'));

        $this->subscriber = new UrlRedirectSubscriber(
            new UrlRedirectMatcher($this->storage),
            new ProtectedPaths(new ConfigurableLocaleProvider('es', ['es', 'en'])),
            new LivePublicPaths(new UrlMatcher($routes, new RequestContext())),
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        @unlink($this->file . '.lock');
    }

    public function testSubscribesBeforeRouter(): void
    {
        self::assertSame([KernelEvents::REQUEST => ['onKernelRequest', 33]], UrlRedirectSubscriber::getSubscribedEvents());
    }

    public function testRedirectsInternalTargetKeepingQueryAndCountsHit(): void
    {
        $response = $this->dispatch(Request::create('/tratamientos/Unas/?utm_source=old'));

        self::assertNotNull($response);
        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/contact?utm_source=old', $response->headers->get('Location'));
        self::assertSame(1, $this->storage->findBySource('/tratamientos/Unas')?->getHits());
    }

    public function testPercentEncodedPathMatchesDecodedSource(): void
    {
        $response = $this->dispatch(Request::create('/podolog%C3%ADa'));

        self::assertSame(308, $response?->getStatusCode());
        self::assertSame('/contact', $response->headers->get('Location'));
    }

    public function testTargetQueryWinsAndExternalTargetsNeverGetTheVisitorQuery(): void
    {
        self::assertSame('/target?keep=1', $this->dispatch(Request::create('/with-query?a=1'))?->headers->get('Location'));
        self::assertSame('https://example.com/x', $this->dispatch(Request::create('/external?token=secret'))?->headers->get('Location'));
    }

    public function testSkips(): void
    {
        self::assertNull($this->dispatch(Request::create('/tratamientos/unas')), 'Paths are case-sensitive.');
        self::assertNull($this->dispatch(Request::create('/tratamientos/Unas', 'POST')), 'Only GET/HEAD.');
        self::assertNotNull($this->dispatch(Request::create('/tratamientos/Unas', 'HEAD')));
        self::assertNull($this->dispatch(Request::create('/tratamientos/Unas'), HttpKernelInterface::SUB_REQUEST));
        self::assertNull($this->dispatch(Request::create('/contacto')), 'A live page wins over a stale redirect.');
        self::assertNull($this->dispatch(Request::create('/unsafe')), 'Unsafe legacy target refused.');
        self::assertNull($this->dispatch(Request::create('/js')), 'Non-http external target refused.');
        self::assertNull($this->dispatch(Request::create('/admin/legacy')), 'Protected path never redirected.');
        self::assertNull($this->dispatch(Request::create('/unknown')));
    }

    private function dispatch(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): ?Response
    {
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $type);
        $this->subscriber->onKernelRequest($event);

        return $event->getResponse();
    }
}
