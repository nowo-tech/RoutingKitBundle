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
        );

        self::assertSame([
            'nowo_routing_kit_layout_template' => 'base.html.twig',
            'nowo_routing_kit_css_framework'   => 'bootstrap5',
            'nowo_routing_kit_icon_set'        => 'bootstrap-icons',
        ], $ext->getGlobals());
    }
}
