<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Redirect;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Hot-path lookup of operator URL redirects (since 1.16.0, REQ-ARCH-001 rule 1). The default is
 * {@see UrlRedirectMatcher} (cached enabled map, buffered hit counting). reset() (symfony/contracts) is the request
 * boundary: write the pending hits and drop the memoised map.
 */
interface UrlRedirectMatcherInterface extends ResetInterface
{
    /**
     * The enabled redirect for a request path, or null.
     *
     * @return array{target: string, status: int}|null
     */
    public function match(string $path): ?array;

    /**
     * Counts one hit for a matched source path (buffered or sent to the hit bus when hit counting is enabled).
     */
    public function recordHit(string $path): void;

    /**
     * Writes the buffered hits.
     */
    public function flushHits(): void;

    /**
     * Drops the cached map: call it after every write to the redirect storage.
     */
    public function invalidate(): void;
}
