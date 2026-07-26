<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Event;

use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Symfony\Contracts\EventDispatcher\Event;

final class RoutePathsChangedEvent extends Event
{
    public function __construct(
        public readonly RoutePathDefinition $definition,
        public readonly bool $deleted = false,
    ) {
    }
}
