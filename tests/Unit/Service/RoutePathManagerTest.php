<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Service;

use Nowo\RoutingKitBundle\Discovery\RoutableControllerDiscovery;
use Nowo\RoutingKitBundle\Event\RoutePathsChangedEvent;
use Nowo\RoutingKitBundle\Locale\ConfigurableLocaleProvider;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Routing\PublicPathResolver;
use Nowo\RoutingKitBundle\Routing\RouteCacheInvalidator;
use Nowo\RoutingKitBundle\Service\RoutePathConflictDetector;
use Nowo\RoutingKitBundle\Service\RoutePathManager;
use Nowo\RoutingKitBundle\Storage\RoutePathStorageInterface;
use Nowo\RoutingKitBundle\Validation\RoutePathValidator;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function count;

final class RoutePathManagerTest extends TestCase
{
    private string $controllerDir;
    private string $cacheDir;

    protected function setUp(): void
    {
        $suffix              = uniqid('', true);
        $this->controllerDir = sys_get_temp_dir() . '/rk_manager_ctrl_' . $suffix;
        $this->cacheDir      = sys_get_temp_dir() . '/rk_manager_cache_' . $suffix;

        mkdir($this->controllerDir, 0777, true);
        mkdir($this->cacheDir, 0777, true);

        file_put_contents($this->controllerDir . '/ArticleController.php', <<<'PHP'
<?php
namespace App\Controller;

use Nowo\RoutingKitBundle\Attribute\Routable;
use Nowo\RoutingKitBundle\Attribute\RouteParam;

final class ManagerArticleController
{
    #[Routable(name: 'app_article_show', params: [
        new RouteParam('slug', required: true, requirement: '[a-z0-9-]+'),
    ])]
    public function show(string $slug): void
    {
    }
}
PHP);

        if (!class_exists('App\\Controller\\ManagerArticleController')) {
            require_once $this->controllerDir . '/ArticleController.php';
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->controllerDir . '/ArticleController.php');
        @rmdir($this->controllerDir);
        @rmdir($this->cacheDir);
    }

    public function testSaveDispatchesEventAndInvalidatesCache(): void
    {
        $storage    = new InMemoryRoutePathStorage();
        $router     = new WarmableRouterSpy();
        $dispatches = [];
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$dispatches): object {
                $dispatches[] = $event;

                return $event;
            });

        $manager = new RoutePathManager(
            $storage,
            $this->createValidator(),
            new RouteCacheInvalidator($router, $this->cacheDir),
            $dispatcher,
            $this->createConflictDetector($storage),
        );

        $saved = $manager->save(new RoutePathDefinition('app_article_show', 'en', '/articles/{slug}'));

        self::assertNotNull($saved->id);
        self::assertSame(1, $router->warmUpCalls);
        self::assertCount(1, $dispatches);
        self::assertInstanceOf(RoutePathsChangedEvent::class, $dispatches[0]);
        self::assertFalse($dispatches[0]->deleted);
        self::assertSame($saved->id, $storage->findById($saved->id)?->id);
    }

    public function testSaveThrowsWhenValidationFails(): void
    {
        $storage    = new InMemoryRoutePathStorage();
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $manager = new RoutePathManager(
            $storage,
            $this->createValidator(),
            new RouteCacheInvalidator(new WarmableRouterSpy(), $this->cacheDir),
            $dispatcher,
            $this->createConflictDetector($storage),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid route path:');

        $manager->save(new RoutePathDefinition('app_article_show', 'en', '/articles'));
    }

    public function testDeleteDispatchesDeletedEventAndInvalidatesCache(): void
    {
        $existing   = new RoutePathDefinition('app_article_show', 'en', '/articles/{slug}', id: 'rk_existing');
        $storage    = new InMemoryRoutePathStorage([$existing]);
        $router     = new WarmableRouterSpy();
        $dispatches = [];
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$dispatches): object {
                $dispatches[] = $event;

                return $event;
            });

        $manager = new RoutePathManager(
            $storage,
            $this->createValidator(),
            new RouteCacheInvalidator($router, $this->cacheDir),
            $dispatcher,
            $this->createConflictDetector($storage),
        );

        $manager->delete('rk_existing');

        self::assertNull($storage->findById('rk_existing'));
        self::assertSame(1, $router->warmUpCalls);
        self::assertCount(1, $dispatches);
        self::assertTrue($dispatches[0] instanceof RoutePathsChangedEvent);
        self::assertTrue($dispatches[0]->deleted);
    }

    public function testClearCacheAlwaysInvalidates(): void
    {
        $router     = new WarmableRouterSpy();
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $storage    = new InMemoryRoutePathStorage();

        $manager = new RoutePathManager(
            $storage,
            $this->createValidator(),
            new RouteCacheInvalidator($router, $this->cacheDir),
            $dispatcher,
            $this->createConflictDetector($storage),
        );

        $manager->clearCache();

        self::assertSame(1, $router->warmUpCalls);
    }

    public function testAutoInvalidateFalseSkipsInvalidation(): void
    {
        $existing   = new RoutePathDefinition('app_article_show', 'en', '/articles/{slug}', id: 'rk_existing');
        $storage    = new InMemoryRoutePathStorage([$existing]);
        $router     = new WarmableRouterSpy();
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::exactly(2))->method('dispatch')->willReturnArgument(0);

        $manager = new RoutePathManager(
            $storage,
            $this->createValidator(),
            new RouteCacheInvalidator($router, $this->cacheDir),
            $dispatcher,
            $this->createConflictDetector($storage),
            autoInvalidateCache: false,
        );

        $manager->save(new RoutePathDefinition('app_article_show', 'es', '/articulos/{slug}'));
        $manager->delete('rk_existing');

        self::assertSame(0, $router->warmUpCalls);
    }

    private function createValidator(): RoutePathValidator
    {
        return new RoutePathValidator(
            new RoutableControllerDiscovery([$this->controllerDir]),
            new ConfigurableLocaleProvider('en', ['en', 'es']),
        );
    }

    private function createConflictDetector(RoutePathStorageInterface $storage): RoutePathConflictDetector
    {
        $locales = new ConfigurableLocaleProvider('en', ['en', 'es']);

        return new RoutePathConflictDetector(
            $storage,
            new PublicPathResolver($storage, $locales),
        );
    }
}

