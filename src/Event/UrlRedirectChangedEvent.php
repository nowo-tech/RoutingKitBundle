<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Event;

use DateTimeImmutable;

/**
 * A URL redirect was created, updated or deleted from the panel (since 1.15). Dispatched after the storage write
 * succeeded, never when validation or the write failed. Scalars only: a host writes its audit or projection from it.
 */
final readonly class UrlRedirectChangedEvent
{
    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DELETED = 'deleted';

    /**
     * @param string $redirectId Storage id of the redirect
     * @param string $sourcePath Source path of the redirect (as stored)
     * @param self::ACTION_* $action What happened
     * @param string|null $actorId User identifier of the panel user (null when unauthenticated access is allowed)
     * @param DateTimeImmutable $occurredAt When the write happened
     */
    public function __construct(
        public string $redirectId,
        public string $sourcePath,
        public string $action,
        public ?string $actorId,
        public DateTimeImmutable $occurredAt,
    ) {
    }

    public function isDeleted(): bool
    {
        return $this->action === self::ACTION_DELETED;
    }
}
