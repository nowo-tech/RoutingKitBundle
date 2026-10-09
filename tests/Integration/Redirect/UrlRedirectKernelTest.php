<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Integration\Redirect;

use Nowo\RoutingKitBundle\Model\UrlRedirect;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectMatcher;
use Nowo\RoutingKitBundle\Storage\UrlRedirectStorageInterface;
use Nowo\RoutingKitBundle\Tests\Integration\Redirect\App\RedirectTestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * End-to-end: panel CRUD (real Twig templates, forms, CSRF, translations) and the public listener.
 */
final class UrlRedirectKernelTest extends TestCase
{
    private string $baseDir;

    private RedirectTestKernel $kernel;

    /** @var array<string, string> */
    private array $cookies = [];

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/rk_redirect_kernel_' . uniqid('', true);
        mkdir($this->baseDir . '/templates', 0777, true);
        mkdir($this->baseDir . '/translations', 0777, true);
        $this->kernel = new RedirectTestKernel($this->baseDir);
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
        (new Filesystem())->remove($this->baseDir);
    }

    public function testOperatorCreatesRedirectThatPublicSiteFollows(): void
    {
        $index = $this->request('GET', '/_routing/');
        self::assertSame(200, $index->getStatusCode());
        self::assertStringContainsString('href="/_routing/redirects"', (string) $index->getContent(), 'Routes panel links the redirects section.');

        $list = $this->request('GET', '/_routing/redirects');
        self::assertSame(200, $list->getStatusCode());
        self::assertStringContainsString('data-testid="redirect-table"', (string) $list->getContent());
        self::assertStringNotContainsString('onsubmit', (string) $list->getContent(), 'No inline JavaScript.');

        $created = $this->submit('/tratamientos/Unas-Encarnadas/', '/contact', ['note' => 'Old site']);
        self::assertSame(302, $created->getStatusCode());
        self::assertSame('/_routing/redirects?status=created', $created->headers->get('Location'));
        $list = (string) $this->request('GET', '/_routing/redirects?status=created')->getContent();
        self::assertStringContainsString('/tratamientos/Unas-Encarnadas', $list);
        self::assertStringContainsString('data-testid="redirect-status"', $list);

        $hit = $this->request('GET', '/tratamientos/Unas-Encarnadas?utm_source=old');
        self::assertSame(301, $hit->getStatusCode());
        self::assertSame('/contact?utm_source=old', $hit->headers->get('Location'));
        self::assertSame(404, $this->request('GET', '/tratamientos/unas-encarnadas')->getStatusCode(), 'Paths are case-sensitive.');
        self::assertFalse($this->request('POST', '/tratamientos/Unas-Encarnadas')->isRedirect(), 'Only GET/HEAD are redirected.');

        $saved = $this->storage()->findBySource('/tratamientos/Unas-Encarnadas');
        self::assertSame(1, $saved?->getHits());
        self::assertNotNull($saved->getLastHitAt());
    }

    public function testRejectsProtectedLiveLoopChainAndDuplicate(): void
    {
        self::assertStringContainsString('reserved', $this->errorsFor('/_routing/x', '/'));
        self::assertStringContainsString('reserved', $this->errorsFor('/admin/users', '/'));
        self::assertStringContainsString('reserved', $this->errorsFor('/en/login/check', 'https://evil.example/x'));
        self::assertStringContainsString('live page', $this->errorsFor('/contacto', '/blog'));
        self::assertStringContainsString('live page', $this->errorsFor('/blog/published', '/contact'));
        self::assertStringContainsString('same URL', $this->errorsFor('/a', '/a/'));
        self::assertStringContainsString('path starting with /', $this->errorsFor('/b', 'javascript:alert(1)'));
        self::assertStringContainsString('path starting with /', $this->errorsFor('/b', '//evil.example'));
        self::assertStringContainsString('path starting with /', $this->errorsFor('/b', '/\\evil.example'));

        self::assertSame(302, $this->submit('/blog/retired-post', '/blog/published')->getStatusCode(), 'A retired slug URL can be redirected.');
        self::assertSame(302, $this->submit('/old', '/new')->getStatusCode());
        self::assertStringContainsString('already a redirect', $this->errorsFor('/old/', '/other'));
        self::assertStringContainsString('itself redirected', $this->errorsFor('/older', '/old'));
    }

    public function testEditAndDeleteWithConfirmationPage(): void
    {
        $this->submit('/old', '/new');
        $id = (string) $this->storage()->findBySource('/old')?->getId();

        $edit = $this->request('GET', '/_routing/redirects/' . $id . '/edit');
        self::assertSame(200, $edit->getStatusCode());
        self::assertStringContainsString('value="/old"', (string) $edit->getContent());

        $forged = $this->request('POST', '/_routing/redirects/' . $id . '/delete', ['_csrf_token' => 'forged', 'confirmed' => '1']);
        self::assertSame(403, $forged->getStatusCode());

        $confirm = (string) $this->request('GET', '/_routing/redirects/' . $id . '/delete')->getContent();
        self::assertStringContainsString('data-testid="redirect-delete-form"', $confirm);
        self::assertStringNotContainsString('<script', $confirm);
        $deleted = $this->request('POST', '/_routing/redirects/' . $id . '/delete', [
            '_csrf_token' => $this->token($confirm, '_csrf_token'),
            'confirmed'   => '1',
        ]);
        self::assertSame('/_routing/redirects?status=deleted', $deleted->headers->get('Location'));
        self::assertNull($this->storage()->findBySource('/old'));
        self::assertSame(404, $this->request('GET', '/old')->getStatusCode(), 'Cache invalidated on delete.');
        self::assertSame(404, $this->request('GET', '/_routing/redirects/missing/edit')->getStatusCode());
    }

    public function testAccentedSourcesMatchAndLivePagesWinOverStaleRedirects(): void
    {
        self::assertSame(302, $this->submit('/podología', '/contact')->getStatusCode());

        // Saved before a page existed at that path (or written outside the panel).
        $this->storage()->save((new UrlRedirect())->setSourcePath('/contacto')->setTargetUrl('/blog'));
        $this->service(UrlRedirectMatcher::class)->invalidate();

        $hit = $this->request('GET', '/podolog%C3%ADa');
        self::assertSame(301, $hit->getStatusCode(), 'Browsers send accented paths percent-encoded.');
        self::assertSame('/contact', $hit->headers->get('Location'));
        self::assertSame('page:contacto', $this->request('GET', '/contacto')->getContent(), 'The live page wins over a stale redirect.');
    }

    private function storage(): UrlRedirectStorageInterface
    {
        return $this->service(UrlRedirectStorageInterface::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $service = $container->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }

    /**
     * @param array<string, string> $extra
     */
    private function submit(string $source, string $target, array $extra = []): Response
    {
        $form = (string) $this->request('GET', '/_routing/redirects/new')->getContent();

        return $this->request('POST', '/_routing/redirects/new', ['url_redirect' => [
            'source_path' => $source,
            'target_url'  => $target,
            'status_code' => '301',
            'enabled'     => '1',
            '_csrf_token' => $this->token($form, 'url_redirect[_csrf_token]'),
        ] + $extra]);
    }

    private function errorsFor(string $source, string $target): string
    {
        $response = $this->submit($source, $target);
        self::assertSame(200, $response->getStatusCode(), $source . ' → ' . $target . ' must be rejected.');

        return html_entity_decode((string) $response->getContent());
    }

    private function token(string $html, string $name): string
    {
        $pattern = '/name="' . preg_quote(htmlspecialchars($name), '/') . '"[^>]*value="([^"]+)"/';
        self::assertSame(1, preg_match($pattern, $html, $m), 'CSRF field ' . $name . ' rendered.');

        return $m[1];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function request(string $method, string $uri, array $parameters = []): Response
    {
        $request  = Request::create($uri, $method, $parameters, $this->cookies);
        $response = $this->kernel->handle($request);
        foreach ($response->headers->getCookies() as $cookie) {
            $this->cookies[$cookie->getName()] = (string) $cookie->getValue();
        }
        $this->kernel->terminate($request, $response);

        return $response;
    }
}
