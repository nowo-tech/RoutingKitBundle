<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Yaml\Yaml;

use function array_diff;
use function array_keys;
use function array_map;
use function array_values;
use function basename;
use function dirname;
use function explode;
use function file_get_contents;
use function glob;
use function preg_match_all;
use function sort;
use function sprintf;
use function str_ends_with;

/**
 * Every shipped locale carries the English key set, and every literal key a panel template translates exists.
 */
final class TranslationCataloguesTest extends TestCase
{
    public function testAllLocalesShareTheEnglishKeySet(): void
    {
        $english = $this->keys('en');
        foreach (glob($this->dir() . '/NowoRoutingKitBundle.*.yaml') ?: [] as $file) {
            $locale = explode('.', basename($file))[1];
            $keys   = $this->keys($locale);
            self::assertSame([], array_values(array_diff($english, $keys)), sprintf('Missing in %s.', $locale));
            self::assertSame([], array_values(array_diff($keys, $english)), sprintf('Extra in %s.', $locale));
        }
    }

    public function testPanelTemplatesOnlyUseExistingKeys(): void
    {
        $english = $this->keys('en');
        $missing = [];
        $files   = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3) . '/src/Resources/views/panel'));
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.twig')) {
                continue;
            }
            preg_match_all("#'((?:panel|nav|redirects)\\.[a-z0-9_.]+)'\\s*\\|\\s*trans#", (string) file_get_contents($file->getPathname()), $m);
            foreach (array_diff($m[1], $english) as $key) {
                $missing[] = $file->getFilename() . ': ' . $key;
            }
        }

        self::assertSame([], $missing);
    }

    /**
     * @return list<string>
     */
    private function keys(string $locale): array
    {
        $data = Yaml::parseFile($this->dir() . '/NowoRoutingKitBundle.' . $locale . '.yaml');
        self::assertIsArray($data);
        $keys = array_keys($data);
        sort($keys);

        return array_values(array_map('strval', $keys));
    }

    private function dir(): string
    {
        return dirname(__DIR__, 3) . '/src/Resources/translations';
    }
}
