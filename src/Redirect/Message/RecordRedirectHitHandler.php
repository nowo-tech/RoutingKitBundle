<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Redirect\Message;

use Nowo\RoutingKitBundle\Storage\UrlRedirectStorageInterface;

/**
 * Tagged `messenger.message_handler` by the extension only when a hit bus is configured.
 */
final class RecordRedirectHitHandler
{
    public function __construct(
        private readonly UrlRedirectStorageInterface $storage,
    ) {
    }

    public function __invoke(RecordRedirectHit $message): void
    {
        $this->storage->recordHit($message->sourcePath, $message->at);
    }
}
