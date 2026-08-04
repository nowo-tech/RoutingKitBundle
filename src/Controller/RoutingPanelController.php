<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Controller;

use JsonException;
use Nowo\RoutingKitBundle\Discovery\RoutableControllerDiscovery;
use Nowo\RoutingKitBundle\Form\RoutePathDefinitionType;
use Nowo\RoutingKitBundle\Locale\LocaleProviderInterface;
use Nowo\RoutingKitBundle\Model\AliasMode;
use Nowo\RoutingKitBundle\Model\CanonicalStyle;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Model\TrailingSlashStyle;
use Nowo\RoutingKitBundle\Security\PanelAccessGuard;
use Nowo\RoutingKitBundle\Service\RoutePathImportExport;
use Nowo\RoutingKitBundle\Service\RoutePathManager;
use RuntimeException;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
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
        private readonly FormFactoryInterface $formFactory,
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
        $rows     = array_slice($all, $offset, $pageSize);

        return new Response($this->twig->render('@NowoRoutingKitBundle/panel/index.html.twig', [
            'definitions'        => $rows,
            'path_prefix'        => $this->pathPrefix,
            'locales'            => $this->locales->getLocales(),
            'default'            => $this->locales->getDefaultLocale(),
            'csrf_token'         => $this->csrfToken(),
            'role_gate_disabled' => $this->roleGateDisabled,
            'page'               => $page,
            'pages'              => $pages,
            'total'              => $total,
            'page_size'          => $pageSize,
            'item_count'         => count($rows),
        ]));
    }

    #[Route('/new', name: 'nowo_routing_kit_panel_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $this->accessGuard->assertGranted();

        return $this->form($request, null);
    }

    #[Route('/edit/{id}', name: 'nowo_routing_kit_panel_edit', requirements: ['id' => '[A-Za-z0-9_.-]+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id): Response
    {
        $this->accessGuard->assertGranted();

        $definition = $this->manager->get($id);
        if (!$definition instanceof RoutePathDefinition) {
            return new Response('Not found', 404);
        }

        return $this->form($request, $definition);
    }

    #[Route('/delete/{id}', name: 'nowo_routing_kit_panel_delete', requirements: ['id' => '[A-Za-z0-9_.-]+'], methods: ['POST'])]
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
        $routables    = $this->discovery->discover();
        $localeList   = $this->locales->getLocales();
        $initialRoute = $existing?->routeName ?? ($routables[0]['route_name'] ?? '');

        /** @var FormInterface<array<string, mixed>|null> $form */
        $form = $this->formFactory->createNamed(
            '',
            RoutePathDefinitionType::class,
            $this->formDataFromDefinition($existing, $initialRoute),
            [
                'routables'                 => $routables,
                'locales'                   => $localeList,
                'allow_controller_override' => $this->allowControllerOverride,
                'is_create'                 => $existing === null,
                'initial_route_name'        => $initialRoute,
            ],
        );

        $form->handleRequest($request);

        $errors    = [];
        $conflicts = [];
        $definition = $existing;

        if ($form->isSubmitted()) {
            if (!$form->isValid()) {
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
            } else {
                /** @var array<string, mixed> $data */
                $data       = $form->getData() ?? [];
                $definition = $this->definitionFromFormData($data, $existing?->id);
                $conflicts  = $this->manager->previewConflicts($definition);

                try {
                    $this->manager->save($definition);

                    return new RedirectResponse($this->pathPrefix . '/');
                } catch (Throwable $e) {
                    $errors[] = $e->getMessage();
                }
            }
        } elseif ($existing instanceof RoutePathDefinition) {
            $conflicts = $this->manager->previewConflicts($existing);
        }

        return new Response($this->twig->render('@NowoRoutingKitBundle/panel/form.html.twig', [
            'definition'         => $definition,
            'form'               => $form->createView(),
            'path_prefix'        => $this->pathPrefix,
            'errors'             => $errors,
            'conflicts'          => $conflicts,
            'role_gate_disabled' => $this->roleGateDisabled,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formDataFromDefinition(?RoutePathDefinition $definition, string $initialRoute): array
    {
        if ($definition instanceof RoutePathDefinition) {
            return [
                'route_name'      => $definition->routeName,
                'locale'          => $definition->locale,
                'path'            => $definition->path,
                'canonical_style' => $definition->canonicalStyle->value,
                'trailing_slash'  => $definition->trailingSlash->value,
                'alias_mode'      => $definition->aliasMode->value,
                'enabled'         => $definition->enabled,
                'controller'      => $definition->controller ?? '',
            ];
        }

        return [
            'route_name'      => $initialRoute,
            'locale'          => $this->locales->getDefaultLocale(),
            'path'            => '/',
            'canonical_style' => CanonicalStyle::WithoutPrefix->value,
            'trailing_slash'  => TrailingSlashStyle::Omit->value,
            'alias_mode'      => AliasMode::Redirect->value,
            'enabled'         => true,
            'controller'      => '',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function definitionFromFormData(array $data, ?string $id): RoutePathDefinition
    {
        $controller = null;
        if ($this->allowControllerOverride) {
            $raw        = $data['controller'] ?? null;
            $controller = is_string($raw) && $raw !== '' ? $raw : null;
        }

        return new RoutePathDefinition(
            routeName: (string) ($data['route_name'] ?? ''),
            locale: (string) ($data['locale'] ?? ''),
            path: (string) ($data['path'] ?? '/'),
            canonicalStyle: CanonicalStyle::tryFrom((string) ($data['canonical_style'] ?? CanonicalStyle::WithoutPrefix->value)) ?? CanonicalStyle::WithoutPrefix,
            trailingSlash: TrailingSlashStyle::tryFrom((string) ($data['trailing_slash'] ?? TrailingSlashStyle::Omit->value)) ?? TrailingSlashStyle::Omit,
            aliasMode: AliasMode::tryFrom((string) ($data['alias_mode'] ?? AliasMode::Redirect->value)) ?? AliasMode::Redirect,
            enabled: (bool) ($data['enabled'] ?? false),
            controller: $controller,
            id: $id,
        );
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
