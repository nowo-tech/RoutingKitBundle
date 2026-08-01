<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\RoutingKitBundle\DependencyInjection\Compiler\PanelAccessGuardPass;
use Nowo\RoutingKitBundle\Security\PanelAccessGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class PanelAccessGuardPassTest extends TestCase
{
    public function testWiresAuthorizationCheckerWhenSecurityBundleIsPresent(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(PanelAccessGuard::class, new Definition(PanelAccessGuard::class));
        $container->setDefinition('security.authorization_checker', new Definition());

        (new PanelAccessGuardPass())->process($container);

        $guard = $container->getDefinition(PanelAccessGuard::class);
        self::assertEquals(new Reference('security.authorization_checker'), $guard->getArgument('$authorizationChecker'));
    }

    public function testNoopsWhenGuardDefinitionMissing(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('security.authorization_checker', new Definition());

        (new PanelAccessGuardPass())->process($container);

        self::assertFalse($container->hasDefinition(PanelAccessGuard::class));
    }

    public function testNoopsWhenAuthorizationCheckerMissing(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(PanelAccessGuard::class, new Definition(PanelAccessGuard::class));

        (new PanelAccessGuardPass())->process($container);

        $guard = $container->getDefinition(PanelAccessGuard::class);
        self::assertArrayNotHasKey('$authorizationChecker', $guard->getArguments());
    }
}
