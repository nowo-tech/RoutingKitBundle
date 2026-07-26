<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Routing;

use Nowo\RoutingKitBundle\Discovery\RoutableControllerDiscovery;
use Nowo\RoutingKitBundle\Locale\LocaleProviderInterface;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Storage\RoutePathStorageInterface;
use RuntimeException;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

use function implode;
use function is_string;
use function preg_quote;
use function sprintf;

/**
 * Loads DB/filesystem path definitions as Symfony routes (overwrites same names when imported last).
 *
 * Registers one route per locale as `{name}.{locale}` with `_canonical_route` set so
 * `UrlGenerator::generate('name', ['_locale' => 'en'])` and Twig `path()` work.
 *
 * Default locale uses the unprefixed path when {@see $registerUnprefixedDefault} is true;
 * other locales use `/{locale}{path}`.
 */
final class DbRouteLoader extends Loader
{
    private bool $loaded = false;

    public function __construct(
        private readonly RoutePathStorageInterface $storage,
        private readonly LocaleProviderInterface $locales,
        private readonly PublicPathResolver $pathResolver,
        private readonly RoutableControllerDiscovery $discovery,
        private readonly bool $registerUnprefixedDefault = true,
        ?string $env = null,
    ) {
        parent::__construct($env);
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->loaded) {
            throw new RuntimeException('Do not add the RoutingKit loader twice.');
        }
        $this->loaded = true;

        $collection    = new RouteCollection();
        $defaultLocale = $this->locales->getDefaultLocale();

        $byRoute = [];
        foreach ($this->storage->all() as $definition) {
            if (!$definition->enabled) {
                continue;
            }
            $byRoute[$definition->routeName][$definition->locale] = $definition;
        }

        foreach ($byRoute as $routeName => $localeMap) {
            /** @var array<string, RoutePathDefinition> $localeMap */
            $discovered = $this->discovery->findByRouteName($routeName);
            $controller = null;
            if (is_string($discovered['controller'] ?? null)) {
                $controller = $discovered['controller'];
            }

            $requirements = $this->buildRequirements($routeName);

            foreach ($this->locales->getLocales() as $locale) {
                $definition = $localeMap[$locale]
                    ?? (isset($localeMap[$defaultLocale])
                        ? $this->pathResolver->resolveDefinition($routeName, $locale)
                        : null);

                if ($definition === null) {
                    continue;
                }

                if ($definition->controller !== null && $definition->controller !== '') {
                    $controller = $definition->controller;
                }
                if ($controller === null) {
                    continue;
                }

                $path = ($locale === $defaultLocale && $this->registerUnprefixedDefault)
                    ? $this->pathResolver->unprefixedPath($definition)
                    : $this->pathResolver->prefixedPath($definition);

                $collection->add(
                    sprintf('%s.%s', $routeName, $locale),
                    new Route(
                        path: $path,
                        defaults: [
                            '_controller'      => $controller,
                            '_locale'          => $locale,
                            '_canonical_route' => $routeName,
                        ],
                        requirements: $requirements + ['_locale' => preg_quote($locale, '')],
                    ),
                );
            }
        }

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === 'nowo_routing_kit';
    }

    /**
     * @return array<string, string>
     */
    private function buildRequirements(string $routeName): array
    {
        $requirements = [];
        foreach ($this->discovery->paramsForRoute($routeName) as $param) {
            if ($param->requirement !== null && $param->requirement !== '') {
                $requirements[$param->name] = $param->requirement;
            } elseif ($param->enum !== null && $param->enum !== []) {
                $requirements[$param->name] = implode('|', $param->enum);
            }
        }

        return $requirements;
    }
}
