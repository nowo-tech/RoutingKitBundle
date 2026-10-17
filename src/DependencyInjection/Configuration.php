<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\DependencyInjection;

use InvalidArgumentException;
use Nowo\RoutingKitBundle\Panel\PanelChrome;
use Nowo\RoutingKitBundle\Panel\PanelView;
use Nowo\RoutingKitBundle\Redirect\ProtectedPaths;
use Nowo\RoutingKitBundle\Routing\SafePublicPath;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function array_diff;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_values;
use function implode;
use function is_array;
use function is_string;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strlen;

final class Configuration implements ConfigurationInterface
{
    public const ALIAS = 'nowo_routing_kit';

    /** Default panel URL prefix (web_ui.path_prefix). */
    public const DEFAULT_PATH_PREFIX = '/_routing';

    /** 1.x default of web_ui.layout_template (removed template); mapped to null. */
    public const LEGACY_LAYOUT_TEMPLATE = '@NowoRoutingKitBundle/panel/layout.html.twig';

    private const DEPRECATION_PACKAGE = 'nowo-tech/routing-kit-bundle';

    private const DEPRECATION_VERSION = '1.9';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->info('Nowo Routing Kit Bundle configuration.')
            ->beforeNormalization()
                ->always(static function (mixed $v): mixed {
                    if (!is_array($v)) {
                        return $v; // @codeCoverageIgnore
                    }

                    $panel    = is_array($v['panel'] ?? null) ? $v['panel'] : [];
                    $security = is_array($v['security'] ?? null) ? $v['security'] : [];

                    // BC: panel.role → security.access_roles when access_roles not set (REQ-UI-002 / REQ-BC-001).
                    if (array_key_exists('role', $panel) && !array_key_exists('access_roles', $security)) {
                        $role = $panel['role'];
                        if ($role === null || $role === '') {
                            $security['access_roles'] = [];
                        } elseif (is_string($role)) {
                            $security['access_roles'] = [$role];
                        } elseif (is_array($role)) {
                            $security['access_roles'] = array_values(array_filter($role, static fn (mixed $r): bool => is_string($r) && $r !== ''));
                        }
                        $v['security'] = $security;
                    }

                    // REQ-UI-003 canonical keys: panel.enabled / panel.path_prefix → web_ui.* (the canonical key wins).
                    $webUi = is_array($v['web_ui'] ?? null) ? $v['web_ui'] : [];
                    foreach (['enabled', 'path_prefix'] as $key) {
                        if (array_key_exists($key, $panel) && !array_key_exists($key, $webUi)) {
                            $webUi[$key] = $panel[$key];
                        }
                    }
                    // The 1.x default layout (a standalone HTML page) is gone: null = UiKit layout.
                    if (($webUi['layout_template'] ?? null) === self::LEGACY_LAYOUT_TEMPLATE) {
                        $webUi['layout_template'] = null;
                    }
                    if ($webUi !== []) {
                        $v['web_ui'] = $webUi;
                    }

                    return $v;
                })
            ->end()
            ->children()
                ->booleanNode('enabled')
                    ->defaultTrue()
                ->end()
                ->scalarNode('default_locale')
                    ->defaultValue('en')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('locales')
                    ->scalarPrototype()->end()
                    ->defaultValue(['en'])
                ->end()
                ->scalarNode('locale_provider')
                    ->info('Service id implementing LocaleProviderInterface. null = ConfigurableLocaleProvider from YAML.')
                    ->defaultNull()
                ->end()
                ->arrayNode('storage')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('paths_file')
                            ->defaultValue('%kernel.project_dir%/var/routing_kit/paths.json')
                        ->end()
                        ->scalarNode('path_storage')
                            ->info('Service id implementing RoutePathStorageInterface.')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('discovery')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('scan_dirs')
                            ->scalarPrototype()->end()
                            ->defaultValue(['%kernel.project_dir%/src/Controller'])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('panel')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Deprecated alias of web_ui.enabled.')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_routing_kit.web_ui.enabled" instead.')
                        ->end()
                        ->append($this->pathPrefixNode('path_prefix')
                            ->info('Deprecated alias of web_ui.path_prefix.')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_routing_kit.web_ui.path_prefix" instead.'))
                        ->variableNode('role')
                            ->info('Deprecated alias of security.access_roles (scalar, list or null).')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_routing_kit.security.access_roles" instead.')
                        ->end()
                        ->booleanNode('allow_controller_override')
                            ->info('When false (default), panel cannot set a free-form _controller; discovery controller is used.')
                            ->defaultFalse()
                        ->end()
                        ->integerNode('max_definitions')
                            ->info('Hard cap on stored path rows (REQ-PERF-001).')
                            ->min(1)
                            ->defaultValue(500)
                        ->end()
                        ->integerNode('list_page_size')
                            ->info('Page size for the panel index list (REQ-PERF-001).')
                            ->min(1)
                            ->max(200)
                            ->defaultValue(50)
                        ->end()
                        ->booleanNode('reject_conflicts')
                            ->defaultTrue()
                        ->end()
                        ->scalarNode('export_signing_key')
                            ->info('HMAC key for signed export/import. null = kernel.secret. When set, must be at least 32 characters.')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(static function (mixed $v): bool {
                                    return is_string($v) && $v !== '' && strlen($v) < 32;
                                })
                                ->thenInvalid('panel.export_signing_key must be at least 32 characters (or null to use kernel.secret).')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('web_ui')
                    ->info('REQ-UI-003 admin panel UI: pages extend the UiKit scaffolds; theme them once in nowo_ui_kit.theme.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Register the admin panel (paths, redirects, import / export).')
                            ->defaultTrue()
                        ->end()
                        ->append($this->pathPrefixNode('path_prefix')
                            ->info('URL prefix of the admin panel.')
                            ->defaultValue(self::DEFAULT_PATH_PREFIX))
                        ->scalarNode('layout_template')
                            ->info('Layout the panel scaffolds extend instead of nowo_ui_kit.layouts.admin (null = UiKit layout). It must render the UiKit canonical blocks (nowo_ui_content…); a 1.x layout that renders "body" is bridged (deprecated).')
                            ->defaultNull()
                        ->end()
                        ->arrayNode('templates')
                            ->info('Per-page template override (page => template): ' . implode(', ', array_keys(PanelView::TEMPLATES)) . '. Prefer a thin {% extends \'@!NowoRoutingKitBundle/…\' %} that overrides a data block.')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('page')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => array_diff(array_keys($v), array_keys(PanelView::TEMPLATES)) !== [])
                                ->then(static function (array $v): array {
                                    throw new InvalidArgumentException(sprintf('Unknown nowo_routing_kit.web_ui.templates page(s): %s. Allowed: %s.', implode(', ', array_diff(array_keys($v), array_keys(PanelView::TEMPLATES))), implode(', ', array_keys(PanelView::TEMPLATES))));
                                })
                            ->end()
                        ->end()
                        ->enumNode('css_framework')
                            ->info('Deprecated: set nowo_ui_kit.css_framework (seeded from this key when the host does not set it).')
                            ->values(['bootstrap', 'bootstrap4', 'bootstrap5', 'tailwind', 'foundation', 'custom', 'tabler', 'none'])
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, set "nowo_ui_kit.css_framework" instead.')
                        ->end()
                        ->enumNode('icon_set')
                            ->info('Deprecated: set nowo_ui_kit.icon_set (seeded from this key when the host does not set it).')
                            ->values(['bootstrap-icons', 'tabler-icons', 'ux_icon', 'svg_inline', 'none'])
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, set "nowo_ui_kit.icon_set" instead.')
                        ->end()
                        ->arrayNode('classes')
                            ->info('Deprecated 1.x class map, bridged into nowo_ui_kit.theme.bundles.NowoRoutingKitBundle.classes (see docs/UPGRADING.md). Keys: ' . implode(', ', PanelChrome::CLASS_KEYS) . '.')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_ui_kit.theme.bundles.NowoRoutingKitBundle.classes" (UiKit theme slots) instead.')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->scalarPrototype()->end()
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => PanelChrome::unknownKeys($v, PanelChrome::CLASS_KEYS) !== [])
                                ->then(static function (array $v): array {
                                    throw new InvalidArgumentException(sprintf('Unknown nowo_routing_kit.web_ui.classes key(s): %s. Allowed: %s.', implode(', ', PanelChrome::unknownKeys($v, PanelChrome::CLASS_KEYS)), implode(', ', PanelChrome::CLASS_KEYS)));
                                })
                            ->end()
                        ->end()
                        ->arrayNode('partials')
                            ->info('Deprecated 1.x partial map: nav and pagination are bridged into nowo_ui_kit.theme.bundles.NowoRoutingKitBundle.partials (tabs, pagination); action_inner and modal_shell are ignored.')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_ui_kit.theme.bundles.NowoRoutingKitBundle.partials" (UiKit partial names) instead.')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->scalarPrototype()->end()
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => PanelChrome::unknownKeys($v, PanelChrome::PARTIAL_KEYS) !== [])
                                ->then(static function (array $v): array {
                                    throw new InvalidArgumentException(sprintf('Unknown nowo_routing_kit.web_ui.partials key(s): %s. Allowed: %s.', implode(', ', PanelChrome::unknownKeys($v, PanelChrome::PARTIAL_KEYS)), implode(', ', PanelChrome::PARTIAL_KEYS)));
                                })
                            ->end()
                        ->end()
                        ->booleanNode('ui_kit_assets')
                            ->info('Deprecated: UiKit assets come from the layout (nowo_ui_kit.layouts.admin). Only read by the 1.x layout bridge.')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, the layout (nowo_ui_kit.layouts.admin) loads the UiKit assets.')
                        ->end()
                        ->enumNode('import_display')
                            ->info('Signed JSON import: a card under the paths table (inline, default) or a UiKit modal opened from the page actions.')
                            ->values(['inline', 'modal'])
                            ->defaultValue('inline')
                        ->end()
                        ->booleanNode('show_row_count')
                            ->info('Show the "N row(s)" caption of the tables.')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('primary_action_last')
                            ->info('Render the primary "New" action after the secondary page actions.')
                            ->defaultFalse()
                        ->end()
                        ->booleanNode('main_landmark')
                            ->info('Deprecated, no effect: the layout (UiKit or host) owns the <main> landmark.')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated and has no effect: the layout owns the <main> landmark.')
                        ->end()
                        ->arrayNode('delete_confirm_attr')
                            ->info('Deprecated, no effect: deletes use the UiKit confirm dialog (CSP-safe).')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated and has no effect: deletes use the UiKit confirm dialog.')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('security')
                    ->info('REQ-UI-002 private panel access (firewall the path_prefix in the host app as well).')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('access_roles')
                            ->info('User must be granted at least one role. Empty list is fail-closed (deny via access checker). Use allow_unauthenticated for demos only.')
                            ->scalarPrototype()->end()
                            ->defaultValue(['ROLE_ADMIN'])
                        ->end()
                        ->scalarNode('access_checker')
                            ->info('Optional custom service id implementing RoutingKitAccessCheckerInterface. null = ConfigurableRoutingKitAccessChecker.')
                            ->defaultNull()
                        ->end()
                        ->booleanNode('allow_unauthenticated')
                            ->info('DEV/DEMO only: skip in-bundle role check. Production MUST keep false.')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('redirects')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('canonical_enabled')->defaultTrue()->end()
                        ->integerNode('canonical_status')->defaultValue(301)->min(301)->max(308)->end()
                        ->scalarNode('canonical_cache_pool')
                            ->info('Cache pool (Symfony Contracts CacheInterface) for the resolved canonical/alias path table, keyed by route-table version. null = storage read once per request.')
                            ->defaultValue('cache.app')
                        ->end()
                        ->booleanNode('root_enabled')->defaultFalse()->end()
                        ->enumNode('root_canonical_style')
                            ->values(['without_prefix', 'with_prefix'])
                            ->defaultValue('without_prefix')
                        ->end()
                        ->scalarNode('root_home_path')
                            ->defaultValue('/')
                            ->validate()
                                ->ifTrue(static function (mixed $v): bool {
                                    return !is_string($v) || !SafePublicPath::isSafeStoredPath($v);
                                })
                                ->thenInvalid('redirects.root_home_path must be a safe absolute public path (same rules as stored paths).')
                            ->end()
                        ->end()
                        ->integerNode('root_status')->defaultValue(302)->min(301)->max(308)->end()
                    ->end()
                ->end()
                ->append($this->urlRedirectsNode())
                ->booleanNode('auto_invalidate_cache')
                    ->defaultTrue()
                ->end()
                ->booleanNode('register_unprefixed_default')
                    ->defaultTrue()
                ->end()
                ->booleanNode('seo_kit_bridge')
                    ->info('When true and SeoKitBundle is installed, decorate SeoPathBuilderInterface with RoutingKit paths.')
                    ->defaultTrue()
                ->end()
            ->end();

        return $treeBuilder;
    }

    private function pathPrefixNode(string $name): ScalarNodeDefinition
    {
        /** @var ScalarNodeDefinition $node */
        $node = (new TreeBuilder($name, 'scalar'))->getRootNode();
        $node
            ->cannotBeEmpty()
            ->validate()
                ->ifTrue(static function (mixed $v): bool {
                    if (!is_string($v)) {
                        return true; // @codeCoverageIgnore
                    }

                    return str_contains($v, '//')
                        || str_contains($v, '\\')
                        || str_contains($v, '://')
                        || preg_match('#^/[A-Za-z0-9/_-]+$#', $v) !== 1;
                })
                ->thenInvalid('path_prefix must look like /_routing (letters, digits, /, _, - only; no schemes or //).')
            ->end();

        return $node;
    }

    /**
     * Operator URL redirect manager (REQ-REDIR-*). Opt-in: adds a kernel.request listener and a
     * panel section under {web_ui.path_prefix}/redirects.
     */
    private function urlRedirectsNode(): ArrayNodeDefinition
    {
        /** @var ArrayNodeDefinition $node */
        $node = (new TreeBuilder('url_redirects'))->getRootNode();

        $isPathList = static function (mixed $v): bool {
            if (!is_array($v)) {
                return true; // @codeCoverageIgnore
            }
            foreach ($v as $item) {
                if (!is_string($item) || !str_starts_with($item, '/') || str_contains($item, '\\')) {
                    return true;
                }
            }

            return false;
        };

        $node
            ->info('Operator-managed redirects (old public path → new path or URL), applied before routing.')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')
                    ->info('Opt-in. Registers the request listener (priority 33) and the panel section.')
                    ->defaultFalse()
                ->end()
                ->scalarNode('storage')
                    ->info('Service id implementing UrlRedirectStorageInterface (e.g. a Doctrine repository). null = JSON file.')
                    ->defaultNull()
                ->end()
                ->scalarNode('file')
                    ->info('JSON file used by the default FilesystemUrlRedirectStorage.')
                    ->defaultValue('%kernel.project_dir%/var/routing_kit/redirects.json')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('cache_pool')
                    ->info('Cache pool (Symfony Contracts CacheInterface) for the enabled-redirects map. null = storage read once per request.')
                    ->defaultValue('cache.app')
                ->end()
                ->integerNode('cache_ttl')
                    ->info('Seconds the cached map lives; bounds staleness after writes made outside the panel.')
                    ->min(0)
                    ->defaultValue(3600)
                ->end()
                ->arrayNode('protected_prefixes')
                    ->info('Whole-segment prefixes never redirected (also behind a /{locale} segment). Replaces the default list; web_ui.path_prefix is always added.')
                    ->scalarPrototype()->end()
                    ->defaultValue(ProtectedPaths::DEFAULT_PREFIXES)
                    ->validate()
                        ->ifTrue($isPathList)
                        ->thenInvalid('url_redirects.protected_prefixes entries must start with "/".')
                    ->end()
                ->end()
                ->arrayNode('protected_starts')
                    ->info('Raw string prefixes never redirected (e.g. "/_" covers /_profiler). Replaces the default list.')
                    ->scalarPrototype()->end()
                    ->defaultValue(ProtectedPaths::DEFAULT_STARTS)
                    ->validate()
                        ->ifTrue($isPathList)
                        ->thenInvalid('url_redirects.protected_starts entries must start with "/".')
                    ->end()
                ->end()
                ->arrayNode('hits')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Count hits / last hit per redirect.')
                            ->defaultTrue()
                        ->end()
                        ->scalarNode('message_bus')
                            ->info('Messenger bus service id (e.g. messenger.default_bus) to count hits asynchronously. null = synchronous write.')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }
}
