<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\DependencyInjection;

use LogicException;
use Nowo\RoutingKitBundle\Controller\RoutingPanelController;
use Nowo\RoutingKitBundle\Controller\UrlRedirectPanelController;
use Nowo\RoutingKitBundle\Discovery\RoutableControllerDiscovery;
use Nowo\RoutingKitBundle\EventSubscriber\CanonicalRedirectSubscriber;
use Nowo\RoutingKitBundle\EventSubscriber\DefaultLocalePrefixRedirectSubscriber;
use Nowo\RoutingKitBundle\EventSubscriber\RootRedirectSubscriber;
use Nowo\RoutingKitBundle\EventSubscriber\RoutePathAuditSubscriber;
use Nowo\RoutingKitBundle\EventSubscriber\RouteTableFreshnessSubscriber;
use Nowo\RoutingKitBundle\EventSubscriber\UrlRedirectSubscriber;
use Nowo\RoutingKitBundle\Locale\ConfigurableLocaleProvider;
use Nowo\RoutingKitBundle\Locale\ConfiguredLocales;
use Nowo\RoutingKitBundle\Locale\LocaleProviderInterface;
use Nowo\RoutingKitBundle\Model\CanonicalStyle;
use Nowo\RoutingKitBundle\NowoRoutingKitBundle;
use Nowo\RoutingKitBundle\Panel\PanelChrome;
use Nowo\RoutingKitBundle\Panel\PanelView;
use Nowo\RoutingKitBundle\Redirect\LivePathCheckerInterface;
use Nowo\RoutingKitBundle\Redirect\LivePublicPaths;
use Nowo\RoutingKitBundle\Redirect\Message\RecordRedirectHitHandler;
use Nowo\RoutingKitBundle\Redirect\ProtectedPaths;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectMatcher;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectValidator;
use Nowo\RoutingKitBundle\Routing\DbRouteLoader;
use Nowo\RoutingKitBundle\Routing\DefaultLocaleRouter;
use Nowo\RoutingKitBundle\Routing\RouteCacheInvalidator;
use Nowo\RoutingKitBundle\Security\AllowAllRoutingKitAccessChecker;
use Nowo\RoutingKitBundle\Security\ConfigurableRoutingKitAccessChecker;
use Nowo\RoutingKitBundle\Security\PanelAccessGuard;
use Nowo\RoutingKitBundle\Security\RoutingKitAccessCheckerInterface;
use Nowo\RoutingKitBundle\Service\RoutePathImportExport;
use Nowo\RoutingKitBundle\Service\RoutePathManager;
use Nowo\RoutingKitBundle\Storage\FilesystemRoutePathStorage;
use Nowo\RoutingKitBundle\Storage\FilesystemUrlRedirectStorage;
use Nowo\RoutingKitBundle\Storage\RoutePathStorageInterface;
use Nowo\RoutingKitBundle\Storage\UrlRedirectStorageInterface;
use Nowo\RoutingKitBundle\Twig\RoutingKitTwigExtension;
use Nowo\RoutingKitBundle\Validation\RoutePathValidator;
use Psr\Clock\ClockInterface;
use Symfony\Component\Asset\Package;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

