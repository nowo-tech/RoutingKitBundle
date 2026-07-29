<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Security;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use function implode;
use function sprintf;

/**
 * Optional in-bundle role gate for the CRUD panel (apps should still firewall the prefix).
 *
 * Empty {@see $accessRoles} disables the in-bundle check (REQ-UI-002).
 */
final class PanelAccessGuard
{
    /**
     * @param list<string> $accessRoles
     */
    public function __construct(
        private readonly ?AuthorizationCheckerInterface $authorizationChecker = null,
        private readonly array $accessRoles = ['ROLE_ADMIN'],
    ) {
    }

    public function assertGranted(): void
    {
        if ($this->accessRoles === []) {
            return;
        }

        if (!$this->authorizationChecker instanceof AuthorizationCheckerInterface) {
            throw new AccessDeniedHttpException('RoutingKit panel requires AuthorizationCheckerInterface when security.access_roles is non-empty. Install symfony/security-bundle and grant a role, set security.access_roles: [], or use panel.role: null (BC).');
        }

        foreach ($this->accessRoles as $role) {
            if ($this->authorizationChecker->isGranted($role)) {
                return;
            }
        }

        throw new AccessDeniedHttpException(sprintf('Access denied. Required one of: %s.', implode(', ', $this->accessRoles)));
    }

    public function deniedResponse(): Response
    {
        return new Response('Access denied.', Response::HTTP_FORBIDDEN);
    }
}
