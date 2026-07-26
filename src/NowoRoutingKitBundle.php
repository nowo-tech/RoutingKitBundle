<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle;

use Nowo\RoutingKitBundle\DependencyInjection\Compiler\SeoKitBridgePass;
use Nowo\RoutingKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\RoutingKitBundle\DependencyInjection\RoutingKitExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class NowoRoutingKitBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new TwigPathsPass());
        $container->addCompilerPass(new SeoKitBridgePass());
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if ($this->extension === null) {
            $this->extension = new RoutingKitExtension();
        }

        return $this->extension instanceof ExtensionInterface ? $this->extension : null;
    }
}
