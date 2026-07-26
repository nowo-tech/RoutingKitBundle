<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Service;

use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Service\RoutePathImportExport;
use Nowo\RoutingKitBundle\Storage\FilesystemRoutePathStorage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RoutePathImportExportTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/rk_export_' . uniqid('', true) . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function testExportImportRoundTripAndRejectsBadSignature(): void
    {
        $storage = new FilesystemRoutePathStorage($this->file);
        $storage->save(new RoutePathDefinition('app_about', 'en', '/about'));
        $service = new RoutePathImportExport($storage, 'secret-key');

        $envelope = $service->export();
        self::assertSame(1, $envelope['version']);
        self::assertNotSame('', $envelope['signature']);

        $targetFile = sys_get_temp_dir() . '/rk_import_' . uniqid('', true) . '.json';
        $target     = new FilesystemRoutePathStorage($targetFile);
        $importer   = new RoutePathImportExport($target, 'secret-key');
        self::assertSame(1, $importer->import($envelope));
        self::assertCount(1, $target->all());

        $envelope['signature'] = 'deadbeef';
        $this->expectException(RuntimeException::class);
        $importer->import($envelope);

        @unlink($targetFile);
    }
}
