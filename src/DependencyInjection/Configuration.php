<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function is_string;

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
                            ->info('Required security role for the panel. null disables the in-bundle check (firewall still recommended).')
                            ->defaultValue('ROLE_ADMIN')
                        ->end()
                        ->booleanNode('allow_controller_override')
                            ->info('When false (default), panel cannot set a free-form _controller; discovery controller is used.')
                            ->defaultFalse()
                        ->end()
                        ->integerNode('max_definitions')
                            ->min(1)
                            ->defaultValue(500)
                        ->end()
                        ->booleanNode('reject_conflicts')
                            ->defaultTrue()
                        ->end()
                        ->scalarNode('export_signing_key')
                            ->info('HMAC key for signed export/import. null = kernel.secret.')
                            ->defaultNull()
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
                        ->scalarNode('root_home_path')->defaultValue('/')->end()
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
