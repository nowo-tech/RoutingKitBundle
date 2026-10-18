<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Integration\Contract;

use Nowo\RoutingKitBundle\Panel\PanelView;
use Nowo\RoutingKitBundle\Tests\Support\PanelPageFixtures;
use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;
use Nowo\UiKitBundle\View\ConfirmPage;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\DataCollectorTranslator;

use function array_filter;
use function array_map;
use function array_values;
use function assert;
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
        yield 'paths list (empty, role gate off)' => new PageFixture('@NowoRoutingKitBundle/panel/index.html.twig', PanelPageFixtures::index([], 'modal', true));
        yield 'paths list (inline import, row count)' => new PageFixture('@NowoRoutingKitBundle/panel/index.html.twig', PanelPageFixtures::index($definitions, 'inline', rowCount: true));
        yield 'paths list (redirects section off)' => new PageFixture('@NowoRoutingKitBundle/panel/index.html.twig', PanelPageFixtures::index($definitions, redirects: false));
        yield 'path form (create, error + conflicts)' => new PageFixture('@NowoRoutingKitBundle/panel/form.html.twig', PanelPageFixtures::form(null, ['Path /blog/{slug} (en) conflicts with app_news_show.'], 'Path definitions limit reached.'));
        yield 'path form (edit)' => new PageFixture('@NowoRoutingKitBundle/panel/form.html.twig', PanelPageFixtures::form($definitions[2]));
        yield 'path form (create, controller override)' => new PageFixture('@NowoRoutingKitBundle/panel/form.html.twig', PanelPageFixtures::form(null, controllerOverride: true));
        yield 'path delete (no-JS confirm)' => new PageFixture('@NowoRoutingKitBundle/panel/delete.html.twig', PanelPageFixtures::delete($definitions[2]));
        yield 'redirects list' => new PageFixture('@NowoRoutingKitBundle/panel/redirects/index.html.twig', PanelPageFixtures::redirectsIndex($redirects, 'created'));
        yield 'redirects list (row count)' => new PageFixture('@NowoRoutingKitBundle/panel/redirects/index.html.twig', PanelPageFixtures::redirectsIndex($redirects, rowCount: true));
        yield 'redirects list (empty)' => new PageFixture('@NowoRoutingKitBundle/panel/redirects/index.html.twig', PanelPageFixtures::redirectsIndex([]));
        yield 'redirect form (create)' => new PageFixture('@NowoRoutingKitBundle/panel/redirects/form.html.twig', PanelPageFixtures::redirectsForm(null));
        yield 'redirect form (edit)' => new PageFixture('@NowoRoutingKitBundle/panel/redirects/form.html.twig', PanelPageFixtures::redirectsForm($redirects[0]));
        yield 'redirect delete (no-JS confirm)' => new PageFixture('@NowoRoutingKitBundle/panel/redirects/delete.html.twig', PanelPageFixtures::redirectsDelete($redirects[0]));
    }

    /**
     * @return iterable<string, array{PageFixture}>
     */
    public static function translationPages(): iterable
    {
        foreach (self::pageFixtures() as $name => $fixture) {
            yield $name => [$fixture];
        }
    }

    /**
     * The form theme translates placeholders and choice labels in the field domain: literal examples (`/about`, JSON)
     * are catalogue entries and literal choices (locale codes, routes, status codes) are not looked up. Every lookup
     * of a page hits the catalogues (no profiler missing message).
     */
    #[DataProvider('translationPages')]
    public function testPageLooksUpNoMissingMessage(PageFixture $fixture): void
    {
        $twig      = $this->createTwig();
        $collector = $twig->getExtension(TranslationExtension::class)->getTranslator();
        assert($collector instanceof DataCollectorTranslator);
        $twig->render($fixture->template, $fixture->context);

        $missing = array_filter(
            $collector->getCollectedMessages(),
            static fn (array $message): bool => $message['state'] === DataCollectorTranslator::MESSAGE_MISSING,
        );
        self::assertSame([], array_values(array_map(static fn (array $message): string => $message['domain'] . ': ' . $message['id'], $missing)));
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

        self::assertMatchesRegularExpression('#<code class="[^"]*nowo-ui-code[^"]*"[^>]*>app_blog_show</code>#', $html, 'Route names use the UiKit code slot.');
        self::assertMatchesRegularExpression('#<code class="[^"]*nowo-ui-code[^"]*"[^>]*>/sobre-nosotros</code>#', $html, 'Paths use the UiKit code slot.');
        self::assertStringNotContainsString('<code>', $html, 'No unstyled <code> cells.');
        self::assertStringContainsString('Without locale prefix', $html);
        self::assertStringContainsString('>Yes</span>', $html);
        self::assertSame(4, substr_count($html, '<dialog'), 'One UiKit confirm dialog per row delete + the import dialog.');
        self::assertStringContainsString('Delete path /sobre-nosotros (es)?', $html, 'The confirm title names the item.');
        self::assertStringContainsString('The route app_about falls back to its default path for es.', $html);
        self::assertStringContainsString('action="/_routing/export"', $html);
        self::assertStringContainsString('action="/_routing/import"', $html);
        self::assertStringContainsString('data-testid="routing-kit-import-dialog"', $html, 'The import form opens in a dialog by default.');
        self::assertStringNotContainsString('id="routing-kit-import"', $html);
        self::assertStringNotContainsString('<caption', $html, 'No "N row(s)" caption by default (the pagination summary shows the count).');
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
        self::assertStringContainsString('Delete path /sobre-nosotros (es)?', $html);
        self::assertStringContainsString('The route app_about falls back to its default path for es.', $html);
        self::assertStringContainsString('href="/_routing/"', $html);
    }

    public function testRowActionsAreUiKitIconActionsWithAccessibleLabels(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoRoutingKitBundle/panel/index.html.twig', PanelPageFixtures::index(PanelPageFixtures::definitions())))->html;

        self::assertStringContainsString('nowo-ui-row-actions--icon', $html, 'Row actions follow nowo_ui_kit.row_actions_display (icon).');
        self::assertMatchesRegularExpression('#<a [^>]*href="/_routing/edit/rk_1"[^>]*title="Edit"[^>]*aria-label="Edit"#s', $html);
        self::assertMatchesRegularExpression('#<span class="visually-hidden">Edit</span>#', $html);
        self::assertMatchesRegularExpression('#title="Delete"\s+aria-label="Delete"#', $html);
    }

    public function testInlineImportCardAndRowCountCaptionStayAvailable(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoRoutingKitBundle/panel/index.html.twig', PanelPageFixtures::index(PanelPageFixtures::definitions(), 'inline', rowCount: true)))->html;

        self::assertStringContainsString('id="routing-kit-import"', $html);
        self::assertStringNotContainsString('data-testid="routing-kit-import-dialog"', $html);
        self::assertStringContainsString('3 row(s)', $html);
        self::assertMatchesRegularExpression('#<textarea id="payload_json"[^>]* class="nowo-ui-input nowo-ui-input--full[^"]*"#', $html, 'Full-width textarea (input_full slot).');
        self::assertStringContainsString('<label for="payload_json">Signed JSON payload</label>', $html);
        self::assertMatchesRegularExpression('#<label class="nowo-ui-check" for="replace_all">\s*<input type="checkbox" id="replace_all" name="replace_all" value="1">\s*<span>Replace all existing rows</span>#', $html, 'UiKit check label through the bundle form theme.');
    }

    public function testFormCheckboxesUseTheBundleFormTheme(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoRoutingKitBundle/panel/redirects/form.html.twig', PanelPageFixtures::redirectsForm(PanelPageFixtures::redirects()[0])))->html;

        self::assertMatchesRegularExpression('#<label class="nowo-ui-check" for="url_redirect_enabled">\s*<input type="checkbox" id="url_redirect_enabled" name="url_redirect\[enabled\]" value="1" checked>#', $html);
    }

    public function testImportModalRendersThroughTheUiKitDialog(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoRoutingKitBundle/panel/index.html.twig', PanelPageFixtures::index([], 'modal', true)))->html;

        self::assertMatchesRegularExpression('/<dialog id="modal-routing-import" class="[^"]*nowo-ui-dialog--lg"[^>]* data-nowo-ui-dialog/', $html);
        self::assertStringContainsString('data-nowo-ui-dialog-open="modal-routing-import"', $html);
        self::assertStringContainsString('data-nowo-ui-dialog-close>', $html);
        self::assertMatchesRegularExpression('#<footer class="[^"]*"[^>]*>\s*<button type="button"[^>]*data-nowo-ui-dialog-close>Cancel</button>\s*<button type="submit" form="routing-kit-import-form"[^>]*>Import</button>#', $html, 'Dialog actions in the dialog footer, bound to the form.');
        self::assertStringContainsString('<form method="post" action="/_routing/import" id="routing-kit-import-form"', $html);
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
        self::assertStringContainsString('Delete redirect /promo-2025?', $html);
        self::assertStringContainsString('>Yes</span>', $html, 'Same enabled badge text as the paths list.');
        self::assertStringContainsString('>No</span>', $html);
        self::assertStringNotContainsString('<caption', $html);
        self::assertStringContainsString('nowo-ui-action__fallback" href="/_routing/redirects/r1/delete"', $html);
        self::assertStringContainsString('· 2026-10-01 10:00', $html);
    }

    public function testLegacyBodyLayoutIsBridged(): void
    {
        $context                                   = PanelPageFixtures::redirectsDelete(PanelPageFixtures::redirects()[0]);
        $context['page']                           = new ConfirmPage($context['page']->header, $context['page']->confirm, $context['page']->action, $context['page']->cancelHref, PanelView::BUNDLE, PanelView::LEGACY_LAYOUT_BRIDGE);
        $context['nowo_routing_kit_legacy_layout'] = 'host/legacy.html.twig';

        $html = $this->createTwig()->render('@NowoRoutingKitBundle/panel/redirects/delete.html.twig', $context);

        self::assertStringStartsWith('<legacy-layout><title>Delete redirect /tratamientos/unas-encarnadas?</title>', $html);
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
