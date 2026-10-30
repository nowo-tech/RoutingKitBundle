<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Integration\Routing;

use Nowo\RoutingKitBundle\Tests\Integration\Redirect\App\RedirectTestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Loader\AttributeServicesLoader;
use Symfony\Component\Routing\RouterInterface;

use function array_filter;
use function array_keys;
use function array_values;
use function assert;
use function str_starts_with;

/**
 * REQ-SF-004 rule 7: the panel routes are opt-in. The host's default `routing.controllers` resource must not expose
 * them (they exist only through the documented routes.yaml import, under web_ui.path_prefix; UrlRedirectKernelTest
 * serves both panels through that import).
 */
final class OptInRoutesTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/rk_opt_in_routes_' . uniqid('', true);
        mkdir($this->baseDir . '/templates', 0777, true);
        mkdir($this->baseDir . '/translations', 0777, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->baseDir);
    }

    public function testRoutingControllersResourceDoesNotRegisterBundleRoutes(): void
    {
        if (!class_exists(AttributeServicesLoader::class)) {
            self::markTestSkipped('The routing.controllers resource needs symfony/routing >= 7.3.');
        }

        $kernel = new RedirectTestKernel($this->baseDir, false);
        $kernel->boot();

        try {
            $router = $kernel->getContainer()->get('router');
            assert($router instanceof RouterInterface);
            $bundleRoutes = array_values(array_filter(
                array_keys($router->getRouteCollection()->all()),
                static fn (string $name): bool => str_starts_with($name, 'nowo_routing_kit'),
            ));
            self::assertSame([], $bundleRoutes, 'Bundle routes must only exist when the host imports routes.yaml.');
        } finally {
            $kernel->shutdown();
        }
    }
}
