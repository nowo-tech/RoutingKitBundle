<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Redirect;

use Nowo\RoutingKitBundle\Locale\LocaleProviderInterface;

use function array_map;
use function explode;
use function in_array;
use function rtrim;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;

/**
 * Back-office, authentication and technical paths that operator redirects can never take over
 * (REQ-REDIR-002), with or without a leading `/{locale}` segment from the locale provider.
 *
 * - `prefixes` match a whole segment: `/admin` covers `/admin` and `/admin/x`, not `/administration`.
 * - `starts` match a raw string prefix: `/_` covers `/_profiler`, `/_routing`, `/_wdt`…
 * - `/` (site root) is always protected.
 *
 * Comparison is case-insensitive (`/ADMIN/x` is protected). A bare `/{locale}` is not stripped:
 * locale homes are refused as live pages instead, and short links such as `/ig` stay usable.
 */
final class ProtectedPaths
{
    /** @var list<string> */
    public const DEFAULT_PREFIXES = [
        '/admin', '/login', '/logout', '/register', '/reset-password', '/api', '/health',
        '/build', '/bundles', '/media', '/.well-known',
    ];

    /** @var list<string> */
    public const DEFAULT_STARTS = ['/_', '/robots.txt', '/sitemap', '/favicon'];

    /** @var list<string> */
    private readonly array $prefixes;

    /** @var list<string> */
    private readonly array $starts;

    /**
     * @param list<string> $prefixes
     * @param list<string> $starts
     */
    public function __construct(
        private readonly LocaleProviderInterface $locales,
        array $prefixes = self::DEFAULT_PREFIXES,
        array $starts = self::DEFAULT_STARTS,
    ) {
        $this->prefixes = array_map(static fn (string $p): string => rtrim(strtolower($p), '/'), $prefixes);
        $this->starts   = array_map(strtolower(...), $starts);
    }

    public function isProtected(string $path): bool
    {
        if ($path === '' || $path === '/') {
            return true;
        }

        $path = strtolower($path);
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        foreach ($this->candidates($path) as $candidate) {
            foreach ($this->prefixes as $prefix) {
                if ($prefix !== '' && ($candidate === $prefix || str_starts_with($candidate, $prefix . '/'))) {
                    return true;
                }
            }
            foreach ($this->starts as $start) {
                if ($start !== '' && str_starts_with($candidate, $start)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The path itself, plus the path without its `/{locale}` segment when it has one and more follows.
     *
     * @return list<string>
     */
    private function candidates(string $path): array
    {
        $candidates = [$path];
        $segment    = explode('/', $path, 3)[1];
        $rest       = substr($path, 1 + strlen($segment));
        if ($rest !== '' && $rest !== '/' && $this->isLocale($segment)) {
            $candidates[] = $rest;
        }

        return $candidates;
    }

    private function isLocale(string $segment): bool
    {
        $locales = array_map(static fn (string $l): string => strtolower(str_replace('_', '-', $l)), $this->locales->getLocales());

        return in_array(str_replace('_', '-', $segment), $locales, true);
    }
}
