<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\EventSubscriber;

use Nowo\RoutingKitBundle\EventSubscriber\DefaultLocalePrefixRedirectSubscriber;
use Nowo\RoutingKitBundle\Locale\ConfigurableLocaleProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DefaultLocalePrefixRedirectSubscriberTest extends TestCase
{
    public function testSubscribesRightAfterTheRouter(): void
    {
        self::assertSame([KernelEvents::REQUEST => ['onKernelRequest', 31]], DefaultLocalePrefixRedirectSubscriber::getSubscribedEvents());
    }

    public function testRedirectsUnderABaseUrlWithTheConfiguredStatus(): void
    {
        $request = $this->request('/app/index.php/en/page', '/app/index.php', 'en');
        $event   = $this->dispatch($request, '/app/index.php/page', 302);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/app/index.php/page', $response->headers->get('Location'));
    }

    public function testIgnoresSubRequestsOtherLocalesAndUnmatchedRequests(): void
    {
        $request = $this->request('/en/page', '', 'en');
        self::assertNull($this->dispatch($request, '/page', 301, HttpKernelInterface::SUB_REQUEST)->getResponse());

        self::assertNull($this->dispatch($this->request('/es/page', '', 'es'), '/es/page')->getResponse());

        $unmatched = Request::create('/en/page');
        self::assertNull($this->dispatch($unmatched, '/page')->getResponse());

        // `_locale` attribute of another locale under the default prefix (e.g. /en-gb).
        self::assertNull($this->dispatch($this->request('/en', '', 'fr'), '/page')->getResponse());

        // The bare URL is still prefixed (no twin): no redirect.
        $request = $this->request('/en', '', 'en');
        $request->attributes->set('_route_params', 'not-an-array');
        self::assertNull($this->dispatch($request, '/en')->getResponse());
    }

    private function request(string $uri, string $baseUrl, string $locale): Request
    {
        $request = Request::create($uri, 'GET', [], [], [], $baseUrl === '' ? [] : ['SCRIPT_FILENAME' => '/var/www' . $baseUrl, 'SCRIPT_NAME' => $baseUrl, 'PHP_SELF' => $baseUrl]);
        $request->attributes->set('_route', 'app_page');
        $request->attributes->set('_locale', $locale);
        $request->attributes->set('_route_params', ['_locale' => $locale]);

        return $request;
    }

    private function dispatch(Request $request, string $generated, int $status = 301, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn($generated);
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $type);
        (new DefaultLocalePrefixRedirectSubscriber($urls, new ConfigurableLocaleProvider('en', ['en', 'es', 'fr']), $status))->onKernelRequest($event);

        return $event;
    }
}
