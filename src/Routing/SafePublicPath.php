<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Routing;

use function preg_match;
use function str_contains;
use function str_starts_with;

/**
 * Hardens stored / redirect paths against open redirects and control characters.
 */
final class SafePublicPath
{
    public static function isSafeStoredPath(string $path): bool
    {
        if ($path === '' || !str_starts_with($path, '/')) {
            return false;
        }

        if (str_starts_with($path, '//')) {
            return false;
        }

        if (str_contains($path, '\\')
            || str_contains($path, "\0")
            || str_contains($path, "\r")
            || str_contains($path, "\n")
            || str_contains($path, "\t")
            || str_contains($path, '://')
            || str_contains(strtolower($path), '%2f%2f')
        ) {
            return false;
        }

        // Reject scheme-relative leftovers and opaque "http:…" segments.
        return preg_match('#/(?:[a-z][a-z0-9+.-]*):#i', $path) !== 1

        ;
    }

    /**
     * Targets used in RedirectResponse Location headers (no scheme-relative).
     */
    public static function isSafeRedirectTarget(string $path): bool
    {
        return self::isSafeStoredPath($path);
    }
}
