<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Redirect;

/**
 * What a caller asks {@see UrlRedirectManagerInterface::save()} to store (since 1.16.0): plain values, validated by the
 * manager exactly like a panel submission.
 */
final readonly class UrlRedirectData
{
    public function __construct(
        public string $sourcePath,
        public string $targetUrl,
        public int $statusCode = 301,
        public bool $enabled = true,
        public string $note = '',
    ) {
    }
}
