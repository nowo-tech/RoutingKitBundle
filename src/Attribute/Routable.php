<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Attribute;

use Attribute;

/**
 * Marks a controller action as offerable in the RoutingKit CRUD panel.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final class Routable
{
    /**
     * @param list<RouteParam> $params
     */
    public function __construct(
        public readonly string $name,
        public readonly array $params = [],
        public readonly ?string $label = null,
    ) {
    }
}
