<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function basename;
use function class_exists;
use function class_uses;
use function dirname;
use function glob;

/**
 * REQ-TWIG-005 rule 11: every panel form type of the bundle is a FormKit Type on the `routing_kit` profile.
 */
final class FormKitIntegrationTest extends TestCase
{
    #[Test]
    public function everyFormTypeIsAFormKitTypeOnTheBundleProfile(): void
    {
        $files = glob(dirname(__DIR__, 3) . '/src/Form/*Type.php');
        self::assertNotFalse($files);
        self::assertNotSame([], $files);

        foreach ($files as $file) {
            $class = 'Nowo\\RoutingKitBundle\\Form\\' . basename($file, '.php');
            self::assertTrue(class_exists($class), $class);

            $attributes = (new ReflectionClass($class))->getAttributes(FormKitConfig::class);
            self::assertCount(1, $attributes, $class . ' must carry #[FormKitConfig].');
            self::assertSame('routing_kit', $attributes[0]->newInstance()->name, $class);
            self::assertContains(FormOptionsTrait::class, class_uses($class), $class . ' must use FormOptionsTrait.');
        }
    }
}
