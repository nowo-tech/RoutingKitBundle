<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Routing;

use Closure;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Routing\Router as FrameworkRouter;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\ResetInterface;

use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function get_debug_type;
use function is_dir;
use function is_file;
use function is_string;
use function random_bytes;
use function rename;
use function rmdir;
use function scandir;
use function tempnam;
use function trim;
use function unlink;

/**
 * Clears / warms the Symfony router cache after CRUD changes and keeps every worker's router in sync.
 *
 * Symfony's router memoizes its route collection, matcher and generator for the lifetime of the
 * process, which in long-running workers (FrankenPHP, RoadRunner, Swoole) spans many requests,
 * with or without `kernel.reset`. Each invalidation therefore writes a new route-table version to
 * a file shared by all workers of the same cache directory; {@see refreshIfStale()} (called on every
 * main request) compares it with the version this worker's router was built from and drops the
 * memoized router state when they differ.
 */
final class RouteCacheInvalidator
{
    public const VERSION_FILE = 'nowo_routing_kit_route_table.version';

    private ?string $appliedVersion = null;

    private bool $versionKnown = false;

    public function __construct(
        private readonly RouterInterface $router,
        private readonly string $cacheDir,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?DbRouteLoader $routeLoader = null,
    ) {
    }

    public function invalidate(): void
    {
        $candidates = [
            $this->cacheDir . '/url_generating_routes.php',
            $this->cacheDir . '/url_generating_routes.php.meta',
            $this->cacheDir . '/url_matching_routes.php',
            $this->cacheDir . '/url_matching_routes.php.meta',
            $this->cacheDir . '/url_generating_routes.php.meta.json',
            $this->cacheDir . '/url_matching_routes.php.meta.json',
        ];

        foreach ($candidates as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        foreach (['url_matching', 'url_generating'] as $subdir) {
            $path = $this->cacheDir . '/' . $subdir;
            if (is_dir($path)) {
                $this->removeDir($path);
            }
        }

        $this->appliedVersion = $this->writeVersion();
        $this->versionKnown   = true;
        $this->resetRouter();

        if ($this->router instanceof WarmableInterface) {
            $this->router->warmUp($this->cacheDir);
        }

        $this->logger?->info('RoutingKit: router cache invalidated.');
    }

    /**
     * Drops the in-memory router state when another worker (or this one) changed the route table
     * since this worker's router was built.
     *
     * The first call of a worker only records the current version: its router is built from the
     * current cache files on first use.
     *
     * @return bool Whether the router state was reset
     */
    public function refreshIfStale(): bool
    {
        $current = $this->readVersion();

        if (!$this->versionKnown) {
            $this->versionKnown   = true;
            $this->appliedVersion = $current;

            return false;
        }

        if ($current === $this->appliedVersion) {
            return false;
        }

        $this->appliedVersion = $current;
        $this->resetRouter();
        $this->logger?->info('RoutingKit: route table changed in another worker, router state reset.');

        return true;
    }

    public function getVersionFile(): string
    {
        return $this->cacheDir . '/' . self::VERSION_FILE;
    }

    private function readVersion(): ?string
    {
        $raw = @file_get_contents($this->getVersionFile());
        if ($raw === false) {
            return null;
        }

        $version = trim($raw);

        return $version !== '' ? $version : null;
    }

    private function writeVersion(): ?string
    {
        $version = bin2hex(random_bytes(16));
        $tmp     = @tempnam($this->cacheDir, 'rkv');
        if ($tmp !== false && @file_put_contents($tmp, $version) !== false && @rename($tmp, $this->getVersionFile())) {
            return $version;
        }

        if ($tmp !== false && is_file($tmp)) {
            @unlink($tmp);
        }
        $this->logger?->warning('RoutingKit: unable to write "{file}", other workers keep their routes until restarted.', ['file' => $this->getVersionFile()]);

        return $this->readVersion();
    }

    private function resetRouter(): void
    {
        $this->routeLoader?->reset();

        if ($this->router instanceof ResetInterface) {
            $this->router->reset();

            return;
        }

        if ($this->router instanceof FrameworkRouter) {
            // Symfony exposes no public API to drop the memoized collection/matcher/generator, nor the
            // per-thread compiled-routes cache used when OPcache is disabled.
            $reset = Closure::bind(static function (FrameworkRouter $router): void {
                foreach (['collection', 'matcher', 'generator'] as $property) {
                    unset($router->{$property});
                }

                $cacheDir = $router->getOption('cache_dir');
                if (is_string($cacheDir)) {
                    unset(self::$cache[$cacheDir . '/url_matching_routes.php'], self::$cache[$cacheDir . '/url_generating_routes.php']);
                }
            }, null, Router::class);
            $reset($this->router);

            return;
        }

        $this->logger?->warning('RoutingKit: router "{class}" cannot be rebuilt in-process; restart workers to apply path changes.', ['class' => get_debug_type($this->router)]);
    }

    private function removeDir(string $dir): void
    {
        $entries = scandir($dir);
        if ($entries === false) {
            return; // @codeCoverageIgnore
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDir($path);
            } elseif (is_file($path)) {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
