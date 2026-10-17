<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Integration\Contract;

use Nowo\RoutingKitBundle\Panel\PanelView;
use Nowo\RoutingKitBundle\Tests\Support\PanelPageFixtures;
use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;
use Nowo\UiKitBundle\View\ConfirmPage;

use function dirname;

/**
 * REQ-UI-003: every panel page extends a UiKit scaffold, fills the canonical blocks, ships no chrome of its own
 * (no <html>, CDN, inline handlers, framework classes outside the theme slots) and renders translated labels only.
 */
final class PageContractTest extends PageContractTestCase
{
    public static function pageFixtures(): iterable
    {
        $definitions = PanelPageFixtures::definitions();
        $redirects   = PanelPageFixtures::redirects();

        yield 'paths list' => new PageFixture('@NowoRoutingKitBundle/panel/index.html.twig', PanelPageFixtures::index($definitions));
        yield 'paths list (empty, import modal, role gate off)' => new PageFixture('@NowoRoutingKitBundle/panel/index.html.twig', PanelPageFixtures::index([], 'modal', true));
        yield 'paths list (redirects section off)' => new PageFixture('@NowoRoutingKitBundle/panel/index.html.twig', PanelPageFixtures::index($definitions, redirects: false));
        yield 'path form (create, error + conflicts)' => new PageFixture('@NowoRoutingKitBundle/panel/form.html.twig', PanelPageFixtures::form(null, ['Path /blog/{slug} (en) conflicts with app_news_show.'], 'Path definitions limit reached.'));
        yield 'path form (edit)' => new PageFixture('@NowoRoutingKitBundle/panel/form.html.twig', PanelPageFixtures::form($definitions[2]));
        yield 'redirects list' => new PageFixture('@NowoRoutingKitBundle/panel/redirects/index.html.twig', PanelPageFixtures::redirectsIndex($redirects, 'created'));
        yield 'redirects list (empty)' => new PageFixture('@NowoRoutingKitBundle/panel/redirects/index.html.twig', PanelPageFixtures::redirectsIndex([]));
        yield 'redirect form (create)' => new PageFixture('@NowoRoutingKitBundle/panel/redirects/form.html.twig', PanelPageFixtures::redirectsForm(null));
        yield 'redirect form (edit)' => new PageFixture('@NowoRoutingKitBundle/panel/redirects/form.html.twig', PanelPageFixtures::redirectsForm($redirects[0]));
        yield 'redirect delete (no-JS confirm)' => new PageFixture('@NowoRoutingKitBundle/panel/redirects/delete.html.twig', PanelPageFixtures::redirectsDelete($redirects[0]));
    }

    /**
     * UiKit's own `modal_shell` partial (import modal) keeps its 1.x structure classes for every framework (Bootstrap
     * JS and nowo-ui.css rely on them); the contract allow-list does not cover them yet. Documented in
     * docs/TEMPLATES.md ("Allowed exceptions").
     */
    protected static function allowedClasses(): array
    {
        return ['modal', 'fade', 'modal-dialog', 'modal-content', 'modal-header', 'modal-title', 'btn-close', 'modal-body', 'modal-footer'];
    }

    protected static function inlineTemplates(): array
    {
        return [
            // 1.x host layout (renders `body`): wrapped by the deprecated legacy bridge.
            'host/legacy.html.twig' => '<legacy-layout><title>{% block title %}{% endblock %}</title><head-css>{% block stylesheets %}<host-css/>{% endblock %}</head-css>{% block body %}{% endblock %}<js>{% block javascripts %}{% endblock %}</js></legacy-layout>',
            // Thin app override through the bang namespace (REQ-UI-003 "For applications" 4).
            'host/index_override.html.twig' => "{% extends '@!NowoRoutingKitBundle/panel/index.html.twig' %}{% block nowo_ui_list_after %}<p class=\"host-import\">custom import</p>{% endblock %}",
        ];
    }

    public function testListRowsRenderTheBundleCellsAndTheConfirmDialogs(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoRoutingKitBundle/panel/index.html.twig', PanelPageFixtures::index(PanelPageFixtures::definitions())))->html;

        self::assertStringContainsString('<code>app_blog_show</code>', $html);
        self::assertStringContainsString('<code>/sobre-nosotros</code>', $html);
        self::assertStringContainsString('Without locale prefix', $html);
        self::assertSame(3, substr_count($html, '<dialog'), 'One UiKit confirm dialog per row delete.');
        self::assertStringContainsString('Delete the path /sobre-nosotros (es) of the route app_about?', $html);
        self::assertStringContainsString('action="/_routing/export"', $html);
        self::assertStringContainsString('action="/_routing/import"', $html);
        self::assertStringContainsString('3 row(s)', $html);
        self::assertStringContainsString('href="/_routing/redirects"', $html);
        self::assertStringContainsString('aria-current="page"', $html);
        self::assertStringNotContainsString('routing-kit-role-gate-warning', $html);
    }

