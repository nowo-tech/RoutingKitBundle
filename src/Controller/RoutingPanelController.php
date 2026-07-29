<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Controller;

use JsonException;
use Nowo\RoutingKitBundle\Discovery\RoutableControllerDiscovery;
use Nowo\RoutingKitBundle\Locale\LocaleProviderInterface;
use Nowo\RoutingKitBundle\Model\AliasMode;
use Nowo\RoutingKitBundle\Model\CanonicalStyle;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Model\TrailingSlashStyle;
use Nowo\RoutingKitBundle\Security\PanelAccessGuard;
use Nowo\RoutingKitBundle\Service\RoutePathImportExport;
use Nowo\RoutingKitBundle\Service\RoutePathManager;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Throwable;
use Twig\Environment;

use function array_slice;
use function ceil;
use function count;
use function is_array;
use function is_string;
use function json_decode;
use function max;
use function strlen;

use const JSON_THROW_ON_ERROR;

#[AsController]
final class RoutingPanelController
{
    public const CSRF_TOKEN_ID = 'nowo_routing_kit_panel';

    /** Max raw import JSON size (bytes) to limit admin-panel DoS. */
    private const MAX_IMPORT_PAYLOAD_BYTES = 1_048_576;

    public function __construct(
        private readonly RoutePathManager $manager,
        private readonly RoutableControllerDiscovery $discovery,
        private readonly LocaleProviderInterface $locales,
        private readonly Environment $twig,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly PanelAccessGuard $accessGuard,
        private readonly RoutePathImportExport $importExport,
        private readonly string $pathPrefix = '/_routing',
        private readonly bool $allowControllerOverride = false,
        private readonly bool $roleGateDisabled = false,
        private readonly int $listPageSize = 50,
    ) {
    }

    #[Route('/', name: 'nowo_routing_kit_panel', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->accessGuard->assertGranted();

        $all      = $this->manager->all();
        $total    = count($all);
        $pageSize = max(1, $this->listPageSize);
        $pages    = max(1, (int) ceil($total / $pageSize));
        $page     = max(1, min($pages, $request->query->getInt('page', 1)));
        $offset   = ($page - 1) * $pageSize;

