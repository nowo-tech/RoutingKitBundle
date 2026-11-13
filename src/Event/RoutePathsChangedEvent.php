<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Event;

use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * A route path row was saved, imported or deleted through the route path manager.
 *
 * @deprecated since 1.16.0, listen to {@see RoutePathDefinitionChangedEvent} (plain PHP, ids and scalars, dispatched right
 *             after this one with the same rows); removed in 2.0
 */
final class RoutePathsChangedEvent extends Event
{
    public function __construct(
        public readonly RoutePathDefinition $definition,
        public readonly bool $deleted = false,
    ) {
    }
}
