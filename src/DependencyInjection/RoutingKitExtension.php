<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\DependencyInjection;

use Nowo\RoutingKitBundle\Controller\RoutingPanelController;
use Nowo\RoutingKitBundle\Discovery\RoutableControllerDiscovery;
use Nowo\RoutingKitBundle\EventSubscriber\CanonicalRedirectSubscriber;
use Nowo\RoutingKitBundle\EventSubscriber\RootRedirectSubscriber;
use Nowo\RoutingKitBundle\Locale\ConfigurableLocaleProvider;
use Nowo\RoutingKitBundle\Locale\LocaleProviderInterface;
use Nowo\RoutingKitBundle\Model\CanonicalStyle;
use Nowo\RoutingKitBundle\Routing\DbRouteLoader;
use Nowo\RoutingKitBundle\Routing\RouteCacheInvalidator;
use Nowo\RoutingKitBundle\Service\RoutePathManager;
use Nowo\RoutingKitBundle\Storage\FilesystemRoutePathStorage;
use Nowo\RoutingKitBundle\Storage\RoutePathStorageInterface;
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
        $container->setParameter('nowo.routing_kit.auto_invalidate_cache', $config['auto_invalidate_cache']);
        $container->setParameter('nowo.routing_kit.register_unprefixed_default', $config['register_unprefixed_default']);
        $container->setParameter('nowo.routing_kit.redirects', $config['redirects']);
        $container->setParameter('nowo.routing_kit.seo_kit_bridge', $config['seo_kit_bridge']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $this->configureLocales($container, $config);
        $this->configureStorage($container, $config);
        $this->configureDiscovery($container, $config);
        $this->configureManager($container, $config);
        $this->configureLoader($container, $config);
        $this->configureSubscribers($container, $config);
        $this->configurePanel($container, $config);
        $this->configureCacheInvalidator($container);
    }

    public function getAlias(): string
    {
        return Configuration::ALIAS;
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

    /**
     * @param array<string, mixed> $config
     */
    private function configureManager(ContainerBuilder $container, array $config): void
    {
        $container->getDefinition(RoutePathManager::class)
            ->setArgument('$autoInvalidateCache', (bool) $config['auto_invalidate_cache']);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configureLoader(ContainerBuilder $container, array $config): void
    {
        $container->getDefinition(DbRouteLoader::class)
            ->setArgument('$registerUnprefixedDefault', (bool) $config['register_unprefixed_default'])
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
     */
    private function configurePanel(ContainerBuilder $container, array $config): void
    {
        if (!$config['panel']['enabled']) {
            $container->removeDefinition(RoutingPanelController::class);

            return;
        }

        $container->getDefinition(RoutingPanelController::class)
            ->setArgument('$pathPrefix', $config['panel']['path_prefix'])
            ->setPublic(true)
            ->addTag('controller.service_arguments');
    }

    private function configureCacheInvalidator(ContainerBuilder $container): void
    {
        $container->getDefinition(RouteCacheInvalidator::class)
            ->setArgument('$router', new Reference('router'))
            ->setArgument('$cacheDir', '%kernel.cache_dir%');
    }
}
