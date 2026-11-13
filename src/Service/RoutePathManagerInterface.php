<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Service;

use Nowo\RoutingKitBundle\Event\RoutePathDefinitionChangedEvent;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use RuntimeException;

/**
 * Application service for route path rows (since 1.17.0, REQ-ARCH-001 rule 1): the panel, the import and your own code
 * change rows through it, with the same validation, limits, conflict rules, events and route cache invalidation.
 * The default is {@see RoutePathManager}; alias this interface to decorate or replace it.
 *
 * Every write dispatches {@see RoutePathDefinitionChangedEvent} (and the deprecated RoutePathsChangedEvent) after the
 * storage write returned.
 */
interface RoutePathManagerInterface
{
    /**
     * @return list<RoutePathDefinition>
     */
    public function all(): array;

    public function get(string $id): ?RoutePathDefinition;

    /**
     * Conflict messages the definition would raise against the stored rows (empty when none).
     *
     * @return list<string>
     */
    public function previewConflicts(RoutePathDefinition $definition): array;

    /**
     * Validates and stores one row.
     *
     * @throws RuntimeException when the row is invalid, conflicts or exceeds the configured limit
     */
    public function save(RoutePathDefinition $definition, bool $invalidateCache = true): RoutePathDefinition;

    /**
     * Imports rows through the same rules as save(); with $replaceAll the stored rows not in the import are removed.
     *
     * @param list<RoutePathDefinition> $definitions
     *
     * @throws RuntimeException when a row is invalid, conflicts or the limit is exceeded (nothing is written)
     */
    public function import(array $definitions, bool $replaceAll = false): int;

    public function delete(string $id, bool $invalidateCache = true): void;

    public function clearCache(): void;
}
