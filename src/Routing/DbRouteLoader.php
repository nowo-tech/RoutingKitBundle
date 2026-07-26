<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Routing;

use Nowo\RoutingKitBundle\Discovery\RoutableControllerDiscovery;
use Nowo\RoutingKitBundle\Locale\LocaleProviderInterface;
use Nowo\RoutingKitBundle\Model\CanonicalStyle;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Storage\RoutePathStorageInterface;
use RuntimeException;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

use function implode;
use function is_string;
use function sprintf;
use function strlen;

/**
 * Loads DB/filesystem path definitions as Symfony routes (overwrites same names when imported last).
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
        $localePattern = implode('|', $this->locales->getLocales());

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

                $requirements = $this->buildRequirements($routeName);
                $defaults     = [
                    '_controller' => $controller,
                    '_locale'     => $locale,
                ];

                $prefixed = $this->pathResolver->prefixedPath($definition);
                $collection->add(
                    $this->routeKey($routeName, $locale, true),
                    new Route(
                        path: $this->toRoutePath($prefixed, $locale),
                        defaults: $defaults,
                        requirements: $requirements + ['_locale' => $localePattern],
                    ),
                );

                // Unprefixed only meaningful for default locale (or when canonical is without_prefix)
                if ($this->registerUnprefixedDefault && $locale === $defaultLocale) {
                    $unprefixed = $this->pathResolver->unprefixedPath($definition);
                    $collection->add(
                        $routeName,
                        new Route(
                            path: $unprefixed === '/' ? '/' : $unprefixed,
                            defaults: $defaults,
                            requirements: $requirements,
                        ),
                    );
                } elseif ($definition->canonicalStyle === CanonicalStyle::WithoutPrefix && $locale === $defaultLocale) {
                    $collection->add(
                        $routeName,
                        new Route(
                            path: $this->pathResolver->unprefixedPath($definition),
                            defaults: $defaults,
                            requirements: $requirements,
                        ),
                    );
                }

                // Also expose primary name for non-default when only prefixed exists
                if ($locale !== $defaultLocale && !$collection->get($routeName)) {
                    // Keep first-seen default; for non-default locales use dotted name only
                }
            }

            // Ensure $routeName points at default-locale canonical when we have a default row
            if (isset($localeMap[$defaultLocale]) || $this->pathResolver->resolveDefinition($routeName, $defaultLocale)) {
                $def = $this->pathResolver->resolveDefinition($routeName, $defaultLocale);
                if ($def !== null) {
                    $ctrl = $def->controller ?? $controller;
                    if ($ctrl !== null) {
                        $requirements = $this->buildRequirements($routeName);
                        $canonical    = $this->pathResolver->canonicalPath($def);
                        $collection->add($routeName, new Route(
                            path: $canonical,
                            defaults: [
                                '_controller' => $ctrl,
                                '_locale'     => $defaultLocale,
                            ],
                            requirements: $requirements,
                        ));
                    }
                }
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

    private function routeKey(string $routeName, string $locale, bool $prefixed): string
    {
        return $prefixed ? sprintf('%s.%s', $routeName, $locale) : $routeName;
    }

    /**
     * Prefixed public path like /es/about → /{_locale}/about for the Route object.
     */
    private function toRoutePath(string $prefixedPublicPath, string $locale): string
    {
        $prefix = '/' . $locale;
        if ($prefixedPublicPath === $prefix || $prefixedPublicPath === $prefix . '/') {
            return '/{_locale}/';
        }

        if (str_starts_with($prefixedPublicPath, $prefix . '/')) {
            return '/{_locale}/' . ltrim(substr($prefixedPublicPath, strlen($prefix)), '/');
        }

        return '/{_locale}' . $prefixedPublicPath;
    }
}
