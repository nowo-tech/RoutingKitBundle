<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Routing;

use function preg_match;
use function str_contains;
use function str_starts_with;
use function strtolower;

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

        // C0 controls + DEL (header / log injection).
        if (preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            return false;
        }

        if (str_contains($path, '\\') || str_contains($path, '://')) {
            return false;
        }

        $lower = strtolower($path);
        if (str_contains($lower, '%2f%2f')
            || str_contains($lower, '%252f%252f')
            || str_contains($lower, '%5c')
            || str_contains($lower, '%00')
            || str_contains($lower, '%0d')
            || str_contains($lower, '%0a')
            || str_contains($lower, '%09')
        ) {
            return false;
        }

        // Reject scheme-relative leftovers and opaque "http:…" / "javascript:…" segments.
        if (preg_match('#/(?:[a-z][a-z0-9+.-]*):#i', $path) === 1) {
            return false;
        }

        // Reject ".." path segments (traversal-shaped public paths).
        return preg_match('#(?:^|/)\.\.(?:/|$)#', $path) !== 1;
    }

    /**
     * Targets used in RedirectResponse Location headers (no scheme-relative).
     */
    public static function isSafeRedirectTarget(string $path): bool
    {
        return self::isSafeStoredPath($path);
    }
}
