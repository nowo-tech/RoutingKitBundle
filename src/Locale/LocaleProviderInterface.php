<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Locale;

interface LocaleProviderInterface
{
    public function getDefaultLocale(): string;

    /**
     * @return list<string>
     */
    public function getLocales(): array;
}
