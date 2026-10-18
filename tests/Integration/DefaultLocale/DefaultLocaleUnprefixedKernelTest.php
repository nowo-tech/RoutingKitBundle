<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Integration\DefaultLocale;

use Nowo\RoutingKitBundle\EventSubscriber\DefaultLocalePrefixRedirectSubscriber;
use Nowo\RoutingKitBundle\Routing\DefaultLocaleRouter;
use Nowo\RoutingKitBundle\Routing\RouteCacheInvalidator;
use Nowo\RoutingKitBundle\Tests\Integration\DefaultLocale\App\DefaultLocaleTestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

use function sys_get_temp_dir;
use function uniqid;

/**
 * Real kernel: `default_locale_unprefixed` generates the bare twin for the default locale and 301s `/{default}/…`.
 */
final class DefaultLocaleUnprefixedKernelTest extends TestCase
{
    private string $baseDir;

    private ?DefaultLocaleTestKernel $kernel = null;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/rk_default_locale_' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        (new Filesystem())->remove($this->baseDir);
    }

    public function testDefaultLocaleGeneratesTheBareTwin(): void
    {
        $router = $this->router(['default_locale_unprefixed' => true]);

        self::assertInstanceOf(DefaultLocaleRouter::class, $router);
        self::assertSame('/page', $router->generate('app_page', ['_locale' => 'en']));
        self::assertSame('/page?x=1', $router->generate('app_page', ['_locale' => 'en', 'x' => '1']));
        self::assertSame('/es/page', $router->generate('app_page', ['_locale' => 'es']));
        self::assertSame('/page', $router->generate('app_page_unlocalized'));
        // No twin: unchanged (twice: the miss is memoized).
        self::assertSame('/en/other', $router->generate('app_other', ['_locale' => 'en']));
        self::assertSame('/en/other', $router->generate('app_other', ['_locale' => 'en']));
        self::assertSame('http://localhost/page', $router->generate('app_page', ['_locale' => 'en'], RouterInterface::ABSOLUTE_URL));

        // Locale from the request context.
        $router->setContext((new RequestContext())->setParameter('_locale', 'en'));
        self::assertSame('/page', $router->generate('app_page'));
        $router->getContext()->setParameter('_locale', 'fr');
        self::assertSame('/fr/page', $router->generate('app_page'));

        // Delegated API.
        self::assertSame('app_page_unlocalized', $router->match('/page')['_route']);
        self::assertSame('app_page', $router->matchRequest(Request::create('/es/page'))['_route']);
        self::assertNotNull($router->getRouteCollection()->get('app_other'));
        self::assertIsArray($router->warmUp($this->baseDir . '/cache'));
    }

    public function testPagesLinkBareAndPrefixedDefaultUrlsRedirect(): void
    {
        $kernel = $this->boot(['default_locale_unprefixed' => true]);

        $response = $kernel->handle(Request::create('/page'));
        self::assertSame('page:en link:/page other:/en/other', $response->getContent());

        $response = $kernel->handle(Request::create('/es/page'));
        self::assertSame('page:es link:/es/page other:/es/other', $response->getContent());

        $response = $kernel->handle(Request::create('/en/page?tab=2'));
        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/page?tab=2', $response->headers->get('Location'));

        self::assertSame(301, $kernel->handle(Request::create('/en/page', 'HEAD'))->getStatusCode());
        // A route without twin is served prefixed.
        self::assertSame(200, $kernel->handle(Request::create('/en/other'))->getStatusCode());
    }

    public function testPostToPrefixedDefaultUrlIsNotRedirected(): void
    {
        $kernel   = $this->boot(['default_locale_unprefixed' => true]);
        $response = $kernel->handle(Request::create('/en/page', 'POST'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('page:en link:/page other:/en/other', $response->getContent());
    }

    public function testCustomStatusAndSuffix(): void
    {
        $kernel = $this->boot(['default_locale_unprefixed' => ['enabled' => true, 'redirect_status' => 308, 'twin_suffix' => '_bare']]);

        // No `_bare` twins in this app: nothing changes.
        self::assertSame(200, $kernel->handle(Request::create('/en/page'))->getStatusCode());
        $router = $this->container()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);
        self::assertSame('/en/page', $router->generate('app_page', ['_locale' => 'en']));
    }

    public function testRedirectCanBeDisabled(): void
    {
        $kernel = $this->boot(['default_locale_unprefixed' => ['enabled' => true, 'redirect_prefixed' => false]]);

        self::assertFalse($this->container()->has(DefaultLocalePrefixRedirectSubscriber::class));
        self::assertSame(200, $kernel->handle(Request::create('/en/page'))->getStatusCode());
    }

    public function testDisabledByDefault(): void
    {
        $router = $this->router([]);

        self::assertNotInstanceOf(DefaultLocaleRouter::class, $router);
        self::assertSame('/en/page', $router->generate('app_page', ['_locale' => 'en']));
        self::assertSame(200, $this->kernel?->handle(Request::create('/en/page'))->getStatusCode());
        self::assertFalse($this->container()->getParameter('nowo.routing_kit.default_locale_unprefixed'));
        self::assertFalse($this->container()->has(DefaultLocalePrefixRedirectSubscriber::class));
        self::assertFalse($this->container()->has(DefaultLocaleRouter::class));
    }

    public function testRouteCacheInvalidationResetsTheDecoratedRouter(): void
    {
        $router = $this->router(['default_locale_unprefixed' => true]);
        self::assertSame('/en/other', $router->generate('app_other', ['_locale' => 'en']));

        $invalidator = $this->container()->get(RouteCacheInvalidator::class);
        self::assertInstanceOf(RouteCacheInvalidator::class, $invalidator);
        $invalidator->invalidate();

        self::assertSame('/page', $router->generate('app_page', ['_locale' => 'en']));
        self::assertSame('/en/other', $router->generate('app_other', ['_locale' => 'en']));
        self::assertSame('app_page', $router->match('/fr/page')['_route']);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function router(array $config): RouterInterface
    {
        $this->boot($config);
        $router = $this->container()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        return $router;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function boot(array $config): DefaultLocaleTestKernel
    {
        $this->kernel = new DefaultLocaleTestKernel($this->baseDir, $config);
        $this->kernel->boot();

        return $this->kernel;
    }

    private function container(): ContainerInterface
    {
        self::assertNotNull($this->kernel);
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }
}
