<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\DependencyInjection;

use Nowo\RoutingKitBundle\DependencyInjection\Configuration;
use Nowo\RoutingKitBundle\DependencyInjection\RoutingKitExtension;
use Nowo\RoutingKitBundle\Twig\RoutingKitTwigExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Spec 003: web_ui.classes / partials / ui_kit_assets / import_display / show_row_count / primary_action_last /
 * delete_confirm_attr / main_landmark (1.7.2).
 */
final class PanelChromeConfigTest extends TestCase
{
    public function testDefaultsKeepTheLegacyChrome(): void
    {
        $webUi = $this->process([])['web_ui'];

        self::assertSame([], $webUi['classes']);
        self::assertSame([], $webUi['partials']);
        self::assertArrayNotHasKey('ui_kit_assets', $webUi);
        self::assertSame('inline', $webUi['import_display']);
        self::assertTrue($webUi['show_row_count']);
        self::assertFalse($webUi['primary_action_last']);
        self::assertSame([], $webUi['delete_confirm_attr']);
        self::assertArrayNotHasKey('main_landmark', $webUi);
    }

    public function testUnknownClassKeyIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unknown nowo_routing_kit.web_ui.classes key(s): tabel');

        $this->process(['web_ui' => ['classes' => ['tabel' => 'x']]]);
    }

    public function testUnknownPartialKeyIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unknown nowo_routing_kit.web_ui.partials key(s): layout');

        $this->process(['web_ui' => ['partials' => ['layout' => 'x.html.twig']]]);
    }

    public function testInvalidImportDisplayIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['web_ui' => ['import_display' => 'drawer']]);
    }

    public function testExtensionPassesTheHooksToTheTwigGlobals(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter('kernel.secret', str_repeat('s', 32));
        $container->setParameter('kernel.bundles', []);

        foreach (['classes', 'partials', 'ui_kit_assets', 'main_landmark', 'delete_confirm_attr'] as $key) {
            $this->expectUserDeprecationMessageMatches('/"nowo_routing_kit\.web_ui\.' . $key . '" option is deprecated/');
        }

        (new RoutingKitExtension())->load([[
            'security' => ['allow_unauthenticated' => true],
            'web_ui'   => [
                'enabled'             => true,
                'classes'             => ['table' => ' data-table ', 'th' => ''],
                'partials'            => ['pagination' => 'kit/_pagination.html.twig'],
                'ui_kit_assets'       => false,
                'import_display'      => 'modal',
                'show_row_count'      => false,
                'primary_action_last' => true,
                'delete_confirm_attr' => ['data-controller' => 'confirm-submit', 'data-confirm-submit-message-value' => '%message%'],
                'main_landmark'       => false,
            ],
        ]], $container);

        $arguments = $container->getDefinition(RoutingKitTwigExtension::class)->getArguments();
        self::assertSame(['table' => 'data-table'], $arguments['$classes']);
        self::assertSame(['pagination' => 'kit/_pagination.html.twig'], $arguments['$partials']);
        self::assertFalse($arguments['$uiKitAssets']);
        self::assertSame('modal', $arguments['$importDisplay']);
        self::assertFalse($arguments['$showRowCount']);
        self::assertTrue($arguments['$primaryActionLast']);
        self::assertSame(['data-controller' => 'confirm-submit', 'data-confirm-submit-message-value' => '%message%'], $arguments['$deleteConfirmAttr']);
        self::assertFalse($arguments['$mainLandmark']);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
