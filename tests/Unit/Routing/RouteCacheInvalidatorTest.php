<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Routing;

use Nowo\RoutingKitBundle\Routing\RouteCacheInvalidator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class RouteCacheInvalidatorTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/rk_cache_' . uniqid('', true);
        mkdir($this->cacheDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->cacheDir);
    }

    public function testInvalidateRemovesFilesAndWarmsRouter(): void
    {
        file_put_contents($this->cacheDir . '/url_generating_routes.php', 'x');
        file_put_contents($this->cacheDir . '/url_matching_routes.php.meta', 'x');

        mkdir($this->cacheDir . '/url_matching/nested', 0777, true);
        file_put_contents($this->cacheDir . '/url_matching/nested/file.php', 'x');

        mkdir($this->cacheDir . '/url_generating', 0777, true);
        file_put_contents($this->cacheDir . '/url_generating/file.php', 'x');

        $router = new WarmableRouterForInvalidator();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('RoutingKit: router cache invalidated.');

        $invalidator = new RouteCacheInvalidator($router, $this->cacheDir, $logger);
        $invalidator->invalidate();

        self::assertFileDoesNotExist($this->cacheDir . '/url_generating_routes.php');
        self::assertFileDoesNotExist($this->cacheDir . '/url_matching_routes.php.meta');
        self::assertDirectoryDoesNotExist($this->cacheDir . '/url_matching');
        self::assertDirectoryDoesNotExist($this->cacheDir . '/url_generating');
        self::assertSame([$this->cacheDir], $router->warmUps);
    }

    public function testInvalidateSkipsWarmUpForNonWarmableRouter(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('getContext')->willReturn(new RequestContext());
        $router->method('getRouteCollection')->willReturn(new RouteCollection());
        $router->method('generate')->willReturn('/generated');
        $router->method('match')->willReturn([]);

        $invalidator = new RouteCacheInvalidator($router, $this->cacheDir);
        $invalidator->invalidate();

        self::assertDirectoryExists($this->cacheDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = scandir($dir);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDir($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}

final class WarmableRouterForInvalidator implements RouterInterface, WarmableInterface
{
    /**
     * @var list<string>
     */
    public array $warmUps = [];

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
        $this->warmUps[] = $cacheDir;

        return [];
    }
}
