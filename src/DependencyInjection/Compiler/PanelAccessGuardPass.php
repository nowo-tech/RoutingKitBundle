<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\DependencyInjection\Compiler;

use Nowo\RoutingKitBundle\Security\PanelAccessGuard;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wires {@see PanelAccessGuard} to SecurityBundle after extensions have loaded.
 *
 * Extension-time {@see ContainerBuilder::hasDefinition()} / hasAlias() often miss
 * {@code security.authorization_checker} (registered later by SecurityBundle).
 * Compile-time {@see ContainerBuilder::has()} matches CookieConsentSecurityPass.
 */
final class PanelAccessGuardPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(PanelAccessGuard::class)) {
            return;
        }

        if (!$container->has('security.authorization_checker')) {
            return;
        }

        $container->getDefinition(PanelAccessGuard::class)
            ->setArgument('$authorizationChecker', new Reference('security.authorization_checker'));
    }
}
