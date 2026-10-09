<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Controller;

use Nowo\RoutingKitBundle\Controller\UrlRedirectPanelController;
use Nowo\RoutingKitBundle\Locale\ConfigurableLocaleProvider;
use Nowo\RoutingKitBundle\Model\UrlRedirect;
use Nowo\RoutingKitBundle\Redirect\LivePublicPaths;
use Nowo\RoutingKitBundle\Redirect\ProtectedPaths;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectMatcher;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectValidator;
use Nowo\RoutingKitBundle\Security\AllowAllRoutingKitAccessChecker;
use Nowo\RoutingKitBundle\Security\PanelAccessGuard;
use Nowo\RoutingKitBundle\Security\RoutingKitAccessCheckerInterface;
use Nowo\RoutingKitBundle\Storage\FilesystemUrlRedirectStorage;
use Nowo\RoutingKitBundle\Storage\UrlRedirectStorageInterface;
use Nowo\RoutingKitBundle\Tests\Support\FormKitTestSupport;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class UrlRedirectPanelControllerTest extends TestCase
{
    private string $file;

    /** @var list<array{string, array<string, mixed>}> */
    private array $rendered = [];

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/rk_redirect_ctrl_' . uniqid('', true) . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        @unlink($this->file . '.lock');
    }

    public function testIndexListsPaginatesAndShowsKnownStatusOnly(): void
    {
        [$controller, $storage] = $this->controller(pageSize: 1);
        $storage->save((new UrlRedirect())->setSourcePath('/b')->setTargetUrl('/x'));
        $storage->save((new UrlRedirect())->setSourcePath('/a')->setTargetUrl('/x'));

        $controller->index(Request::create('/_routing/redirects?page=2&status=created'));
        [$template, $context] = $this->rendered[0];
        self::assertSame('@NowoRoutingKitBundle/panel/redirects/index.html.twig', $template);
        self::assertSame('/b', $context['redirects'][0]->getSourcePath());
        self::assertSame(['created', 2, 2, 2, 1, '/_routing'], [$context['status'], $context['page'], $context['pages'], $context['total'], $context['item_count'], $context['path_prefix']]);

        $controller->index(Request::create('/_routing/redirects?status=<script>'));
        self::assertNull($this->rendered[1][1]['status']);
    }

    public function testCreateRendersFormThenSavesAndInvalidatesCache(): void
    {
        $user   = new InMemoryUser('alice@example.com', null, ['ROLE_ADMIN']);
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken($user, 'main', ['ROLE_ADMIN']));
        $matcherStorage = $this->createMock(UrlRedirectStorageInterface::class);
        $matcherStorage->expects(self::exactly(2))->method('enabledMap')->willReturn([]);
        $matcher = new UrlRedirectMatcher($matcherStorage);
        $matcher->match('/warm');

        [$controller, $storage] = $this->controller(tokens: $tokens, matcher: $matcher);

        $controller->create(Request::create('/_routing/redirects/new'));
        self::assertSame('@NowoRoutingKitBundle/panel/redirects/form.html.twig', $this->rendered[0][0]);
        self::assertNull($this->rendered[0][1]['redirect']);

        $response = $controller->create($this->post('/_routing/redirects/new', [
            'source_path' => '/tratamientos/Unas/',
            'target_url'  => '/contact',
            'status_code' => '308',
            'enabled'     => '1',
            'note'        => ' Old site ',
        ]));

        self::assertSame('/_routing/redirects?status=created', $response->headers->get('Location'));
        $saved = $storage->findBySource('/tratamientos/Unas');
        self::assertNotNull($saved);
        self::assertSame(['/contact', 308, true, 'Old site', 'alice@example.com', 'alice@example.com'], [
            $saved->getTargetUrl(), $saved->getStatusCode(), $saved->isEnabled(), $saved->getNote(), $saved->getCreatedBy(), $saved->getUpdatedBy(),
        ]);
        self::assertNotNull($saved->getCreatedAt());
        $matcher->match('/warm');
    }

    public function testCreateRejectsWithTranslatedFieldErrors(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => 'T:' . $key);
        [$controller, $storage] = $this->controller(translator: $translator);

        $controller->create($this->post('/_routing/redirects/new', ['source_path' => '/contact', 'target_url' => '/x', 'status_code' => '301']));

        $form = $this->rendered[0][1]['form'];
        self::assertSame('T:redirects.error.source_live_page', $form['source_path']->vars['errors'][0]->getMessage());
        self::assertSame([], $storage->all());
    }

    public function testCreateWithoutTranslatorUsesKeysAndInvalidChoiceIsRejected(): void
    {
        [$controller] = $this->controller();

        $controller->create($this->post('/_routing/redirects/new', ['source_path' => '/admin', 'target_url' => '/x', 'status_code' => '301']));
        self::assertSame('redirects.error.source_protected', $this->rendered[0][1]['form']['source_path']->vars['errors'][0]->getMessage());

        $controller->create($this->post('/_routing/redirects/new', ['source_path' => '/a', 'target_url' => '/x', 'status_code' => '200']));
        self::assertFalse($this->rendered[1][1]['form']->vars['valid']);
    }

    public function testStorageFailureIsShownOnTheForm(): void
    {
        $storage = $this->createStub(UrlRedirectStorageInterface::class);
        $storage->method('findBySource')->willReturn(null);
        $storage->method('save')->willThrowException(new RuntimeException('disk full'));
        [$controller] = $this->controller(storage: $storage);

        $controller->create($this->post('/_routing/redirects/new', ['source_path' => '/a', 'target_url' => '/x', 'status_code' => '301']));

        self::assertSame('disk full', $this->rendered[0][1]['form']->vars['errors'][0]->getMessage());
    }

    public function testEditKeepsIdAndAudit(): void
    {
        [$controller, $storage] = $this->controller();
        $existing               = $storage->save((new UrlRedirect())->setSourcePath('/old')->setTargetUrl('/new')->setCreatedBy('bob'));
        $id                     = (string) $existing->getId();

        $controller->edit(Request::create('/_routing/redirects/' . $id . '/edit'), $id);
        self::assertSame($id, $this->rendered[0][1]['redirect']->getId());
        self::assertSame('/old', $this->rendered[0][1]['form']['source_path']->vars['value']);

        $response = $controller->edit($this->post('/_routing/redirects/' . $id . '/edit', [
            'source_path' => '/old',
            'target_url'  => 'https://example.com/',
            'status_code' => '302',
        ]), $id);

        self::assertSame('/_routing/redirects?status=saved', $response->headers->get('Location'));
        $saved = $storage->findById($id);
        self::assertSame(['https://example.com/', 302, false, 'bob', null], [
            $saved?->getTargetUrl(), $saved?->getStatusCode(), $saved?->isEnabled(), $saved?->getCreatedBy(), $saved?->getUpdatedBy(),
        ]);
    }

    public function testEditUnknownIdIs404(): void
    {
        [$controller] = $this->controller();

        $this->expectException(NotFoundHttpException::class);
        $controller->edit(Request::create('/x'), 'missing');
    }

    public function testDeleteConfirmsOnGetAndDeletesOnPostWithValidToken(): void
    {
        [$controller, $storage] = $this->controller();
        $id                     = (string) $storage->save((new UrlRedirect())->setSourcePath('/old')->setTargetUrl('/new'))->getId();

        $controller->delete(Request::create('/_routing/redirects/' . $id . '/delete'), $id);
        self::assertSame('@NowoRoutingKitBundle/panel/redirects/delete.html.twig', $this->rendered[0][0]);
        self::assertNotNull($storage->findById($id), 'GET only asks for confirmation.');

        $response = $controller->delete(Request::create('/_routing/redirects/' . $id . '/delete', 'POST', [
            '_csrf_token' => 'token-value',
            'confirmed'   => '1',
        ]), $id);

        self::assertSame('/_routing/redirects?status=deleted', $response->headers->get('Location'));
        self::assertNull($storage->findById($id));
    }

    public function testDeleteRejectsForgedToken(): void
    {
        [$controller, $storage] = $this->controller(csrfValid: false);
        $id                     = (string) $storage->save((new UrlRedirect())->setSourcePath('/old')->setTargetUrl('/new'))->getId();

        $response = $controller->delete(Request::create('/x', 'POST', ['_csrf_token' => 'forged', 'confirmed' => '1']), $id);

        self::assertSame(403, $response->getStatusCode());
        self::assertNotNull($storage->findById($id));
    }

    public function testDisabledFeatureIs404(): void
    {
        $controller = new UrlRedirectPanelController(
            $this->createStub(Environment::class),
            FormKitTestSupport::createFormFactory($this->csrf(true)),
            new PanelAccessGuard(new AllowAllRoutingKitAccessChecker(), allowUnauthenticated: true),
        );

        $this->expectException(NotFoundHttpException::class);
        $controller->index(Request::create('/_routing/redirects'));
    }

    public function testAccessIsGuarded(): void
    {
        $deny = $this->createStub(RoutingKitAccessCheckerInterface::class);
        $deny->method('canAccess')->willReturn(false);
        [$controller] = $this->controller(guard: new PanelAccessGuard($deny, new TokenStorage()));

        $this->expectException(AccessDeniedHttpException::class);
        $controller->create(Request::create('/_routing/redirects/new'));
    }

    /**
     * @return array{UrlRedirectPanelController, UrlRedirectStorageInterface}
     */
    private function controller(
        int $pageSize = 50,
        ?TokenStorage $tokens = null,
        ?UrlRedirectMatcher $matcher = null,
        ?TranslatorInterface $translator = null,
        ?UrlRedirectStorageInterface $storage = null,
        bool $csrfValid = true,
        ?PanelAccessGuard $guard = null,
    ): array {
        $storage ??= new FilesystemUrlRedirectStorage($this->file);
        $routes = new RouteCollection();
        $routes->add('contact', new Route('/contact'));
        $validator = new UrlRedirectValidator(
            $storage,
            new ProtectedPaths(new ConfigurableLocaleProvider('en', ['en', 'es']), ['/admin', '/_routing']),
            new LivePublicPaths(new UrlMatcher($routes, new RequestContext())),
        );

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(function (string $template, array $context): string {
            $this->rendered[] = [$template, $context];

            return 'rendered';
        });

        $controller = new UrlRedirectPanelController(
            $twig,
            FormKitTestSupport::createFormFactory($this->csrf($csrfValid)),
            $guard ?? new PanelAccessGuard(new AllowAllRoutingKitAccessChecker(), $tokens, allowUnauthenticated: true),
            $storage,
            $matcher ?? new UrlRedirectMatcher($storage),
            $validator,
            $translator,
            '/_routing',
            $pageSize,
        );

        return [$controller, $storage];
    }

    /**
     * @param array<string, string> $fields
     */
    private function post(string $uri, array $fields): Request
    {
        return Request::create($uri, 'POST', ['url_redirect' => $fields + ['_csrf_token' => 'token-value']]);
    }

    private function csrf(bool $valid): CsrfTokenManagerInterface
    {
        $manager = $this->createStub(CsrfTokenManagerInterface::class);
        $manager->method('getToken')->willReturnCallback(static fn (string $id): CsrfToken => new CsrfToken($id, 'token-value'));
        $manager->method('isTokenValid')->willReturn($valid);

        return $manager;
    }
}
