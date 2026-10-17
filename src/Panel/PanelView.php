<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Panel;

use InvalidArgumentException;
use Nowo\RoutingKitBundle\NowoRoutingKitBundle;
use Nowo\UiKitBundle\View\Label;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function array_key_exists;
use function array_keys;
use function implode;
use function sprintf;
use function trigger_deprecation;

/**
 * Renders the panel pages on the UiKit page contract (REQ-UI-003): resolves the per-page template
 * ({@code web_ui.templates.<page>}), the layout override for the view models ({@code web_ui.layout_template}; null =
 * {@code nowo_ui_layout.admin}) and the shared page context (section nav, role-gate warning).
 *
 * Immutable and request-agnostic (worker safe).
 */
final readonly class PanelView
{
    public const BUNDLE = 'NowoRoutingKitBundle';

    /**
     * Bridge used when {@code web_ui.layout_template} is a 1.x layout that renders {@code body} instead of the UiKit
     * canonical blocks (deprecated since 1.9).
     */
    public const LEGACY_LAYOUT_BRIDGE = '@NowoRoutingKitBundle/panel/_legacy_layout.html.twig';

    /**
     * Page id => default template (override with {@code web_ui.templates.<page>}).
     *
     * @var array<string, string>
     */
    public const TEMPLATES = [
        'index'            => '@NowoRoutingKitBundle/panel/index.html.twig',
        'form'             => '@NowoRoutingKitBundle/panel/form.html.twig',
        'redirects_index'  => '@NowoRoutingKitBundle/panel/redirects/index.html.twig',
        'redirects_form'   => '@NowoRoutingKitBundle/panel/redirects/form.html.twig',
        'redirects_delete' => '@NowoRoutingKitBundle/panel/redirects/delete.html.twig',
    ];

    public const IMPORT_MODAL_ID = 'modal-routing-import';

    /**
     * @param array<string, string> $templates web_ui.templates (page id => template)
     */
    public function __construct(
        private Environment $twig,
        public string $pathPrefix = '/_routing',
        private ?string $layoutTemplate = null,
        private array $templates = [],
        public string $importDisplay = 'inline',
        public bool $showRowCount = true,
        public bool $primaryActionLast = false,
        public bool $urlRedirectsEnabled = false,
        public bool $roleGateDisabled = false,
    ) {
    }

    /**
     * Translation key of the bundle domain, translated by the UiKit scaffolds.
     *
     * @param array<string, mixed> $params
     */
    public static function label(string $key, array $params = []): Label
    {
        return new Label($key, NowoRoutingKitBundle::TRANSLATION_DOMAIN, $params);
    }

    public function template(string $page): string
    {
        if (!array_key_exists($page, self::TEMPLATES)) {
            throw new InvalidArgumentException(sprintf('Unknown RoutingKit panel page "%s" (%s).', $page, implode(', ', array_keys(self::TEMPLATES))));
        }

        return $this->templates[$page] ?? self::TEMPLATES[$page];
    }

    /**
     * The view-model layout: null (UiKit admin layout), the configured layout when it renders the canonical blocks,
     * or the legacy bridge around a 1.x {@code body} layout.
     */
    public function layout(): ?string
    {
        if ($this->layoutTemplate === null) {
            return null;
        }

        return $this->isLegacyLayout() ? self::LEGACY_LAYOUT_BRIDGE : $this->layoutTemplate;
    }

    /**
     * Section switcher items (Routes | Redirects); empty when the redirects section is off.
     *
     * @return list<array{label: Label, href: string, current: bool, attr: array<string, string>}>
     */
    public function nav(string $current): array
    {
        if (!$this->urlRedirectsEnabled) {
            return [];
        }

        return [
            ['label' => self::label('nav.routes'), 'href' => $this->pathPrefix . '/', 'current' => $current === 'routes', 'attr' => ['data-testid' => 'routing-kit-nav-routes']],
            ['label' => self::label('nav.redirects'), 'href' => $this->pathPrefix . '/redirects', 'current' => $current === 'redirects', 'attr' => ['data-testid' => 'routing-kit-nav-redirects']],
        ];
    }

    /**
     * @param array<string, mixed> $context page context (at least `page`, the view model)
     */
    public function render(string $page, string $section, array $context, int $status = Response::HTTP_OK): Response
    {
        $legacy = $this->layoutTemplate !== null && $this->isLegacyLayout();
        if ($legacy) {
            trigger_deprecation('nowo-tech/routing-kit-bundle', '1.9', 'The layout "%s" (nowo_routing_kit.web_ui.layout_template) does not render the UiKit canonical blocks (nowo_ui_content…); it is wrapped in "%s". Render the canonical blocks in your layout (see docs/UPGRADING.md) or set web_ui.layout_template to null.', (string) $this->layoutTemplate, self::LEGACY_LAYOUT_BRIDGE);
        }

        return new Response($this->twig->render($this->template($page), $context + [
            'path_prefix'                    => $this->pathPrefix,
            'nav'                            => $this->nav($section),
            'nav_current'                    => $section,
            'role_gate_disabled'             => $this->roleGateDisabled,
            'nowo_routing_kit_legacy_layout' => $legacy ? $this->layoutTemplate : null,
        ]), $status);
    }

    private function isLegacyLayout(): bool
    {
        return !$this->twig->load((string) $this->layoutTemplate)->hasBlock('nowo_ui_content');
    }
}
