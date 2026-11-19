<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Concurrency;

use Nowo\RoutingKitBundle\Concurrency\StorageWriteLedger;
use PHPUnit\Framework\TestCase;

final class StorageWriteLedgerTest extends TestCase
{
    public function testCountsMonotonically(): void
    {
        $ledger = new StorageWriteLedger();
        self::assertSame(0, $ledger->count());
        $ledger->record();
        $ledger->record();
        self::assertSame(2, $ledger->count());
    }
}
