<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Controller;

use DateTimeImmutable;
use Nowo\RoutingKitBundle\Form\RoutingPanelActionType;
use Nowo\RoutingKitBundle\Form\UrlRedirectType;
use Nowo\RoutingKitBundle\Model\UrlRedirect;
use Nowo\RoutingKitBundle\NowoRoutingKitBundle;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectMatcher;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectValidator;
use Nowo\RoutingKitBundle\Security\PanelAccessGuard;
use Nowo\RoutingKitBundle\Storage\UrlRedirectStorageInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use Twig\Environment;

use function array_slice;
use function ceil;
use function count;
use function in_array;
use function is_numeric;
use function is_string;
use function max;
use function min;

/**
 * Panel CRUD for operator URL redirects under `{panel.path_prefix}/redirects` (REQ-REDIR-007).
 *
 * Active only when `url_redirects.enabled` and `panel.enabled` are true; otherwise every action is a
 * 404. Access uses the panel guard (security.access_roles). Writes are CSRF-protected; delete goes
 * through a confirmation page (no inline JavaScript, CSP-friendly).
 */
#[AsController]
final class UrlRedirectPanelController
{
    public const CSRF_TOKEN_ID = 'nowo_routing_kit_url_redirect';

    public const DELETE_CSRF_TOKEN_PREFIX = 'nowo_routing_kit_url_redirect_delete_';

    private const STATUS_MESSAGES = ['created', 'saved', 'deleted'];

    public function __construct(
        private readonly Environment $twig,
        private readonly FormFactoryInterface $formFactory,
        private readonly ?PanelAccessGuard $accessGuard = null,
        private readonly ?UrlRedirectStorageInterface $storage = null,
        private readonly ?UrlRedirectMatcher $matcher = null,
        private readonly ?UrlRedirectValidator $validator = null,
        private readonly ?TranslatorInterface $translator = null,
        private readonly string $pathPrefix = '/_routing',
        private readonly int $listPageSize = 50,
    ) {
    }

    #[Route('/redirects', name: 'nowo_routing_kit_redirects', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $storage  = $this->enabledStorage();
        $all      = $storage->all();
        $total    = count($all);
        $pageSize = max(1, $this->listPageSize);
        $pages    = max(1, (int) ceil($total / $pageSize));
        $page     = max(1, min($pages, $request->query->getInt('page', 1)));
        $rows     = array_slice($all, ($page - 1) * $pageSize, $pageSize);
        $status   = $request->query->getString('status');

