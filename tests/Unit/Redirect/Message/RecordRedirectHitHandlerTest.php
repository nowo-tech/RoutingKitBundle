<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Redirect\Message;

use DateTimeImmutable;
use Nowo\RoutingKitBundle\Redirect\Message\RecordRedirectHit;
use Nowo\RoutingKitBundle\Redirect\Message\RecordRedirectHitHandler;
use Nowo\RoutingKitBundle\Storage\UrlRedirectStorageInterface;
use PHPUnit\Framework\TestCase;

final class RecordRedirectHitHandlerTest extends TestCase
{
    public function testRecordsHitInStorage(): void
    {
        $at      = new DateTimeImmutable();
        $storage = $this->createMock(UrlRedirectStorageInterface::class);
        $storage->expects(self::once())->method('recordHit')->with('/old', $at);

        (new RecordRedirectHitHandler($storage))(new RecordRedirectHit('/old', $at));
    }
}
