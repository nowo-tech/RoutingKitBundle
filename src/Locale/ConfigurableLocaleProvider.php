<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Locale;

use InvalidArgumentException;

use function array_values;
use function in_array;
use function sprintf;

/**
 * Locales from bundle YAML configuration (default provider).
 */
final class ConfigurableLocaleProvider implements LocaleProviderInterface
{
    /**
     * @param list<string> $locales
     */
    public function __construct(
        private readonly string $defaultLocale,
        private readonly array $locales,
    ) {
        if ($locales === []) {
            throw new InvalidArgumentException('At least one locale is required.');
        }
        if (!in_array($defaultLocale, $locales, true)) {
            throw new InvalidArgumentException(sprintf('Default locale "%s" must be included in locales.', $defaultLocale));
        }
    }

    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    public function getLocales(): array
    {
        return array_values($this->locales);
    }
}
