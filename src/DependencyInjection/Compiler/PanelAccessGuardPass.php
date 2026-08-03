<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\DependencyInjection\Compiler;

use Nowo\RoutingKitBundle\Security\PanelAccessGuard;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wires {@see PanelAccessGuard} to SecurityBundle token storage after extensions have loaded.
 *
 * Extension-time {@see ContainerBuilder::hasDefinition()} / hasAlias() often miss
 * {@code security.token_storage} (registered later by SecurityBundle).
 */
final class PanelAccessGuardPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(PanelAccessGuard::class)) {
            return;
        }

        if (!$container->has('security.token_storage')) {
            return;
        }

        $container->getDefinition(PanelAccessGuard::class)
            ->setArgument('$tokenStorage', new Reference('security.token_storage'));
    }
}
