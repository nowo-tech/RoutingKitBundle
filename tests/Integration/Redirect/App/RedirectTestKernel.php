<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Integration\Redirect\App;

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
 * Minimal host app for the URL redirect manager: panel (no SecurityBundle, demo-mode gate),
 * a live page, a slug route with a {@see BlogLivePathChecker}, and file storage in a temp dir.
 */
final class RedirectTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(private readonly string $baseDir)
    {
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
            'secret'                => 'integration-secret',
            'test'                  => true,
            'http_method_override'  => false,
            'handle_all_throwables' => true,
            'php_errors'            => ['log' => true],
            'session'               => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'csrf_protection'       => true,
            'form'                  => ['csrf_protection' => true],
            'translator'            => ['default_path' => $this->baseDir . '/translations', 'fallbacks' => ['en']],
            'default_locale'        => 'en',
        ]);
        $container->extension('twig', ['default_path' => $this->baseDir . '/templates']);
        $container->extension('nowo_routing_kit', [
            'default_locale' => 'es',
            'locales'        => ['es', 'en'],
            'storage'        => ['paths_file' => $this->baseDir . '/var/paths.json'],
            'discovery'      => ['scan_dirs' => []],
            'security'       => ['access_roles' => [], 'allow_unauthenticated' => true],
            'url_redirects'  => [
                'enabled' => true,
                'file'    => $this->baseDir . '/var/redirects.json',
            ],
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set(PagesController::class)->public()->tag('controller.service_arguments');
        $services->set(BlogLivePathChecker::class);
        $services->set('logger', NullLogger::class);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@NowoRoutingKitBundle/Resources/config/routes.yaml');
        $routes->add('contacto', '/contacto')->controller([PagesController::class, 'page'])->methods(['GET']);
        $routes->add('contact', '/contact')->controller([PagesController::class, 'page'])->methods(['GET']);
        $routes->add('blog_post', '/blog/{slug}')->controller([PagesController::class, 'page'])->methods(['GET']);
    }
}