use function array_key_exists;
use function array_unique;
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
     * When UiKit is installed: seed nowo_ui_kit.css_framework / icon_set from the deprecated web_ui keys when the host
     * set them (and not the nowo_ui_kit ones), and bridge the deprecated web_ui.classes / web_ui.partials maps into
     * nowo_ui_kit.theme.bundles.NowoRoutingKitBundle (REQ-UI-003). Prepended: the host's own UiKit config wins.
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

        $config   = $this->processConfiguration(new Configuration(), $this->webUiConfigs($container));
        $webUi    = is_array($config['web_ui'] ?? null) ? $config['web_ui'] : [];
        $defaults = [];

        $fw = $webUi['css_framework'] ?? null;
        if (!$hostHasCssFramework && is_string($fw)) {
            $defaults['css_framework'] = $fw === 'bootstrap' ? 'bootstrap5' : $fw;
        }
        $iconSet = $webUi['icon_set'] ?? null;
        if (!$hostHasIconSet && is_string($iconSet)) {
            $defaults['icon_set'] = $iconSet;
        }

        $bridge = PanelChrome::themeBridge(
            is_array($webUi['classes'] ?? null) ? $webUi['classes'] : [],
            is_array($webUi['partials'] ?? null) ? $webUi['partials'] : [],
        );
        if ($bridge !== []) {
            $defaults['theme'] = ['bundles' => [PanelView::BUNDLE => $bridge]];
        }

        if ($defaults !== []) {
            $container->prependExtensionConfig('nowo_ui_kit', $defaults);
        }
    }

    /**
     * The raw host configs reduced to the web_ui / panel keys prepend() needs (panel.* is a deprecated alias of
     * web_ui.*). prepend() sees the raw config, where any other key may still be a "%parameter%" or env placeholder
     * (e.g. locales: '%kernel.enabled_locales%', defined by FrameworkExtension::load() after prepend), so the rest of
     * the tree is not processed here.
     *
     * @return list<array<string, mixed>>
     */
    private function webUiConfigs(ContainerBuilder $container): array
    {
        $configs = [];
        foreach ($container->getExtensionConfig(Configuration::ALIAS) as $cfg) {
            $subset = [];
            foreach (['web_ui', 'panel'] as $key) {
                if (is_array($cfg[$key] ?? null)) {
                    $subset[$key] = $cfg[$key];
                }
            }
            if ($subset !== []) {
                $configs[] = $subset;
            }
        }

        return $configs;
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
                        'hidden' => [
                            'attr'     => ['class' => ''],
                            'row_attr' => [],
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
        // locales may be a parameter (%app.locales%, %kernel.enabled_locales%): the kernel resolves it before load(),
        // a direct load() caller may not. An env var (%env(json:APP_LOCALES)%) stays a placeholder until runtime.
        $locales  = $container->getParameterBag()->resolveValue($config['locales']);
        $usedEnvs = [];
        $container->resolveEnvPlaceholders($locales, null, $usedEnvs);
        $config['locales']         = $usedEnvs !== [] ? $locales : ConfiguredLocales::normalize($locales);
        $config['runtime_locales'] = $usedEnvs !== [];
        $container->setParameter('nowo.routing_kit.locales', $config['locales']);
        $container->setParameter('nowo.routing_kit.storage.paths_file', $config['storage']['paths_file']);
        $container->setParameter('nowo.routing_kit.discovery.scan_dirs', $config['discovery']['scan_dirs']);
        $accessRoles = array_values($config['security']['access_roles']);
        $firstRole   = $accessRoles[0] ?? null;
        $container->setParameter('nowo.routing_kit.panel.enabled', (bool) $config['web_ui']['enabled']);
        $container->setParameter('nowo.routing_kit.panel.path_prefix', $config['web_ui']['path_prefix']);
        $container->setParameter('nowo.routing_kit.web_ui.path_prefix', $config['web_ui']['path_prefix']);
        // BC: first access role (panel.role is a deprecated alias of security.access_roles).
        $container->setParameter('nowo.routing_kit.panel.role', is_string($firstRole) && $firstRole !== '' ? $firstRole : null);
        $container->setParameter('nowo.routing_kit.panel.list_page_size', (int) $config['panel']['list_page_size']);
        $container->setParameter('nowo.routing_kit.web_ui', $config['web_ui']);
        $container->setParameter('nowo.routing_kit.web_ui.enabled', (bool) $config['web_ui']['enabled']);
        $container->setParameter('nowo.routing_kit.web_ui.layout_template', $config['web_ui']['layout_template']);
        $container->setParameter('nowo.routing_kit.web_ui.css_framework', $config['web_ui']['css_framework'] ?? 'custom');
        $container->setParameter('nowo.routing_kit.web_ui.icon_set', $config['web_ui']['icon_set'] ?? 'none');
        $container->setParameter('nowo.routing_kit.security', $config['security']);
        $container->setParameter('nowo.routing_kit.security.access_roles', array_values($config['security']['access_roles']));
        $container->setParameter('nowo.routing_kit.security.access_checker', $config['security']['access_checker']);
        $container->setParameter('nowo.routing_kit.security.allow_unauthenticated', (bool) $config['security']['allow_unauthenticated']);
        $container->setParameter('nowo.routing_kit.auto_invalidate_cache', $config['auto_invalidate_cache']);
        $container->setParameter('nowo.routing_kit.register_unprefixed_default', $config['register_unprefixed_default']);
        $container->setParameter('nowo.routing_kit.redirects', $config['redirects']);
        $container->setParameter('nowo.routing_kit.seo_kit_bridge', $config['seo_kit_bridge']);
        $container->setParameter('nowo.routing_kit.url_redirects', $config['url_redirects']);
        $container->setParameter('nowo.routing_kit.url_redirects.enabled', (bool) $config['url_redirects']['enabled']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        if (!$config['enabled']) {
            $this->disableBundle($container);

            return;
        }

        if (
            $config['web_ui']['enabled']
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
        $this->configureUrlRedirects($container, $config);
        $this->configureWebUi($container, $config);
        $this->configureImportExport($container, $config);
        $this->configureCacheInvalidator($container);
        $this->configureAudit($container);
        $this->configureDefaultLocaleUnprefixed($container, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configureDefaultLocaleUnprefixed(ContainerBuilder $container, array $config): void
    {
        $options = $config['default_locale_unprefixed'];
        $container->setParameter('nowo.routing_kit.default_locale_unprefixed', (bool) $options['enabled']);
        if (!$options['enabled']) {
            return;
        }

        $container->setDefinition(DefaultLocaleRouter::class, (new Definition(DefaultLocaleRouter::class))
            ->setDecoratedService('router.default')
            ->setArgument('$inner', new Reference(DefaultLocaleRouter::class . '.inner'))
            ->setArgument('$locales', new Reference(LocaleProviderInterface::class))
            ->setArgument('$twinSuffix', (string) $options['twin_suffix'])
            ->setPublic(false));

        if ($options['redirect_prefixed']) {
            $container->setDefinition(DefaultLocalePrefixRedirectSubscriber::class, (new Definition(DefaultLocalePrefixRedirectSubscriber::class))
                ->setArgument('$urls', new Reference('router'))
                ->setArgument('$locales', new Reference(LocaleProviderInterface::class))
                ->setArgument('$status', (int) $options['redirect_status'])
                // Conditional service (redirect_prefixed): autoconfigure applies the EventSubscriberInterface tag.
                ->setAutoconfigured(true)
                ->setPublic(false));
        }
    }

    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }

    private function disableBundle(ContainerBuilder $container): void
    {
        $container->removeDefinition(RoutingPanelController::class);
        $container->removeDefinition(PanelView::class);
        $container->removeDefinition(DbRouteLoader::class);
        $container->removeDefinition(CanonicalRedirectSubscriber::class);
        $container->removeDefinition(RootRedirectSubscriber::class);
        $container->removeDefinition(RoutePathAuditSubscriber::class);
        $container->removeDefinition(RouteTableFreshnessSubscriber::class);
        $container->removeDefinition(PanelAccessGuard::class);
        $container->removeDefinition(RoutePathImportExport::class);
        $container->removeDefinition(UrlRedirectPanelController::class);
        $this->removeUrlRedirectServices($container);
        if ($container->hasDefinition(RoutingKitTwigExtension::class)) {
            $container->removeDefinition(RoutingKitTwigExtension::class);
        }
    }

    private function removeUrlRedirectServices(ContainerBuilder $container): void
    {
        foreach ([
            FilesystemUrlRedirectStorage::class,
            UrlRedirectMatcher::class,
            UrlRedirectSubscriber::class,
            UrlRedirectValidator::class,
            ProtectedPaths::class,
            LivePublicPaths::class,
            RecordRedirectHitHandler::class,
        ] as $id) {
            $container->removeDefinition($id);
        }
        // Resource loading aliases singly-implemented interfaces: drop it with its target.
        $container->removeAlias(UrlRedirectStorageInterface::class);
    }

    /**
     * Operator URL redirects (REQ-REDIR-*). Disabled: services removed, panel actions answer 404.
     *
     * @param array<string, mixed> $config
     */
    private function configureUrlRedirects(ContainerBuilder $container, array $config): void
    {
        /** @var array{enabled: bool, storage: ?string, file: string, cache_pool: ?string, cache_ttl: int, protected_prefixes: list<string>, protected_starts: list<string>, hits: array{enabled: bool, message_bus: ?string}} $redirects */
        $redirects = $config['url_redirects'];

        if (!$redirects['enabled']) {
            $this->removeUrlRedirectServices($container);

            return;
        }

        $container->getDefinition(FilesystemUrlRedirectStorage::class)
            ->setArgument('$filePath', $redirects['file']);
        $storage = $redirects['storage'];
        $container->setAlias(
            UrlRedirectStorageInterface::class,
            is_string($storage) && $storage !== '' ? $storage : FilesystemUrlRedirectStorage::class,
        )->setPublic(false);

        $prefixes   = $redirects['protected_prefixes'];
        $prefixes[] = (string) $config['web_ui']['path_prefix'];
        $container->getDefinition(ProtectedPaths::class)
            ->setArgument('$locales', new Reference(LocaleProviderInterface::class))
            ->setArgument('$prefixes', array_values(array_unique($prefixes)))
            ->setArgument('$starts', array_values($redirects['protected_starts']));

        $container->registerForAutoconfiguration(LivePathCheckerInterface::class)
            ->addTag(LivePathCheckerInterface::TAG);
        $container->getDefinition(LivePublicPaths::class)
            ->setArgument('$router', new Reference('router'))
            ->setArgument('$checkers', new TaggedIteratorArgument(LivePathCheckerInterface::TAG));

        $cachePool = $redirects['cache_pool'];
        $bus       = $redirects['hits']['message_bus'];
        $container->getDefinition(UrlRedirectMatcher::class)
            ->setArgument('$storage', new Reference(UrlRedirectStorageInterface::class))
            ->setArgument('$cache', is_string($cachePool) && $cachePool !== '' ? new Reference($cachePool, ContainerInterface::NULL_ON_INVALID_REFERENCE) : null)
            ->setArgument('$logger', new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$bus', is_string($bus) && $bus !== '' ? new Reference($bus) : null)
            ->setArgument('$cacheTtl', $redirects['cache_ttl'])
            ->setArgument('$trackHits', $redirects['hits']['enabled'])
            ->setArgument('$clock', new Reference(ClockInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE));

        // The handler carries #[AsMessageHandler] (autoconfigured by services.yaml); it only exists with a hit bus.
        if (!is_string($bus) || $bus === '' || !$redirects['hits']['enabled']) {
            $container->removeDefinition(RecordRedirectHitHandler::class);
        }

        if ($container->hasDefinition(UrlRedirectPanelController::class)) {
            $container->getDefinition(UrlRedirectPanelController::class)
                ->setArgument('$listPageSize', (int) $config['panel']['list_page_size'])
                ->setArgument('$logger', new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE))
                ->setArgument('$clock', new Reference(ClockInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE));
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configureLocales(ContainerBuilder $container, array $config): void
    {
        // Env var locales are only known at runtime: normalized by a factory when the provider is created.
        $locales = $config['runtime_locales']
            ? (new Definition('array'))->setFactory([ConfiguredLocales::class, 'normalize'])->setArguments(['%nowo.routing_kit.locales%'])
            : $config['locales'];
        $container->getDefinition(ConfigurableLocaleProvider::class)
            ->setArgument('$defaultLocale', $config['default_locale'])
            ->setArgument('$locales', $locales);

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
        $redirects     = $config['redirects'];
        $canonicalPool = $redirects['canonical_cache_pool'] ?? null;

        $container->getDefinition(CanonicalRedirectSubscriber::class)
            ->setArgument('$enabled', (bool) $redirects['canonical_enabled'])
            ->setArgument('$redirectStatus', (int) $redirects['canonical_status'])
            ->setArgument('$cache', is_string($canonicalPool) && $canonicalPool !== '' ? new Reference($canonicalPool, ContainerInterface::NULL_ON_INVALID_REFERENCE) : null)
            ->setArgument('$routeTable', new Reference(RouteCacheInvalidator::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$logger', new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE));

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
        if (!$config['web_ui']['enabled']) {
            $container->removeDefinition(PanelView::class);
            $container->removeDefinition(RoutingPanelController::class);
            $container->removeDefinition(UrlRedirectPanelController::class);
            $container->removeDefinition(PanelAccessGuard::class);

            return;
        }

        $this->registerAccessChecker($container, $config['security']);

        // Empty access_roles must NOT disable the gate — fail-closed via the access checker.
        $container->getDefinition(PanelAccessGuard::class)
            ->setArgument('$allowUnauthenticated', (bool) $config['security']['allow_unauthenticated'])
            ->setArgument('$roleGateDisabled', false);

        $webUi = $config['web_ui'];
        $container->getDefinition(PanelView::class)
            ->setArgument('$pathPrefix', $webUi['path_prefix'])
            ->setArgument('$layoutTemplate', $webUi['layout_template'])
            ->setArgument('$templates', $webUi['templates'])
            ->setArgument('$importDisplay', (string) $webUi['import_display'])
            ->setArgument('$showRowCount', (bool) $webUi['show_row_count'])
            ->setArgument('$primaryActionLast', (bool) $webUi['primary_action_last'])
            ->setArgument('$urlRedirectsEnabled', (bool) $config['url_redirects']['enabled'])
            // Shows panel.role_gate_warning: the in-bundle role check is skipped (AllowAll checker).
            ->setArgument('$roleGateDisabled', (bool) $config['security']['allow_unauthenticated']);

        $container->getDefinition(RoutingPanelController::class)
            ->setArgument('$pathPrefix', $webUi['path_prefix'])
            ->setArgument('$allowControllerOverride', (bool) $config['panel']['allow_controller_override'])
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
        if (!$config['web_ui']['enabled'] || !$container->hasDefinition(RoutingKitTwigExtension::class)) {
            if ($container->hasDefinition(RoutingKitTwigExtension::class)) {
                $container->removeDefinition(RoutingKitTwigExtension::class);
            }

            return;
        }

        $webUi = $config['web_ui'];
        $container->getDefinition(RoutingKitTwigExtension::class)
            ->setArgument('$layoutTemplate', $webUi['layout_template'])
            ->setArgument('$cssFramework', $webUi['css_framework'] ?? 'custom')
            ->setArgument('$iconSet', $webUi['icon_set'] ?? 'none')
            ->setArgument('$urlRedirectsEnabled', (bool) $config['url_redirects']['enabled'])
            ->setArgument('$classes', PanelChrome::clean($webUi['classes']))
            ->setArgument('$partials', PanelChrome::clean($webUi['partials']))
            ->setArgument('$uiKitAssets', (bool) ($webUi['ui_kit_assets'] ?? true))
            ->setArgument('$importDisplay', (string) $webUi['import_display'])
            ->setArgument('$showRowCount', (bool) $webUi['show_row_count'])
            ->setArgument('$primaryActionLast', (bool) $webUi['primary_action_last'])
            ->setArgument('$deleteConfirmAttr', PanelChrome::clean($webUi['delete_confirm_attr']))
            ->setArgument('$mainLandmark', (bool) ($webUi['main_landmark'] ?? true));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configureImportExport(ContainerBuilder $container, array $config): void
    {
        if (!$config['web_ui']['enabled']) {
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
            ->setArgument('$cacheDir', '%kernel.cache_dir%')
            ->setArgument('$routeLoader', new Reference(DbRouteLoader::class, ContainerInterface::NULL_ON_INVALID_REFERENCE));
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
