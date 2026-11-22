<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Panel;

use Nowo\RoutingKitBundle\Panel\HeaderActionsPlacement;
use Nowo\RoutingKitBundle\Panel\PanelView;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\AreaNav;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\Crumb;
use Nowo\UiKitBundle\View\PageHeader;
use PHPUnit\Framework\TestCase;

final class HeaderActionsPlacementTest extends TestCase
{
    public function testButtonsKeepTheActionsAndTheToolbarLayout(): void
    {
        $actions = [Action::create('/new', PanelView::label('panel.new'))];
        $header  = HeaderActionsPlacement::apply(HeaderActionsPlacement::BUTTONS, new PageHeader('T', null, $actions));

        self::assertSame($actions, $header->actions);
        self::assertSame('toolbar', $header->actionsLayout);
    }

    public function testPageActionsUseTheSplitLayoutWithoutAPrimaryButton(): void
    {
        $dialog = new Action('import', PanelView::label('panel.import'), '#modal', attributes: ['data-nowo-ui-dialog-open' => 'modal'], dialogId: 'modal');
        $delete = new Action('delete', 'Delete', '/x', method: 'POST', confirm: new Confirm('Sure?'));
        $header = HeaderActionsPlacement::apply(HeaderActionsPlacement::PAGE_ACTIONS, new PageHeader('T', 'intro', [Action::create('/new', 'New'), $dialog, $delete], [new Crumb('C')], tabs: new AreaNav([], 'nav')));

        self::assertSame('split', $header->actionsLayout);
        self::assertSame('intro', $header->intro);
        self::assertCount(1, $header->breadcrumbs);
        self::assertNotNull($header->tabs);
        self::assertCount(3, $header->actions);
        self::assertSame('create', $header->actions[0]->kind);
        self::assertSame('secondary', $header->actions[0]->resolvedVariant());
        self::assertSame('/new', $header->actions[0]->href);
        self::assertSame($dialog, $header->actions[1], 'Non-primary actions are the same objects.');
        self::assertSame($delete, $header->actions[2]);
    }

    public function testAHeaderWithoutActionsIsLeftAlone(): void
    {
        $header = new PageHeader('T');

        self::assertSame($header, HeaderActionsPlacement::apply(HeaderActionsPlacement::PAGE_ACTIONS, $header));
    }

    public function testDemotedActionKeepsEverythingElse(): void
    {
        $confirm = new Confirm('Sure?');
        $source  = new Action('create', 'New', '/new', method: 'post', csrfToken: 't', confirm: $confirm, icon: 'plus', variant: 'primary', attributes: ['data-testid' => 'x'], csrfField: '_c', modalId: 'm', disabled: true, visible: false, display: 'icon_text', confirmScope: 's', fallbackHref: '/fb', title: 'tip');

        $copy = HeaderActionsPlacement::menuActions([$source])[0];

        self::assertSame('secondary', $copy->variant);
        self::assertSame(['create', 'POST', 't', $confirm, 'plus', ['data-testid' => 'x'], '_c', 'm', true, false, 'icon_text', 's', '/fb', 'tip'], [$copy->kind, $copy->method, $copy->csrfToken, $copy->confirm, $copy->icon, $copy->attributes, $copy->csrfField, $copy->modalId, $copy->disabled, $copy->visible, $copy->display, $copy->confirmScope, $copy->fallbackHref, $copy->title]);
    }
}
