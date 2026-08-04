<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\DependencyInjection;

use LogicException;
use Nowo\RoutingKitBundle\Controller\RoutingPanelController;
use Nowo\RoutingKitBundle\Discovery\RoutableControllerDiscovery;
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
use Nowo\RoutingKitBundle\Security\ConfigurableRoutingKitAccessChecker;
use Nowo\RoutingKitBundle\Security\PanelAccessGuard;
use Nowo\RoutingKitBundle\Security\RoutingKitAccessCheckerInterface;
use Nowo\RoutingKitBundle\Service\RoutePathImportExport;
use Nowo\RoutingKitBundle\Service\RoutePathManager;
use Nowo\RoutingKitBundle\Storage\FilesystemRoutePathStorage;
use Nowo\RoutingKitBundle\Storage\RoutePathStorageInterface;
use Nowo\RoutingKitBundle\Twig\RoutingKitTwigExtension;
use Nowo\RoutingKitBundle\Validation\RoutePathValidator;
use Symfony\Component\Asset\Package;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

use function array_key_exists;
use function array_values;
use function is_array;
use function is_string;

final class RoutingKitExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Registers the named asset package 'nowo_routing_kit' for any remaining panel assets,
     * and seeds UiKit defaults from web_ui when the host has not set nowo_ui_kit (REQ-UI-001-kit).
     * Admin CSS is served from the UiKit package: asset('css/nowo-ui.css', 'nowo_ui_kit').
     */
    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('framework') && class_exists(Package::class)) {
            $container->prependExtensionConfig('framework', [
                'assets' => [
                    'packages' => [
                        Configuration::ALIAS => [
                            'base_path' => '/bundles/noworoutingkit',
                        ],
                    ],
                ],
            ]);
        }

        $this->prependUiKitDefaults($container);
        $this->prependFormKitDefaults($container);
    }

    /**
     * When UiKit is installed, seed nowo_ui_kit.css_framework / icon_set from web_ui.
     * Does not override keys the host already set under nowo_ui_kit.
     */
    private function prependUiKitDefaults(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('nowo_ui_kit')) {
            return;
        }

        $hostHasCssFramework = false;
        $hostHasIconSet      = false;
        foreach ($container->getExtensionConfig('nowo_ui_kit') as $cfg) {
            if (!is_array($cfg)) {
                continue;
            }
            if (array_key_exists('css_framework', $cfg)) {
                $hostHasCssFramework = true;
            }
            if (array_key_exists('icon_set', $cfg)) {
                $hostHasIconSet = true;
            }
        }

        if ($hostHasCssFramework && $hostHasIconSet) {
            return;
        }

        $config   = $this->processConfiguration(new Configuration(), $container->getExtensionConfig(Configuration::ALIAS));
        $webUi    = is_array($config['web_ui'] ?? null) ? $config['web_ui'] : [];
        $defaults = [];

        if (!$hostHasCssFramework) {
            $fw                        = (string) ($webUi['css_framework'] ?? 'custom');
            $defaults['css_framework'] = $fw === 'bootstrap' ? 'bootstrap5' : $fw;
        }
        if (!$hostHasIconSet) {
            $defaults['icon_set'] = (string) ($webUi['icon_set'] ?? 'none');
        }

        if ($defaults !== []) {
            $container->prependExtensionConfig('nowo_ui_kit', $defaults);
        }
    }

    /**
     * When FormKit is installed, register the {@code routing_kit} profile. Forms select it via {@code #[FormKitConfig]}.
     */
    private function prependFormKitDefaults(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('nowo_form_kit')) {
            return;
        }

        $hostHasCssFramework = false;
        $hostHasProfile      = false;
        foreach ($container->getExtensionConfig('nowo_form_kit') as $cfg) {
            if (!is_array($cfg)) {
                continue;
            }
            if (array_key_exists('css_framework', $cfg)) {
                $hostHasCssFramework = true;
            }
            $profiles = $cfg['profiles'] ?? null;
            if (is_array($profiles) && array_key_exists('routing_kit', $profiles)) {
                $hostHasProfile = true;
            }
        }

        $seed = [];

        if (!$hostHasCssFramework) {
            $seed['css_framework'] = 'bootstrap';
        }

        if (!$hostHasProfile) {
            $seed['profiles'] = [
                'routing_kit' => [
                    'alias'              => 'routing_kit',
                    'translation_domain' => NowoRoutingKitBundle::TRANSLATION_DOMAIN,
                    'auto_placeholder'   => false,
                    'auto_help'          => false,
                    'defaults'           => [
                        'attr'     => ['class' => 'nowo-ui-input form-control'],
                        'row_attr' => ['class' => 'mb-2'],
                    ],
                    'field_types' => [
                        'checkbox' => [
                            'attr'     => ['class' => 'form-check-input'],
                            'row_attr' => ['class' => 'form-check mb-2'],
                        ],
                        'choice' => [
                            'attr' => ['class' => 'form-select'],
                        ],
                        'entity' => [
                            'attr' => ['class' => 'form-select'],
                        ],
                        'file' => [
                            'attr' => ['class' => 'nowo-ui-input form-control'],
                        ],
                        'textarea' => [
                            'attr' => ['class' => 'nowo-ui-input form-control'],
                        ],
                    ],
                ],
            ];
        }

        if ($seed !== []) {
            $container->prependExtensionConfig('nowo_form_kit', $seed);
        }
    }

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
        $container->setParameter('nowo.routing_kit.security.access_checker', $config['security']['access_checker']);
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

        if (
            $config['panel']['enabled']
            && !$config['security']['allow_unauthenticated']
            && !$this->isSecurityBundleAvailable($container)
        ) {
            throw new LogicException('NowoRoutingKitBundle panel requires symfony/security-bundle when security.allow_unauthenticated is false.');
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

        $this->registerAccessChecker($container, $config['security']);

        $accessRoles = $this->resolveAccessRoles($config);

        $container->getDefinition(PanelAccessGuard::class)
            ->setArgument('$allowUnauthenticated', (bool) $config['security']['allow_unauthenticated'])
            ->setArgument('$roleGateDisabled', $accessRoles === []);

        $container->getDefinition(RoutingPanelController::class)
            ->setArgument('$pathPrefix', $config['panel']['path_prefix'])
            ->setArgument('$allowControllerOverride', (bool) $config['panel']['allow_controller_override'])
            ->setArgument('$roleGateDisabled', $accessRoles === [])
            ->setArgument('$listPageSize', (int) $config['panel']['list_page_size'])
            ->setPublic(true)
            ->addTag('controller.service_arguments');
    }

    /** @param array<string, mixed> $security */
    private function registerAccessChecker(ContainerBuilder $container, array $security): void
    {
        if ($security['allow_unauthenticated']) {
            $accessCheckerId = 'nowo.routing_kit.access_checker.allow_all';
            $container->setDefinition($accessCheckerId, new Definition(AllowAllRoutingKitAccessChecker::class));
            $container->setAlias(RoutingKitAccessCheckerInterface::class, $accessCheckerId)->setPublic(false);

            return;
        }

        $accessCheckerId = $security['access_checker'] ?? null;
        if (is_string($accessCheckerId) && $accessCheckerId !== '') {
            $container->setAlias(RoutingKitAccessCheckerInterface::class, $accessCheckerId)->setPublic(false);

            return;
        }

        $accessCheckerId = 'nowo.routing_kit.access_checker.default';
        $container->setDefinition($accessCheckerId, (new Definition(ConfigurableRoutingKitAccessChecker::class))
            ->setAutowired(true)
            ->setArgument('$accessRoles', array_values($security['access_roles'])));
        $container->setAlias(RoutingKitAccessCheckerInterface::class, $accessCheckerId)->setPublic(false);
    }

    /**
     * Prefer kernel.bundles: ContainerBuilder::hasExtension() can be false while SecurityBundle
     * is already registered (e.g. during early Flex cache:clear boots).
     */
    private function isSecurityBundleAvailable(ContainerBuilder $container): bool
    {
        if ($container->hasExtension('security')) {
            return true;
        }

        if (!$container->hasParameter('kernel.bundles')) {
            return false;
        }

        /** @var array<string, class-string> $bundles */
        $bundles = $container->getParameter('kernel.bundles');

        return isset($bundles['SecurityBundle']);
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
