<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Security;

/**
 * Access control for the RoutingKit CRUD panel (REQ-UI-002).
 */
interface RoutingKitAccessCheckerInterface
{
    public function canAccess(object $user): bool;
}
