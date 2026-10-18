<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\DependencyInjection;

use Nowo\RoutingKitBundle\DependencyInjection\RoutingKitExtension;
use Nowo\RoutingKitBundle\Locale\ConfigurableLocaleProvider;
use Nowo\RoutingKitBundle\Locale\ConfiguredLocales;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * nowo_routing_kit.locales as a literal list, a parameter or an env placeholder (direct load() callers).
 */
final class LocalesConfigTest extends TestCase
{
    public function testParameterStringIsResolvedInLoad(): void
    {
        $container = $this->container();
        $container->setParameter('app.locales', ['es', 'en', 'es']);
        (new RoutingKitExtension())->load([['locales' => '%app.locales%']], $container);

        self::assertSame(['es', 'en'], $container->getParameter('nowo.routing_kit.locales'));
        self::assertSame(['es', 'en'], $container->getDefinition(ConfigurableLocaleProvider::class)->getArgument('$locales'));
    }

    public function testEnvPlaceholderIsResolvedAtRuntimeByAFactory(): void
    {
        $container = $this->container();
        (new RoutingKitExtension())->load([['locales' => '%env(json:APP_LOCALES)%']], $container);

        self::assertSame('%env(json:APP_LOCALES)%', $container->resolveEnvPlaceholders($container->getParameter('nowo.routing_kit.locales')));
        $argument = $container->getDefinition(ConfigurableLocaleProvider::class)->getArgument('$locales');
        self::assertInstanceOf(Definition::class, $argument);
        self::assertSame([ConfiguredLocales::class, 'normalize'], $argument->getFactory());
        self::assertSame(['%nowo.routing_kit.locales%'], $argument->getArguments());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideInvalidLocales(): iterable
    {
        yield 'nested list' => [['en', ['es']]];
        yield 'integer' => [42];
    }

    #[DataProvider('provideInvalidLocales')]
    public function testInvalidLocalesAreRejected(mixed $locales): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid nowo_routing_kit.locales');

        (new RoutingKitExtension())->load([['locales' => $locales]], $this->container());
    }

    public function testPrependIgnoresParameterStringsOutsideTheWebUiKeys(): void
    {
        $container = $this->container();
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'nowo_ui_kit';
            }
        });
        $container->registerExtension($extension = new RoutingKitExtension());
        $container->loadFromExtension('nowo_routing_kit', [
            'locales'        => '%kernel.enabled_locales%',
            'default_locale' => '%kernel.default_locale%',
            'web_ui'         => ['css_framework' => 'tailwind'],
        ]);
        $container->loadFromExtension('nowo_routing_kit', ['enabled' => '%app.routing_kit_enabled%']);

        $extension->prepend($container);

        self::assertSame([['css_framework' => 'tailwind']], $container->getExtensionConfig('nowo_ui_kit'));
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/tmp/project');
        $container->setParameter('kernel.cache_dir', '/tmp/cache');
        $container->setParameter('kernel.secret', 'test-secret');
        $container->setParameter('kernel.bundles', ['SecurityBundle' => 'Symfony\\Bundle\\SecurityBundle\\SecurityBundle']);

        return $container;
    }
}
