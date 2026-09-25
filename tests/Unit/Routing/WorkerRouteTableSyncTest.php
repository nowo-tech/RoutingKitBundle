<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Routing;

use Nowo\RoutingKitBundle\Discovery\RoutableControllerDiscovery;
use Nowo\RoutingKitBundle\EventSubscriber\RouteTableFreshnessSubscriber;
use Nowo\RoutingKitBundle\Locale\ConfigurableLocaleProvider;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Routing\DbRouteLoader;
use Nowo\RoutingKitBundle\Routing\PublicPathResolver;
use Nowo\RoutingKitBundle\Routing\RouteCacheInvalidator;
use Nowo\RoutingKitBundle\Storage\FilesystemRoutePathStorage;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Router as BaseRouter;

/**
 * Two long-running workers (separate containers) share the storage file and the kernel cache dir;
 * nothing is reset between requests.
 */
final class WorkerRouteTableSyncTest extends TestCase
{
    private string $root;
    private string $file;
    private string $cacheDir;
    private string $controllerDir;

    protected function setUp(): void
    {
        $this->root          = sys_get_temp_dir() . '/rk_worker_' . uniqid('', true);
        $this->file          = $this->root . '/paths.json';
        $this->cacheDir      = $this->root . '/cache';
        $this->controllerDir = $this->root . '/controllers';
        mkdir($this->cacheDir, 0777, true);
        mkdir($this->controllerDir, 0777, true);
        file_put_contents($this->controllerDir . '/WorkerPageController.php', <<<'PHP'
<?php
namespace App\RkWorker\Controller;
use Nowo\RoutingKitBundle\Attribute\Routable;
class WorkerPageController {
    #[Routable(name: 'rk_worker_page')]
    public function page(): void {}
}
PHP);
        if (!class_exists('App\\RkWorker\\Controller\\WorkerPageController')) {
            require_once $this->controllerDir . '/WorkerPageController.php';
        }
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testPathEditInOneWorkerIsServedByTheOtherOnItsNextRequest(): void
    {
        $storage = new FilesystemRoutePathStorage($this->file);
        $saved   = $storage->save(new RoutePathDefinition('rk_worker_page', 'en', '/old'));

        [$routerA, $invalidatorA, $subscriberA] = $this->worker();
        [$routerB, $invalidatorB, $subscriberB] = $this->worker();

        // Request 1 on both workers.
        $subscriberA->onKernelRequest($this->mainRequestEvent());
        $subscriberB->onKernelRequest($this->mainRequestEvent());
        self::assertSame('rk_worker_page', $routerA->match('/old')['_route']);
        self::assertSame('rk_worker_page', $routerB->match('/old')['_route']);
        self::assertSame('/old', $routerB->generate('rk_worker_page', ['_locale' => 'en']));
        $staleMatching   = require $this->cacheDir . '/url_matching_routes.php';
        $staleGenerating = require $this->cacheDir . '/url_generating_routes.php';

        // Request 2 on worker A: the panel renames the path.
        $storage->save(new RoutePathDefinition('rk_worker_page', 'en', '/new', id: $saved->id));
        $invalidatorA->invalidate();

        self::assertSame('rk_worker_page', $routerA->match('/new')['_route']);
        self::assertSame('/new', $routerA->generate('rk_worker_page', ['_locale' => 'en']));
        self::assertFileExists($invalidatorA->getVersionFile());
        self::assertSame('rk_worker_page', $routerB->match('/old')['_route'], 'Worker B still holds its memoized matcher.');

        // Worker B's thread-local compiled-routes cache still holds the old table.
        $this->seedCompiledRoutesCache([
            $this->cacheDir . '/url_matching_routes.php'   => $staleMatching,
            $this->cacheDir . '/url_generating_routes.php' => $staleGenerating,
        ]);

        // Request 3 on worker B, no reset in between: the subscriber rebuilds its router before routing.
        $subscriberB->onKernelRequest($this->mainRequestEvent());

        self::assertSame('rk_worker_page', $routerB->match('/new')['_route']);
        self::assertSame('/new', $routerB->generate('rk_worker_page', ['_locale' => 'en']));

        // Request 4 on both workers: nothing changed, nothing is rebuilt.
        self::assertFalse($invalidatorA->refreshIfStale());
        self::assertFalse($invalidatorB->refreshIfStale());

        $this->expectException(ResourceNotFoundException::class);
        $routerB->match('/old');
    }

    public function testSubRequestsDoNotCheckTheVersion(): void
    {
        [, $invalidator, $subscriber] = $this->worker();
        $subscriber->onKernelRequest($this->mainRequestEvent());

        file_put_contents($invalidator->getVersionFile(), 'changed-elsewhere');
        $subscriber->onKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), new Request(), HttpKernelInterface::SUB_REQUEST));

        self::assertTrue($invalidator->refreshIfStale());
    }

    /**
     * @return array{Router, RouteCacheInvalidator, RouteTableFreshnessSubscriber}
     */
    private function worker(): array
    {
        $storage   = new FilesystemRoutePathStorage($this->file);
        $locales   = new ConfigurableLocaleProvider('en', ['en']);
        $loader    = new DbRouteLoader($storage, $locales, new PublicPathResolver($storage, $locales), new RoutableControllerDiscovery([$this->controllerDir]));
        $container = new Container();
        $container->set('routing.loader', $loader);
        $router      = new Router($container, '.', ['cache_dir' => $this->cacheDir, 'resource_type' => 'nowo_routing_kit']);
        $invalidator = new RouteCacheInvalidator($router, $this->cacheDir, null, $loader);

        return [$router, $invalidator, new RouteTableFreshnessSubscriber($invalidator)];
    }

    private function mainRequestEvent(): RequestEvent
    {
        return new RequestEvent($this->createMock(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST);
    }

    /**
     * @param array<string, mixed> $entries
     */
    private function seedCompiledRoutesCache(array $entries): void
    {
        (new ReflectionProperty(BaseRouter::class, 'cache'))->setValue(null, $entries);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}
