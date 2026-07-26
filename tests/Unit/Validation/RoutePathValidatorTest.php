<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Validation;

use Nowo\RoutingKitBundle\Discovery\RoutableControllerDiscovery;
use Nowo\RoutingKitBundle\Validation\RoutePathValidator;
use PHPUnit\Framework\TestCase;

final class RoutePathValidatorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/rk_validator_' . uniqid('', true);
        mkdir($this->dir, 0777, true);

        file_put_contents($this->dir . '/BlogController.php', <<<'PHP'
<?php
namespace App\Controller;

use Nowo\RoutingKitBundle\Attribute\Routable;
use Nowo\RoutingKitBundle\Attribute\RouteParam;

final class BlogController
{
    #[Routable(name: 'app_blog_show', params: [
        new RouteParam('slug', required: true, requirement: '[a-z0-9-]+'),
        new RouteParam('status', required: false, enum: ['draft', 'published']),
        new RouteParam('category', required: true),
    ])]
    public function show(string $slug): void
    {
    }
}
PHP);

        if (!class_exists('App\\Controller\\BlogController')) {
            require_once $this->dir . '/BlogController.php';
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/BlogController.php');
        @rmdir($this->dir);
    }

    public function testValidatesPlaceholders(): void
    {
        $validator = new RoutePathValidator(new RoutableControllerDiscovery([$this->dir]));

        self::assertSame([], $validator->validate('app_blog_show', '/blog/{slug}/{category}/{status}'));
        self::assertContains('Required parameter "{slug}" is missing from path.', $validator->validate('app_blog_show', '/blog/{status}'));
        self::assertContains(
            'Unknown path placeholder "{unknown}" is not declared on #[Routable] for "app_blog_show".',
            $validator->validate('app_blog_show', '/blog/{slug}/{unknown}'),
        );
        self::assertContains(
            'Do not include {_locale} in the stored path; it is always applied by the loader.',
            $validator->validate('app_blog_show', '/{_locale}/blog/{slug}'),
        );
        self::assertContains(
            'Path must be an absolute public path starting with "/" (no "//", schemes, or control characters).',
            $validator->validate('app_blog_show', 'blog/{slug}'),
        );
        self::assertContains(
            'Path must be an absolute public path starting with "/" (no "//", schemes, or control characters).',
            $validator->validate('app_blog_show', ''),
        );
        self::assertContains(
            'Path must be an absolute public path starting with "/" (no "//", schemes, or control characters).',
            $validator->validate('app_blog_show', '//evil.example/path'),
        );
        self::assertContains('Route "unknown_route" is not marked #[Routable].', $validator->validate('unknown_route', '/x'));
    }

    public function testValidateValuesChecksRequiredEnumAndRequirement(): void
    {
        $validator = new RoutePathValidator(new RoutableControllerDiscovery([$this->dir]));

        $errors = $validator->validateValues('app_blog_show', [
            'slug'   => 'INVALID',
            'status' => 'archived',
        ]);

        self::assertContains('Parameter "slug" does not match requirement "[a-z0-9-]+".', $errors);
        self::assertContains('Parameter "status" must be one of: draft, published.', $errors);
        self::assertContains('Missing required parameter "category".', $validator->validateValues('app_blog_show', [
            'slug'   => 'valid-slug',
            'status' => 'draft',
        ]));
        self::assertSame([], $validator->validateValues('app_blog_show', [
            'slug'     => 'valid-slug',
            'category' => 'news',
        ]));
        self::assertSame([], $validator->validateValues('app_blog_show', [
            'slug'     => 'valid-slug',
            'status'   => 'draft',
            'category' => 'news',
        ]));
    }
}
