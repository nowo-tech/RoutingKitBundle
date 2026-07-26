<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Routing;

use Nowo\RoutingKitBundle\Discovery\RoutableControllerDiscovery;
use Nowo\RoutingKitBundle\Locale\ConfigurableLocaleProvider;
use Nowo\RoutingKitBundle\Model\CanonicalStyle;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Model\TrailingSlashStyle;
use Nowo\RoutingKitBundle\Routing\DbRouteLoader;
use Nowo\RoutingKitBundle\Routing\PublicPathResolver;
use Nowo\RoutingKitBundle\Storage\FilesystemRoutePathStorage;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

final class DbRouteLoaderTest extends TestCase
{
    private string $file;
    private string $dir;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/rk_loader_' . uniqid('', true) . '.json';
        $this->dir  = sys_get_temp_dir() . '/rk_loader_ctrl_' . uniqid('', true);
        mkdir($this->dir);
        file_put_contents($this->dir . '/PageController.php', <<<'PHP'
<?php
namespace App\Controller;
use Nowo\RoutingKitBundle\Attribute\Routable;
use Nowo\RoutingKitBundle\Attribute\RouteParam;
class PageController {
    #[Routable(name: 'app_about', params: [
        new RouteParam('slug', required: true, requirement: '[a-z0-9-]+'),
        new RouteParam('format', required: false, enum: ['html', 'json']),
    ])]
    public function about(): void {}
}
PHP);
        file_put_contents($this->dir . '/HomeController.php', <<<'PHP'
<?php
namespace App\Controller;
use Nowo\RoutingKitBundle\Attribute\Routable;
class HomeController {
    #[Routable(name: 'app_home')]
    public function index(): void {}
}
PHP);
        if (!class_exists('App\\Controller\\PageController')) {
            require_once $this->dir . '/PageController.php';
        }
        if (!class_exists('App\\Controller\\HomeController')) {
            require_once $this->dir . '/HomeController.php';
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        @unlink($this->dir . '/PageController.php');
        @unlink($this->dir . '/HomeController.php');
        @rmdir($this->dir);
    }

    public function testLoadsPrefixedAndUnprefixedRoutes(): void
    {
        $storage = new FilesystemRoutePathStorage($this->file);
        $storage->save(new RoutePathDefinition(
            'app_about',
            'en',
            '/about',
            CanonicalStyle::WithoutPrefix,
        ));

        $locales   = new ConfigurableLocaleProvider('en', ['en', 'es']);
        $resolver  = new PublicPathResolver($storage, $locales);
        $discovery = new RoutableControllerDiscovery([$this->dir]);
        $loader    = new DbRouteLoader($storage, $locales, $resolver, $discovery, true);

        self::assertTrue($loader->supports('.', 'nowo_routing_kit'));
        $collection = $loader->load('.', 'nowo_routing_kit');

        self::assertNotNull($collection->get('app_about'));
        self::assertSame('/about', $collection->get('app_about')->getPath());
        self::assertNotNull($collection->get('app_about.en'));
        self::assertSame('/{_locale}/about', $collection->get('app_about.en')->getPath());
        self::assertSame('[a-z0-9-]+', $collection->get('app_about')->getRequirement('slug'));
        self::assertSame('html|json', $collection->get('app_about')->getRequirement('format'));
    }

    public function testSupportsReturnsFalseForOtherTypes(): void
    {
        $storage   = new FilesystemRoutePathStorage($this->file);
        $locales   = new ConfigurableLocaleProvider('en', ['en']);
        $resolver  = new PublicPathResolver($storage, $locales);
        $discovery = new RoutableControllerDiscovery([$this->dir]);
        $loader    = new DbRouteLoader($storage, $locales, $resolver, $discovery);

        self::assertFalse($loader->supports('.', 'yaml'));
    }

    public function testUsesControllerOverrideAndRootLocaleRoutePath(): void
    {
        $storage = new FilesystemRoutePathStorage($this->file);
        $storage->save(new RoutePathDefinition(
            routeName: 'app_home',
            locale: 'en',
            path: '/',
            canonicalStyle: CanonicalStyle::WithPrefix,
            trailingSlash: TrailingSlashStyle::Keep,
            controller: 'App\\Controller\\OverrideController::__invoke',
        ));

        $locales   = new ConfigurableLocaleProvider('en', ['en', 'es']);
        $resolver  = new PublicPathResolver($storage, $locales);
        $discovery = new RoutableControllerDiscovery([$this->dir]);
        $loader    = new DbRouteLoader($storage, $locales, $resolver, $discovery, false);

        $collection = $loader->load('.', 'nowo_routing_kit');

        self::assertNotNull($collection->get('app_home.en'));
        self::assertNotNull($collection->get('app_home'));
        self::assertSame('/{_locale}/', $collection->get('app_home.en')->getPath());
        self::assertSame('/en/', $collection->get('app_home')->getPath());
        self::assertSame('App\\Controller\\OverrideController::__invoke', $collection->get('app_home')->getDefault('_controller'));
    }

    public function testLoadCannotRunTwice(): void
    {
        $storage = new FilesystemRoutePathStorage($this->file);
        $storage->save(new RoutePathDefinition('app_about', 'en', '/about'));

        $locales   = new ConfigurableLocaleProvider('en', ['en']);
        $resolver  = new PublicPathResolver($storage, $locales);
        $discovery = new RoutableControllerDiscovery([$this->dir]);
        $loader    = new DbRouteLoader($storage, $locales, $resolver, $discovery);

        $loader->load('.', 'nowo_routing_kit');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Do not add the RoutingKit loader twice.');

        $loader->load('.', 'nowo_routing_kit');
    }

    public function testSkipsDisabledAndControllerLessRoutes(): void
    {
        $storage = new FilesystemRoutePathStorage($this->file);
        $storage->save(new RoutePathDefinition(
            routeName: 'app_disabled',
            locale: 'en',
            path: '/disabled',
            enabled: false,
        ));
        $storage->save(new RoutePathDefinition(
            routeName: 'app_missing_controller',
            locale: 'en',
            path: '/missing-controller',
        ));

        $locales   = new ConfigurableLocaleProvider('en', ['en']);
        $resolver  = new PublicPathResolver($storage, $locales);
        $discovery = new RoutableControllerDiscovery([$this->dir]);
        $loader    = new DbRouteLoader($storage, $locales, $resolver, $discovery);

        $collection = $loader->load('.', 'nowo_routing_kit');

        self::assertNull($collection->get('app_disabled'));
        self::assertNull($collection->get('app_missing_controller'));
    }

    public function testSkipsLocalesWithoutResolvableDefinition(): void
    {
        $storage = new FilesystemRoutePathStorage($this->file);
        $storage->save(new RoutePathDefinition(
            routeName: 'app_about',
            locale: 'es',
            path: '/sobre/{slug}',
        ));

        $locales   = new ConfigurableLocaleProvider('en', ['en', 'es']);
        $resolver  = new PublicPathResolver($storage, $locales);
        $discovery = new RoutableControllerDiscovery([$this->dir]);
        $loader    = new DbRouteLoader($storage, $locales, $resolver, $discovery);

        $collection = $loader->load('.', 'nowo_routing_kit');

        self::assertNull($collection->get('app_about'));
        self::assertNotNull($collection->get('app_about.es'));
    }

    public function testAddsUnprefixedRouteWhenDefaultLocaleIsCanonicalWithoutPrefixEvenIfDisabledGlobally(): void
    {
        $storage = new FilesystemRoutePathStorage($this->file);
        $storage->save(new RoutePathDefinition(
            routeName: 'app_about',
            locale: 'en',
            path: '/about',
            canonicalStyle: CanonicalStyle::WithoutPrefix,
        ));

        $locales   = new ConfigurableLocaleProvider('en', ['en']);
        $resolver  = new PublicPathResolver($storage, $locales);
        $discovery = new RoutableControllerDiscovery([$this->dir]);
        $loader    = new DbRouteLoader($storage, $locales, $resolver, $discovery, false);

        $collection = $loader->load('.', 'nowo_routing_kit');

        self::assertSame('/about', $collection->get('app_about')?->getPath());
    }

    public function testToRoutePathFallsBackWhenPrefixDoesNotMatchLocale(): void
    {
        $storage   = new FilesystemRoutePathStorage($this->file);
        $locales   = new ConfigurableLocaleProvider('en', ['en']);
        $resolver  = new PublicPathResolver($storage, $locales);
        $discovery = new RoutableControllerDiscovery([$this->dir]);
        $loader    = new DbRouteLoader($storage, $locales, $resolver, $discovery);

        $method = new ReflectionMethod($loader, 'toRoutePath');
        $method->setAccessible(true);

        self::assertSame('/{_locale}/custom', $method->invoke($loader, '/custom', 'en'));
    }
}
