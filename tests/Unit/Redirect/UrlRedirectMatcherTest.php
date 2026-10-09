<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Redirect;

use Nowo\RoutingKitBundle\Redirect\Message\RecordRedirectHit;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectMatcher;
use Nowo\RoutingKitBundle\Storage\UrlRedirectStorageInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Cache\CacheInterface;

final class UrlRedirectMatcherTest extends TestCase
{
    public function testMatchesFromCachedMapAndCountsHits(): void
    {
        $storage = $this->createMock(UrlRedirectStorageInterface::class);
        $storage->expects(self::once())->method('enabledMap')->willReturn(['/old' => ['target' => '/new', 'status' => 301]]);
        $storage->expects(self::once())->method('recordHit')->with('/old');
        $cache   = new ArrayAdapter();
        $matcher = new UrlRedirectMatcher($storage, $cache, cacheTtl: 60);

        self::assertSame(['target' => '/new', 'status' => 301], $matcher->match('/old/'));
        self::assertNull($matcher->match('/other'));
        self::assertNull($matcher->match(''));
        $matcher->reset();
        self::assertNotNull($matcher->match('/old'), 'Reloaded from the cache pool, not the storage.');
        $matcher->recordHit('/old/');
    }

    public function testInvalidateDropsCachedMap(): void
    {
        $storage = $this->createMock(UrlRedirectStorageInterface::class);
        $storage->expects(self::exactly(2))->method('enabledMap')->willReturnOnConsecutiveCalls(
            [],
            ['/old' => ['target' => '/new', 'status' => 302]],
        );
        $matcher = new UrlRedirectMatcher($storage, new ArrayAdapter());

        self::assertNull($matcher->match('/old'));
        $matcher->invalidate();
        self::assertSame(['target' => '/new', 'status' => 302], $matcher->match('/old'));
    }

    public function testWithoutCacheReadsStorageOncePerRequest(): void
    {
        $storage = $this->createMock(UrlRedirectStorageInterface::class);
        $storage->expects(self::exactly(2))->method('enabledMap')->willReturn([]);
        $matcher = new UrlRedirectMatcher($storage);

        $matcher->match('/a');
        $matcher->match('/b');
        $matcher->invalidate();
        $matcher->match('/c');
    }

    public function testHitsAreQueuedWhenABusIsWired(): void
    {
        $storage = $this->createMock(UrlRedirectStorageInterface::class);
        $storage->expects(self::never())->method('recordHit');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(self::callback(static fn (object $m): bool => $m instanceof RecordRedirectHit && $m->sourcePath === '/old'))
            ->willReturnCallback(static fn (object $m): Envelope => new Envelope($m));

        (new UrlRedirectMatcher($storage, null, null, $bus))->recordHit('/old/');
    }

    public function testHitTrackingCanBeDisabled(): void
    {
        $storage = $this->createMock(UrlRedirectStorageInterface::class);
        $storage->expects(self::never())->method('recordHit');

        (new UrlRedirectMatcher($storage, trackHits: false))->recordHit('/old');
    }

    public function testFailuresDegradeToNoRedirects(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willThrowException(new RuntimeException('redis down'));
        $cache->method('delete')->willThrowException(new RuntimeException('redis down'));
        $storage = $this->createStub(UrlRedirectStorageInterface::class);
        $storage->method('recordHit')->willThrowException(new RuntimeException('disk full'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(3))->method('warning');
        $matcher = new UrlRedirectMatcher($storage, $cache, $logger);

        self::assertNull($matcher->match('/old'));
        $matcher->recordHit('/old');
        $matcher->invalidate();
    }
}
