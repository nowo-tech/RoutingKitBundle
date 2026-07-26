<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Attribute;

use Attribute;

/**
 * Declares a path placeholder and its validation constraints for #[Routable].
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class RouteParam
{
    /**
     * @param list<string>|null $enum Allowed values when set
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $required = true,
        public readonly ?string $requirement = null,
        public readonly ?string $type = null,
        public readonly ?array $enum = null,
        public readonly mixed $default = null,
    ) {
    }
}
