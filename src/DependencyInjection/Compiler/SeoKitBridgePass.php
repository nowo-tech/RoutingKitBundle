<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\DependencyInjection\Compiler;

use Nowo\RoutingKitBundle\Seo\RoutingKitSeoPathBuilderDecorator;
use Nowo\RoutingKitBundle\Seo\RoutingKitSeoPathProvider;
use Nowo\SeoKitBundle\Service\SeoPathBuilderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * When SeoKit is installed and seo_kit_bridge is enabled, decorate SeoPathBuilderInterface.
 */
final class SeoKitBridgePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('nowo.routing_kit.seo_kit_bridge')
            || !$container->getParameter('nowo.routing_kit.seo_kit_bridge')
        ) {
            return;
        }

        if (!interface_exists(SeoPathBuilderInterface::class)) {
            return;
        }

        if (!$container->hasDefinition(SeoPathBuilderInterface::class)
            && !$container->hasAlias(SeoPathBuilderInterface::class)
            && !$container->hasDefinition('Nowo\SeoKitBundle\Service\SeoPathBuilder')
        ) {
            return;
        }

        $innerId = $container->hasDefinition(SeoPathBuilderInterface::class)
            || $container->hasAlias(SeoPathBuilderInterface::class)
            ? SeoPathBuilderInterface::class
            : 'Nowo\SeoKitBundle\Service\SeoPathBuilder';

        $decorator = new Definition(RoutingKitSeoPathBuilderDecorator::class);
        $decorator->setAutowired(true);
        $decorator->setAutoconfigured(true);
        $decorator->setArgument('$inner', new Reference($innerId . '.routing_kit_inner'));
        $decorator->setArgument('$routingKitPaths', new Reference(RoutingKitSeoPathProvider::class));
        $decorator->setDecoratedService($innerId, $innerId . '.routing_kit_inner', 10);
        $decorator->setPublic(false);

        $container->setDefinition(RoutingKitSeoPathBuilderDecorator::class, $decorator);
    }
}
