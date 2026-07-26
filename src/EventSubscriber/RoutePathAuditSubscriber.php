<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\EventSubscriber;

use Nowo\RoutingKitBundle\Event\RoutePathsChangedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Audit trail for path CRUD (logs user identifier when a security token is available).
 */
final class RoutePathAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
        private readonly ?TokenStorageInterface $tokenStorage = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RoutePathsChangedEvent::class => 'onPathsChanged',
        ];
    }

    public function onPathsChanged(RoutePathsChangedEvent $event): void
    {
        if (!$this->logger instanceof LoggerInterface) {
            return;
        }

        $definition = $event->definition;
        $this->logger->info('RoutingKit path {action}', [
            'action'     => $event->deleted ? 'deleted' : 'saved',
            'route_name' => $definition->routeName,
            'locale'     => $definition->locale,
            'path'       => $definition->path,
            'id'         => $definition->id,
            'user'       => $this->currentUserIdentifier(),
        ]);
    }

    private function currentUserIdentifier(): string
    {
        $token = $this->tokenStorage?->getToken();
        if (!$token instanceof TokenInterface) {
            return 'anonymous';
        }

        $user = $token->getUser();
        if ($user instanceof UserInterface) {
            return $user->getUserIdentifier();
        }

        return 'token:' . $token::class;
    }
}