final class InMemoryRoutePathStorage implements RoutePathStorageInterface
{
    /**
     * @var array<string, RoutePathDefinition>
     */
    private array $items = [];

    /**
     * @param list<RoutePathDefinition> $definitions
     */
    public function __construct(array $definitions = [])
    {
        foreach ($definitions as $definition) {
            $this->items[(string) $definition->id] = $definition;
        }
    }

    public function all(): array
    {
        return array_values($this->items);
    }

    public function find(string $routeName, string $locale): ?RoutePathDefinition
    {
        foreach ($this->items as $item) {
            if ($item->routeName === $routeName && $item->locale === $locale) {
                return $item;
            }
        }

        return null;
    }

    public function findById(string $id): ?RoutePathDefinition
    {
        return $this->items[$id] ?? null;
    }

    public function findByRouteName(string $routeName): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (RoutePathDefinition $definition): bool => $definition->routeName === $routeName,
        ));
    }

    public function save(RoutePathDefinition $definition): RoutePathDefinition
    {
        $id               = $definition->id ?? ('rk_' . count($this->items));
        $saved            = $definition->withId($id);
        $this->items[$id] = $saved;

        return $saved;
    }

    public function delete(string $id): void
    {
        unset($this->items[$id]);
    }
}

final class WarmableRouterSpy implements RouterInterface, WarmableInterface
{
    public int $warmUpCalls = 0;

    private RequestContext $context;

    public function __construct()
    {
        $this->context = new RequestContext();
    }

    public function setContext(RequestContext $context): void
    {
        $this->context = $context;
    }

    public function getContext(): RequestContext
    {
        return $this->context;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        return '/generated';
    }

    /**
     * @return array<string, mixed>
     */
    public function match(string $pathinfo): array
    {
        return [];
    }

    public function getRouteCollection(): RouteCollection
    {
        return new RouteCollection();
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        ++$this->warmUpCalls;

        return [];
    }
}
