<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Panel;

use Nowo\RoutingKitBundle\Panel\PanelChrome;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function array_unique;
use function array_values;

final class PanelChromeTest extends TestCase
{
    public function testUnknownKeysListsOnlyKeysOutsideTheAllowList(): void
    {
        self::assertSame([], PanelChrome::unknownKeys(['table' => 'a', 'btn_primary' => 'b'], PanelChrome::CLASS_KEYS));
        self::assertSame(['nope', '0'], PanelChrome::unknownKeys(['table' => 'a', 'nope' => 'b', 0 => 'c'], PanelChrome::CLASS_KEYS));
    }

    public function testCleanTrimsAndDropsEmptyOrNonStringValues(): void
    {
        self::assertSame(['table' => 'data-table'], PanelChrome::clean(['table' => ' data-table ', 'th' => '  ', 'td' => null, 0 => 'x', 'row' => 1]));
    }

    public function testThemeBridgeMapsTheOneXKeysToUiKitSlotsAndPartials(): void
    {
        self::assertSame([
            'classes' => [
                'table'          => ['value' => 'data-table', 'framework_defaults' => false],
                'action_danger'  => 'link-danger',
                'table_head_row' => ['value' => 'head', 'framework_defaults' => false],
            ],
            'partials' => [
                'tabs'       => 'kit/_tabs.html.twig',
                'pagination' => 'kit/_pagination.html.twig',
            ],
        ], PanelChrome::themeBridge(
            ['table' => ' data-table ', 'action_delete' => 'link-danger', 'thead_row' => 'head', 'results' => 'dropped', 'th' => ''],
            ['nav' => 'kit/_tabs.html.twig', 'pagination' => 'kit/_pagination.html.twig', 'modal_shell' => 'ignored.html.twig'],
        ));
        self::assertSame([], PanelChrome::themeBridge([], []));
    }

    public function testEveryOneXKeyHasABridgeEntry(): void
    {
        self::assertSame(PanelChrome::CLASS_KEYS, array_keys(PanelChrome::CLASS_SLOTS));
        self::assertSame(PanelChrome::PARTIAL_KEYS, array_keys(PanelChrome::PARTIAL_NAMES));
    }

    public function testKeyListsHaveNoDuplicates(): void
    {
        self::assertSame(PanelChrome::CLASS_KEYS, array_values(array_unique(PanelChrome::CLASS_KEYS)));
        self::assertContains('modal_shell', PanelChrome::PARTIAL_KEYS);
    }
}
