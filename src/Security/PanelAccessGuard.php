<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Security;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use function sprintf;

/**
 * Optional in-bundle role gate for the CRUD panel (apps should still firewall the prefix).
 */
final class PanelAccessGuard
{
    public function __construct(
        private readonly ?AuthorizationCheckerInterface $authorizationChecker = null,
        private readonly ?string $requiredRole = 'ROLE_ADMIN',
    ) {
    }

    public function assertGranted(): void
    {
        if ($this->requiredRole === null || $this->requiredRole === '') {
            return;
        }

        if (!$this->authorizationChecker instanceof AuthorizationCheckerInterface) {
            throw new AccessDeniedHttpException('RoutingKit panel requires AuthorizationCheckerInterface when panel.role is set. Install symfony/security-bundle and grant the role, or set panel.role: null.');
        }

        if (!$this->authorizationChecker->isGranted($this->requiredRole)) {
            throw new AccessDeniedHttpException(sprintf('Access denied. Required role: %s.', $this->requiredRole));
        }
    }

    public function deniedResponse(): Response
    {
        return new Response('Access denied.', Response::HTTP_FORBIDDEN);
    }
}
