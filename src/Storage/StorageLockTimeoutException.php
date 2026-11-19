<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Storage;

use Nowo\RoutingKitBundle\EventSubscriber\ConcurrencyConflictSubscriber;
use Nowo\TransactionContract\TransientStorageFailure;
use Throwable;

/**
 * The storage lock (`{file}.lock`) was still held by another request or worker when `storage.lock_timeout` ran out
 * (REQ-RUNTIME-003 rule 9). Nothing was read or written by the call that threw: retrying it later is safe.
 *
 * A {@see TransientStorageFailure} (and so a RuntimeException): the bundle's admin routes answer it with 409 +
 * `X-Conflict-Code` ({@see ConcurrencyConflictSubscriber}); a Messenger handler
 * lets it propagate so the message is retried. The message is generic on purpose; the lock path is kept in
 * {@see $lockPath} for the logs only.
 */
final class StorageLockTimeoutException extends TransientStorageFailure
{
    public const MESSAGE = 'The routing storage is locked by another request. Retry later.';

    public function __construct(
        public readonly string $lockPath,
        public readonly float $timeoutSeconds,
        ?Throwable $previous = null,
    ) {
        parent::__construct(null, self::MESSAGE, $previous);
    }
}
