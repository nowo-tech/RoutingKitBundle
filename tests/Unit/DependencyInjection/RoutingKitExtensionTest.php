<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\DependencyInjection;

use LogicException;
use Nowo\RoutingKitBundle\Controller\RoutingPanelController;
use Nowo\RoutingKitBundle\DependencyInjection\Compiler\PanelAccessGuardPass;
use Nowo\RoutingKitBundle\DependencyInjection\Configuration;
use Nowo\RoutingKitBundle\DependencyInjection\RoutingKitExtension;
use Nowo\RoutingKitBundle\EventSubscriber\CanonicalRedirectSubscriber;
use Nowo\RoutingKitBundle\EventSubscriber\RootRedirectSubscriber;
use Nowo\RoutingKitBundle\EventSubscriber\RoutePathAuditSubscriber;
use Nowo\RoutingKitBundle\Locale\ConfigurableLocaleProvider;
use Nowo\RoutingKitBundle\Locale\LocaleProviderInterface;
use Nowo\RoutingKitBundle\Model\CanonicalStyle;
use Nowo\RoutingKitBundle\NowoRoutingKitBundle;
use Nowo\RoutingKitBundle\Routing\DbRouteLoader;
use Nowo\RoutingKitBundle\Routing\RouteCacheInvalidator;
use Nowo\RoutingKitBundle\Security\AllowAllRoutingKitAccessChecker;
use Nowo\RoutingKitBundle\Security\PanelAccessGuard;
use Nowo\RoutingKitBundle\Security\RoutingKitAccessCheckerInterface;
use Nowo\RoutingKitBundle\Service\RoutePathImportExport;
use Nowo\RoutingKitBundle\Service\RoutePathManager;
use Nowo\RoutingKitBundle\Storage\FilesystemRoutePathStorage;
use Nowo\RoutingKitBundle\Storage\RoutePathStorageInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

final class RoutingKitExtensionTest extends TestCase
{
    public function testLoadConfiguresDefaultsAndDefinitions(): void
    {
        $container = $this->createContainer();
        $extension = new RoutingKitExtension();

        $extension->load([[
            'default_locale' => 'es',
            'locales'        => ['es', 'en'],
            'storage'        => ['paths_file' => '/tmp/custom-paths.json'],
            'discovery'      => ['scan_dirs' => ['/app/src/Controller']],
            'panel'          => ['enabled' => true, 'path_prefix' => '/_admin/routing'],
            'redirects'      => [
                'canonical_enabled'    => false,
                'canonical_status'     => 308,
                'root_enabled'         => true,
                'root_canonical_style' => 'with_prefix',
                'root_home_path'       => '/home',
                'root_status'          => 307,
            ],
            'auto_invalidate_cache'       => false,
            'register_unprefixed_default' => false,
            'seo_kit_bridge'              => false,
        ]], $container);

        self::assertSame(Configuration::ALIAS, $extension->getAlias());
        self::assertSame('es', $container->getParameter('nowo.routing_kit.default_locale'));
        self::assertSame(['es', 'en'], $container->getParameter('nowo.routing_kit.locales'));
        self::assertSame('/tmp/custom-paths.json', $container->getParameter('nowo.routing_kit.storage.paths_file'));
        self::assertSame(['/app/src/Controller'], $container->getParameter('nowo.routing_kit.discovery.scan_dirs'));
        self::assertSame('/_admin/routing', $container->getParameter('nowo.routing_kit.panel.path_prefix'));
        self::assertFalse($container->getParameter('nowo.routing_kit.auto_invalidate_cache'));
        self::assertFalse($container->getParameter('nowo.routing_kit.register_unprefixed_default'));
        self::assertFalse($container->getParameter('nowo.routing_kit.seo_kit_bridge'));

        $localeProvider = $container->getDefinition(ConfigurableLocaleProvider::class);
        self::assertSame('es', $localeProvider->getArgument('$defaultLocale'));
        self::assertSame(['es', 'en'], $localeProvider->getArgument('$locales'));

        $storage = $container->getDefinition(FilesystemRoutePathStorage::class);
        self::assertSame('/tmp/custom-paths.json', $storage->getArgument('$filePath'));

        self::assertSame(
            FilesystemRoutePathStorage::class,
            (string) $container->getAlias(RoutePathStorageInterface::class),
        );
        self::assertSame(
            ConfigurableLocaleProvider::class,
            (string) $container->getAlias(LocaleProviderInterface::class),
        );

        $manager = $container->getDefinition(RoutePathManager::class);
        self::assertFalse($manager->getArgument('$autoInvalidateCache'));

        $loader = $container->getDefinition(DbRouteLoader::class);
        self::assertFalse($loader->getArgument('$registerUnprefixedDefault'));
        self::assertSame([[]], $loader->getTag('routing.loader'));

        $canonicalSubscriber = $container->getDefinition(CanonicalRedirectSubscriber::class);
        self::assertFalse($canonicalSubscriber->getArgument('$enabled'));
        self::assertSame(308, $canonicalSubscriber->getArgument('$redirectStatus'));

        $rootSubscriber = $container->getDefinition(RootRedirectSubscriber::class);
        self::assertTrue($rootSubscriber->getArgument('$enabled'));
        self::assertSame(CanonicalStyle::WithPrefix, $rootSubscriber->getArgument('$homeCanonicalStyle'));
        self::assertSame('/home', $rootSubscriber->getArgument('$homePath'));
        self::assertSame(307, $rootSubscriber->getArgument('$redirectStatus'));

        $controller = $container->getDefinition(RoutingPanelController::class);
        self::assertSame('/_admin/routing', $controller->getArgument('$pathPrefix'));
        self::assertTrue($controller->isPublic());
        self::assertCount(2, $controller->getTag('controller.service_arguments'));

        $invalidator = $container->getDefinition(RouteCacheInvalidator::class);
        self::assertEquals(new Reference('router'), $invalidator->getArgument('$router'));
        self::assertSame('%kernel.cache_dir%', $invalidator->getArgument('$cacheDir'));
    }

