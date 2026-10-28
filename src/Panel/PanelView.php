<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Panel;

use InvalidArgumentException;
use Nowo\RoutingKitBundle\NowoRoutingKitBundle;
use Nowo\UiKitBundle\Navigation\AreaNavProviderInterface;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\AreaNav;
use Nowo\UiKitBundle\View\AreaNavItem;
use Nowo\UiKitBundle\View\Label;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function array_key_exists;
use function array_keys;
use function implode;
use function is_string;
use function sprintf;
use function trigger_deprecation;

/**
 * Renders the panel pages on the UiKit page contract (REQ-UI-003): resolves the per-page template
 * ({@code web_ui.templates.<page>}), the layout override for the view models ({@code web_ui.layout_template}; null =
 * {@code nowo_ui_layout.admin}), the area navigation of the panel (Routes | Redirects, {@code PageHeader::$tabs}, also
 * published to the UiKit {@code AreaNavRegistry} as area {@see self::AREA}) and the shared page context (role-gate
 * warning).
 *
 * Immutable and request-agnostic (worker safe).
 */
final readonly class PanelView implements AreaNavProviderInterface
{
    public const BUNDLE = 'NowoRoutingKitBundle';

    /** Area code of the panel navigation in the UiKit area-nav registry ({@code nowo_ui_area_nav('routing_kit')}). */
    public const AREA = 'routing_kit';

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
        'delete'           => '@NowoRoutingKitBundle/panel/delete.html.twig',
        'redirects_index'  => '@NowoRoutingKitBundle/panel/redirects/index.html.twig',
        'redirects_form'   => '@NowoRoutingKitBundle/panel/redirects/form.html.twig',
        'redirects_delete' => '@NowoRoutingKitBundle/panel/redirects/delete.html.twig',
    ];

    public const IMPORT_MODAL_ID = 'modal-routing-import';

    /**
     * Bundle form theme of the panel forms ({@code FormPage::formThemes}, import form): checkbox rows as UiKit check
     * labels ({@code ui.check()}), on top of the host's form themes.
     */
    public const FORM_THEME = '@NowoRoutingKitBundle/panel/_form_theme.html.twig';

    /**
     * @param array<string, string> $templates web_ui.templates (page id => template)
     */
    public function __construct(
        private Environment $twig,
        public string $pathPrefix = '/_routing',
        private ?string $layoutTemplate = null,
        private array $templates = [],
        public string $importDisplay = 'modal',
        public bool $showRowCount = false,
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

    /**
     * Header action opening the import dialog ({@code web_ui.import_display: modal}): the UiKit {@code dialog} partial
     * and its framework-free opener ({@code data-nowo-ui-dialog-open}, js/nowo-ui-dialog.js).
     */
    public static function importDialogAction(): Action
    {
        return new Action('import', self::label('panel.import'), '#' . self::IMPORT_MODAL_ID, attributes: [
            'data-nowo-ui-dialog-open' => self::IMPORT_MODAL_ID,
            'aria-haspopup'            => 'dialog',
            'data-testid'              => 'routing-kit-import-open',
        ]);
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
     * Area navigation of the panel sections (Routes | Redirects) for {@code PageHeader::$tabs}, with the current
     * section active; null when the redirects section is off (a single section needs no tab bar).
     */
    public function nav(string $current): ?AreaNav
    {
        if (!$this->urlRedirectsEnabled) {
            return null;
        }

        return new AreaNav([
            new AreaNavItem(self::label('nav.routes'), $this->pathPrefix . '/', $current === 'routes', ['data-testid' => 'routing-kit-nav-routes'], 'tabler:route'),
            new AreaNavItem(self::label('nav.redirects'), $this->pathPrefix . '/redirects', $current === 'redirects', ['data-testid' => 'routing-kit-nav-redirects'], 'tabler:arrow-forward-up'),
        ], self::label('nav.label'));
    }

    /**
     * The panel navigation for other bundles (DashboardMenu, host menus): area {@see self::AREA}; {@code context.current}
     * (an href) marks that item active.
     *
     * @param array<string, mixed> $context
     */
    public function areaNav(string $area, array $context = []): ?AreaNav
    {
        if ($area !== self::AREA) {
            return null;
        }

        $nav     = $this->nav('');
        $current = $context['current'] ?? null;

        return $nav instanceof AreaNav && is_string($current) ? $nav->withActive($current) : $nav;
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
