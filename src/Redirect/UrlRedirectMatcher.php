<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Redirect;

use DateTimeImmutable;
use Nowo\RoutingKitBundle\Model\UrlRedirect;
use Nowo\RoutingKitBundle\Redirect\Message\RecordRedirectHit;
use Nowo\RoutingKitBundle\Storage\UrlRedirectStorageInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

/**
 * Enabled redirects by source path (REQ-REDIR-004): cached in a PSR-6/Contracts pool for
 * `cache_ttl` seconds and memoized for the current request. Panel writes call {@see invalidate()};
 * writes outside the panel (SQL, backup restore) are picked up when the TTL expires.
 *
 * Every failure (cache, storage, bus) degrades to "no redirect" and a log warning, never a 500.
 *
 * Hit counting without a Messenger bus: the storage write (the filesystem storage takes an exclusive lock and rewrites
 * its file) is deferred to {@see flushHits()} on `kernel.terminate`, after the redirect response is sent, so a hot
 * legacy URL no longer makes every redirected request wait on that lock.
 */
final class UrlRedirectMatcher implements ResetInterface
{
    public const CACHE_KEY = 'nowo_routing_kit.url_redirects.v1';

    public const DEFAULT_CACHE_TTL = 3600;

    /** @var array<string, array{target: string, status: int}>|null */
    private ?array $map = null; // @igor-ignore - Per-request memo cleared by reset() (kernel.reset) and invalidate().

    /** @var list<array{string, DateTimeImmutable}> */
    private array $pendingHits = []; // @igor-ignore - Hits of the current request, written by flushHits() (kernel.terminate / reset()).

    public function __construct(
        private readonly UrlRedirectStorageInterface $storage,
        private readonly ?CacheInterface $cache = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?MessageBusInterface $bus = null,
        private readonly int $cacheTtl = self::DEFAULT_CACHE_TTL,
        private readonly bool $trackHits = true,
        private readonly ?ClockInterface $clock = null,
    ) {
    }

    /**
     * @return array{target: string, status: int}|null
     */
    public function match(string $path): ?array
    {
        $path = UrlRedirect::normalizePath($path);

        return $path === '' ? null : ($this->map()[$path] ?? null);
    }

    public function recordHit(string $path): void
    {
        if (!$this->trackHits) {
            return;
        }

        $path = UrlRedirect::normalizePath($path);
        $now  = $this->clock?->now() ?? new DateTimeImmutable();
        try {
            if ($this->bus instanceof MessageBusInterface) {
                $this->bus->dispatch(new RecordRedirectHit($path, $now));
            } else {
                $this->pendingHits[] = [$path, $now];
            }
        } catch (Throwable $exception) {
            $this->logger?->warning('Redirect hit not recorded: {message}', ['message' => $exception->getMessage()]);
        }
    }

    /**
     * Writes the hits recorded without a bus. Called on `kernel.terminate` (after the response is sent), from
     * {@see reset()} between worker requests and on destruction, so no hit is lost outside HTTP either.
     */
    public function flushHits(): void
    {
        $hits = $this->pendingHits;
        // @igor-ignore - Request buffer emptied here; reset() (kernel.reset) and kernel.terminate both call flushHits().
        $this->pendingHits = [];
        foreach ($hits as [$path, $at]) {
            try {
                $this->storage->recordHit($path, $at);
            } catch (Throwable $exception) {
                $this->logger?->warning('Redirect hit not recorded: {message}', ['message' => $exception->getMessage()]);
            }
        }
    }

    public function invalidate(): void
    {
        $this->map = null;
        if (!$this->cache instanceof CacheInterface) {
            return;
        }

        try {
            $this->cache->delete(self::CACHE_KEY);
        } catch (Throwable $exception) {
            $this->logger?->warning('Redirect cache not cleared: {message}', ['message' => $exception->getMessage()]);
        }
    }

    public function reset(): void
    {
        $this->flushHits();
        $this->map = null;
    }

    // @igor-ignore - Safety net for CLI / non-HTTP use only; in workers kernel.terminate and reset() flush the hits.
    public function __destruct()
    {
        $this->flushHits();
    }

    /**
     * @return array<string, array{target: string, status: int}>
     */
    private function map(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        try {
            if ($this->cache instanceof CacheInterface) {
                /** @var array<string, array{target: string, status: int}> $map */
                $map = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
                    $item->expiresAfter($this->cacheTtl);

                    return $this->storage->enabledMap();
                });
            } else {
                $map = $this->storage->enabledMap();
            }
        } catch (Throwable $exception) {
            $this->logger?->warning('Redirects unavailable: {message}', ['message' => $exception->getMessage()]);
            $map = [];
        }

        return $this->map = $map;
    }
}
