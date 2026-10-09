<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Redirect\Message;

use DateTimeImmutable;

/**
 * One redirect hit, counted by a Messenger worker when `url_redirects.hits.message_bus` is set:
 * bots hammering a legacy URL must not turn every request into a locking write.
 *
 * Route it to an async transport in the host (`framework.messenger.routing`); unrouted messages
 * are handled synchronously.
 */
final class RecordRedirectHit
{
    public function __construct(
        public readonly string $sourcePath,
        public readonly DateTimeImmutable $at,
    ) {
    }
}
