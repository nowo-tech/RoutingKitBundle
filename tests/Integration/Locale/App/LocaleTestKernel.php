<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Integration\Locale\App;

use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\RoutingKitBundle\NowoRoutingKitBundle;
use Nowo\RoutingKitBundle\Tests\Integration\Locale\App\Controller\LocalePageController;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

/**
 * Minimal host app whose nowo_routing_kit.locales is a container parameter or an env var (not a literal list),
 * with one stored route path so the RoutingKit loader registers a route per locale.
 */
final class LocaleTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $routingKitConfig
     */
    public function __construct(
        private readonly string $baseDir,
        private readonly array $routingKitConfig,
    ) {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new TwigExtraBundle();
        yield new NowoUiKitBundle();
        yield new NowoFormKitBundle();
        yield new NowoRoutingKitBundle();
    }

    public function getProjectDir(): string
    {
        return $this->baseDir;
    }

    public function getCacheDir(): string
    {
        return $this->baseDir . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->baseDir . '/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()->set('app.locales', ['es', 'en', 'fr']);
        $container->extension('framework', [
            'secret'               => 'integration-secret',
            'test'                 => true,
            'http_method_override' => false,
            'enabled_locales'      => ['de', 'en'],
        ]);
        $container->extension('nowo_routing_kit', $this->routingKitConfig + [
            'storage'   => ['paths_file' => $this->baseDir . '/var/paths.json'],
            'discovery' => ['scan_dirs' => [__DIR__ . '/Controller']],
            'security'  => ['access_roles' => [], 'allow_unauthenticated' => true],
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set(LocalePageController::class)->public()->tag('controller.service_arguments');
        // Env vars of the host (no process env mutation in tests): read through Symfony's env var loaders.
        $services->set(LocaleEnvVarLoader::class)->tag('container.env_var_loader');
        $services->set('logger', NullLogger::class);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'nowo_routing_kit');
    }
}
