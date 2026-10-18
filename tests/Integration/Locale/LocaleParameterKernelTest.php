<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Integration\Locale;

use Nowo\RoutingKitBundle\Locale\LocaleProviderInterface;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Storage\FilesystemRoutePathStorage;
use Nowo\RoutingKitBundle\Tests\Integration\Locale\App\LocaleEnvVarLoader;
use Nowo\RoutingKitBundle\Tests\Integration\Locale\App\LocaleTestKernel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

use function array_keys;
use function sys_get_temp_dir;
use function uniqid;

/**
 * Real kernel: nowo_routing_kit.locales given as %app.locales%, %kernel.enabled_locales% or an env var (the host keeps
 * one locale list) must compile, and the RoutingKit loader must register the stored route once per resolved locale.
 */
final class LocaleParameterKernelTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/rk_locale_kernel_' . uniqid('', true);
        (new FilesystemRoutePathStorage($this->baseDir . '/var/paths.json'))
            ->save(new RoutePathDefinition('rk_locale_page', 'en', '/page'));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->baseDir);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>}>
     */
    public static function provideLocaleConfigs(): iterable
    {
        yield 'app parameter' => [['locales' => '%app.locales%'], ['es', 'en', 'fr']];
        yield 'kernel.enabled_locales' => [['locales' => '%kernel.enabled_locales%'], ['de', 'en']];
        yield 'env var (json)' => [['locales' => '%env(json:' . LocaleEnvVarLoader::JSON . ')%'], ['pt', 'en']];
        yield 'env var (csv)' => [['locales' => '%env(csv:' . LocaleEnvVarLoader::CSV . ')%'], ['it', 'en']];
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string> $expectedLocales
     */
    #[DataProvider('provideLocaleConfigs')]
    public function testLocaleListFromParameterBuildsOneRoutePerLocale(array $config, array $expectedLocales): void
    {
        $kernel = new LocaleTestKernel($this->baseDir, $config);
        $kernel->boot();

        try {
            $container = $kernel->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $container);

            $provider = $container->get(LocaleProviderInterface::class);
            self::assertInstanceOf(LocaleProviderInterface::class, $provider);
            self::assertSame($expectedLocales, $provider->getLocales());
            self::assertSame('en', $provider->getDefaultLocale());

            $router = $container->get('router');
            self::assertInstanceOf(RouterInterface::class, $router);
            $expectedRoutes = [];
            foreach ($expectedLocales as $locale) {
                $expectedRoutes[] = 'rk_locale_page.' . $locale;
            }
            self::assertSame($expectedRoutes, array_keys($router->getRouteCollection()->all()));
            self::assertSame('/' . $expectedLocales[0] . '/page', $router->generate('rk_locale_page', ['_locale' => $expectedLocales[0]]));

            $response = $kernel->handle(Request::create('/' . $expectedLocales[0] . '/page'));
            self::assertSame('page:' . $expectedLocales[0], $response->getContent());
        } finally {
            $kernel->shutdown();
        }
    }
}
