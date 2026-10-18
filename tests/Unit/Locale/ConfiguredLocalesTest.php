<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Locale;

use Nowo\RoutingKitBundle\Locale\ConfiguredLocales;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfiguredLocalesTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, list<string>}>
     */
    public static function provideLocales(): iterable
    {
        yield 'list' => [['es', 'en'], ['es', 'en']];
        yield 'keyed, padded, duplicated, non-string' => [['a' => ' es ', 'b' => 'en', 'c' => 'es', 'd' => '', 'e' => 3], ['es', 'en']];
        yield 'json string' => ['["pt","en"]', ['pt', 'en']];
        yield 'csv string' => ['it, en', ['it', 'en']];
        yield 'empty string' => ['', []];
        yield 'not a list' => [42, []];
        yield 'null' => [null, []];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideLocales')]
    public function testNormalize(mixed $locales, array $expected): void
    {
        self::assertSame($expected, ConfiguredLocales::normalize($locales));
    }
}
