<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Service;

use Nowo\RoutingKitBundle\Discovery\RoutableControllerDiscovery;
use Nowo\RoutingKitBundle\Locale\ConfigurableLocaleProvider;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Routing\PublicPathResolver;
use Nowo\RoutingKitBundle\Routing\RouteCacheInvalidator;
use Nowo\RoutingKitBundle\Service\RoutePathConflictDetector;
use Nowo\RoutingKitBundle\Service\RoutePathImportExport;
use Nowo\RoutingKitBundle\Service\RoutePathManager;
use Nowo\RoutingKitBundle\Storage\FilesystemRoutePathStorage;
use Nowo\RoutingKitBundle\Storage\RoutePathStorageInterface;
use Nowo\RoutingKitBundle\Validation\RoutePathValidator;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use const JSON_THROW_ON_ERROR;

final class RoutePathImportExportTest extends TestCase
{
    private string $file;
    private string $controllerDir;
    private string $cacheDir;

    protected function setUp(): void
    {
        $suffix              = uniqid('', true);
        $this->file          = sys_get_temp_dir() . '/rk_export_' . $suffix . '.json';
        $this->cacheDir      = sys_get_temp_dir() . '/rk_export_cache_' . $suffix;
        $this->controllerDir = sys_get_temp_dir() . '/rk_export_ctrl_' . $suffix;
        mkdir($this->cacheDir, 0777, true);
        mkdir($this->controllerDir, 0777, true);
        file_put_contents($this->controllerDir . '/PageController.php', <<<'PHP'
<?php
namespace App\Controller;
use Nowo\RoutingKitBundle\Attribute\Routable;
use Nowo\RoutingKitBundle\Attribute\RouteParam;
final class ImportExportPageController {
    #[Routable(name: 'app_about')]
    public function about(): void {}
    #[Routable(name: 'app_blog', params: [new RouteParam('slug', required: true)])]
    public function blog(string $slug): void {}
}
PHP);
        if (!class_exists('App\\Controller\\ImportExportPageController')) {
            require_once $this->controllerDir . '/PageController.php';
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        @unlink($this->controllerDir . '/PageController.php');
        @rmdir($this->controllerDir);
        @rmdir($this->cacheDir);
    }

    public function testExportImportRoundTripAndRejectsBadSignature(): void
    {
        $storage = new FilesystemRoutePathStorage($this->file);
        $service = $this->createService($storage);
        $storage->save(new RoutePathDefinition('app_about', 'en', '/about'));

        $envelope = $service->export();
        self::assertSame(1, $envelope['version']);
        self::assertNotSame('', $envelope['signature']);

        $targetFile = sys_get_temp_dir() . '/rk_import_' . uniqid('', true) . '.json';
        $target     = new FilesystemRoutePathStorage($targetFile);
        $importer   = $this->createService($target);
        self::assertSame(1, $importer->import($envelope));
        self::assertCount(1, $target->all());

        $envelope['signature'] = 'deadbeef';
        try {
            $importer->import($envelope);
            self::fail('expected signature failure');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('signature', $e->getMessage());
        }

        @unlink($targetFile);
    }

    public function testImportStripsControllerWhenOverrideDisabled(): void
    {
        $source = new FilesystemRoutePathStorage($this->file);
        $source->save(new RoutePathDefinition(
            routeName: 'app_about',
            locale: 'en',
            path: '/about',
            controller: 'Evil\\Hack::run',
        ));
        $export   = $this->createService($source, allowOverride: false);
        $envelope = $export->export();

        $targetFile = sys_get_temp_dir() . '/rk_import_strip_' . uniqid('', true) . '.json';
        $target     = new FilesystemRoutePathStorage($targetFile);
        $importer   = $this->createService($target, allowOverride: false);
        $importer->import($envelope, replaceAll: true);

        self::assertNull($target->all()[0]->controller);
        @unlink($targetFile);
    }

    public function testImportRejectsUnknownRoute(): void
    {
        $storage  = new FilesystemRoutePathStorage($this->file);
        $service  = $this->createService($storage);
        $payload  = [['route_name' => 'app_unknown', 'locale' => 'en', 'path' => '/x']];
        $json     = json_encode($payload, JSON_THROW_ON_ERROR);
        $envelope = [
            'version'   => 1,
            'payload'   => $payload,
            'signature' => hash_hmac('sha256', $json, 'routing-kit-test-signing-key-32ch!!'),
        ];

        $this->expectException(RuntimeException::class);
        $service->import($envelope);
    }

    public function testRejectsWeakSigningKey(): void
    {
        $storage = new FilesystemRoutePathStorage($this->file);
        $weak    = $this->createService($storage, signingKey: 'short');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('at least 32 characters');
        $weak->export();
    }

    private function createService(
        RoutePathStorageInterface $storage,
        bool $allowOverride = false,
        int $maxDefinitions = 500,
        string $signingKey = 'routing-kit-test-signing-key-32ch!!',
    ): RoutePathImportExport {
        $locales    = new ConfigurableLocaleProvider('en', ['en', 'es']);
        $discovery  = new RoutableControllerDiscovery([$this->controllerDir]);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnArgument(0);
        $manager = new RoutePathManager(
            $storage,
            new RoutePathValidator($discovery, $locales),
            new RouteCacheInvalidator($this->createRouter(), $this->cacheDir),
            $dispatcher,
            new RoutePathConflictDetector($storage, new PublicPathResolver($storage, $locales)),
            autoInvalidateCache: false,
            maxDefinitions: $maxDefinitions,
            allowControllerOverride: $allowOverride,
        );

        return new RoutePathImportExport($manager, $signingKey);
    }

    private function createRouter(): RouterInterface&WarmableInterface
    {
        return new class implements RouterInterface, WarmableInterface {
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
                return '/';
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
                return [];
            }
        };
    }
}
