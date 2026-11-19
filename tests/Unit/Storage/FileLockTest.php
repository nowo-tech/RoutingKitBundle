<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Storage;

use Nowo\RoutingKitBundle\Storage\FileLock;
use Nowo\RoutingKitBundle\Storage\StorageLockTimeoutException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

use const LOCK_EX;
use const LOCK_SH;
use const PHP_BINARY;

/**
 * flock() locks belong to the open file description, so a second fopen() of the same file in this process contends
 * with the first exactly as another worker would.
 */
final class FileLockTest extends TestCase
{
    private string $lock;

    protected function setUp(): void
    {
        $this->lock = sys_get_temp_dir() . '/rk_filelock_' . uniqid('', true) . '.lock';
    }

    protected function tearDown(): void
    {
        @unlink($this->lock);
    }

    public function testFreeLockIsTakenAndReleased(): void
    {
        $handle = FileLock::acquire($this->lock, LOCK_EX, 1.0);
        FileLock::release($handle);

        // Released: the next exclusive lock is immediate even with no wait at all.
        FileLock::release(FileLock::acquire($this->lock, LOCK_EX, 0.0));
        $this->addToAssertionCount(1);
    }

    public function testHeldExclusiveLockTimesOutAfterTheConfiguredWait(): void
    {
        $holder = $this->hold(LOCK_EX);

        $start = hrtime(true);
        try {
            FileLock::acquire($this->lock, LOCK_SH, 0.1);
            self::fail('A held exclusive lock must time out.');
        } catch (StorageLockTimeoutException $timeout) {
            $waited = (hrtime(true) - $start) / 1e9;
            self::assertGreaterThanOrEqual(0.09, $waited, 'It waited for the deadline.');
            self::assertLessThan(2.0, $waited, 'It did not block indefinitely.');
            self::assertSame($this->lock, $timeout->lockPath);
            self::assertSame(0.1, $timeout->timeoutSeconds);
            self::assertSame(StorageLockTimeoutException::MESSAGE, $timeout->getMessage());
            self::assertStringNotContainsString($this->lock, $timeout->getMessage(), 'No storage detail in the message.');
            self::assertNull($timeout->retryAfterSeconds());
        } finally {
            FileLock::release($holder);
        }
    }

    public function testZeroTimeoutTriesOnce(): void
    {
        $holder = $this->hold(LOCK_EX);

        $start = hrtime(true);
        try {
            $this->expectException(StorageLockTimeoutException::class);
            FileLock::acquire($this->lock, LOCK_EX, 0.0);
        } finally {
            self::assertLessThan(0.5, (hrtime(true) - $start) / 1e9);
            FileLock::release($holder);
        }
    }

    public function testSharedLocksDoNotExcludeEachOtherButExcludeWriters(): void
    {
        $reader = $this->hold(LOCK_SH);

        try {
            FileLock::release(FileLock::acquire($this->lock, LOCK_SH, 0.0));

            $this->expectException(StorageLockTimeoutException::class);
            FileLock::acquire($this->lock, LOCK_EX, 0.02);
        } finally {
            FileLock::release($reader);
        }
    }

    /**
     * Another process holds the lock for a while and exits: the waiter gets it once it is free, before its deadline.
     */
    public function testLockReleasedByAnotherProcessIsTakenBeforeTheDeadline(): void
    {
        $ready = $this->lock . '.ready';
        $code  = '$h = fopen($argv[1], "c+"); flock($h, LOCK_EX); touch($argv[2]); usleep(300000); flock($h, LOCK_UN);';
        $other = new Process([PHP_BINARY, '-r', $code, '--', $this->lock, $ready], timeout: 10);
        $other->start();

        try {
            $until = microtime(true) + 10;
            while (!is_file($ready) && microtime(true) < $until && $other->isRunning()) {
                usleep(5_000);
            }
            self::assertFileExists($ready, 'The other process holds the lock: ' . $other->getErrorOutput());

            $start  = hrtime(true);
            $handle = FileLock::acquire($this->lock, LOCK_EX, 10.0);
            $waited = (hrtime(true) - $start) / 1e9;
            FileLock::release($handle);

            self::assertGreaterThan(0.1, $waited, 'It waited for the other process.');
            self::assertLessThan(5.0, $waited, 'It took the lock as soon as it was free.');
        } finally {
            $other->stop(1);
            @unlink($ready);
        }
    }

    /**
     * @param LOCK_EX|LOCK_SH $operation
     *
     * @return resource
     */
    private function hold(int $operation)
    {
        $handle = fopen($this->lock, 'c+');
        self::assertIsResource($handle);
        self::assertTrue(flock($handle, $operation));

        return $handle;
    }
}
