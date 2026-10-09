<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Storage;

use DateTimeImmutable;
use Nowo\RoutingKitBundle\Model\UrlRedirect;
use Nowo\RoutingKitBundle\Storage\FilesystemUrlRedirectStorage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FilesystemUrlRedirectStorageTest extends TestCase
{
    private string $dir;

    private string $file;

    protected function setUp(): void
    {
        $this->dir  = sys_get_temp_dir() . '/rk_redirects_' . uniqid('', true);
        $this->file = $this->dir . '/nested/redirects.json';
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->dir . '/nested/*') as $f) {
            @unlink((string) $f);
        }
        @rmdir($this->dir . '/nested');
        @rmdir($this->dir);
    }

    public function testCrudOrderingMapAndHits(): void
    {
        $storage = new FilesystemUrlRedirectStorage($this->file);
        self::assertSame([], $storage->all());
        self::assertSame([], $storage->enabledMap());

        $b = $storage->save((new UrlRedirect())->setSourcePath('/b')->setTargetUrl('/new-b'));
        $a = $storage->save((new UrlRedirect())->setSourcePath('/podología')->setTargetUrl('https://example.com')->setStatusCode(302));
        $storage->save((new UrlRedirect())->setSourcePath('/a-off')->setTargetUrl('/x')->setEnabled(false));

        self::assertNotNull($b->getId());
        self::assertStringStartsWith('rd_', $b->getId());
        self::assertSame(['/a-off', '/b', '/podología'], array_map(static fn (UrlRedirect $r): string => $r->getSourcePath(), $storage->all()));
        self::assertSame([
            '/b'         => ['target' => '/new-b', 'status' => 301],
            '/podología' => ['target' => 'https://example.com', 'status' => 302],
        ], $storage->enabledMap());
        self::assertStringContainsString('/podología', (string) file_get_contents($this->file), 'Unicode kept readable.');

        self::assertSame('/b', $storage->findById($b->getId())?->getSourcePath());
        self::assertNull($storage->findById('missing'));
        self::assertSame($a->getId(), $storage->findBySource('/podología')?->getId());
        self::assertNull($storage->findBySource('/nope'));

        $at = new DateTimeImmutable('2026-05-06T07:08:09+00:00');
        $storage->recordHit('/b', $at);
        $storage->recordHit('/b', $at);
        $storage->recordHit('/unknown', $at);
        $reloaded = $storage->findBySource('/b');
        self::assertSame(2, $reloaded?->getHits());
        self::assertEquals($at, $reloaded->getLastHitAt());

        $b->setTargetUrl('/newer');
        $storage->save($b);
        self::assertSame('/newer', $storage->findBySource('/b')?->getTargetUrl());

        $storage->delete($b->getId());
        $storage->delete('missing');
        self::assertNull($storage->findBySource('/b'));
        self::assertCount(2, $storage->all());
    }

    public function testRejectsDuplicateSource(): void
    {
        $storage = new FilesystemUrlRedirectStorage($this->file);
        $storage->save((new UrlRedirect())->setSourcePath('/old')->setTargetUrl('/a'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already exists');
        $storage->save((new UrlRedirect())->setSourcePath('/old/')->setTargetUrl('/b'));
    }

    public function testEmptyFileAndSkippedRows(): void
    {
        mkdir($this->dir . '/nested', 0777, true);
        file_put_contents($this->file, '');
        $storage = new FilesystemUrlRedirectStorage($this->file);
        self::assertSame([], $storage->all());

        file_put_contents($this->file, json_encode(['junk', ['source_path' => '/no-id'], ['id' => 'rd_1', 'source_path' => '/ok', 'target_url' => '/t']]));
        self::assertCount(1, $storage->all());
    }

    public function testCorruptJsonFailsClosed(): void
    {
        mkdir($this->dir . '/nested', 0777, true);
        file_put_contents($this->file, '{not json');
        $storage = new FilesystemUrlRedirectStorage($this->file);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Corrupt redirect storage');
        $storage->all();
    }

    public function testNonArrayJsonFailsClosed(): void
    {
        mkdir($this->dir . '/nested', 0777, true);
        file_put_contents($this->file, '"string"');
        $storage = new FilesystemUrlRedirectStorage($this->file);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('expected a JSON array');
        $storage->enabledMap();
    }

    public function testUncreatableDirectoryThrows(): void
    {
        mkdir($this->dir, 0777, true);
        file_put_contents($this->dir . '/nested', 'file, not a directory');
        $storage = new FilesystemUrlRedirectStorage($this->dir . '/nested/sub/redirects.json');

        try {
            $this->expectException(RuntimeException::class);
            $storage->all();
        } finally {
            @unlink($this->dir . '/nested');
        }
    }
}
