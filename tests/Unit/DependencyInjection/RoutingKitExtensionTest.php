<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\DependencyInjection;

use Nowo\RoutingKitBundle\Controller\RoutingPanelController;
use Nowo\RoutingKitBundle\DependencyInjection\Configuration;
use Nowo\RoutingKitBundle\DependencyInjection\RoutingKitExtension;
use Nowo\RoutingKitBundle\EventSubscriber\CanonicalRedirectSubscriber;
use Nowo\RoutingKitBundle\EventSubscriber\RootRedirectSubscriber;
use Nowo\RoutingKitBundle\EventSubscriber\RoutePathAuditSubscriber;
use Nowo\RoutingKitBundle\Locale\ConfigurableLocaleProvider;
use Nowo\RoutingKitBundle\Locale\LocaleProviderInterface;
use Nowo\RoutingKitBundle\Model\CanonicalStyle;
use Nowo\RoutingKitBundle\Routing\DbRouteLoader;
use Nowo\RoutingKitBundle\Routing\RouteCacheInvalidator;
use Nowo\RoutingKitBundle\Security\PanelAccessGuard;
use Nowo\RoutingKitBundle\Service\RoutePathImportExport;
use Nowo\RoutingKitBundle\Service\RoutePathManager;
use Nowo\RoutingKitBundle\Storage\FilesystemRoutePathStorage;
use Nowo\RoutingKitBundle\Storage\RoutePathStorageInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
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
        self::assertEquals(new Reference('security.authorization_checker'), $guard->getArgument('$authorizationChecker'));
        self::assertSame('ROLE_ADMIN', $guard->getArgument('$accessRoles')[0] ?? null);
        self::assertSame(['ROLE_ADMIN'], $guard->getArgument('$accessRoles'));

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
        self::assertSame([], $guard->getArgument('$accessRoles'));
        self::assertTrue($container->getDefinition(RoutingPanelController::class)->getArgument('$roleGateDisabled'));
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

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/tmp/project');
        $container->setParameter('kernel.cache_dir', '/tmp/cache');
        $container->setParameter('kernel.secret', 'test-secret');

        return $container;
    }
}
