<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Security;

use Error;
use Nowo\RoutingKitBundle\Controller\RoutingPanelController;
use Nowo\RoutingKitBundle\Discovery\RoutableControllerDiscovery;
use Nowo\RoutingKitBundle\Locale\ConfigurableLocaleProvider;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Routing\PublicPathResolver;
use Nowo\RoutingKitBundle\Routing\RouteCacheInvalidator;
use Nowo\RoutingKitBundle\Security\AllowAllRoutingKitAccessChecker;
use Nowo\RoutingKitBundle\Security\ConfigurableRoutingKitAccessChecker;
use Nowo\RoutingKitBundle\Security\PanelAccessGuard;
use Nowo\RoutingKitBundle\Service\RoutePathConflictDetector;
use Nowo\RoutingKitBundle\Service\RoutePathImportExport;
use Nowo\RoutingKitBundle\Service\RoutePathManager;
use Nowo\RoutingKitBundle\Storage\FilesystemRoutePathStorage;
use Nowo\RoutingKitBundle\Storage\RoutePathStorageInterface;
use Nowo\RoutingKitBundle\Tests\Support\FormKitTestSupport;
use Nowo\RoutingKitBundle\Validation\RoutePathValidator;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

use const JSON_THROW_ON_ERROR;

final class SecurityHardeningCoverageTest extends TestCase
{
    private string $controllerDir;
    private string $storageFile;
    private string $cacheDir;

    protected function setUp(): void
    {
        $suffix              = uniqid('', true);
        $this->controllerDir = sys_get_temp_dir() . '/rk_sec_ctrl_' . $suffix;
        $this->storageFile   = sys_get_temp_dir() . '/rk_sec_store_' . $suffix . '.json';
        $this->cacheDir      = sys_get_temp_dir() . '/rk_sec_cache_' . $suffix;
        mkdir($this->controllerDir, 0777, true);
        mkdir($this->cacheDir, 0777, true);

        file_put_contents($this->controllerDir . '/PageController.php', <<<'PHP'
<?php
namespace App\Controller;
use Nowo\RoutingKitBundle\Attribute\Routable;
use Nowo\RoutingKitBundle\Attribute\RouteParam;
final class SecPageController {
    #[Routable(name: 'app_about')]
    public function about(): void {}
    #[Routable(name: 'app_contact')]
    public function contact(): void {}
    #[Routable(name: 'app_blog', params: [new RouteParam('slug', required: true)])]
    public function blog(string $slug): void {}
}
PHP);
        if (!class_exists('App\\Controller\\SecPageController')) {
            require_once $this->controllerDir . '/PageController.php';
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->controllerDir . '/PageController.php');
        @rmdir($this->controllerDir);
        @unlink($this->storageFile);
        @rmdir($this->cacheDir);
    }

