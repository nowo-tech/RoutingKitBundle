<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Twig;

use Nowo\RoutingKitBundle\Twig\RoutingKitTwigExtension;
use PHPUnit\Framework\TestCase;

final class RoutingKitTwigExtensionTest extends TestCase
{
    public function testExposesUiGlobals(): void
    {
        $ext = new RoutingKitTwigExtension(
            layoutTemplate: 'base.html.twig',
            cssFramework: 'bootstrap5',
            iconSet: 'bootstrap-icons',
            urlRedirectsEnabled: true,
        );

        self::assertSame([
            'nowo_routing_kit_layout_template'       => 'base.html.twig',
            'nowo_routing_kit_css_framework'         => 'bootstrap5',
            'nowo_routing_kit_icon_set'              => 'bootstrap-icons',
            'nowo_routing_kit_url_redirects_enabled' => true,
            'nowo_routing_kit_classes'               => [],
            'nowo_routing_kit_partials'              => [],
            'nowo_routing_kit_ui_kit_assets'         => true,
            'nowo_routing_kit_import_display'        => 'modal',
            'nowo_routing_kit_show_row_count'        => false,
            'nowo_routing_kit_primary_action_last'   => false,
            'nowo_routing_kit_delete_confirm_attr'   => [],
            'nowo_routing_kit_main_landmark'         => true,
        ], $ext->getGlobals());
        self::assertFalse((new RoutingKitTwigExtension())->getGlobals()['nowo_routing_kit_url_redirects_enabled']);
    }

    public function testExposesAdminChromeHooks(): void
    {
        $globals = (new RoutingKitTwigExtension(
            classes: ['table' => 'data-table'],
            partials: ['pagination' => 'kit/_pagination.html.twig'],
            uiKitAssets: false,
            importDisplay: 'modal',
            showRowCount: false,
            primaryActionLast: true,
            deleteConfirmAttr: ['data-controller' => 'confirm-submit'],
            mainLandmark: false,
        ))->getGlobals();

        self::assertSame(['table' => 'data-table'], $globals['nowo_routing_kit_classes']);
        self::assertSame(['pagination' => 'kit/_pagination.html.twig'], $globals['nowo_routing_kit_partials']);
        self::assertFalse($globals['nowo_routing_kit_ui_kit_assets']);
        self::assertSame('modal', $globals['nowo_routing_kit_import_display']);
        self::assertFalse($globals['nowo_routing_kit_show_row_count']);
        self::assertTrue($globals['nowo_routing_kit_primary_action_last']);
        self::assertSame(['data-controller' => 'confirm-submit'], $globals['nowo_routing_kit_delete_confirm_attr']);
        self::assertFalse($globals['nowo_routing_kit_main_landmark']);
    }
}
