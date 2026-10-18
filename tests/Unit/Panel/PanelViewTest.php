<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Panel;

use InvalidArgumentException;
use Nowo\RoutingKitBundle\Panel\PanelView;
use Nowo\UiKitBundle\View\AreaNav;
use Nowo\UiKitBundle\View\AreaNavItem;
use Nowo\UiKitBundle\View\Label;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class PanelViewTest extends TestCase
{
    public function testLabelUsesTheBundleDomain(): void
    {
        self::assertEquals(new Label('panel.title', 'NowoRoutingKitBundle', ['%a%' => 1]), PanelView::label('panel.title', ['%a%' => 1]));
    }

    public function testTemplatesCanBeOverriddenPerPage(): void
    {
        $view = new PanelView($this->twig(), templates: ['index' => 'admin/routes.html.twig']);

        self::assertSame('admin/routes.html.twig', $view->template('index'));
        self::assertSame(PanelView::TEMPLATES['redirects_delete'], $view->template('redirects_delete'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown RoutingKit panel page "show"');
        $view->template('show');
    }

    public function testLayoutIsNullByDefaultAndKeptWhenItRendersTheCanonicalBlocks(): void
    {
        self::assertNull((new PanelView($this->twig()))->layout());
        self::assertSame('layouts/admin.html.twig', (new PanelView($this->twig(), layoutTemplate: 'layouts/admin.html.twig'))->layout());
        self::assertSame('layouts/child.html.twig', (new PanelView($this->twig(), layoutTemplate: 'layouts/child.html.twig'))->layout(), 'Blocks of parent layouts count.');
    }

    public function testOneXBodyLayoutIsBridgedWithADeprecation(): void
    {
        $view = new PanelView($this->twig(), '/_r', 'base.html.twig', ['index' => 'page.html.twig']);
        self::assertSame(PanelView::LEGACY_LAYOUT_BRIDGE, $view->layout());

        $this->expectUserDeprecationMessageMatches('/"base\.html\.twig" \(nowo_routing_kit\.web_ui\.layout_template\) does not render the UiKit canonical blocks/');
        $response = $view->render('index', 'routes', ['page' => 'x']);

        self::assertSame('page:x|/_r|routes|base.html.twig', $response->getContent());
    }

    public function testRenderAddsTheSharedContext(): void
    {
        $view = new PanelView($this->twig(), '/_r', templates: ['form' => 'page.html.twig'], urlRedirectsEnabled: true, roleGateDisabled: true);

        $response = $view->render('form', 'redirects', ['page' => 'y'], 422);

        self::assertSame('page:y|/_r|redirects|', $response->getContent());
        self::assertSame(422, $response->getStatusCode());
    }

    public function testNavListsBothSectionsOnlyWithRedirects(): void
    {
        self::assertNull((new PanelView($this->twig()))->nav('routes'));

        $nav = (new PanelView($this->twig(), '/_r', urlRedirectsEnabled: true))->nav('redirects');
        self::assertInstanceOf(AreaNav::class, $nav);
        self::assertSame(['/_r/', '/_r/redirects'], array_map(static fn (AreaNavItem $item): string => $item->href, $nav->items));
        self::assertSame([false, true], array_map(static fn (AreaNavItem $item): bool => $item->active, $nav->items));
        self::assertEquals(PanelView::label('nav.redirects'), $nav->items[1]->label);
        self::assertEquals(PanelView::label('nav.label'), $nav->label);
        self::assertSame(['data-testid' => 'routing-kit-nav-routes'], $nav->items[0]->attributes);
    }

    public function testAreaNavIsPublishedForTheRoutingKitArea(): void
    {
        $view = new PanelView($this->twig(), '/_r', urlRedirectsEnabled: true);

        self::assertNull($view->areaNav('cookie_consent'));
        self::assertNull((new PanelView($this->twig()))->areaNav(PanelView::AREA));
        self::assertNull($view->areaNav(PanelView::AREA)?->active());
        self::assertSame('/_r/redirects', $view->areaNav(PanelView::AREA, ['current' => '/_r/redirects'])?->active()?->href);
    }

    public function testImportDialogActionOpensTheUiKitDialog(): void
    {
        $action = PanelView::importDialogAction();

        self::assertSame('import', $action->kind);
        self::assertSame('#' . PanelView::IMPORT_MODAL_ID, $action->href);
        self::assertNull($action->modalId);
        self::assertSame(PanelView::IMPORT_MODAL_ID, $action->attributes['data-nowo-ui-dialog-open']);
    }

    private function twig(): Environment
    {
        return new Environment(new ArrayLoader([
            'layouts/admin.html.twig' => '{% block nowo_ui_content %}{% endblock %}',
            'layouts/child.html.twig' => '{% extends "layouts/admin.html.twig" %}',
            'base.html.twig'          => '{% block body %}{% endblock %}',
            'page.html.twig'          => 'page:{{ page }}|{{ path_prefix }}|{{ nav_current }}|{{ nowo_routing_kit_legacy_layout }}{% if role_gate_disabled %}{% endif %}',
        ]));
    }
}