    public function testLoadUsesCustomAliasesWhenConfigured(): void
    {
        $container = $this->createContainer();
        $container->setDefinition('app.locale_provider', new Definition());
        $container->setDefinition('app.path_storage', new Definition());

        $extension = new RoutingKitExtension();
        $extension->load([[
            'locale_provider' => 'app.locale_provider',
            'storage'         => ['path_storage' => 'app.path_storage'],
        ]], $container);

        self::assertSame('app.locale_provider', (string) $container->getAlias(LocaleProviderInterface::class));
        self::assertSame('app.path_storage', (string) $container->getAlias(RoutePathStorageInterface::class));
    }

    public function testLoadWiresLoggerTokenStorageAndExportKey(): void
    {
        $container = $this->createContainer();
        $container->setDefinition('logger', new Definition());
        $container->setDefinition('security.token_storage', new Definition());
        $container->setDefinition('security.authorization_checker', new Definition());

        $extension = new RoutingKitExtension();
        $extension->load([[
            'panel' => [
                'enabled'            => true,
                'role'               => 'ROLE_ADMIN',
                'export_signing_key' => 'routing-kit-test-signing-key-32ch!!',
            ],
        ]], $container);

        $guard = $container->getDefinition(PanelAccessGuard::class);
        // Extension sets flags; PanelAccessGuardPass wires TokenStorage at compile time.
        self::assertFalse($guard->getArgument('$allowUnauthenticated'));
        self::assertFalse($guard->getArgument('$roleGateDisabled'));

        (new PanelAccessGuardPass())->process($container);
        self::assertEquals(new Reference('security.token_storage'), $guard->getArgument('$tokenStorage'));

        $export = $container->getDefinition(RoutePathImportExport::class);
        self::assertSame('routing-kit-test-signing-key-32ch!!', $export->getArgument('$signingKey'));

        $audit = $container->getDefinition(RoutePathAuditSubscriber::class);
        self::assertEquals(new Reference('logger'), $audit->getArgument('$logger'));
        self::assertEquals(new Reference('security.token_storage'), $audit->getArgument('$tokenStorage'));
    }

