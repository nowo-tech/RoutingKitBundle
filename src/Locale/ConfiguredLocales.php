<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Locale;

use function explode;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function ltrim;
use function str_starts_with;
use function trim;

/**
 * Normalizes nowo_routing_kit.locales: a literal list, or the value of a container parameter / env var
 * ("%app.locales%", "%kernel.enabled_locales%", "%env(json:APP_LOCALES)%", "%env(csv:APP_LOCALES)%").
 *
 * Used at build time (literal list or build-time parameter) and as a service factory when the list comes from an
 * env var, which is only known at runtime.
 */
final class ConfiguredLocales
{
    /**
     * @return list<string> non-empty locale codes, in order, without duplicates
     */
    public static function normalize(mixed $locales): array
    {
        if (is_string($locales)) {
            $decoded = str_starts_with(ltrim($locales), '[') ? json_decode($locales, true) : null;
            $locales = is_array($decoded) ? $decoded : explode(',', $locales);
        }
        if (!is_array($locales)) {
            return [];
        }

        $list = [];
        foreach ($locales as $locale) {
            if (!is_string($locale)) {
                continue;
            }
            $locale = trim($locale);
            if ($locale !== '' && !in_array($locale, $list, true)) {
                $list[] = $locale;
            }
        }

        return $list;
    }
}
