<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Locale;

use InvalidArgumentException;
use Nowo\RoutingKitBundle\Locale\ConfigurableLocaleProvider;
use PHPUnit\Framework\TestCase;

final class ConfigurableLocaleProviderTest extends TestCase
{
    public function testProvidesDefaultAndList(): void
    {
        $provider = new ConfigurableLocaleProvider('en', ['en', 'es', 'fr']);
        self::assertSame('en', $provider->getDefaultLocale());
        self::assertSame(['en', 'es', 'fr'], $provider->getLocales());
    }

    public function testRejectsDefaultOutsideList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ConfigurableLocaleProvider('de', ['en', 'es']);
    }

    public function testRejectsEmptyLocales(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one locale is required.');

        new ConfigurableLocaleProvider('en', []);
    }
}