    public function testAllowUnauthenticatedClearsAccessRoles(): void
    {
        $container = $this->createContainer();
        $extension = new RoutingKitExtension();
        $extension->load([[
            'security' => [
                'access_roles'          => ['ROLE_ADMIN'],
                'allow_unauthenticated' => true,
            ],
        ]], $container);

        $guard = $container->getDefinition(PanelAccessGuard::class);
        self::assertTrue($guard->getArgument('$allowUnauthenticated'));
        self::assertTrue($guard->getArgument('$roleGateDisabled'));
        self::assertTrue($container->getDefinition(RoutingPanelController::class)->getArgument('$roleGateDisabled'));
        self::assertTrue($container->hasDefinition('nowo.routing_kit.access_checker.allow_all'));
    }

    public function testPanelRequiresSecurityBundleWhenAuthenticated(): void
    {
        $container = $this->createContainer();
        $container->setParameter('kernel.bundles', []);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires symfony/security-bundle');

        (new RoutingKitExtension())->load([[
            'panel'    => ['enabled' => true],
            'security' => ['allow_unauthenticated' => false],
        ]], $container);
    }

    public function testPanelRequiresSecurityBundleWhenKernelBundlesMissing(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/tmp/project');
        $container->setParameter('kernel.cache_dir', '/tmp/cache');
        $container->setParameter('kernel.secret', 'test-secret');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires symfony/security-bundle');

        (new RoutingKitExtension())->load([[
            'panel'    => ['enabled' => true],
            'security' => ['allow_unauthenticated' => false],
        ]], $container);
    }

    public function testAcceptsSecurityBundleViaRegisteredExtension(): void
    {
        $container = $this->createContainer();
        $container->setParameter('kernel.bundles', []);
        $container->registerExtension(new class implements ExtensionInterface {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getNamespace(): string
            {
                return '';
            }

            public function getXsdValidationBasePath(): false
            {
                return false;
            }

            public function getAlias(): string
            {
                return 'security';
            }
        });
        $container->setDefinition('security.authorization_checker', new Definition());

        (new RoutingKitExtension())->load([[
            'panel'    => ['enabled' => true],
            'security' => ['allow_unauthenticated' => false, 'access_roles' => ['ROLE_ADMIN']],
        ]], $container);

        self::assertTrue($container->hasDefinition('nowo.routing_kit.access_checker.default'));
    }

    public function testCustomAccessCheckerAlias(): void
    {
        $container = $this->createContainer();
        $container->setDefinition('app.routing_access', new Definition(AllowAllRoutingKitAccessChecker::class));

        (new RoutingKitExtension())->load([[
            'security' => [
                'allow_unauthenticated' => false,
                'access_checker'        => 'app.routing_access',
            ],
        ]], $container);

        self::assertSame(
            'app.routing_access',
            (string) $container->getAlias(RoutingKitAccessCheckerInterface::class),
        );
    }

    public function testLoadRemovesPanelControllerWhenPanelIsDisabled(): void
    {
        $container = $this->createContainer();
        $extension = new RoutingKitExtension();

        $extension->load([[
            'panel' => ['enabled' => false],
        ]], $container);

        self::assertFalse($container->hasDefinition(RoutingPanelController::class));
    }

    public function testLoadDisablesBundleWhenEnabledFalse(): void
    {
        $container = $this->createContainer();
        $extension = new RoutingKitExtension();

        $extension->load([['enabled' => false]], $container);

        self::assertFalse($container->hasDefinition(RoutingPanelController::class));
        self::assertFalse($container->hasDefinition(DbRouteLoader::class));
        self::assertFalse($container->hasDefinition(CanonicalRedirectSubscriber::class));
        self::assertFalse($container->hasDefinition(RootRedirectSubscriber::class));
    }

    public function testPrependRegistersNamedAssetPackageWhenFrameworkIsPresent(): void
    {
        $container = $this->createContainer();
        $container->registerExtension(new class implements ExtensionInterface {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getNamespace(): string
            {
                return '';
            }

            public function getXsdValidationBasePath(): false
            {
                return false;
            }

            public function getAlias(): string
            {
                return 'framework';
            }
        });

        (new RoutingKitExtension())->prepend($container);

        $prepended = $container->getExtensionConfig('framework');
        self::assertNotSame([], $prepended);
        self::assertSame(
            '/bundles/noworoutingkit',
            $prepended[0]['assets']['packages'][Configuration::ALIAS]['base_path'] ?? null,
        );
    }

