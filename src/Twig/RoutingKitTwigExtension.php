<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Exposes REQ-UI-001 layout / CSS globals and the admin chrome hooks (spec 003) for panel templates.
 */
final class RoutingKitTwigExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @param array<string, string> $classes web_ui.classes (empty values removed)
     * @param array<string, string> $partials web_ui.partials (empty values removed)
     * @param array<string, string> $deleteConfirmAttr web_ui.delete_confirm_attr
     */
    public function __construct(
        private readonly string $layoutTemplate = '@NowoRoutingKitBundle/panel/layout.html.twig',
        private readonly string $cssFramework = 'custom',
        private readonly string $iconSet = 'none',
        private readonly bool $urlRedirectsEnabled = false,
        private readonly array $classes = [],
        private readonly array $partials = [],
        private readonly bool $uiKitAssets = true,
        private readonly string $importDisplay = 'inline',
        private readonly bool $showRowCount = true,
        private readonly bool $primaryActionLast = false,
        private readonly array $deleteConfirmAttr = [],
    ) {
    }

    public function getGlobals(): array
    {
        return [
            'nowo_routing_kit_layout_template'       => $this->layoutTemplate,
            'nowo_routing_kit_css_framework'         => $this->cssFramework,
            'nowo_routing_kit_icon_set'              => $this->iconSet,
            'nowo_routing_kit_url_redirects_enabled' => $this->urlRedirectsEnabled,
            'nowo_routing_kit_classes'               => $this->classes,
            'nowo_routing_kit_partials'              => $this->partials,
            'nowo_routing_kit_ui_kit_assets'         => $this->uiKitAssets,
            'nowo_routing_kit_import_display'        => $this->importDisplay,
            'nowo_routing_kit_show_row_count'        => $this->showRowCount,
            'nowo_routing_kit_primary_action_last'   => $this->primaryActionLast,
            'nowo_routing_kit_delete_confirm_attr'   => $this->deleteConfirmAttr,
        ];
    }
}