        return new Response($this->twig->render('@NowoRoutingKitBundle/panel/index.html.twig', [
            'definitions'        => array_slice($all, $offset, $pageSize),
            'path_prefix'        => $this->pathPrefix,
            'locales'            => $this->locales->getLocales(),
            'default'            => $this->locales->getDefaultLocale(),
            'csrf_token'         => $this->csrfToken(),
            'role_gate_disabled' => $this->roleGateDisabled,
            'page'               => $page,
            'pages'              => $pages,
            'total'              => $total,
            'page_size'          => $pageSize,
        ]));
    }

    #[Route('/new', name: 'nowo_routing_kit_panel_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $this->accessGuard->assertGranted();

        return $this->form($request, null);
    }

    #[Route('/edit/{id}', name: 'nowo_routing_kit_panel_edit', methods: ['GET', 'POST'], requirements: ['id' => '[A-Za-z0-9_.-]+'])]
    public function edit(Request $request, string $id): Response
    {
        $this->accessGuard->assertGranted();

        $definition = $this->manager->get($id);
        if (!$definition instanceof RoutePathDefinition) {
            return new Response('Not found', 404);
        }

        return $this->form($request, $definition);
    }

    #[Route('/delete/{id}', name: 'nowo_routing_kit_panel_delete', methods: ['POST'], requirements: ['id' => '[A-Za-z0-9_.-]+'])]
    public function delete(Request $request, string $id): Response
    {
        $this->accessGuard->assertGranted();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfValid($request)) {
                return new Response('Invalid CSRF token.', 403);
            }
            $this->manager->delete($id);
        }

        return new RedirectResponse($this->pathPrefix . '/');
    }

    #[Route('/clear-cache', name: 'nowo_routing_kit_panel_clear_cache', methods: ['POST'])]
    public function clearCache(Request $request): Response
    {
        $this->accessGuard->assertGranted();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfValid($request)) {
                return new Response('Invalid CSRF token.', 403);
            }
            $this->manager->clearCache();
        }

        return new RedirectResponse($this->pathPrefix . '/');
    }

    #[Route('/export', name: 'nowo_routing_kit_panel_export', methods: ['POST'])]
    public function export(Request $request): Response
    {
        $this->accessGuard->assertGranted();

        if (!$request->isMethod('POST')) {
            return new Response('Method Not Allowed', Response::HTTP_METHOD_NOT_ALLOWED);
        }
        if (!$this->isCsrfValid($request)) {
            return new Response('Invalid CSRF token.', 403);
        }

        $envelope = $this->importExport->export();

        return new JsonResponse($envelope, 200, [
            'Content-Disposition' => 'attachment; filename="routing-kit-paths.json"',
        ]);
    }

    #[Route('/import', name: 'nowo_routing_kit_panel_import', methods: ['POST'])]
    public function import(Request $request): Response
    {
        $this->accessGuard->assertGranted();

        if (!$request->isMethod('POST')) {
            return new RedirectResponse($this->pathPrefix . '/');
        }
        if (!$this->isCsrfValid($request)) {
            return new Response('Invalid CSRF token.', 403);
        }

        $raw = (string) $request->request->get('payload_json', '');
        if (strlen($raw) > self::MAX_IMPORT_PAYLOAD_BYTES) {
            return new Response('Import payload too large.', 413);
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new Response('Invalid JSON payload.', 400);
        }
        if (!is_array($decoded)) {
            return new Response('Invalid JSON payload.', 400);
        }

        /* @var array{payload?: mixed, signature?: mixed, version?: mixed} $decoded */
        try {
            $this->importExport->import($decoded, $request->request->getBoolean('replace_all', false));
            $this->manager->clearCache();
        } catch (Throwable $e) {
            $message = $e instanceof RuntimeException ? $e->getMessage() : 'Import failed.';

            return new Response($message, 400);
        }

        return new RedirectResponse($this->pathPrefix . '/');
    }

    #[Route('/conflicts', name: 'nowo_routing_kit_panel_conflicts', methods: ['GET'])]
    public function previewConflicts(Request $request): JsonResponse
    {
        $this->accessGuard->assertGranted();

        $definition = new RoutePathDefinition(
            routeName: (string) $request->query->get('route_name', ''),
            locale: (string) $request->query->get('locale', ''),
            path: (string) $request->query->get('path', '/'),
            canonicalStyle: CanonicalStyle::tryFrom((string) $request->query->get('canonical_style', CanonicalStyle::WithoutPrefix->value)) ?? CanonicalStyle::WithoutPrefix,
            trailingSlash: TrailingSlashStyle::tryFrom((string) $request->query->get('trailing_slash', TrailingSlashStyle::Omit->value)) ?? TrailingSlashStyle::Omit,
            aliasMode: AliasMode::tryFrom((string) $request->query->get('alias_mode', AliasMode::Redirect->value)) ?? AliasMode::Redirect,
            enabled: true,
            id: $request->query->get('id') !== null && $request->query->get('id') !== ''
                ? (string) $request->query->get('id')
                : null,
        );

        return new JsonResponse([
            'conflicts' => $this->manager->previewConflicts($definition),
        ]);
    }

    private function form(Request $request, ?RoutePathDefinition $existing): Response
    {
        $errors    = [];
        $conflicts = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfValid($request)) {
                $errors[] = 'Invalid CSRF token.';
            } else {
                $routeName = (string) $request->request->get('route_name', '');
                $locale    = (string) $request->request->get('locale', '');
                $path      = (string) $request->request->get('path', '/');

                $controller = null;
                if ($this->allowControllerOverride) {
                    $raw        = $request->request->get('controller');
                    $controller = is_string($raw) && $raw !== '' ? $raw : null;
                }

                $definition = new RoutePathDefinition(
                    routeName: $routeName,
                    locale: $locale,
                    path: $path,
                    canonicalStyle: CanonicalStyle::tryFrom((string) $request->request->get('canonical_style', CanonicalStyle::WithoutPrefix->value)) ?? CanonicalStyle::WithoutPrefix,
                    trailingSlash: TrailingSlashStyle::tryFrom((string) $request->request->get('trailing_slash', TrailingSlashStyle::Omit->value)) ?? TrailingSlashStyle::Omit,
                    aliasMode: AliasMode::tryFrom((string) $request->request->get('alias_mode', AliasMode::Redirect->value)) ?? AliasMode::Redirect,
                    enabled: $request->request->getBoolean('enabled', true),
                    controller: $controller,
                    id: $existing?->id,
                );

                $conflicts = $this->manager->previewConflicts($definition);

                try {
                    $this->manager->save($definition);

                    return new RedirectResponse($this->pathPrefix . '/');
                } catch (Throwable $e) {
                    $errors[] = $e->getMessage();
                    $existing = $definition;
                }
            }
        } elseif ($existing instanceof RoutePathDefinition) {
            $conflicts = $this->manager->previewConflicts($existing);
        }

        return new Response($this->twig->render('@NowoRoutingKitBundle/panel/form.html.twig', [
            'definition'                => $existing,
            'routables'                 => $this->discovery->discover(),
            'locales'                   => $this->locales->getLocales(),
            'path_prefix'               => $this->pathPrefix,
            'errors'                    => $errors,
            'conflicts'                 => $conflicts,
            'canonical_styles'          => CanonicalStyle::cases(),
            'trailing_styles'           => TrailingSlashStyle::cases(),
            'alias_modes'               => AliasMode::cases(),
            'csrf_token'                => $this->csrfToken(),
            'allow_controller_override' => $this->allowControllerOverride,
            'role_gate_disabled'        => $this->roleGateDisabled,
        ]));
    }

    private function csrfToken(): string
    {
        return $this->csrfTokenManager->getToken(self::CSRF_TOKEN_ID)->getValue();
    }

    private function isCsrfValid(Request $request): bool
    {
        $value = (string) $request->request->get('_csrf_token', '');

        return $this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $value));
    }
}
