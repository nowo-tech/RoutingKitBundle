<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Event;

use Nowo\RoutingKitBundle\Model\RoutePathDefinition;

/**
 * A route path row was saved, imported or deleted through the route path manager (since 1.16.0). Dispatched after the
 * storage write returned, right after the deprecated {@see RoutePathsChangedEvent}, with the same rows and order.
 *
 * Plain PHP (no framework base class, REQ-ARCH-001 rules 3 and 4): ids and scalars, plus the immutable row itself.
 * Key projections by $id.
 */
final readonly class RoutePathDefinitionChangedEvent
{
    public function __construct(
        public string $id,
        public string $routeName,
        public string $locale,
        public string $path,
        public bool $enabled,
        public bool $deleted,
        public RoutePathDefinition $definition,
    ) {
    }

    public static function fromDefinition(RoutePathDefinition $definition, bool $deleted = false): self
    {
        return new self(
            (string) $definition->id,
            $definition->routeName,
            $definition->locale,
            $definition->path,
            $definition->enabled,
            $deleted,
            $definition,
        );
    }
}
