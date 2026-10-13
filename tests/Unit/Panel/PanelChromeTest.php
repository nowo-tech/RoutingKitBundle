<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Panel;

use Nowo\RoutingKitBundle\Panel\PanelChrome;
use PHPUnit\Framework\TestCase;

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

    public function testKeyListsHaveNoDuplicates(): void
    {
        self::assertSame(PanelChrome::CLASS_KEYS, array_values(array_unique(PanelChrome::CLASS_KEYS)));
        self::assertContains('modal_shell', PanelChrome::PARTIAL_KEYS);
    }
}
