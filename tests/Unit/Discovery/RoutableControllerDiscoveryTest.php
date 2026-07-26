<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Discovery;

use Nowo\RoutingKitBundle\Discovery\RoutableControllerDiscovery;
use PHPUnit\Framework\TestCase;

final class RoutableControllerDiscoveryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/rk_discovery_' . uniqid('', true);
        mkdir($this->dir, 0777, true);

        file_put_contents($this->dir . '/ArticleController.php', <<<'PHP'
<?php
namespace App\Controller;

use Nowo\RoutingKitBundle\Attribute\Routable;
use Nowo\RoutingKitBundle\Attribute\RouteParam;

final class DiscoveryArticleController
{
    #[Routable(name: 'app_article_show', params: [
        new RouteParam('slug', required: true, requirement: '[a-z0-9-]+'),
        new RouteParam('page', required: false, type: 'int', default: 1),
    ])]
    public function show(string $slug): void
    {
    }
}
PHP);

        file_put_contents($this->dir . '/InvokableController.php', <<<'PHP'
<?php
namespace App\Controller;

use Nowo\RoutingKitBundle\Attribute\Routable;
use Nowo\RoutingKitBundle\Attribute\RouteParam;

#[Routable(name: 'app_invokable', params: [
    new RouteParam('slug', required: true),
])]
final class InvokableController
{
    public function __invoke(string $slug): void
    {
    }
}
PHP);

        file_put_contents($this->dir . '/MissingController.php', <<<'PHP'
<?php
namespace App\Controller;

final class MissingController
{
}
PHP);

        file_put_contents($this->dir . '/PlainFileController.php', <<<'PHP'
<?php
// no namespace on purpose
final class PlainFileController
{
}
PHP);

        file_put_contents($this->dir . '/NamespaceOnlyController.php', <<<'PHP'
<?php
namespace App\Controller;
PHP);

        file_put_contents($this->dir . '/ClassLevelIgnoredController.php', <<<'PHP'
<?php
namespace App\Controller;

use Nowo\RoutingKitBundle\Attribute\Routable;

#[Routable(name: 'app_ignored')]
final class ClassLevelIgnoredController
{
    public function index(): void
    {
    }
}
PHP);

        file_put_contents($this->dir . '/InvokableWithoutAttributeController.php', <<<'PHP'
<?php
namespace App\Controller;

final class InvokableWithoutAttributeController
{
    public function __invoke(): void
    {
    }
}
PHP);

        if (!class_exists('App\\Controller\\DiscoveryArticleController')) {
            require_once $this->dir . '/ArticleController.php';
        }
        if (!class_exists('App\\Controller\\InvokableController')) {
            require_once $this->dir . '/InvokableController.php';
        }
        if (!class_exists('App\\Controller\\ClassLevelIgnoredController')) {
            require_once $this->dir . '/ClassLevelIgnoredController.php';
        }
        if (!class_exists('App\\Controller\\InvokableWithoutAttributeController')) {
            require_once $this->dir . '/InvokableWithoutAttributeController.php';
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/ArticleController.php');
        @unlink($this->dir . '/InvokableController.php');
        @unlink($this->dir . '/MissingController.php');
        @unlink($this->dir . '/PlainFileController.php');
        @unlink($this->dir . '/NamespaceOnlyController.php');
        @unlink($this->dir . '/ClassLevelIgnoredController.php');
        @unlink($this->dir . '/InvokableWithoutAttributeController.php');
        @rmdir($this->dir);
    }

    public function testDiscoverFindsMethodAndClassLevelRoutables(): void
    {
        $discovery = new RoutableControllerDiscovery([$this->dir]);

        $items      = $discovery->discover();
        $routeNames = array_column($items, 'route_name');
        sort($routeNames);
        $article   = $discovery->findByRouteName('app_article_show');
        $invokable = $discovery->findByRouteName('app_invokable');

        self::assertCount(2, $items);
        self::assertSame(['app_article_show', 'app_invokable'], $routeNames);
        self::assertNotNull($article);
        self::assertNotNull($invokable);
        self::assertSame('App\\Controller\\DiscoveryArticleController::show', $article['controller']);
        self::assertSame('__invoke', $invokable['method']);
    }

    public function testFindByRouteNameAndParamsForRoute(): void
    {
        $discovery = new RoutableControllerDiscovery([$this->dir]);

        $item   = $discovery->findByRouteName('app_article_show');
        $params = $discovery->paramsForRoute('app_article_show');

        self::assertNotNull($item);
        self::assertSame('app_article_show', $item['route_name']);
        self::assertArrayNotHasKey('label', $item);
        self::assertCount(2, $params);
        self::assertSame('slug', $params[0]->name);
        self::assertSame('[a-z0-9-]+', $params[0]->requirement);
        self::assertSame('page', $params[1]->name);
        self::assertSame(1, $params[1]->default);
        self::assertSame([], $discovery->paramsForRoute('missing_route'));
    }

    public function testDiscoverSkipsClassesThatAreNotLoaded(): void
    {
        $discovery = new RoutableControllerDiscovery([$this->dir]);

        self::assertNull($discovery->findByRouteName('missing_route'));
        self::assertCount(2, $discovery->discover());
    }

    public function testDiscoverSkipsInvalidFilesAndMissingDirectories(): void
    {
        $discovery = new RoutableControllerDiscovery([$this->dir, $this->dir . '/missing']);

        $items      = $discovery->discover();
        $routeNames = array_column($items, 'route_name');
        sort($routeNames);

        self::assertSame(['app_article_show', 'app_invokable'], $routeNames);
    }
}
