<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Redirect;

use ArrayObject;
use Nowo\RoutingKitBundle\Redirect\Message\RecordRedirectHit;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectMatcher;
use Nowo\RoutingKitBundle\Storage\UrlRedirectStorageInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
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
        $matcher->flushHits();
    }

    public function testHitsWithoutABusAreWrittenOnFlushNotDuringTheRequest(): void
    {
        /** @var ArrayObject<int, string> $writes */
        $writes  = new ArrayObject();
        $written = static fn (): array => $writes->getArrayCopy();
        $storage = $this->createMock(UrlRedirectStorageInterface::class);
        $storage->method('recordHit')->willReturnCallback(static function (string $path) use ($writes): void {
            $writes->append($path);
        });
        $matcher = new UrlRedirectMatcher($storage);

        $matcher->recordHit('/a/');
        $matcher->recordHit('/b');
        self::assertSame([], $written(), 'no storage write (file lock) before the response is sent');

        $matcher->flushHits();
        self::assertSame(['/a', '/b'], $written());
        $matcher->flushHits();
        self::assertSame(['/a', '/b'], $written(), 'flushed hits are not written twice');

        $matcher->recordHit('/c');
        $matcher->reset();
        self::assertSame(['/a', '/b', '/c'], $written(), 'reset() (worker, between requests) flushes');

        $matcher->recordHit('/d');
        unset($matcher);
        self::assertSame(['/a', '/b', '/c', '/d'], $written(), 'destruction flushes (CLI / no kernel.terminate)');
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

    public function testHitTimeComesFromTheInjectedClock(): void
    {
        $storage = $this->createStub(UrlRedirectStorageInterface::class);
        $bus     = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(self::callback(static fn (object $m): bool => $m instanceof RecordRedirectHit && $m->at->format('Y-m-d H:i:s') === '2026-10-28 12:00:00'))
            ->willReturnCallback(static fn (object $m): Envelope => new Envelope($m));

        (new UrlRedirectMatcher($storage, null, null, $bus, clock: new MockClock('2026-10-28 12:00:00')))->recordHit('/old');
    }

    public function testABusFailureIsLoggedNotThrown(): void
    {
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willThrowException(new RuntimeException('transport down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        (new UrlRedirectMatcher($this->createStub(UrlRedirectStorageInterface::class), null, $logger, $bus))->recordHit('/old');
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
        $matcher->flushHits();
        $matcher->invalidate();
    }
}
