<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Routing;

use Nowo\RoutingKitBundle\Model\RoutePathDefinition;

/**
 * Resolves the public paths of a route path row (prefixed / unprefixed) and which one is canonical (since 1.16.0,
 * REQ-ARCH-001 rule 1). The default is {@see PublicPathResolver}; the static helper
 * {@see PublicPathResolver::indexDefinitions()} builds the optional $index.
 */
interface PublicPathResolverInterface
{
    /**
     * The enabled row for a route and locale, falling back to the default locale's row; null when none.
     *
     * @param array<string, array<string, RoutePathDefinition>>|null $index pre-loaded rows; null queries the storage
     */
    public function resolveDefinition(string $routeName, string $locale, ?array $index = null): ?RoutePathDefinition;

    public function prefixedPath(RoutePathDefinition $definition): string;

    public function unprefixedPath(RoutePathDefinition $definition): string;

    public function canonicalPath(RoutePathDefinition $definition): string;

    public function aliasPath(RoutePathDefinition $definition): string;

    public function shouldRedirectAlias(RoutePathDefinition $definition): bool;

    /**
     * Public paths this stored row can occupy once loaded (incl. default-locale fallbacks).
     *
     * @return list<string>
     */
    public function occupiedPublicPaths(RoutePathDefinition $definition): array;
}
