<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Controller;

use Nowo\RoutingKitBundle\Controller\RoutingPanelController;
use Nowo\RoutingKitBundle\Discovery\RoutableControllerDiscovery;
use Nowo\RoutingKitBundle\Locale\ConfigurableLocaleProvider;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Routing\PublicPathResolver;
use Nowo\RoutingKitBundle\Routing\RouteCacheInvalidator;
use Nowo\RoutingKitBundle\Security\AllowAllRoutingKitAccessChecker;
use Nowo\RoutingKitBundle\Security\PanelAccessGuard;
use Nowo\RoutingKitBundle\Service\RoutePathConflictDetector;
use Nowo\RoutingKitBundle\Service\RoutePathImportExport;
use Nowo\RoutingKitBundle\Service\RoutePathManager;
use Nowo\RoutingKitBundle\Storage\FilesystemRoutePathStorage;
use Nowo\RoutingKitBundle\Validation\RoutePathValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

final class RoutingPanelControllerTest extends TestCase
{
    private string $controllerDir;
    private string $storageFile;
    private string $cacheDir;

    protected function setUp(): void
    {
        $suffix              = uniqid('', true);
        $this->controllerDir = sys_get_temp_dir() . '/rk_panel_ctrl_' . $suffix;
        $this->storageFile   = sys_get_temp_dir() . '/rk_panel_store_' . $suffix . '.json';
        $this->cacheDir      = sys_get_temp_dir() . '/rk_panel_cache_' . $suffix;

        mkdir($this->controllerDir, 0777, true);
        mkdir($this->cacheDir, 0777, true);

        file_put_contents($this->controllerDir . '/ArticleController.php', <<<'PHP'
<?php
namespace App\Controller;

use Nowo\RoutingKitBundle\Attribute\Routable;
use Nowo\RoutingKitBundle\Attribute\RouteParam;

final class PanelArticleController
{
    #[Routable(name: 'app_article_show', params: [
        new RouteParam('slug', required: true, requirement: '[a-z0-9-]+'),
    ])]
    public function show(string $slug): void
    {
    }
}
PHP);

        if (!class_exists('App\\Controller\\PanelArticleController')) {
            require_once $this->controllerDir . '/ArticleController.php';
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->controllerDir . '/ArticleController.php');
        @rmdir($this->controllerDir);
        @unlink($this->storageFile);
        @rmdir($this->cacheDir);
    }

    public function testIndexRendersPanel(): void
    {
        [$controller] = $this->createController(
            definitions: [new RoutePathDefinition('app_article_show', 'en', '/articles/{slug}', id: 'rk_1')],
            twig: $this->createTwigMock(static function (string $template, array $context): string {
                self::assertSame('@NowoRoutingKitBundle/panel/index.html.twig', $template);
                self::assertCount(1, $context['definitions']);
                self::assertSame('/_routing-kit', $context['path_prefix']);
                self::assertSame(['en', 'es'], $context['locales']);
                self::assertSame('en', $context['default']);
                self::assertSame('token-value', $context['csrf_token']);

                return '<html>index</html>';
            }),
            csrfTokenManager: $this->createCsrfManager(valid: true),
        );

        $response = $controller->index(Request::create('/_routing-kit/', 'GET'));

        self::assertSame('<html>index</html>', $response->getContent());
    }

    public function testCreateGetRendersForm(): void
    {
        [$controller] = $this->createController(
            twig: $this->createTwigMock(static function (string $template, array $context): string {
                self::assertSame('@NowoRoutingKitBundle/panel/form.html.twig', $template);
                self::assertNull($context['definition']);
                self::assertCount(1, $context['routables']);
                self::assertSame([], $context['errors']);

                return '<html>form</html>';
            }),
        );

        $response = $controller->create(Request::create('/_routing-kit/create', 'GET'));

        self::assertSame('<html>form</html>', $response->getContent());
    }

    public function testCreatePostSuccessRedirects(): void
    {
        [$controller, $storage] = $this->createController();

        $response = $controller->create(Request::create('/_routing-kit/create', 'POST', [
            '_csrf_token'     => 'token-value',
            'route_name'      => 'app_article_show',
            'locale'          => 'en',
            'path'            => '/articles/{slug}',
            'canonical_style' => 'without_prefix',
            'trailing_slash'  => 'omit',
            'alias_mode'      => 'redirect',
            'enabled'         => '1',
        ]));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/_routing-kit/', $response->headers->get('Location'));
        self::assertCount(1, $storage->all());
    }

    public function testCreatePostErrorRendersFormWithErrors(): void
    {
        [$controller] = $this->createController(
            twig: $this->createTwigMock(static function (string $template, array $context): string {
                self::assertSame('@NowoRoutingKitBundle/panel/form.html.twig', $template);
                self::assertNotEmpty($context['errors']);
                self::assertSame('app_article_show', $context['definition']->routeName);

                return '<html>error</html>';
            }),
        );

        $response = $controller->create(Request::create('/_routing-kit/create', 'POST', [
            '_csrf_token' => 'token-value',
            'route_name'  => 'app_article_show',
            'locale'      => 'en',
            'path'        => '/articles',
        ]));

        self::assertSame('<html>error</html>', $response->getContent());
    }

    public function testCreatePostWithInvalidCsrfRendersFormError(): void
    {
        [$controller] = $this->createController(
            twig: $this->createTwigMock(static function (string $template, array $context): string {
                self::assertSame('@NowoRoutingKitBundle/panel/form.html.twig', $template);
                self::assertContains('Invalid CSRF token.', $context['errors']);

                return '<html>csrf-error</html>';
            }),
            csrfTokenManager: $this->createCsrfManager(valid: false),
        );

        $response = $controller->create(Request::create('/_routing-kit/create', 'POST', [
            '_csrf_token' => 'bad',
            'route_name'  => 'app_article_show',
            'locale'      => 'en',
            'path'        => '/articles/{slug}',
        ]));

        self::assertSame('<html>csrf-error</html>', $response->getContent());
    }

    public function testEditGetReturnsNotFoundWhenDefinitionIsMissing(): void
    {
        [$controller] = $this->createController();

        $response = $controller->edit(Request::create('/_routing-kit/edit/missing', 'GET'), 'missing');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Not found', $response->getContent());
    }

    public function testEditGetAndPostSuccessUseExistingDefinition(): void
    {
        $existing               = new RoutePathDefinition('app_article_show', 'en', '/articles/{slug}', id: 'rk_1');
        [$controller, $storage] = $this->createController(
            definitions: [$existing],
            twig: $this->createTwigMock(static function (string $template, array $context): string {
                self::assertSame('@NowoRoutingKitBundle/panel/form.html.twig', $template);
                self::assertSame('rk_1', $context['definition']?->id);

                return '<html>edit</html>';
            }),
        );

        $getResponse  = $controller->edit(Request::create('/_routing-kit/edit/rk_1', 'GET'), 'rk_1');
        $postResponse = $controller->edit(Request::create('/_routing-kit/edit/rk_1', 'POST', [
            '_csrf_token' => 'token-value',
            'route_name'  => 'app_article_show',
            'locale'      => 'en',
            'path'        => '/stories/{slug}',
            'enabled'     => '1',
        ]), 'rk_1');

        self::assertSame('<html>edit</html>', $getResponse->getContent());
        self::assertSame('/stories/{slug}', $storage->findById('rk_1')?->path);
        self::assertSame('/_routing-kit/', $postResponse->headers->get('Location'));
    }

    public function testEditPostErrorRendersFormWithUpdatedDefinition(): void
    {
        $existing     = new RoutePathDefinition('app_article_show', 'en', '/articles/{slug}', id: 'rk_1');
        [$controller] = $this->createController(
            definitions: [$existing],
            twig: $this->createTwigMock(static function (string $template, array $context): string {
                self::assertSame('@NowoRoutingKitBundle/panel/form.html.twig', $template);
                self::assertNotEmpty($context['errors']);
                self::assertSame('rk_1', $context['definition']->id);
                self::assertSame('/broken', $context['definition']->path);

                return '<html>edit-error</html>';
            }),
        );

        $response = $controller->edit(Request::create('/_routing-kit/edit/rk_1', 'POST', [
            '_csrf_token' => 'token-value',
            'route_name'  => 'app_article_show',
            'locale'      => 'en',
            'path'        => '/broken',
        ]), 'rk_1');

        self::assertSame('<html>edit-error</html>', $response->getContent());
    }

    public function testDeleteAndClearCacheRequireValidCsrfWhenManagerExists(): void
    {
        $existing                        = new RoutePathDefinition('app_article_show', 'en', '/articles/{slug}', id: 'rk_1');
        [$controller, $storage, $router] = $this->createController(
            definitions: [$existing],
            csrfTokenManager: $this->createCsrfManager(valid: false),
        );

        $deleteResponse = $controller->delete(Request::create('/_routing-kit/delete/rk_1', 'POST', [
            '_csrf_token' => 'bad',
        ]), 'rk_1');
        $clearResponse = $controller->clearCache(Request::create('/_routing-kit/clear-cache', 'POST', [
            '_csrf_token' => 'bad',
        ]));

        self::assertSame(403, $deleteResponse->getStatusCode());
        self::assertSame(403, $clearResponse->getStatusCode());
        self::assertNotNull($storage->findById('rk_1'));
        self::assertSame(0, $router->warmUpCalls);
    }

    public function testDeleteAndClearCacheSucceedWithValidCsrf(): void
    {
        $existing                        = new RoutePathDefinition('app_article_show', 'en', '/articles/{slug}', id: 'rk_1');
        [$controller, $storage, $router] = $this->createController(
            definitions: [$existing],
            csrfTokenManager: $this->createCsrfManager(valid: true),
        );

        $deleteResponse = $controller->delete(Request::create('/_routing-kit/delete/rk_1', 'POST', [
            '_csrf_token' => 'token-value',
        ]), 'rk_1');
        $clearResponse = $controller->clearCache(Request::create('/_routing-kit/clear-cache', 'POST', [
            '_csrf_token' => 'token-value',
        ]));

        self::assertSame('/_routing-kit/', $deleteResponse->headers->get('Location'));
        self::assertSame('/_routing-kit/', $clearResponse->headers->get('Location'));
        self::assertNull($storage->findById('rk_1'));
        self::assertSame(2, $router->warmUpCalls);
    }

    /**
     * @param list<RoutePathDefinition> $definitions
     *
     * @return array{RoutingPanelController, FilesystemRoutePathStorage, WarmableRouterForPanel}
     */
    private function createController(
        array $definitions = [],
        ?Environment $twig = null,
        ?CsrfTokenManagerInterface $csrfTokenManager = null,
    ): array {
        $storage = new FilesystemRoutePathStorage($this->storageFile);
        foreach ($definitions as $definition) {
            $storage->save($definition);
        }

        $router     = new WarmableRouterForPanel();
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnArgument(0);
        $locales   = new ConfigurableLocaleProvider('en', ['en', 'es']);
        $discovery = new RoutableControllerDiscovery([$this->controllerDir]);
        $paths     = new PublicPathResolver($storage, $locales);

        $manager = new RoutePathManager(
            $storage,
            new RoutePathValidator($discovery, $locales),
            new RouteCacheInvalidator($router, $this->cacheDir),
            $dispatcher,
            new RoutePathConflictDetector($storage, $paths),
        );

        $csrf = $csrfTokenManager ?? $this->createCsrfManager(valid: true);

        return [
            new RoutingPanelController(
                $manager,
                $discovery,
                $locales,
                $twig ?? $this->createTwigMock(static fn (): string => '<html>default</html>'),
                $csrf,
                new PanelAccessGuard(new AllowAllRoutingKitAccessChecker(), null, false, true),
                new RoutePathImportExport($manager, 'routing-kit-test-signing-key-32ch!!'),
                '/_routing-kit',
                false,
            ),
            $storage,
            $router,
        ];
    }

    private function createTwigMock(callable $callback): Environment
    {
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback($callback);

        return $twig;
    }

    private function createCsrfManager(bool $valid): CsrfTokenManagerInterface
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->method('getToken')->with(RoutingPanelController::CSRF_TOKEN_ID)->willReturn(new CsrfToken(
            RoutingPanelController::CSRF_TOKEN_ID,
            'token-value',
        ));
        $manager->method('isTokenValid')->willReturn($valid);

        return $manager;
    }
}

final class WarmableRouterForPanel implements RouterInterface, WarmableInterface
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
