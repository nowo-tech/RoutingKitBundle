<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Concurrency;

use Nowo\RoutingKitBundle\EventSubscriber\ConcurrencyConflictSubscriber;

/**
 * Counts the writes the bundle's filesystem stores have made durable (after the atomic rename) in this process.
 *
 * Monotonic and read by difference only: {@see ConcurrencyConflictSubscriber}
 * takes a mark at the main kernel.request and compares at kernel.exception, so a conflict met after an earlier write of
 * the same request answers CONFLICT_002 (REQ-RUNTIME-003 rule 6, request-level detection). It never needs a reset in a
 * FrankenPHP worker with kernel reset off. A host storage (`storage.path_storage`, `url_redirects.storage`) may call
 * {@see record()} after each commit to get the same detection.
 */
final class StorageWriteLedger
{
    private int $count = 0;

    public function record(): void
    {
        // @igor-ignore - Monotonic counter, read by difference against a per-request mark; never reset (see class doc).
        ++$this->count;
    }

    public function count(): int
    {
        return $this->count;
    }
}
