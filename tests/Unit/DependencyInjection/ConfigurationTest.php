<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\DependencyInjection;

use Nowo\RoutingKitBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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
        self::assertFalse($config['web_ui']['enabled']);
        self::assertSame('/_routes', $config['web_ui']['path_prefix']);
        self::assertSame([
            'canonical_enabled'    => false,
            'canonical_status'     => 308,
            'root_enabled'         => true,
            'root_canonical_style' => 'with_prefix',
            'root_home_path'       => '/inicio',
            'root_status'          => 307,
            'canonical_cache_pool' => 'cache.app',
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
        self::assertArrayNotHasKey('enabled', $config['panel']);
        self::assertArrayNotHasKey('path_prefix', $config['panel']);
        self::assertArrayNotHasKey('role', $config['panel']);
        self::assertSame('/_routing', $config['web_ui']['path_prefix']);
        self::assertSame([], $config['web_ui']['templates']);
        self::assertFalse($config['panel']['allow_controller_override']);
        self::assertSame(500, $config['panel']['max_definitions']);
        self::assertSame(50, $config['panel']['list_page_size']);
        self::assertTrue($config['panel']['reject_conflicts']);
        self::assertNull($config['panel']['export_signing_key']);
        self::assertTrue($config['web_ui']['enabled']);
        self::assertNull($config['web_ui']['layout_template']);
        self::assertArrayNotHasKey('css_framework', $config['web_ui']);
        self::assertArrayNotHasKey('icon_set', $config['web_ui']);
        self::assertSame(['ROLE_ADMIN'], $config['security']['access_roles']);
        self::assertFalse($config['security']['allow_unauthenticated']);
        self::assertNull($config['security']['access_checker']);
        self::assertSame([
            'canonical_enabled'    => true,
            'canonical_status'     => 301,
            'canonical_cache_pool' => 'cache.app',
            'root_enabled'         => false,
            'root_canonical_style' => 'without_prefix',
            'root_home_path'       => '/',
            'root_status'          => 302,
        ], $config['redirects']);
        self::assertTrue($config['auto_invalidate_cache']);
        self::assertTrue($config['register_unprefixed_default']);
        self::assertTrue($config['seo_kit_bridge']);
    }

    public function testMapsNullPanelRoleToEmptyAccessRoles(): void
    {
        $processor     = new Processor();
        $configuration = new Configuration();

        $config = $processor->processConfiguration($configuration, [[
            'panel' => ['role' => null],
        ]]);

        self::assertSame([], $config['security']['access_roles']);
    }

    public function testMapsEmptyStringPanelRoleToEmptyAccessRoles(): void
    {
        $processor     = new Processor();
        $configuration = new Configuration();

        $config = $processor->processConfiguration($configuration, [[
            'panel' => ['role' => ''],
        ]]);

        self::assertSame([], $config['security']['access_roles']);
    }

    public function testMapsArrayPanelRoleToAccessRoles(): void
    {
        $processor     = new Processor();
        $configuration = new Configuration();

        $config = $processor->processConfiguration($configuration, [[
            'panel' => ['role' => ['ROLE_A', '', 'ROLE_B']],
        ]]);

        self::assertSame(['ROLE_A', 'ROLE_B'], $config['security']['access_roles']);
    }

    public function testAccessRolesAloneDoNotTriggerThePanelRoleDeprecation(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'security' => ['access_roles' => ['ROLE_EDITOR']],
        ]]);

        self::assertSame(['ROLE_EDITOR'], $config['security']['access_roles']);
        self::assertArrayNotHasKey('role', $config['panel'], 'access_roles is no longer mirrored into the deprecated panel.role.');
    }

    public function testDeprecatedPanelKeysMapToTheCanonicalWebUiKeys(): void
    {
        $this->expectUserDeprecationMessageMatches('/"nowo_routing_kit\.panel\.enabled" option is deprecated, use "nowo_routing_kit\.web_ui\.enabled"/');
        $this->expectUserDeprecationMessageMatches('/"nowo_routing_kit\.panel\.path_prefix" option is deprecated, use "nowo_routing_kit\.web_ui\.path_prefix"/');
        $this->expectUserDeprecationMessageMatches('/"nowo_routing_kit\.panel\.role" option is deprecated, use "nowo_routing_kit\.security\.access_roles"/');

        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'panel' => ['enabled' => false, 'path_prefix' => '/_old', 'role' => 'ROLE_OLD'],
        ]]);

        self::assertFalse($config['web_ui']['enabled']);
        self::assertSame('/_old', $config['web_ui']['path_prefix']);
        self::assertSame(['ROLE_OLD'], $config['security']['access_roles']);
    }

    public function testCanonicalWebUiKeysWinOverTheDeprecatedPanelKeys(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'panel'  => ['enabled' => false, 'path_prefix' => '/_old'],
            'web_ui' => ['enabled' => true, 'path_prefix' => '/_new'],
        ]]);

        self::assertTrue($config['web_ui']['enabled']);
        self::assertSame('/_new', $config['web_ui']['path_prefix']);
    }

    public function testLegacyDefaultLayoutMapsToTheUiKitLayout(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'web_ui' => ['layout_template' => Configuration::LEGACY_LAYOUT_TEMPLATE],
        ]]);
        self::assertNull($config['web_ui']['layout_template']);

        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'web_ui' => ['layout_template' => 'admin/layout.html.twig', 'templates' => ['index' => 'admin/routes.html.twig']],
        ]]);
        self::assertSame('admin/layout.html.twig', $config['web_ui']['layout_template']);
        self::assertSame(['index' => 'admin/routes.html.twig'], $config['web_ui']['templates']);
    }

    public function testRejectsUnknownTemplatePage(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unknown nowo_routing_kit.web_ui.templates page(s): show');

        (new Processor())->processConfiguration(new Configuration(), [['web_ui' => ['templates' => ['show' => 'x.html.twig']]]]);
    }

    public function testSecurityAccessRolesPreferredOverPanelRole(): void
    {
        $processor     = new Processor();
        $configuration = new Configuration();

        $config = $processor->processConfiguration($configuration, [[
            'panel'    => ['role' => 'ROLE_OLD'],
            'security' => ['access_roles' => ['ROLE_NEW']],
        ]]);

        self::assertSame(['ROLE_NEW'], $config['security']['access_roles']);
    }

    public function testRejectsUnsafePathPrefix(): void
    {
        $processor     = new Processor();
        $configuration = new Configuration();

        foreach (['javascript:alert(1)', '//evil', '/http://x', '/bad space'] as $prefix) {
            try {
                $processor->processConfiguration($configuration, [['web_ui' => ['path_prefix' => $prefix]]]);
                self::fail('expected invalid prefix: ' . $prefix);
            } catch (InvalidConfigurationException $e) {
                self::assertStringContainsString('path_prefix', $e->getMessage());
            }
        }
    }

    public function testRejectsShortExportSigningKey(): void
    {
        $processor     = new Processor();
        $configuration = new Configuration();

        $this->expectException(InvalidConfigurationException::class);
        $processor->processConfiguration($configuration, [[
            'panel' => ['export_signing_key' => 'too-short'],
        ]]);
    }

    public function testRejectsUnsafeRootHomePath(): void
    {
        $processor     = new Processor();
        $configuration = new Configuration();

        $this->expectException(InvalidConfigurationException::class);
        $processor->processConfiguration($configuration, [[
            'redirects' => ['root_home_path' => '//evil.example'],
        ]]);
    }

    public function testUrlRedirectsDefaultsAndOverrides(): void
    {
        $processor = new Processor();
        $defaults  = $processor->processConfiguration(new Configuration(), [[]])['url_redirects'];

        self::assertFalse($defaults['enabled']);
        self::assertNull($defaults['storage']);
        self::assertSame('%kernel.project_dir%/var/routing_kit/redirects.json', $defaults['file']);
        self::assertSame('cache.app', $defaults['cache_pool']);
        self::assertSame(3600, $defaults['cache_ttl']);
        self::assertContains('/admin', $defaults['protected_prefixes']);
        self::assertContains('/_', $defaults['protected_starts']);
        self::assertSame(['enabled' => true, 'message_bus' => null], $defaults['hits']);

        $custom = $processor->processConfiguration(new Configuration(), [[
            'url_redirects' => [
                'enabled'            => true,
                'storage'            => 'app.redirect_storage',
                'cache_pool'         => null,
                'cache_ttl'          => 0,
                'protected_prefixes' => ['/panel'],
                'protected_starts'   => ['/llms'],
                'hits'               => ['enabled' => false, 'message_bus' => 'messenger.default_bus'],
            ],
        ]])['url_redirects'];

        self::assertTrue($custom['enabled']);
        self::assertSame('app.redirect_storage', $custom['storage']);
        self::assertNull($custom['cache_pool']);
        self::assertSame(['/panel'], $custom['protected_prefixes']);
        self::assertSame(['/llms'], $custom['protected_starts']);
        self::assertSame(['enabled' => false, 'message_bus' => 'messenger.default_bus'], $custom['hits']);
    }

    public function testUrlRedirectsRejectsRelativeProtectedPrefix(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('protected_prefixes');

        (new Processor())->processConfiguration(new Configuration(), [['url_redirects' => ['protected_prefixes' => ['admin']]]]);
    }

    public function testUrlRedirectsRejectsBackslashProtectedStart(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('protected_starts');

        (new Processor())->processConfiguration(new Configuration(), [['url_redirects' => ['protected_starts' => ['/\\x']]]]);
    }
}
