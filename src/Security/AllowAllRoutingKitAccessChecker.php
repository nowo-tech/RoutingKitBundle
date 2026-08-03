<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Security;

/**
 * Permissive checker used only when security.allow_unauthenticated is true (demo/dev).
 */
final class AllowAllRoutingKitAccessChecker implements RoutingKitAccessCheckerInterface
{
    public function canAccess(object $user): bool
    {
        return true;
    }
}
