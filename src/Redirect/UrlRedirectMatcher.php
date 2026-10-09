<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Redirect;

use DateTimeImmutable;
use Nowo\RoutingKitBundle\Model\UrlRedirect;
use Nowo\RoutingKitBundle\Redirect\Message\RecordRedirectHit;
use Nowo\RoutingKitBundle\Storage\UrlRedirectStorageInterface;
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
 */
final class UrlRedirectMatcher implements ResetInterface
{
    public const CACHE_KEY = 'nowo_routing_kit.url_redirects.v1';

    public const DEFAULT_CACHE_TTL = 3600;

    /** @var array<string, array{target: string, status: int}>|null */
    private ?array $map = null; // @igor-ignore - Per-request memo cleared by reset() (kernel.reset) and invalidate().

    public function __construct(
        private readonly UrlRedirectStorageInterface $storage,
        private readonly ?CacheInterface $cache = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?MessageBusInterface $bus = null,
        private readonly int $cacheTtl = self::DEFAULT_CACHE_TTL,
        private readonly bool $trackHits = true,
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
        $now  = new DateTimeImmutable();
        try {
            if ($this->bus instanceof MessageBusInterface) {
                $this->bus->dispatch(new RecordRedirectHit($path, $now));
            } else {
                $this->storage->recordHit($path, $now);
            }
        } catch (Throwable $exception) {
            $this->logger?->warning('Redirect hit not recorded: {message}', ['message' => $exception->getMessage()]);
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
        $this->map = null;
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
