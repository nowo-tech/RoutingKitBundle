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
        yield 'path delete (no-JS confirm)' => new PageFixture('@NowoRoutingKitBundle/panel/delete.html.twig', PanelPageFixtures::delete($definitions[2]));
        yield 'redirects list' => new PageFixture('@NowoRoutingKitBundle/panel/redirects/index.html.twig', PanelPageFixtures::redirectsIndex($redirects, 'created'));
        yield 'redirects list (empty)' => new PageFixture('@NowoRoutingKitBundle/panel/redirects/index.html.twig', PanelPageFixtures::redirectsIndex([]));
        yield 'redirect form (create)' => new PageFixture('@NowoRoutingKitBundle/panel/redirects/form.html.twig', PanelPageFixtures::redirectsForm(null));
        yield 'redirect form (edit)' => new PageFixture('@NowoRoutingKitBundle/panel/redirects/form.html.twig', PanelPageFixtures::redirectsForm($redirects[0]));
        yield 'redirect delete (no-JS confirm)' => new PageFixture('@NowoRoutingKitBundle/panel/redirects/delete.html.twig', PanelPageFixtures::redirectsDelete($redirects[0]));
    }

    protected static function inlineTemplates(): array
    {
        return [
            // 1.x host layout (renders `body`): wrapped by the deprecated legacy bridge.
            'host/legacy.html.twig' => '<legacy-layout><title>{% block title %}{% endblock %}</title><head-css>{% block stylesheets %}<host-css/>{% endblock %}</head-css>{% block body %}{% endblock %}<js>{% block javascripts %}{% endblock %}</js></legacy-layout>',
            // Thin app override through the bang namespace (REQ-UI-003 "For applications" 4).
            // The UiKit page header partial itself (the stub host's page_header override does not render header.tabs).
            'host/page_header.html.twig'    => "{{ include('@NowoUiKitBundle/partials/_page_header.html.twig', {header: page.header, bundle: page.bundle}) }}",
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
        self::assertStringContainsString('<noscript><a class="', $html);
        self::assertStringContainsString('nowo-ui-action__fallback" href="/_routing/delete/rk_3"', $html);
        self::assertStringNotContainsString('routing-kit-role-gate-warning', $html);
    }

    public function testRouteDeleteConfirmPagePostsWithCsrf(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoRoutingKitBundle/panel/delete.html.twig', PanelPageFixtures::delete(PanelPageFixtures::definitions()[2])))->html;

        self::assertStringContainsString('data-nowo-ui-page="confirm"', $html);
        self::assertStringContainsString('action="/_routing/delete/rk_3"', $html);
        self::assertStringContainsString('value="token-nowo_routing_kit_panel"', $html);
        self::assertStringContainsString('Delete the path /sobre-nosotros (es) of the route app_about?', $html);
        self::assertStringContainsString('href="/_routing/"', $html);
    }

    public function testImportModalRendersThroughTheUiKitDialog(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoRoutingKitBundle/panel/index.html.twig', PanelPageFixtures::index([], 'modal', true)))->html;

        self::assertMatchesRegularExpression('/<dialog id="modal-routing-import" class="[^"]*nowo-ui-dialog--lg"[^>]* data-nowo-ui-dialog/', $html);
        self::assertStringContainsString('data-nowo-ui-dialog-open="modal-routing-import"', $html);
        self::assertStringContainsString('data-nowo-ui-dialog-close>', $html);
        self::assertStringContainsString('data-testid="routing-kit-import-dialog"', $html);
        self::assertStringNotContainsString('modal-dialog', $html, 'No Bootstrap modal_shell markup.');
        self::assertStringNotContainsString('id="routing-kit-import"', $html, 'No inline import card in modal mode.');
        self::assertStringContainsString('<strong>UNSAFE:</strong>', $html);
        self::assertStringContainsString('No path definitions yet.', $html);
    }

    public function testSectionTabsAreTheUiKitAreaNav(): void
    {
        $twig = $this->createTwig();

        $routes = $twig->render('host/page_header.html.twig', PanelPageFixtures::index(PanelPageFixtures::definitions()));
        self::assertMatchesRegularExpression('/<nav class="[^"]*" aria-label="Routing Kit sections"[^>]* data-nowo-ui-region="area-nav">/', $routes);
        self::assertMatchesRegularExpression('#href="/_routing/"\s+aria-current="page"\s+data-testid="routing-kit-nav-routes"#', $routes);
        self::assertMatchesRegularExpression('#href="/_routing/redirects"\s+data-testid="routing-kit-nav-redirects"#', $routes);

        $redirects = $twig->render('host/page_header.html.twig', PanelPageFixtures::redirectsDelete(PanelPageFixtures::redirects()[0]));
        self::assertMatchesRegularExpression('#href="/_routing/redirects"\s+aria-current="page"#', $redirects);

        $off = $twig->render('host/page_header.html.twig', PanelPageFixtures::index([], redirects: false));
        self::assertStringNotContainsString('area-nav', $off);
        self::assertStringNotContainsString('routing-kit-nav-redirects', $off);
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
        self::assertStringContainsString('nowo-ui-action__fallback" href="/_routing/redirects/r1/delete"', $html);
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
        self::assertStringContainsString('js/nowo-ui-dialog.js', $html);
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
