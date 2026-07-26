<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Storage;

use Nowo\RoutingKitBundle\Model\CanonicalStyle;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Storage\FilesystemRoutePathStorage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FilesystemRoutePathStorageTest extends TestCase
{
    private string $file;
    private string $dir;

    protected function setUp(): void
    {
        $suffix     = uniqid('', true);
        $this->dir  = sys_get_temp_dir() . '/rk_paths_' . $suffix;
        $this->file = $this->dir . '/paths.json';
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    public function testSaveFindAndDelete(): void
    {
        $storage = new FilesystemRoutePathStorage($this->file);

        $saved = $storage->save(new RoutePathDefinition(
            routeName: 'app_home',
            locale: 'en',
            path: '/',
            canonicalStyle: CanonicalStyle::WithoutPrefix,
        ));

        self::assertNotNull($saved->id);
        self::assertSame('/', $storage->find('app_home', 'en')?->path);
        self::assertSame($saved->id, $storage->findById($saved->id)?->id);
        self::assertCount(1, $storage->findByRouteName('app_home'));
        self::assertCount(1, $storage->all());

        $storage->delete($saved->id);
        self::assertCount(0, $storage->all());
    }

    public function testRejectsDuplicateRouteLocale(): void
    {
        $storage = new FilesystemRoutePathStorage($this->file);
        $storage->save(new RoutePathDefinition('app_home', 'en', '/'));

        $this->expectException(RuntimeException::class);
        $storage->save(new RoutePathDefinition('app_home', 'en', '/home'));
    }

    public function testReplaceAllIsAtomicAndAssignsIds(): void
    {
        $storage = new FilesystemRoutePathStorage($this->file);
        $storage->save(new RoutePathDefinition('app_home', 'en', '/old'));

        $saved = $storage->replaceAll([
            new RoutePathDefinition('app_home', 'en', '/'),
            new RoutePathDefinition('app_home', 'es', '/inicio'),
        ]);

        self::assertCount(2, $saved);
        self::assertCount(2, $storage->all());
        self::assertSame('/', $storage->find('app_home', 'en')?->path);
        self::assertNotNull($saved[0]->id);
    }

    public function testReplaceAllRejectsDuplicateRouteLocale(): void
    {
        $storage = new FilesystemRoutePathStorage($this->file);

        $this->expectException(RuntimeException::class);
        $storage->replaceAll([
            new RoutePathDefinition('app_home', 'en', '/'),
            new RoutePathDefinition('app_home', 'en', '/other'),
        ]);
    }

    public function testAllReturnsEmptyForEmptyCorruptOrUnreadableContent(): void
    {
        file_put_contents($this->file, '');
        $storage = new FilesystemRoutePathStorage($this->file);
        self::assertSame([], $storage->all());

        file_put_contents($this->file, '{invalid json');
        self::assertSame([], $storage->all());

        $directoryPath = $this->dir . '/as-directory';
        mkdir($directoryPath);
        $directoryStorage = new FilesystemRoutePathStorage($directoryPath);
        self::assertSame([], $directoryStorage->all());
        rmdir($directoryPath);
    }

    public function testLoadSkipsRowsWithoutIds(): void
    {
        file_put_contents($this->file, json_encode([
            [
                'route_name' => 'app_home',
                'locale'     => 'en',
                'path'       => '/',
            ],
            'invalid-row',
        ]));

        $storage = new FilesystemRoutePathStorage($this->file);

        self::assertSame([], $storage->all());
    }

    public function testSaveThrowsWhenStorageDirectoryCannotBeCreated(): void
    {
        $parentFile = $this->dir . '/not-a-directory';
        file_put_contents($parentFile, 'x');

        $storage = new FilesystemRoutePathStorage($parentFile . '/paths.json');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to create storage directory');

        $storage->save(new RoutePathDefinition('app_home', 'en', '/'));
    }

    public function testSaveThrowsWhenTargetPathCannotBeReplaced(): void
    {
        $targetDir = $this->dir . '/target-dir';
        mkdir($targetDir);

        $storage = new FilesystemRoutePathStorage($targetDir);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to replace storage file');

        $storage->save(new RoutePathDefinition('app_home', 'en', '/'));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = scandir($dir);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDir($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