    public function testPanelAccessGuardDeniedResponseAndRoleDenied(): void
    {
        $guard = new PanelAccessGuard(new AllowAllRoutingKitAccessChecker(), null, false, true);
        self::assertSame(403, $guard->deniedResponse()->getStatusCode());

        $user  = $this->createMock(UserInterface::class);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $storage = $this->createMock(TokenStorageInterface::class);
        $storage->method('getToken')->willReturn($token);

        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);
        $this->expectException(AccessDeniedHttpException::class);
        (new PanelAccessGuard(
            new ConfigurableRoutingKitAccessChecker($auth, ['ROLE_ADMIN']),
            $storage,
            false,
            false,
        ))->assertGranted();
    }

    public function testConflictDetectorFindsCollisionsIncludingFallbackLocale(): void
    {
        $storage  = new FilesystemRoutePathStorage($this->storageFile);
        $locales  = new ConfigurableLocaleProvider('en', ['en', 'es']);
        $paths    = new PublicPathResolver($storage, $locales);
        $detector = new RoutePathConflictDetector($storage, $paths);

        $a         = $storage->save(new RoutePathDefinition('app_about', 'en', '/about'));
        $conflicts = $detector->conflictsFor(new RoutePathDefinition('app_blog', 'en', '/about'));
        self::assertNotSame([], $conflicts);

        $same = $detector->conflictsFor(new RoutePathDefinition('app_about', 'en', '/about', id: $a->id));
        self::assertSame([], $same);

        $fallback = $detector->conflictsFor(new RoutePathDefinition('app_contact', 'es', '/about'));
        self::assertNotSame([], $fallback);
    }

    public function testImportExportRejectsInvalidEnvelope(): void
    {
        $storage = new FilesystemRoutePathStorage($this->storageFile);
        $export  = $this->createImportExport($storage);
        self::assertStringContainsString('hmac-sha256', $export->describeKeySource());

        $this->expectException(RuntimeException::class);
        $export->decodeAndVerify(['payload' => 'nope']);
    }

    public function testImportReplaceAllAndRejectsBadSignature(): void
    {
        $storage = new FilesystemRoutePathStorage($this->storageFile);
        $storage->save(new RoutePathDefinition('app_about', 'en', '/about'));
        $export   = $this->createImportExport($storage);
        $envelope = $export->export();

        $otherFile = sys_get_temp_dir() . '/rk_sec_other_' . uniqid('', true) . '.json';
        $other     = new FilesystemRoutePathStorage($otherFile);
        $other->save(new RoutePathDefinition('app_blog', 'en', '/blog/{slug}'));
        $importer = $this->createImportExport($other);
        self::assertSame(1, $importer->import($envelope, replaceAll: true));
        self::assertCount(1, $other->all());
        self::assertSame('app_about', $other->all()[0]->routeName);
        @unlink($otherFile);

        $envelope['signature'] = 'deadbeef';
        $this->expectException(RuntimeException::class);
        $importer->import($envelope);
    }

    public function testImportRejectsControllerInjectionWhenOverrideOff(): void
    {
        $storage = new FilesystemRoutePathStorage($this->storageFile);
        $payload = [[
            'route_name' => 'app_about',
            'locale'     => 'en',
            'path'       => '/about',
            'controller' => 'Evil\\Controller::hack',
        ]];
        $json     = json_encode($payload, JSON_THROW_ON_ERROR);
        $envelope = [
            'version'   => 1,
            'payload'   => $payload,
            'signature' => hash_hmac('sha256', $json, 'routing-kit-test-signing-key-32ch!!'),
        ];

        $importer = $this->createImportExport($storage, allowOverride: false);
        self::assertSame(1, $importer->import($envelope, replaceAll: true));
        self::assertNull($storage->all()[0]->controller);
    }

    public function testManagerMaxDefinitionsConflictsAndControllerOverride(): void
    {
        $storage    = new FilesystemRoutePathStorage($this->storageFile);
        $locales    = new ConfigurableLocaleProvider('en', ['en', 'es']);
        $discovery  = new RoutableControllerDiscovery([$this->controllerDir]);
        $paths      = new PublicPathResolver($storage, $locales);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnArgument(0);

        $manager = new RoutePathManager(
            $storage,
            new RoutePathValidator($discovery, $locales),
            new RouteCacheInvalidator($this->createRouter(), $this->cacheDir),
            $dispatcher,
            new RoutePathConflictDetector($storage, $paths),
            autoInvalidateCache: false,
            maxDefinitions: 1,
            allowControllerOverride: true,
            rejectConflicts: true,
        );

        $manager->save(new RoutePathDefinition('app_about', 'en', '/about'));
        $this->expectException(RuntimeException::class);
        $manager->save(new RoutePathDefinition('app_blog', 'en', '/blog/{slug}'));
    }

    public function testValidatorLocaleAndControllerAllowlist(): void
    {
        $locales   = new ConfigurableLocaleProvider('en', ['en']);
        $discovery = new RoutableControllerDiscovery([$this->controllerDir]);
        $validator = new RoutePathValidator($discovery, $locales);

        self::assertNotSame([], $validator->validate('app_about', '/about', 'de'));
        self::assertNotSame([], $validator->validate('app_about', '/about', 'en', 'Evil::hack'));
        self::assertSame(
            ['App\\Controller\\SecPageController::about'],
            $validator->allowedControllersFor('app_about'),
        );
        self::assertSame([], $validator->allowedControllersFor('missing'));
        self::assertContains('Locale is required.', $validator->validate('app_about', '/about', ''));
    }

    public function testPanelCoversCsrfImportExportBranches(): void
    {
        $controller = $this->createPanel(role: null, allowOverride: true);

        self::assertSame(405, $controller->export(Request::create('/export', 'GET'))->getStatusCode());
        $export = $controller->export(Request::create('/export', 'POST', ['_csrf_token' => 'token-value', 'confirmed' => '1']));
        self::assertSame(200, $export->getStatusCode());
        $payload = (string) $export->getContent();
        self::assertSame(302, $controller->import(Request::create('/import', 'POST', [
            '_csrf_token'  => 'token-value',
            'payload_json' => $payload,
            'replace_all'  => '1',
        ]))->getStatusCode());

        self::assertSame(400, $controller->import(Request::create('/import', 'POST', [
            '_csrf_token'  => 'token-value',
            'payload_json' => '{',
        ]))->getStatusCode());

        $csrfBad = $this->createPanelWithCsrf(false);
        self::assertSame(403, $csrfBad->export(Request::create('/export', 'POST', ['_csrf_token' => 'x', 'confirmed' => '1']))->getStatusCode());
        self::assertSame(302, $controller->import(Request::create('/import', 'GET'))->getStatusCode());
        self::assertSame(403, $csrfBad->import(Request::create('/import', 'POST', [
            '_csrf_token'  => 'x',
            'payload_json' => '{}',
        ]))->getStatusCode());
        self::assertSame(400, $controller->import(Request::create('/import', 'POST', [
            '_csrf_token'  => 'token-value',
            'payload_json' => 'null',
        ]))->getStatusCode());
        self::assertSame(400, $controller->import(Request::create('/import', 'POST', [
            '_csrf_token'  => 'token-value',
            'payload_json' => '{"payload":[],"signature":"bad"}',
        ]))->getStatusCode());
        self::assertSame(413, $controller->import(Request::create('/import', 'POST', [
            '_csrf_token'  => 'token-value',
            'payload_json' => str_repeat('a', 1_048_577),
        ]))->getStatusCode());

        $exploding = $this->createPanelWithExplodingImport();
        $emptyJson = json_encode([], JSON_THROW_ON_ERROR);
        $envelope  = [
            'version'   => 1,
            'payload'   => [],
            'signature' => hash_hmac('sha256', $emptyJson, 'routing-kit-test-signing-key-32ch!!'),
        ];
        $failed = $exploding->import(Request::create('/import', 'POST', [
            '_csrf_token'  => 'token-value',
            'payload_json' => json_encode($envelope, JSON_THROW_ON_ERROR),
            'replace_all'  => '1',
        ]));
        self::assertSame(400, $failed->getStatusCode());
        self::assertSame('Import failed.', $failed->getContent());

        $create = $controller->create(Request::create('/new', 'POST', [
            '_csrf_token' => 'token-value',
            'route_name'  => 'app_about',
            'locale'      => 'en',
            'path'        => '/about',
            'controller'  => 'App\\Controller\\SecPageController::about',
            'enabled'     => '1',
        ]));
        self::assertSame(302, $create->getStatusCode());

        self::assertSame(200, $controller->previewConflicts(Request::create('/conflicts', 'GET', [
            'route_name' => 'app_about',
            'locale'     => 'en',
            'path'       => '/about',
            'id'         => 'keep-me',
        ]))->getStatusCode());

        $this->expectException(AccessDeniedHttpException::class);
        $this->createPanel(role: 'ROLE_ADMIN')->index(Request::create('/_routing/', 'GET'));
    }

    public function testConflictIncludesDisabledRowsAndDuplicateLocale(): void
    {
        $storage  = new FilesystemRoutePathStorage($this->storageFile);
        $locales  = new ConfigurableLocaleProvider('en', ['en', 'es']);
        $detector = new RoutePathConflictDetector($storage, new PublicPathResolver($storage, $locales));
        $storage->save(new RoutePathDefinition('app_about', 'en', '/about', enabled: false));
        self::assertNotSame([], $detector->conflictsFor(new RoutePathDefinition('app_contact', 'en', '/about')));

        $existing = $storage->save(new RoutePathDefinition('app_about', 'es', '/about-es'));
        $msgs     = $detector->conflictsFor(new RoutePathDefinition('app_about', 'es', '/other', id: 'different-id'));
        self::assertNotSame([], $msgs);
        self::assertNotNull($existing->id);
    }

    public function testManagerRejectsConflictsAndEmptyRouteName(): void
    {
        $storage    = new FilesystemRoutePathStorage($this->storageFile);
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
            maxDefinitions: 50,
            rejectConflicts: true,
        );
        $manager->save(new RoutePathDefinition('app_about', 'en', '/about'));
        try {
            $manager->save(new RoutePathDefinition('app_contact', 'en', '/about'));
            self::fail('expected conflict');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Path conflicts:', $e->getMessage());
        }

        $validator = new RoutePathValidator($discovery, $locales);
        self::assertContains('Route name is required.', $validator->validate('', '/about'));
    }

    public function testImportRejectsNonArrayRow(): void
    {
        $storage  = new FilesystemRoutePathStorage($this->storageFile);
        $service  = $this->createImportExport($storage);
        $payload  = ['not-an-array'];
        $json     = json_encode($payload, JSON_THROW_ON_ERROR);
        $envelope = [
            'version'   => 1,
            'payload'   => $payload,
            'signature' => hash_hmac('sha256', $json, 'routing-kit-test-signing-key-32ch!!'),
        ];
        $this->expectException(RuntimeException::class);
        $service->decodeAndVerify($envelope);
    }

    private function createImportExport(
        RoutePathStorageInterface $storage,
        bool $allowOverride = false,
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
            allowControllerOverride: $allowOverride,
        );

        return new RoutePathImportExport($manager, 'routing-kit-test-signing-key-32ch!!');
    }

    private function createPanelWithExplodingImport(): RoutingPanelController
    {
        $storage = new class implements RoutePathStorageInterface {
            public function all(): array
            {
                return [];
            }

            public function find(string $routeName, string $locale): ?RoutePathDefinition
            {
                return null;
            }

            public function findById(string $id): ?RoutePathDefinition
            {
                return null;
            }

            public function findByRouteName(string $routeName): array
            {
                return [];
            }

            public function save(RoutePathDefinition $definition): RoutePathDefinition
            {
                throw new Error('storage boom');
            }

            public function delete(string $id): void
            {
            }

            public function replaceAll(array $definitions): array
            {
                throw new Error('storage boom');
            }
        };
        $locales    = new ConfigurableLocaleProvider('en', ['en']);
        $discovery  = new RoutableControllerDiscovery([$this->controllerDir]);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $manager    = new RoutePathManager(
            $storage,
            new RoutePathValidator($discovery, $locales),
            new RouteCacheInvalidator($this->createRouter(), $this->cacheDir),
            $dispatcher,
            new RoutePathConflictDetector($storage, new PublicPathResolver($storage, $locales)),
            autoInvalidateCache: false,
        );
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('getToken')->willReturn(new CsrfToken(RoutingPanelController::CSRF_TOKEN_ID, 'token-value'));
        $csrf->method('isTokenValid')->willReturn(true);
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('<html/>');

        return new RoutingPanelController(
            $manager,
            $discovery,
            $locales,
            $twig,
            FormKitTestSupport::createFormFactory($csrf),
            new PanelAccessGuard(new AllowAllRoutingKitAccessChecker(), null, false, true),
            new RoutePathImportExport($manager, 'routing-kit-test-signing-key-32ch!!'),
            '/_routing',
            false,
        );
    }

    private function createPanelWithCsrf(bool $valid): RoutingPanelController
    {
        $storage    = new FilesystemRoutePathStorage($this->storageFile);
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
        );
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('getToken')->willReturn(new CsrfToken(RoutingPanelController::CSRF_TOKEN_ID, 'token-value'));
        $csrf->method('isTokenValid')->willReturn($valid);
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('<html/>');

        return new RoutingPanelController(
            $manager,
            $discovery,
            $locales,
            $twig,
            FormKitTestSupport::createFormFactory($csrf),
            new PanelAccessGuard(new AllowAllRoutingKitAccessChecker(), null, false, true),
            new RoutePathImportExport($manager, 'routing-kit-test-signing-key-32ch!!'),
            '/_routing',
            false,
        );
    }

    private function createPanel(?string $role, bool $allowOverride = false): RoutingPanelController
    {
        $storage    = new FilesystemRoutePathStorage($this->storageFile);
        $locales    = new ConfigurableLocaleProvider('en', ['en', 'es']);
        $discovery  = new RoutableControllerDiscovery([$this->controllerDir]);
        $paths      = new PublicPathResolver($storage, $locales);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnArgument(0);
        $manager = new RoutePathManager(
            $storage,
            new RoutePathValidator($discovery, $locales),
            new RouteCacheInvalidator($this->createRouter(), $this->cacheDir),
            $dispatcher,
            new RoutePathConflictDetector($storage, $paths),
            autoInvalidateCache: false,
            allowControllerOverride: $allowOverride,
        );
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('getToken')->willReturn(new CsrfToken(RoutingPanelController::CSRF_TOKEN_ID, 'token-value'));
        $csrf->method('isTokenValid')->willReturn(true);
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('<html/>');

        $roleGateDisabled = $role === null || $role === '';

        return new RoutingPanelController(
            $manager,
            $discovery,
            $locales,
            $twig,
            FormKitTestSupport::createFormFactory($csrf),
            new PanelAccessGuard(new AllowAllRoutingKitAccessChecker(), null, false, $roleGateDisabled),
            new RoutePathImportExport($manager, 'routing-kit-test-signing-key-32ch!!'),
            '/_routing',
            $allowOverride,
            $roleGateDisabled,
            50,
        );
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
