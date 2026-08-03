<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Security;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Default role-based access checker driven by nowo_routing_kit.security.access_roles.
 */
final readonly class ConfigurableRoutingKitAccessChecker implements RoutingKitAccessCheckerInterface
{
    /** @param list<string> $accessRoles */
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private array $accessRoles,
    ) {
    }

    public function canAccess(object $user): bool
    {
        if ($this->accessRoles === []) {
            return true;
        }

        foreach ($this->accessRoles as $role) {
            if ($this->authorizationChecker->isGranted($role)) {
                return true;
            }
        }

        return false;
    }
}
