<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Security;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function is_object;

/**
 * Optional in-bundle role gate for the CRUD panel (apps should still firewall the prefix).
 *
 * Delegates to {@see RoutingKitAccessCheckerInterface}. Empty access_roles is fail-closed
 * via the checker; only allow_unauthenticated skips the in-bundle check (REQ-UI-002).
 */
final class PanelAccessGuard
{
    public function __construct(
        private readonly RoutingKitAccessCheckerInterface $accessChecker,
        private readonly ?TokenStorageInterface $tokenStorage = null,
        private readonly bool $allowUnauthenticated = false,
        private readonly bool $roleGateDisabled = false,
    ) {
    }

    public function assertGranted(): void
    {
        if ($this->allowUnauthenticated || $this->roleGateDisabled) {
            return;
        }

        $user = $this->tokenStorage?->getToken()?->getUser();
        if (!is_object($user) || !$this->accessChecker->canAccess($user)) {
            throw new AccessDeniedHttpException('Access denied to RoutingKit panel.');
        }
    }

    /**
     * Identifier of the authenticated panel user (audit trail), or null when anonymous / no security.
     */
    public function userIdentifier(): ?string
    {
        $user = $this->tokenStorage?->getToken()?->getUser();

        return $user instanceof UserInterface ? $user->getUserIdentifier() : null;
    }

    public function deniedResponse(): Response
    {
        return new Response('Access denied.', Response::HTTP_FORBIDDEN);
    }
}
