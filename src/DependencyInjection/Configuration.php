<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\DependencyInjection;

use Nowo\RoutingKitBundle\Routing\SafePublicPath;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function array_key_exists;
use function array_values;
use function is_array;
use function is_string;
use function preg_match;
use function str_contains;
use function strlen;

final class Configuration implements ConfigurationInterface
{
    public const ALIAS = 'nowo_routing_kit';

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

                    // Mirror first access role back to panel.role for older docs / demos.
                    if (array_key_exists('access_roles', $security) && !array_key_exists('role', $panel)) {
                        $roles         = is_array($security['access_roles']) ? $security['access_roles'] : [];
                        $first         = $roles[0] ?? null;
                        $panel['role'] = is_string($first) && $first !== '' ? $first : null;
                        $v['panel']    = $panel;
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
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('path_prefix')
                            ->defaultValue('/_routing')
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
                                ->thenInvalid('panel.path_prefix must look like /_routing (letters, digits, /, _, - only; no schemes or //).')
                            ->end()
                        ->end()
                        ->variableNode('role')
                            ->info('Deprecated BC alias for security.access_roles (scalar or null). Prefer security.access_roles.')
                            ->defaultValue('ROLE_ADMIN')
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
                    ->info('REQ-UI-001 look-and-feel for the admin panel (layout, CSS stack, icons).')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                        ->end()
                        ->scalarNode('layout_template')
                            ->info('Twig layout that panel pages extend (host apps SHOULD set their project layout).')
                            ->defaultValue('@NowoRoutingKitBundle/panel/layout.html.twig')
                            ->cannotBeEmpty()
                        ->end()
                        ->enumNode('css_framework')
                            ->values(['bootstrap', 'bootstrap4', 'bootstrap5', 'tailwind', 'foundation', 'custom', 'tabler', 'none'])
                            ->defaultValue('custom')
                        ->end()
                        ->enumNode('icon_set')
                            ->values(['bootstrap-icons', 'tabler-icons', 'ux_icon', 'svg_inline', 'none'])
                            ->defaultValue('none')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('security')
                    ->info('REQ-UI-002 private panel access (firewall the path_prefix in the host app as well).')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('access_roles')
                            ->info('User must be granted at least one role. Empty list = no in-bundle role check.')
                            ->scalarPrototype()->end()
                            ->defaultValue(['ROLE_ADMIN'])
                        ->end()
                        ->scalarNode('access_checker')
                            ->info('Optional custom service id; reserved for future checkers. null = built-in role gate.')
                            ->defaultNull()
                        ->end()
                        ->booleanNode('allow_unauthenticated')
                            ->info('DEV/DEMO only: skip in-bundle role check (same effect as empty access_roles). Production MUST keep false.')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('redirects')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('canonical_enabled')->defaultTrue()->end()
                        ->integerNode('canonical_status')->defaultValue(301)->min(301)->max(308)->end()
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
}
