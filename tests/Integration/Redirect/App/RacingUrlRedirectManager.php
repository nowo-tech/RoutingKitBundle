<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Integration\Redirect\App;

use Nowo\RoutingKitBundle\Model\UrlRedirect;
use Nowo\RoutingKitBundle\Redirect\InvalidUrlRedirectException;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectData;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectManagerInterface;

/**
 * Host manager (aliased onto UrlRedirectManagerInterface) that reproduces the concurrent-write race the panel has to
 * survive: operator B's pre-check sees a free source path, and the write a moment later is refused because operator A
 * took it.
 *
 * `validate()` always answers "valid", as it would for B before A's row existed; `save()` delegates to the real
 * manager, which validates again against the storage A already wrote and throws InvalidUrlRedirectException.
 *
 * With $unknownField set, `save()` reports that refusal on a field name this form does not have — what a host manager
 * with its own rules (a tenant column, a locale) may legitimately do, since the interface is replaceable.
 */
final class RacingUrlRedirectManager implements UrlRedirectManagerInterface
{
    public function __construct(
        private readonly UrlRedirectManagerInterface $inner,
        private readonly ?string $unknownField = null,
    ) {
    }

    public function validate(UrlRedirectData $data, ?string $currentId = null): array
    {
        return [];
    }

    public function save(UrlRedirectData $data, ?string $id = null, ?string $actorId = null): UrlRedirect
    {
        try {
            return $this->inner->save($data, $id, $actorId);
        } catch (InvalidUrlRedirectException $invalid) {
            if ($this->unknownField === null) {
                throw $invalid;
            }

            throw new InvalidUrlRedirectException([$this->unknownField => 'redirects.error.source_duplicate']);
        }
    }

    public function delete(string $id, ?string $actorId = null): bool
    {
        return $this->inner->delete($id, $actorId);
    }
}
