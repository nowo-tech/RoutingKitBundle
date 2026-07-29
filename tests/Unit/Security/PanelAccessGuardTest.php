<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Security;

use Nowo\RoutingKitBundle\Security\PanelAccessGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class PanelAccessGuardTest extends TestCase
{
    public function testAllowsWhenRoleIsNull(): void
    {
        $guard = new PanelAccessGuard(null, []);
        $guard->assertGranted();
        self::assertSame(403, $guard->deniedResponse()->getStatusCode());
    }

    public function testDeniesWhenRoleSetWithoutAuthorizationChecker(): void
    {
        $guard = new PanelAccessGuard(null, ['ROLE_ADMIN']);
        $this->expectException(AccessDeniedHttpException::class);
        $guard->assertGranted();
    }

    public function testChecksAuthorizationChecker(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->expects(self::once())->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);

        (new PanelAccessGuard($checker, ['ROLE_ADMIN']))->assertGranted();
    }
}
