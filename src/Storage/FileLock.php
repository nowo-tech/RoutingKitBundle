<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Storage;

use RuntimeException;

use function fclose;
use function flock;
use function fopen;
use function hrtime;
use function max;
use function min;
use function sprintf;
use function usleep;

use const LOCK_EX;
use const LOCK_NB;
use const LOCK_SH;
use const LOCK_UN;

/**
 * Bounded flock() for the filesystem stores (REQ-RUNTIME-003 rule 9: locks have timeouts).
 *
 * A non-blocking attempt is retried with a short, growing pause (1 ms doubling to 50 ms) until the deadline; then
 * {@see StorageLockTimeoutException}. A timeout of 0 tries once. A failure that is not "would block" (an unsupported
 * filesystem, an I/O error) is a RuntimeException, never a conflict.
 *
 * flock() is advisory and local to one kernel: it does not exclude writers on another host, and most network
 * filesystems (NFS, SMB, many FUSE mounts) either ignore it or emulate it unreliably. See docs/CONCURRENCY.md.
 *
 * Stateless.
 *
 * @internal
 */
final class FileLock
{
    private const FIRST_PAUSE_US = 1_000;

    private const MAX_PAUSE_US = 50_000;

    /**
     * @param LOCK_EX|LOCK_SH $operation
     *
     * @throws StorageLockTimeoutException when the lock is still held by someone else after $timeoutSeconds
     * @throws RuntimeException when the lock file cannot be opened or locked for another reason
     *
     * @return resource the locked handle; pass it to {@see release()}
     */
    public static function acquire(string $lockPath, int $operation, float $timeoutSeconds)
    {
        $handle = @fopen($lockPath, 'c+');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Unable to open storage lock "%s".', $lockPath)); // @codeCoverageIgnore
        }

        $deadline = hrtime(true) + (int) (max(0.0, $timeoutSeconds) * 1e9);
        $pause    = self::FIRST_PAUSE_US;

        while (true) {
            $wouldBlock = 0;
            if (flock($handle, $operation | LOCK_NB, $wouldBlock)) {
                return $handle;
            }

            if ($wouldBlock !== 1) {
                fclose($handle); // @codeCoverageIgnore

                throw new RuntimeException(sprintf('Unable to lock storage "%s".', $lockPath)); // @codeCoverageIgnore
            }

            $left = $deadline - hrtime(true);
            if ($left <= 0) {
                fclose($handle);

                throw new StorageLockTimeoutException($lockPath, $timeoutSeconds);
            }

            usleep(max(1, min($pause, (int) ($left / 1000))));
            $pause = min(self::MAX_PAUSE_US, $pause * 2);
        }
    }

    /**
     * @param resource $handle
     */
    public static function release($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
