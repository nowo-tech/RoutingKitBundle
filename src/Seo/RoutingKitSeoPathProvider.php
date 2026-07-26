<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Seo;

use Nowo\RoutingKitBundle\Locale\LocaleProviderInterface;
use Nowo\RoutingKitBundle\Routing\PublicPathResolver;

/**
 * Bridge API for SeoKit (and apps): resolve the public canonical path for a route + locale.
 *
 * Wire this into SeoKit by decorating SeoPathBuilder::pagePath when both bundles are installed.
 */
final class RoutingKitSeoPathProvider
{
    public function __construct(
        private readonly PublicPathResolver $paths,
        private readonly LocaleProviderInterface $locales,
    ) {
    }

    public function pagePath(string $route, string $locale, ?string $fallbackPath = null): ?string
    {
        $definition = $this->paths->resolveDefinition($route, $locale);
        if ($definition === null) {
            return $fallbackPath;
        }

        return $this->paths->canonicalPath($definition);
    }

    public function defaultLocale(): string
    {
        return $this->locales->getDefaultLocale();
    }

    /**
     * @return list<string>
     */
    public function locales(): array
    {
        return $this->locales->getLocales();
    }
}
