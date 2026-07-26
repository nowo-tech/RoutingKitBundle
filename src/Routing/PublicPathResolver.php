<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Routing;

use Nowo\RoutingKitBundle\Locale\LocaleProviderInterface;
use Nowo\RoutingKitBundle\Model\AliasMode;
use Nowo\RoutingKitBundle\Model\CanonicalStyle;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Model\TrailingSlashStyle;
use Nowo\RoutingKitBundle\Storage\RoutePathStorageInterface;

use function rtrim;

/**
 * Resolves public paths (prefixed / unprefixed) and which one is canonical.
 */
final class PublicPathResolver
{
    public function __construct(
        private readonly RoutePathStorageInterface $storage,
        private readonly LocaleProviderInterface $locales,
    ) {
    }

    public function resolveDefinition(string $routeName, string $locale): ?RoutePathDefinition
    {
        $direct = $this->storage->find($routeName, $locale);
        if ($direct !== null && $direct->enabled) {
            return $direct;
        }

        $defaultLocale = $this->locales->getDefaultLocale();
        if ($locale === $defaultLocale) {
            return null;
        }

        $fallback = $this->storage->find($routeName, $defaultLocale);
        if ($fallback !== null && $fallback->enabled) {
            // Re-bind fallback path under requested locale (same path segment)
            return new RoutePathDefinition(
                routeName: $fallback->routeName,
                locale: $locale,
                path: $fallback->path,
                canonicalStyle: CanonicalStyle::WithPrefix,
                trailingSlash: $fallback->trailingSlash,
                aliasMode: $fallback->aliasMode,
                enabled: true,
                controller: $fallback->controller,
                id: $fallback->id,
            );
        }

        return null;
    }

    public function prefixedPath(RoutePathDefinition $definition): string
    {
        return $this->applyTrailingSlash(
            '/' . $definition->locale . $this->normalizePath($definition->path),
            $definition->trailingSlash,
        );
    }

    public function unprefixedPath(RoutePathDefinition $definition): string
    {
        return $this->applyTrailingSlash(
            $this->normalizePath($definition->path),
            $definition->trailingSlash,
        );
    }

    public function canonicalPath(RoutePathDefinition $definition): string
    {
        return match ($definition->canonicalStyle) {
            CanonicalStyle::WithoutPrefix => $this->unprefixedPath($definition),
            CanonicalStyle::WithPrefix    => $this->prefixedPath($definition),
        };
    }

    public function aliasPath(RoutePathDefinition $definition): string
    {
        return match ($definition->canonicalStyle) {
            CanonicalStyle::WithoutPrefix => $this->prefixedPath($definition),
            CanonicalStyle::WithPrefix    => $this->unprefixedPath($definition),
        };
    }

    public function shouldRedirectAlias(RoutePathDefinition $definition): bool
    {
        return $definition->aliasMode === AliasMode::Redirect;
    }

    private function normalizePath(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }

        return '/' . ltrim($path, '/');
    }

    private function applyTrailingSlash(string $path, TrailingSlashStyle $style): string
    {
        if ($path === '/') {
            return '/';
        }

        return match ($style) {
            TrailingSlashStyle::Keep, TrailingSlashStyle::RedirectToKeep => rtrim($path, '/') . '/',
            TrailingSlashStyle::Omit, TrailingSlashStyle::RedirectToOmit => rtrim($path, '/'),
        };
    }
}
