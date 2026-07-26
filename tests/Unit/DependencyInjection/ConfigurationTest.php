<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\DependencyInjection;

use Nowo\RoutingKitBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testProcessesConfiguration(): void
    {
        $processor     = new Processor();
        $configuration = new Configuration();

        $config = $processor->processConfiguration($configuration, [[
            'enabled'         => false,
            'default_locale'  => 'es',
            'locales'         => ['es', 'en'],
            'locale_provider' => 'app.locale_provider',
            'storage'         => [
                'paths_file'   => '/tmp/routing.json',
                'path_storage' => 'app.path_storage',
            ],
            'discovery' => [
                'scan_dirs' => ['/app/src/Controller', '/app/src/Admin'],
            ],
            'panel' => [
                'enabled'     => false,
                'path_prefix' => '/_routes',
            ],
            'redirects' => [
                'canonical_enabled'    => false,
                'canonical_status'     => 308,
                'root_enabled'         => true,
                'root_canonical_style' => 'with_prefix',
                'root_home_path'       => '/inicio',
                'root_status'          => 307,
            ],
            'auto_invalidate_cache'       => false,
            'register_unprefixed_default' => false,
            'seo_kit_bridge'              => false,
        ]]);

        self::assertFalse($config['enabled']);
        self::assertSame('es', $config['default_locale']);
        self::assertSame(['es', 'en'], $config['locales']);
        self::assertSame('app.locale_provider', $config['locale_provider']);
        self::assertSame('/tmp/routing.json', $config['storage']['paths_file']);
        self::assertSame('app.path_storage', $config['storage']['path_storage']);
        self::assertSame(['/app/src/Controller', '/app/src/Admin'], $config['discovery']['scan_dirs']);
        self::assertFalse($config['panel']['enabled']);
        self::assertSame('/_routes', $config['panel']['path_prefix']);
        self::assertSame([
            'canonical_enabled'    => false,
            'canonical_status'     => 308,
            'root_enabled'         => true,
            'root_canonical_style' => 'with_prefix',
            'root_home_path'       => '/inicio',
            'root_status'          => 307,
        ], $config['redirects']);
        self::assertFalse($config['auto_invalidate_cache']);
        self::assertFalse($config['register_unprefixed_default']);
        self::assertFalse($config['seo_kit_bridge']);
    }

    public function testProvidesDefaults(): void
    {
        $processor     = new Processor();
        $configuration = new Configuration();

        $config = $processor->processConfiguration($configuration, [[]]);

        self::assertTrue($config['enabled']);
        self::assertSame('en', $config['default_locale']);
        self::assertSame(['en'], $config['locales']);
        self::assertNull($config['locale_provider']);
        self::assertSame('%kernel.project_dir%/var/routing_kit/paths.json', $config['storage']['paths_file']);
        self::assertNull($config['storage']['path_storage']);
        self::assertSame(['%kernel.project_dir%/src/Controller'], $config['discovery']['scan_dirs']);
        self::assertTrue($config['panel']['enabled']);
        self::assertSame('/_routing', $config['panel']['path_prefix']);
        self::assertSame([
            'canonical_enabled'    => true,
            'canonical_status'     => 301,
            'root_enabled'         => false,
            'root_canonical_style' => 'without_prefix',
            'root_home_path'       => '/',
            'root_status'          => 302,
        ], $config['redirects']);
        self::assertTrue($config['auto_invalidate_cache']);
        self::assertTrue($config['register_unprefixed_default']);
        self::assertTrue($config['seo_kit_bridge']);
    }
}
