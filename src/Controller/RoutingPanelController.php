<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Controller;

use Nowo\RoutingKitBundle\Discovery\RoutableControllerDiscovery;
use Nowo\RoutingKitBundle\Locale\LocaleProviderInterface;
use Nowo\RoutingKitBundle\Model\AliasMode;
use Nowo\RoutingKitBundle\Model\CanonicalStyle;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Model\TrailingSlashStyle;
use Nowo\RoutingKitBundle\Service\RoutePathManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Throwable;
use Twig\Environment;

use function is_string;

final class RoutingPanelController
{
    public const CSRF_TOKEN_ID = 'nowo_routing_kit_panel';

    public function __construct(
        private readonly RoutePathManager $manager,
        private readonly RoutableControllerDiscovery $discovery,
        private readonly LocaleProviderInterface $locales,
        private readonly Environment $twig,
        private readonly string $pathPrefix = '/_routing',
        private readonly ?CsrfTokenManagerInterface $csrfTokenManager = null,
    ) {
    }

    public function index(): Response
    {
        return new Response($this->twig->render('@NowoRoutingKitBundle/panel/index.html.twig', [
            'definitions' => $this->manager->all(),
            'path_prefix' => $this->pathPrefix,
            'locales'     => $this->locales->getLocales(),
            'default'     => $this->locales->getDefaultLocale(),
            'csrf_token'  => $this->csrfToken(),
        ]));
    }

    public function create(Request $request): Response
    {
        return $this->form($request, null);
    }

    public function edit(Request $request, string $id): Response
    {
        $definition = $this->manager->get($id);
        if (!$definition instanceof RoutePathDefinition) {
            return new Response('Not found', 404);
        }

        return $this->form($request, $definition);
    }

    public function delete(Request $request, string $id): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfValid($request)) {
                return new Response('Invalid CSRF token.', 403);
            }
            $this->manager->delete($id);
        }

        return new RedirectResponse($this->pathPrefix . '/');
    }

    public function clearCache(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfValid($request)) {
                return new Response('Invalid CSRF token.', 403);
            }
            $this->manager->clearCache();
        }

        return new RedirectResponse($this->pathPrefix . '/');
    }

    private function form(Request $request, ?RoutePathDefinition $existing): Response
    {
        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfValid($request)) {
                $errors[] = 'Invalid CSRF token.';
            } else {
                $routeName  = (string) $request->request->get('route_name', '');
                $locale     = (string) $request->request->get('locale', '');
                $path       = (string) $request->request->get('path', '/');
                $controller = $request->request->get('controller');
                $controller = is_string($controller) && $controller !== '' ? $controller : null;

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

                try {
                    $this->manager->save($definition);

                    return new RedirectResponse($this->pathPrefix . '/');
                } catch (Throwable $e) {
                    $errors[] = $e->getMessage();
                    $existing = $definition;
                }
            }
        }

        return new Response($this->twig->render('@NowoRoutingKitBundle/panel/form.html.twig', [
            'definition'       => $existing,
            'routables'        => $this->discovery->discover(),
            'locales'          => $this->locales->getLocales(),
            'path_prefix'      => $this->pathPrefix,
            'errors'           => $errors,
            'canonical_styles' => CanonicalStyle::cases(),
            'trailing_styles'  => TrailingSlashStyle::cases(),
            'alias_modes'      => AliasMode::cases(),
            'csrf_token'       => $this->csrfToken(),
        ]));
    }

    private function csrfToken(): ?string
    {
        return $this->csrfTokenManager?->getToken(self::CSRF_TOKEN_ID)->getValue();
    }

    private function isCsrfValid(Request $request): bool
    {
        if (!$this->csrfTokenManager instanceof CsrfTokenManagerInterface) {
            return true;
        }

        $value = (string) $request->request->get('_csrf_token', '');

        return $this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $value));
    }
}