    public function testPrependIsNoopWithoutFrameworkExtension(): void
    {
        $container = $this->createContainer();
        (new RoutingKitExtension())->prepend($container);

        self::assertSame([], $container->getExtensionConfig('framework'));
    }

    public function testPrependSeedsFormKitRoutingKitProfileWhenHostUnset(): void
    {
        $container = $this->createContainer();
        $this->registerStubExtension($container, 'nowo_form_kit');
        $this->registerStubExtension($container, 'framework');

        (new RoutingKitExtension())->prepend($container);

        $found = false;
        foreach ($container->getExtensionConfig('nowo_form_kit') as $cfg) {
            if (($cfg['css_framework'] ?? null) === 'bootstrap'
                && isset($cfg['profiles']['routing_kit']['alias'])
                && $cfg['profiles']['routing_kit']['alias'] === 'routing_kit'
            ) {
                $found = true;
                self::assertSame(NowoRoutingKitBundle::TRANSLATION_DOMAIN, $cfg['profiles']['routing_kit']['translation_domain']);
                self::assertFalse($cfg['profiles']['routing_kit']['auto_placeholder']);
                self::assertFalse($cfg['profiles']['routing_kit']['auto_help']);
                self::assertSame('nowo-ui-input form-control', $cfg['profiles']['routing_kit']['defaults']['attr']['class']);
                break;
            }
        }
        self::assertTrue($found, 'Expected nowo_form_kit routing_kit profile and css_framework bootstrap.');
    }

    public function testPrependDoesNotOverrideExplicitFormKitHostConfig(): void
    {
        $container = $this->createContainer();
        $this->registerStubExtension($container, 'nowo_form_kit');
        $container->prependExtensionConfig('nowo_form_kit', [
            'css_framework' => 'none',
            'profiles'      => [
                'routing_kit' => [
                    'alias'              => 'routing_kit',
                    'translation_domain' => 'HostDomain',
                ],
            ],
        ]);
        $this->registerStubExtension($container, 'framework');

        (new RoutingKitExtension())->prepend($container);

        $bootstrapSeed   = false;
        $routingKitReseed = false;
        foreach ($container->getExtensionConfig('nowo_form_kit') as $cfg) {
            if (($cfg['css_framework'] ?? null) === 'bootstrap') {
                $bootstrapSeed = true;
            }
            if (isset($cfg['profiles']['routing_kit']['translation_domain'])
                && $cfg['profiles']['routing_kit']['translation_domain'] === NowoRoutingKitBundle::TRANSLATION_DOMAIN
            ) {
                $routingKitReseed = true;
            }
        }
        self::assertFalse($bootstrapSeed, 'Must not prepend FormKit css_framework when host already set it.');
        self::assertFalse($routingKitReseed, 'Must not re-seed routing_kit profile when host already defined it.');
    }

    public function testPrependSkipsFormKitWhenExtensionMissing(): void
    {
        $container = $this->createContainer();
        $this->registerStubExtension($container, 'framework');

        (new RoutingKitExtension())->prepend($container);

        self::assertSame([], $container->getExtensionConfig('nowo_form_kit'));
    }

    private function registerStubExtension(ContainerBuilder $container, string $alias): void
    {
        $container->registerExtension(new class($alias) implements ExtensionInterface {
            public function __construct(private readonly string $extensionAlias)
            {
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getNamespace(): string
            {
                return '';
            }

            public function getXsdValidationBasePath(): false
            {
                return false;
            }

            public function getAlias(): string
            {
                return $this->extensionAlias;
            }
        });
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/tmp/project');
        $container->setParameter('kernel.cache_dir', '/tmp/cache');
        $container->setParameter('kernel.secret', 'test-secret');
        $container->setParameter('kernel.bundles', ['SecurityBundle' => 'Symfony\\Bundle\\SecurityBundle\\SecurityBundle']);

        return $container;
    }
}