    public function testImportModalRendersThroughTheUiKitModalShell(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoRoutingKitBundle/panel/index.html.twig', PanelPageFixtures::index([], 'modal', true)))->html;

        self::assertStringContainsString('id="modal-routing-import"', $html);
        self::assertStringContainsString('data-nowo-modal-target="modal-routing-import"', $html);
        self::assertStringContainsString('data-nowo-modal-close', $html);
        self::assertStringNotContainsString('id="routing-kit-import"', $html, 'No inline import card in modal mode.');
        self::assertStringContainsString('<strong>UNSAFE:</strong>', $html);
        self::assertStringContainsString('No path definitions yet.', $html);
    }

    public function testSectionNavIsHiddenWithoutTheRedirectsSection(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoRoutingKitBundle/panel/index.html.twig', PanelPageFixtures::index([], redirects: false)))->html;

        self::assertStringNotContainsString('routing-kit-nav-redirects', $html);
    }

    public function testFormShowsErrorsAndConflictsBeforeTheFields(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoRoutingKitBundle/panel/form.html.twig', PanelPageFixtures::form(null, ['Conflicts with app_news_show.'], 'Limit reached.')))->html;

        self::assertStringContainsString('Limit reached.', $html);
        self::assertStringContainsString('data-testid="routing-kit-conflicts"', $html);
        self::assertLessThan(strpos($html, 'name="path"'), strpos($html, 'Conflicts with app_news_show.'));
    }

    public function testRedirectStatusRendersAsAFlash(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoRoutingKitBundle/panel/redirects/index.html.twig', PanelPageFixtures::redirectsIndex(PanelPageFixtures::redirects(), 'deleted')))->html;

        self::assertStringContainsString('Redirect deleted.', $html);
        self::assertStringContainsString('data-testid="redirect-delete"', $html);
        self::assertStringContainsString('· 2026-10-01 10:00', $html);
    }

    public function testLegacyBodyLayoutIsBridged(): void
    {
        $context                                   = PanelPageFixtures::redirectsDelete(PanelPageFixtures::redirects()[0]);
        $context['page']                           = new ConfirmPage($context['page']->header, $context['page']->confirm, $context['page']->action, $context['page']->cancelHref, PanelView::BUNDLE, PanelView::LEGACY_LAYOUT_BRIDGE);
        $context['nowo_routing_kit_legacy_layout'] = 'host/legacy.html.twig';

        $html = $this->createTwig()->render('@NowoRoutingKitBundle/panel/redirects/delete.html.twig', $context);

        self::assertStringStartsWith('<legacy-layout><title>Delete redirect</title>', $html);
        self::assertStringContainsString('<host-css/>', $html);
        self::assertStringContainsString('css/nowo-ui.css', $html);
        self::assertStringContainsString('js/nowo-ui-confirm.js', $html);
        self::assertStringContainsString('data-nowo-ui-bundle="NowoRoutingKitBundle"', $html);
        self::assertStringContainsString('data-nowo-ui-page="confirm"', $html);
    }

    public function testBangNamespaceOverrideKeepsTheBundlePage(): void
    {
        $html = $this->renderPage(new PageFixture('host/index_override.html.twig', PanelPageFixtures::index([])))->html;

        self::assertStringContainsString('<p class="host-import">custom import</p>', $html);
        self::assertStringContainsString('data-nowo-ui-page="list"', $html);
        self::assertStringNotContainsString('id="routing-kit-import"', $html);
    }

    protected static function templatePaths(): array
    {
        $views = dirname(__DIR__, 3) . '/src/Resources/views';

        return ['NowoRoutingKitBundle' => $views, '!NowoRoutingKitBundle' => $views];
    }

    protected static function translationDirs(): array
    {
        return [dirname(__DIR__, 3) . '/src/Resources/translations'];
    }

    protected static function templatesDocument(): string
    {
        return dirname(__DIR__, 3) . '/docs/TEMPLATES.md';
    }
}
