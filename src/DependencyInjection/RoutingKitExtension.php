<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\DependencyInjection;

use Nowo\RoutingKitBundle\Controller\RoutingPanelController;
use Nowo\RoutingKitBundle\Discovery\RoutableControllerDiscovery;
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
use Nowo\RoutingKitBundle\Twig\RoutingKitTwigExtension;
use Nowo\RoutingKitBundle\Validation\RoutePathValidator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

use function array_values;
use function is_string;

final class RoutingKitExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        $container->setParameter('nowo.routing_kit.enabled', $config['enabled']);
        $container->setParameter('nowo.routing_kit.default_locale', $config['default_locale']);
        $container->setParameter('nowo.routing_kit.locales', array_values($config['locales']));
        $container->setParameter('nowo.routing_kit.storage.paths_file', $config['storage']['paths_file']);
        $container->setParameter('nowo.routing_kit.discovery.scan_dirs', $config['discovery']['scan_dirs']);
        $container->setParameter('nowo.routing_kit.panel.enabled', $config['panel']['enabled']);
        $container->setParameter('nowo.routing_kit.panel.path_prefix', $config['panel']['path_prefix']);
        $container->setParameter('nowo.routing_kit.panel.role', $config['panel']['role']);
        $container->setParameter('nowo.routing_kit.panel.list_page_size', (int) $config['panel']['list_page_size']);
        $container->setParameter('nowo.routing_kit.web_ui', $config['web_ui']);
        $container->setParameter('nowo.routing_kit.web_ui.enabled', (bool) $config['web_ui']['enabled']);
        $container->setParameter('nowo.routing_kit.web_ui.layout_template', $config['web_ui']['layout_template']);
        $container->setParameter('nowo.routing_kit.web_ui.css_framework', $config['web_ui']['css_framework']);
        $container->setParameter('nowo.routing_kit.web_ui.icon_set', $config['web_ui']['icon_set']);
        $container->setParameter('nowo.routing_kit.security', $config['security']);
        $container->setParameter('nowo.routing_kit.security.access_roles', array_values($config['security']['access_roles']));
        $container->setParameter('nowo.routing_kit.security.allow_unauthenticated', (bool) $config['security']['allow_unauthenticated']);
        $container->setParameter('nowo.routing_kit.auto_invalidate_cache', $config['auto_invalidate_cache']);
        $container->setParameter('nowo.routing_kit.register_unprefixed_default', $config['register_unprefixed_default']);
        $container->setParameter('nowo.routing_kit.redirects', $config['redirects']);
        $container->setParameter('nowo.routing_kit.seo_kit_bridge', $config['seo_kit_bridge']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        if (!$config['enabled']) {
            $this->disableBundle($container);

            return;
        }

        $this->configureLocales($container, $config);
        $this->configureStorage($container, $config);
        $this->configureDiscovery($container, $config);
        $this->configureValidator($container);
        $this->configureManager($container, $config);
        $this->configureLoader($container, $config);
        $this->configureSubscribers($container, $config);
        $this->configurePanel($container, $config);
        $this->configureWebUi($container, $config);
        $this->configureImportExport($container, $config);
        $this->configureCacheInvalidator($container);
        $this->configureAudit($container);
    }

    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }

    private function disableBundle(ContainerBuilder $container): void
    {
        $container->removeDefinition(RoutingPanelController::class);
        $container->removeDefinition(DbRouteLoader::class);
        $container->removeDefinition(CanonicalRedirectSubscriber::class);
        $container->removeDefinition(RootRedirectSubscriber::class);
        $container->removeDefinition(RoutePathAuditSubscriber::class);
        $container->removeDefinition(PanelAccessGuard::class);
        $container->removeDefinition(RoutePathImportExport::class);
        if ($container->hasDefinition(RoutingKitTwigExtension::class)) {
            $container->removeDefinition(RoutingKitTwigExtension::class);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configureLocales(ContainerBuilder $container, array $config): void
    {
        $container->getDefinition(ConfigurableLocaleProvider::class)
            ->setArgument('$defaultLocale', $config['default_locale'])
            ->setArgument('$locales', array_values($config['locales']));

        $override = $config['locale_provider'] ?? null;
        if (is_string($override) && $override !== '') {
            $container->setAlias(LocaleProviderInterface::class, $override)->setPublic(false);
        } else {
            $container->setAlias(LocaleProviderInterface::class, ConfigurableLocaleProvider::class)->setPublic(false);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configureStorage(ContainerBuilder $container, array $config): void
    {
        $container->getDefinition(FilesystemRoutePathStorage::class)
            ->setArgument('$filePath', $config['storage']['paths_file']);

        $override = $config['storage']['path_storage'] ?? null;
        if (is_string($override) && $override !== '') {
            $container->setAlias(RoutePathStorageInterface::class, $override)->setPublic(false);
        } else {
            $container->setAlias(RoutePathStorageInterface::class, FilesystemRoutePathStorage::class)->setPublic(false);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configureDiscovery(ContainerBuilder $container, array $config): void
    {
        $container->getDefinition(RoutableControllerDiscovery::class)
            ->setArgument('$scanDirs', $config['discovery']['scan_dirs']);
    }

    private function configureValidator(ContainerBuilder $container): void
    {
        $container->getDefinition(RoutePathValidator::class)
            ->setArgument('$locales', new Reference(LocaleProviderInterface::class));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configureManager(ContainerBuilder $container, array $config): void
    {
        $container->getDefinition(RoutePathManager::class)
            ->setArgument('$autoInvalidateCache', (bool) $config['auto_invalidate_cache'])
            ->setArgument('$maxDefinitions', (int) $config['panel']['max_definitions'])
            ->setArgument('$allowControllerOverride', (bool) $config['panel']['allow_controller_override'])
            ->setArgument('$rejectConflicts', (bool) $config['panel']['reject_conflicts']);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configureLoader(ContainerBuilder $container, array $config): void
    {
        $container->getDefinition(DbRouteLoader::class)
            ->setArgument('$registerUnprefixedDefault', (bool) $config['register_unprefixed_default'])
            ->setArgument('$allowControllerOverride', (bool) $config['panel']['allow_controller_override'])
            ->addTag('routing.loader');
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configureSubscribers(ContainerBuilder $container, array $config): void
    {
        $redirects = $config['redirects'];

        $container->getDefinition(CanonicalRedirectSubscriber::class)
            ->setArgument('$enabled', (bool) $redirects['canonical_enabled'])
            ->setArgument('$redirectStatus', (int) $redirects['canonical_status']);

        $style = CanonicalStyle::tryFrom((string) $redirects['root_canonical_style']) ?? CanonicalStyle::WithoutPrefix;

        $container->getDefinition(RootRedirectSubscriber::class)
            ->setArgument('$enabled', (bool) $redirects['root_enabled'])
            ->setArgument('$homeCanonicalStyle', $style)
            ->setArgument('$homePath', (string) $redirects['root_home_path'])
            ->setArgument('$redirectStatus', (int) $redirects['root_status']);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return list<string>
     */
    private function resolveAccessRoles(array $config): array
    {
        if ((bool) $config['security']['allow_unauthenticated']) {
            return [];
        }

        $roles = [];
        foreach ($config['security']['access_roles'] as $role) {
            if (is_string($role) && $role !== '') {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configurePanel(ContainerBuilder $container, array $config): void
    {
        if (!$config['panel']['enabled']) {
            $container->removeDefinition(RoutingPanelController::class);
            $container->removeDefinition(PanelAccessGuard::class);

            return;
        }

        $accessRoles = $this->resolveAccessRoles($config);

        $guard = $container->getDefinition(PanelAccessGuard::class)
            ->setArgument('$accessRoles', $accessRoles);
        if ($container->hasDefinition('security.authorization_checker') || $container->hasAlias('security.authorization_checker')) {
            $guard->setArgument('$authorizationChecker', new Reference('security.authorization_checker'));
        } else {
            $guard->setArgument('$authorizationChecker', null);
        }

        $container->getDefinition(RoutingPanelController::class)
            ->setArgument('$pathPrefix', $config['panel']['path_prefix'])
            ->setArgument('$allowControllerOverride', (bool) $config['panel']['allow_controller_override'])
            ->setArgument('$roleGateDisabled', $accessRoles === [])
            ->setArgument('$listPageSize', (int) $config['panel']['list_page_size'])
            ->setPublic(true)
            ->addTag('controller.service_arguments');
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configureWebUi(ContainerBuilder $container, array $config): void
    {
        if (!$config['panel']['enabled'] || !$container->hasDefinition(RoutingKitTwigExtension::class)) {
            if ($container->hasDefinition(RoutingKitTwigExtension::class)) {
                $container->removeDefinition(RoutingKitTwigExtension::class);
            }

            return;
        }

        $webUi = $config['web_ui'];
        $container->getDefinition(RoutingKitTwigExtension::class)
            ->setArgument('$layoutTemplate', $webUi['layout_template'])
            ->setArgument('$cssFramework', $webUi['css_framework'])
            ->setArgument('$iconSet', $webUi['icon_set']);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configureImportExport(ContainerBuilder $container, array $config): void
    {
        if (!$config['panel']['enabled']) {
            $container->removeDefinition(RoutePathImportExport::class);

            return;
        }

        $key = $config['panel']['export_signing_key'] ?? null;
        if (is_string($key) && $key !== '') {
            $container->getDefinition(RoutePathImportExport::class)
                ->setArgument('$signingKey', $key);
        } else {
            $container->getDefinition(RoutePathImportExport::class)
                ->setArgument('$signingKey', '%kernel.secret%');
        }
    }

    private function configureCacheInvalidator(ContainerBuilder $container): void
    {
        $container->getDefinition(RouteCacheInvalidator::class)
            ->setArgument('$router', new Reference('router'))
            ->setArgument('$cacheDir', '%kernel.cache_dir%');
    }

    private function configureAudit(ContainerBuilder $container): void
    {
        $def = $container->getDefinition(RoutePathAuditSubscriber::class);
        if ($container->hasDefinition('logger') || $container->hasAlias('logger')) {
            $def->setArgument('$logger', new Reference('logger'));
        } else {
            $def->setArgument('$logger', null);
        }
        if ($container->hasDefinition('security.token_storage') || $container->hasAlias('security.token_storage')) {
            $def->setArgument('$tokenStorage', new Reference('security.token_storage'));
        } else {
            $def->setArgument('$tokenStorage', null);
        }
    }
}
