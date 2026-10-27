<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Integration\DefaultLocale\App;

use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\RoutingKitBundle\NowoRoutingKitBundle;
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
 * Host app with locales en (default) / es / fr: `app_page` has a bare twin `app_page_unlocalized` (`/page`, the AuthKit
 * convention), `app_other` has none, and one RoutingKit stored route (`rk_stored`).
 */
final class DefaultLocaleTestKernel extends Kernel
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
        $container->extension('framework', [
            'secret'               => 'integration-secret',
            'test'                 => true,
            'http_method_override' => false,
            'default_locale'       => 'en',
            // The panel is on by default and refuses to compile without form CSRF (CsrfProtectionPass).
            'session'         => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'csrf_protection' => true,
            'form'            => ['csrf_protection' => true],
        ]);
        $container->extension('nowo_routing_kit', $this->routingKitConfig + [
            'default_locale' => 'en',
            'locales'        => ['en', 'es', 'fr'],
            'storage'        => ['paths_file' => $this->baseDir . '/var/paths.json'],
            'discovery'      => ['scan_dirs' => [__DIR__]],
            'security'       => ['access_roles' => [], 'allow_unauthenticated' => true],
            'redirects'      => ['canonical_enabled' => false],
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set(DefaultLocalePageController::class)->public()->tag('controller.service_arguments');
        $services->set('logger', NullLogger::class);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('app_page', '/{_locale}/page')
            ->controller([DefaultLocalePageController::class, 'page'])
            ->requirements(['_locale' => 'en|es|fr']);
        $routes->add('app_page_unlocalized', '/page')
            ->controller([DefaultLocalePageController::class, 'page'])
            ->defaults(['_locale' => 'en']);
        $routes->add('app_other', '/{_locale}/other')
            ->controller([DefaultLocalePageController::class, 'page'])
            ->requirements(['_locale' => 'en|es|fr']);
        $routes->import('.', 'nowo_routing_kit');
    }
}
