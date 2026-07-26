<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Seo;

use Nowo\SeoKitBundle\Service\SeoPathBuilderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Prefers RoutingKit canonical public paths for pagePath(); delegates the rest to SeoKit.
 */
final class RoutingKitSeoPathBuilderDecorator implements SeoPathBuilderInterface
{
    public function __construct(
        private readonly SeoPathBuilderInterface $inner,
        private readonly RoutingKitSeoPathProvider $routingKitPaths,
    ) {
    }

    public function absoluteUrl(Request $request, string $path): string
    {
        return $this->inner->absoluteUrl($request, $path);
    }

    public function pagePath(string $route, string $locale, ?string $fallbackPath = null): ?string
    {
        $fromKit = $this->routingKitPaths->pagePath($route, $locale);
        if ($fromKit !== null) {
            return $fromKit;
        }

        return $this->inner->pagePath($route, $locale, $fallbackPath);
    }

    public function resolveCanonicalSlug(string $route, string $slug): string
    {
        return $this->inner->resolveCanonicalSlug($route, $slug);
    }

    public function slugPath(string $route, string $locale, string $slug): ?string
    {
        return $this->inner->slugPath($route, $locale, $slug);
    }
}
