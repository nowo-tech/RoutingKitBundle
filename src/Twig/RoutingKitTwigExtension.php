<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Exposes REQ-UI-001 layout / CSS globals for panel templates.
 */
final class RoutingKitTwigExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly string $layoutTemplate = '@NowoRoutingKitBundle/panel/layout.html.twig',
        private readonly string $cssFramework = 'custom',
        private readonly string $iconSet = 'none',
    ) {
    }

    public function getGlobals(): array
    {
        return [
            'nowo_routing_kit_layout_template' => $this->layoutTemplate,
            'nowo_routing_kit_css_framework'   => $this->cssFramework,
            'nowo_routing_kit_icon_set'        => $this->iconSet,
        ];
    }
}
