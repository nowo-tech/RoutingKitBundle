<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Integration\Contract;

use Nowo\RoutingKitBundle\Tests\Support\PanelPageFixtures;
use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;

use function dirname;

/**
 * The page contract in a second shipped locale: every label of every page has a real German translation.
 */
final class PageContractGermanTest extends PageContractTestCase
{
    public static function pageFixtures(): iterable
    {
        return PageContractTest::pageFixtures();
    }

    public function testPagesRenderTheGermanCatalogue(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoRoutingKitBundle/panel/index.html.twig', PanelPageFixtures::index([], 'inline', true)))->html;

        self::assertStringContainsString('<strong>UNSICHER:</strong>', $html);
        self::assertStringContainsString('Exportieren', $html);
        self::assertStringContainsString('Noch keine Pfaddefinitionen.', $html);
    }

    protected static function locale(): string
    {
        return 'de';
    }

    protected static function cssFrameworks(): array
    {
        return ['none'];
    }

    protected static function allowedClasses(): array
    {
        return ['modal-dialog', 'modal-content', 'modal-header', 'modal-title', 'btn-close', 'modal-body', 'modal-footer'];
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
