<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Routing;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\RouterInterface;

use function is_dir;
use function is_file;
use function rmdir;
use function scandir;
use function unlink;

/**
 * Clears / warms the Symfony router cache after CRUD changes.
 */
final class RouteCacheInvalidator
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly string $cacheDir,
        private readonly ?LoggerInterface $logger = null,
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

        if ($this->router instanceof WarmableInterface) {
            $this->router->warmUp($this->cacheDir);
        }

        $this->logger?->info('RoutingKit: router cache invalidated.');
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
