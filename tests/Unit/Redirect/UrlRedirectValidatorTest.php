<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Redirect;

use Nowo\RoutingKitBundle\Locale\ConfigurableLocaleProvider;
use Nowo\RoutingKitBundle\Model\UrlRedirect;
use Nowo\RoutingKitBundle\Redirect\LivePublicPaths;
use Nowo\RoutingKitBundle\Redirect\ProtectedPaths;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectValidator;
use Nowo\RoutingKitBundle\Storage\FilesystemUrlRedirectStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class UrlRedirectValidatorTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/rk_redirect_validator_' . uniqid('', true) . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        @unlink($this->file . '.lock');
    }

    public function testRules(): void
    {
        $storage  = new FilesystemUrlRedirectStorage($this->file);
        $existing = $storage->save((new UrlRedirect())->setSourcePath('/old')->setTargetUrl('/new'));
        $routes   = new RouteCollection();
        $routes->add('contact', new Route('/contact'));
        $validator = new UrlRedirectValidator(
            $storage,
            new ProtectedPaths(new ConfigurableLocaleProvider('es', ['es', 'en'])),
            new LivePublicPaths(new UrlMatcher($routes, new RequestContext())),
        );

        self::assertSame([], $validator->validate('/tratamientos/Unas/', '/contact', 301));
        self::assertSame([], $validator->validate('/podología', 'https://example.com/x?a=1', 308, 'note'));
        self::assertSame([], $validator->validate('/old/', '/elsewhere', 302, '', $existing->getId()), 'Editing keeps its own source.');

        self::assertSame([
            'source_path' => 'redirects.error.source_invalid',
            'target_url'  => 'redirects.error.target_invalid',
            'status_code' => 'redirects.error.status_invalid',
            'note'        => 'redirects.error.note_too_long',
        ], $validator->validate('no-slash', 'javascript:alert(1)', 200, str_repeat('x', 256)));
        self::assertSame(['source_path' => 'redirects.error.source_invalid'], $validator->validate('/a?b=1', '/x', 301));
        self::assertSame(['source_path' => 'redirects.error.source_invalid'], $validator->validate('', '/x', 301));
        self::assertSame(['source_path' => 'redirects.error.source_invalid'], $validator->validate('/' . str_repeat('a', 255), '/x', 301));
        self::assertSame(['target_url' => 'redirects.error.target_invalid'], $validator->validate('/b', '//evil.example', 301));
        self::assertSame(['target_url' => 'redirects.error.target_invalid'], $validator->validate('/b', '/\\evil.example', 301));
        self::assertSame(['target_url' => 'redirects.error.target_invalid'], $validator->validate('/b', '', 301));

        self::assertSame(['source_path' => 'redirects.error.source_protected'], $validator->validate('/admin/users', '/', 301));
        self::assertSame(['source_path' => 'redirects.error.source_protected'], $validator->validate('/es/login/check', 'https://evil.example', 301));
        self::assertSame(['source_path' => 'redirects.error.source_live_page'], $validator->validate('/contact', '/x', 301));
        self::assertSame(['target_url' => 'redirects.error.target_loop'], $validator->validate('/a', '/a/', 301));
        self::assertSame(['target_url' => 'redirects.error.target_chain'], $validator->validate('/older', '/old?x=1', 301));
        self::assertSame(['source_path' => 'redirects.error.source_duplicate'], $validator->validate('/old/', '/other', 301));
    }
}
