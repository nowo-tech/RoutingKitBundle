<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Integration\Concurrency;

use Nowo\RoutingKitBundle\Model\UrlRedirect;
use Nowo\RoutingKitBundle\Storage\FilesystemRoutePathStorage;
use Nowo\RoutingKitBundle\Storage\FilesystemUrlRedirectStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

use function dirname;

use const PHP_BINARY;

/**
 * Real races between separate PHP processes (each its own "worker") on the same JSON store: the read-modify-write
 * under the exclusive lock loses no update, keeps the "one row per source / per route+locale" rule, and never leaves
 * a torn file behind.
 */
final class FilesystemStoreRaceTest extends TestCase
{
    private const WORKERS = 4;

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/rk_race_' . uniqid('', true);
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    public function testConcurrentHitCountsAreNotLost(): void
    {
        $file    = $this->dir . '/redirects.json';
        $storage = new FilesystemUrlRedirectStorage($file);
        $storage->save((new UrlRedirect())->setSourcePath('/hot')->setTargetUrl('/new'));

        $lines = $this->race('hits', $file, 25);

        self::assertSame(array_fill(0, self::WORKERS * 25, 'ok'), $lines);
        self::assertSame(self::WORKERS * 25, $storage->findBySource('/hot')?->getHits(), 'No hit lost to a concurrent rewrite.');
    }

    public function testConcurrentInsertsAreAllKept(): void
    {
        $file = $this->dir . '/redirects.json';

        $this->race('distinct', $file, 10);

        self::assertCount(self::WORKERS * 10, (new FilesystemUrlRedirectStorage($file))->all(), 'No row lost to a concurrent rewrite.');
    }

    public function testOnlyOneWriterGetsTheSameSourcePath(): void
    {
        $file  = $this->dir . '/redirects.json';
        $lines = $this->race('same-source', $file, 1);

        self::assertCount(1, array_keys($lines, 'ok', true));
        self::assertCount(self::WORKERS - 1, array_filter($lines, static fn (string $l): bool => str_contains($l, 'already exists')));
        self::assertCount(1, (new FilesystemUrlRedirectStorage($file))->all());
    }

    public function testRoutePathInsertsAreAllKeptAndRouteLocaleStaysUnique(): void
    {
        $file = $this->dir . '/paths.json';
        $this->race('paths', $file, 10);
        self::assertCount(self::WORKERS * 10, (new FilesystemRoutePathStorage($file))->all());

        $same  = $this->dir . '/same.json';
        $lines = $this->race('same-route', $same, 1);
        self::assertCount(1, array_keys($lines, 'ok', true));
        self::assertCount(1, (new FilesystemRoutePathStorage($same))->all());
    }

    /**
     * @return list<string> every worker's outcome lines
     */
    private function race(string $mode, string $file, int $iterations): array
    {
        $go       = $this->dir . '/go-' . $mode;
        $autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
        $script   = __DIR__ . '/App/race-worker.php';

        $workers = [];
        for ($w = 0; $w < self::WORKERS; ++$w) {
            // A hang fails the test after 60 s instead of holding the machine.
            $worker = new Process([PHP_BINARY, $script, $autoload, $go, $mode, $file, (string) $w, (string) $iterations], timeout: 60);
            $worker->start();
            $workers[] = $worker;
        }
        touch($go);

        $lines = [];
        foreach ($workers as $worker) {
            self::assertSame(0, $worker->wait(), 'Worker failed: ' . $worker->getErrorOutput());
            foreach (explode("\n", trim($worker->getOutput())) as $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}
