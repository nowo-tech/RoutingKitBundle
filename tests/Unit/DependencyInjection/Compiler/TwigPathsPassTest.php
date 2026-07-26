<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\RoutingKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

use function dirname;

final class TwigPathsPassTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/rk_twig_' . uniqid('', true);
        mkdir($this->projectDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $overrideDir = $this->projectDir . '/templates/bundles/NowoRoutingKitBundle';
        if (is_dir($overrideDir)) {
            rmdir($overrideDir);
            rmdir(dirname($overrideDir));
            rmdir(dirname($overrideDir, 2));
        }

        if (is_dir($this->projectDir)) {
            rmdir($this->projectDir);
        }
    }

    public function testProcessDoesNothingWithoutTwigLoader(): void
    {
        $container = new ContainerBuilder();

        (new TwigPathsPass())->process($container);

        self::assertFalse($container->hasDefinition('twig.loader.native'));
        self::assertFalse($container->hasDefinition('twig.loader.native_filesystem'));
    }

    public function testProcessPrependsOverrideAndAddsBundlePath(): void
    {
        $overrideDir = $this->projectDir . '/templates/bundles/NowoRoutingKitBundle';
        mkdir($overrideDir, 0777, true);

        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $this->projectDir);
        $container->setDefinition('twig.loader.native_filesystem', new Definition());
        $container->setAlias('twig.loader.native', 'twig.loader.native_filesystem');

        (new TwigPathsPass())->process($container);

        $calls = $container->getDefinition('twig.loader.native_filesystem')->getMethodCalls();

        self::assertCount(2, $calls);
        self::assertSame('prependPath', $calls[0][0]);
        self::assertSame([$overrideDir, 'NowoRoutingKitBundle'], $calls[0][1]);
        self::assertSame('addPath', $calls[1][0]);
        self::assertSame('NowoRoutingKitBundle', $calls[1][1][1]);
    }

    public function testProcessUsesNativeLoaderDefinitionWithoutOverride(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $this->projectDir);
        $container->setDefinition('twig.loader.native', new Definition());

        (new TwigPathsPass())->process($container);

        $calls = $container->getDefinition('twig.loader.native')->getMethodCalls();

        self::assertCount(1, $calls);
        self::assertSame('addPath', $calls[0][0]);
    }

    public function testProcessFallsBackToNativeFilesystemDefinition(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('twig.loader.native_filesystem', new Definition());

        (new TwigPathsPass())->process($container);

        $calls = $container->getDefinition('twig.loader.native_filesystem')->getMethodCalls();

        self::assertCount(1, $calls);
        self::assertSame('addPath', $calls[0][0]);
    }

    public function testProcessResolvesAliasChains(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('twig.loader.real', new Definition());
        $container->setAlias('twig.loader.native', 'twig.loader.first');
        $container->setAlias('twig.loader.first', 'twig.loader.real');

        (new TwigPathsPass())->process($container);

        self::assertCount(1, $container->getDefinition('twig.loader.real')->getMethodCalls());
    }
}
