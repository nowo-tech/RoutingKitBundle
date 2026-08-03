<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Security;

use Nowo\RoutingKitBundle\Security\AllowAllRoutingKitAccessChecker;
use Nowo\RoutingKitBundle\Security\ConfigurableRoutingKitAccessChecker;
use Nowo\RoutingKitBundle\Security\PanelAccessGuard;
use Nowo\RoutingKitBundle\Security\RoutingKitAccessCheckerInterface;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class PanelAccessGuardTest extends TestCase
{
    public function testAllowsWhenRoleGateDisabled(): void
    {
        $checker = $this->createMock(RoutingKitAccessCheckerInterface::class);
        $checker->expects(self::never())->method('canAccess');

        $guard = new PanelAccessGuard($checker, null, false, true);
        $guard->assertGranted();
        self::assertSame(403, $guard->deniedResponse()->getStatusCode());
    }

    public function testAllowsWhenUnauthenticatedAllowed(): void
    {
        $checker = $this->createMock(RoutingKitAccessCheckerInterface::class);
        $checker->expects(self::never())->method('canAccess');

        (new PanelAccessGuard($checker, null, true, false))->assertGranted();
    }

    public function testDeniesWhenNoUser(): void
    {
        $checker = $this->createMock(RoutingKitAccessCheckerInterface::class);
        $guard   = new PanelAccessGuard($checker, null, false, false);

        $this->expectException(AccessDeniedHttpException::class);
        $guard->assertGranted();
    }

    public function testChecksAccessCheckerWithUser(): void
    {
        $user  = $this->createMock(UserInterface::class);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $storage = $this->createMock(TokenStorageInterface::class);
        $storage->method('getToken')->willReturn($token);

        $checker = $this->createMock(RoutingKitAccessCheckerInterface::class);
        $checker->expects(self::once())->method('canAccess')->with($user)->willReturn(true);

        (new PanelAccessGuard($checker, $storage, false, false))->assertGranted();
    }

    public function testConfigurableAllowsEmptyRoles(): void
    {
        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->expects(self::never())->method('isGranted');

        self::assertTrue((new ConfigurableRoutingKitAccessChecker($auth, []))->canAccess(new stdClass()));
    }

    public function testConfigurableDeniesWhenNoRoleGranted(): void
    {
        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);

        self::assertFalse(
            (new ConfigurableRoutingKitAccessChecker($auth, ['ROLE_ADMIN']))->canAccess(new stdClass()),
        );
    }

    public function testConfigurableAllowsWhenRoleGranted(): void
    {
        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->expects(self::once())->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);

        self::assertTrue(
            (new ConfigurableRoutingKitAccessChecker($auth, ['ROLE_ADMIN']))->canAccess(new stdClass()),
        );
    }

    public function testAllowAllAlwaysAllows(): void
    {
        self::assertTrue((new AllowAllRoutingKitAccessChecker())->canAccess(new stdClass()));
    }
}
