<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Model;

use DateTimeImmutable;
use Nowo\RoutingKitBundle\Model\UrlRedirect;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UrlRedirectTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function paths(): iterable
    {
        yield 'trailing slash' => ['/Old-Page/', '/Old-Page'];
        yield 'query dropped' => ['/a?b=1', '/a'];
        yield 'fragment dropped' => ['/a#top', '/a'];
        yield 'missing slash' => ['old', '/old'];
        yield 'root' => ['/', '/'];
        yield 'blank' => ['  ', ''];
        yield 'unparsable' => ['http:///', ''];
        yield 'percent-encoded' => ['/podolog%C3%ADa/', '/podología'];
        yield 'already decoded' => ['/podología', '/podología'];
        yield 'malformed triple slash' => ['///x', ''];
    }

    #[DataProvider('paths')]
    public function testNormalizesPaths(string $input, string $expected): void
    {
        self::assertSame($expected, UrlRedirect::normalizePath($input));
    }

    public function testInternalTargets(): void
    {
        foreach (['/contact', '/es/blog/x?a=1', '/'] as $target) {
            self::assertTrue(UrlRedirect::isInternalTarget($target), $target);
        }
        foreach (['//evil.example', '/\\evil.example', '/%5Cevil.example', '/%2F/evil.example', "/a\nb", 'https://evil.example', 'contact'] as $target) {
            self::assertFalse(UrlRedirect::isInternalTarget($target), $target);
        }
    }

    public function testExternalTargets(): void
    {
        foreach (['https://example.com', 'http://example.com/a?b=1', 'HTTPS://example.com/x'] as $target) {
            self::assertTrue(UrlRedirect::isExternalTarget($target), $target);
        }
        foreach (['javascript:alert(1)', 'https://', 'https:///x', 'https://a b', 'https://ex\\ample', "https://example.com/\n", 'ftp://example.com', '/x'] as $target) {
            self::assertFalse(UrlRedirect::isExternalTarget($target), $target);
        }
    }

    public function testAccessors(): void
    {
        $at       = new DateTimeImmutable('2026-01-02 03:04:05');
        $redirect = (new UrlRedirect())
            ->setSourcePath('/a/')
            ->setTargetUrl(' https://example.com/b ')
            ->setStatusCode(302)
            ->setEnabled(false)
            ->setNote(' moved ')
            ->setHits(3)
            ->setLastHitAt($at)
            ->setCreatedAt($at)
            ->setUpdatedAt($at)
            ->setCreatedBy('alice')
            ->setUpdatedBy('bob');

        self::assertNull($redirect->getId());
        self::assertSame('x', $redirect->setId('x')->getId());
        self::assertSame(['/a', 'https://example.com/b', 302, false, 'moved', 3, 'alice', 'bob'], [
            $redirect->getSourcePath(), $redirect->getTargetUrl(), $redirect->getStatusCode(), $redirect->isEnabled(),
            $redirect->getNote(), $redirect->getHits(), $redirect->getCreatedBy(), $redirect->getUpdatedBy(),
        ]);
        self::assertSame($at, $redirect->getLastHitAt());
        self::assertSame($at, $redirect->getCreatedAt());
        self::assertSame($at, $redirect->getUpdatedAt());
    }

    public function testArrayRoundTrip(): void
    {
        $at       = new DateTimeImmutable('2026-01-02T03:04:05+00:00');
        $redirect = (new UrlRedirect('rd_1'))
            ->setSourcePath('/a')
            ->setTargetUrl('/b')
            ->setStatusCode(308)
            ->setHits(2)
            ->setLastHitAt($at)
            ->setCreatedBy('alice');

        $copy = UrlRedirect::fromArray($redirect->toArray());

        self::assertEquals($redirect->toArray(), $copy->toArray());
        self::assertSame('rd_1', $copy->getId());
    }

    public function testFromArrayToleratesGarbage(): void
    {
        $redirect = UrlRedirect::fromArray([
            'id'          => '',
            'source_path' => 42,
            'status_code' => 200,
            'enabled'     => 'yes',
            'hits'        => -3,
            'last_hit_at' => 'not a date',
            'created_at'  => '',
        ]);

        self::assertNull($redirect->getId());
        self::assertSame('', $redirect->getSourcePath());
        self::assertSame(301, $redirect->getStatusCode());
        self::assertTrue($redirect->isEnabled());
        self::assertSame(0, $redirect->getHits());
        self::assertNull($redirect->getLastHitAt());
        self::assertNull($redirect->getCreatedAt());
    }
}
