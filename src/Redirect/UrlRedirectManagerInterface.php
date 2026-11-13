<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Redirect;

use Nowo\RoutingKitBundle\Event\UrlRedirectChangedEvent;
use Nowo\RoutingKitBundle\Model\UrlRedirect;
use OutOfBoundsException;
use Throwable;

/**
 * Application service for operator URL redirects (since 1.16.0, REQ-ARCH-001 rules 1, 4, 5): the panel and your own
 * code create, update and delete redirects through it, with the same validation, cache invalidation and
 * {@see UrlRedirectChangedEvent}. The default is {@see UrlRedirectManager}; it exists only with
 * `url_redirects.enabled: true`.
 */
interface UrlRedirectManagerInterface
{
    /**
     * Validation errors for a candidate redirect, without writing anything.
     *
     * @return array<string, string> field (source_path, target_url, status_code, note) => translation key; empty when valid
     */
    public function validate(UrlRedirectData $data, ?string $currentId = null): array;

    /**
     * Creates ($id null) or updates a redirect, then dispatches UrlRedirectChangedEvent (created / updated).
     *
     * @param string|null $actorId user identifier stored as created/updated by and sent in the event
     *
     * @throws InvalidUrlRedirectException when the data is invalid (nothing written)
     * @throws OutOfBoundsException when $id is given and no such redirect exists
     * @throws Throwable when the storage write fails (no event)
     */
    public function save(UrlRedirectData $data, ?string $id = null, ?string $actorId = null): UrlRedirect;

    /**
     * Deletes a redirect and dispatches UrlRedirectChangedEvent (deleted). Returns false when it did not exist.
     */
    public function delete(string $id, ?string $actorId = null): bool;
}
