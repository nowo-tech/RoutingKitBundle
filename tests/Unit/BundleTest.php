<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit;

use Nowo\RoutingKitBundle\DependencyInjection\Compiler\SeoKitBridgePass;
use Nowo\RoutingKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\RoutingKitBundle\DependencyInjection\RoutingKitExtension;
use Nowo\RoutingKitBundle\NowoRoutingKitBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class BundleTest extends TestCase
{
    public function testBuildRegistersCompilerPasses(): void
    {
        $bundle    = new NowoRoutingKitBundle();
        $container = new ContainerBuilder();

        $bundle->build($container);

        $passes = $container->getCompilerPassConfig()->getBeforeOptimizationPasses();

        self::assertTrue($this->containsPass($passes, TwigPathsPass::class));
        self::assertTrue($this->containsPass($passes, SeoKitBridgePass::class));
    }

    public function testGetContainerExtensionReturnsMemoizedExtension(): void
    {
        $bundle = new NowoRoutingKitBundle();

        $extension = $bundle->getContainerExtension();

        self::assertInstanceOf(RoutingKitExtension::class, $extension);
        self::assertSame($extension, $bundle->getContainerExtension());
    }

    /**
     * @param array<int, object> $passes
     */
    private function containsPass(array $passes, string $className): bool
    {
        foreach ($passes as $pass) {
            if ($pass instanceof $className) {
                return true;
            }
        }

        return false;
    }
}