        return $this->render('@NowoRoutingKitBundle/panel/redirects/index.html.twig', [
            'redirects'   => $rows,
            'path_prefix' => $this->pathPrefix,
            'status'      => in_array($status, self::STATUS_MESSAGES, true) ? $status : null,
            'page'        => $page,
            'pages'       => $pages,
            'total'       => $total,
            'page_size'   => $pageSize,
            'item_count'  => count($rows),
        ]);
    }

    #[Route('/redirects/new', name: 'nowo_routing_kit_redirects_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $this->enabledStorage();

        return $this->form($request, null);
    }

    #[Route('/redirects/{id}/edit', name: 'nowo_routing_kit_redirects_edit', requirements: ['id' => '[A-Za-z0-9_.-]+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id): Response
    {
        return $this->form($request, $this->find($id));
    }

    #[Route('/redirects/{id}/delete', name: 'nowo_routing_kit_redirects_delete', requirements: ['id' => '[A-Za-z0-9_.-]+'], methods: ['GET', 'POST'])]
    public function delete(Request $request, string $id): Response
    {
        $redirect = $this->find($id);
        $form     = $this->formFactory->createNamed('', RoutingPanelActionType::class, null, [
            'csrf_token_id' => self::DELETE_CSRF_TOKEN_PREFIX . $id,
        ]);
        $form->handleRequest($request);

        if ($request->isMethod('POST')) {
            if (!$form->isSubmitted() || !$form->isValid()) {
                return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
            }

            $this->enabledStorage()->delete($id);
            $this->matcher?->invalidate();

            return new RedirectResponse($this->pathPrefix . '/redirects?status=deleted');
        }

        return $this->render('@NowoRoutingKitBundle/panel/redirects/delete.html.twig', [
            'redirect'    => $redirect,
            'form'        => $form->createView(),
            'path_prefix' => $this->pathPrefix,
        ]);
    }

    private function form(Request $request, ?UrlRedirect $existing): Response
    {
        [$storage, $validator] = $this->services();

        /** @var FormInterface<array<string, mixed>|null> $form */
        $form = $this->formFactory->create(UrlRedirectType::class, [
            'source_path' => $existing?->getSourcePath() ?? '',
            'target_url'  => $existing?->getTargetUrl() ?? '',
            'status_code' => $existing?->getStatusCode() ?? 301,
            'enabled'     => $existing?->isEnabled() ?? true,
            'note'        => $existing?->getNote() ?? '',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $data */
            $data   = $form->getData() ?? [];
            $source = is_string($data['source_path'] ?? null) ? $data['source_path'] : '';
            $target = is_string($data['target_url'] ?? null) ? $data['target_url'] : '';
            $status = is_numeric($data['status_code'] ?? null) ? (int) $data['status_code'] : 0;
            $note   = is_string($data['note'] ?? null) ? $data['note'] : '';
            $errors = $validator->validate($source, $target, $status, $note, $existing?->getId());

            if ($errors === []) {
                $now      = new DateTimeImmutable();
                $user     = $this->accessGuard?->userIdentifier();
                $redirect = $existing ?? (new UrlRedirect())->setCreatedAt($now)->setCreatedBy($user);
                // @igor-ignore - Per-request value object loaded from storage, not a shared service.
                $redirect
                    ->setSourcePath($source)
                    ->setTargetUrl($target)
                    ->setStatusCode($status)
                    ->setEnabled((bool) ($data['enabled'] ?? false))
                    ->setNote($note)
                    ->setUpdatedAt($now)
                    ->setUpdatedBy($user);

                try {
                    $storage->save($redirect);
                    $this->matcher?->invalidate();

                    return new RedirectResponse($this->pathPrefix . '/redirects?status=' . ($existing instanceof UrlRedirect ? 'saved' : 'created'));
                } catch (Throwable $exception) {
                    $form->addError(new FormError($exception->getMessage()));
                }
            }

            foreach ($errors as $field => $key) {
                $form->get($field)->addError(new FormError($this->trans($key), $key));
            }
        }

        return $this->render('@NowoRoutingKitBundle/panel/redirects/form.html.twig', [
            'redirect'    => $existing,
            'form'        => $form->createView(),
            'path_prefix' => $this->pathPrefix,
        ]);
    }

    private function find(string $id): UrlRedirect
    {
        $redirect = $this->enabledStorage()->findById($id);
        if (!$redirect instanceof UrlRedirect) {
            throw new NotFoundHttpException('Redirect not found.');
        }

        return $redirect;
    }

    private function enabledStorage(): UrlRedirectStorageInterface
    {
        return $this->services()[0];
    }

    /**
     * 404 when the feature or the panel is off; 403 via the guard otherwise.
     *
     * @return array{UrlRedirectStorageInterface, UrlRedirectValidator}
     */
    private function services(): array
    {
        if (!$this->accessGuard instanceof PanelAccessGuard
            || !$this->storage instanceof UrlRedirectStorageInterface
            || !$this->validator instanceof UrlRedirectValidator
        ) {
            throw new NotFoundHttpException('URL redirects are not enabled.');
        }

        $this->accessGuard->assertGranted();

        return [$this->storage, $this->validator];
    }

    private function trans(string $key): string
    {
        return $this->translator?->trans($key, [], NowoRoutingKitBundle::TRANSLATION_DOMAIN) ?? $key;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context): Response
    {
        return new Response($this->twig->render($template, $context));
    }
}
