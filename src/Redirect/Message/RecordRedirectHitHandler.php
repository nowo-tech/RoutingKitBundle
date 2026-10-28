<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Redirect\Message;

use Nowo\RoutingKitBundle\Storage\UrlRedirectStorageInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Registered only when `url_redirects.hits.message_bus` is set (the extension removes the service otherwise).
 *
 * Not idempotent: a redelivered message counts the hit again (statistics only; no business state depends on it).
 */
#[AsMessageHandler]
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
