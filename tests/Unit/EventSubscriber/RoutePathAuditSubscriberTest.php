<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\EventSubscriber;

use Nowo\RoutingKitBundle\Event\RoutePathsChangedEvent;
use Nowo\RoutingKitBundle\EventSubscriber\RoutePathAuditSubscriber;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class RoutePathAuditSubscriberTest extends TestCase
{
    public function testLogsWithUserIdentifierWhenTokenPresent(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(
            'RoutingKit path {action}',
            self::callback(static function (array $context): bool {
                return $context['action'] === 'saved'
                    && $context['user'] === 'admin'
                    && $context['route_name'] === 'app_about';
            }),
        );

        $user    = new InMemoryUser('admin', null, ['ROLE_ADMIN']);
        $token   = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $storage = $this->createMock(TokenStorageInterface::class);
        $storage->method('getToken')->willReturn($token);

        $subscriber = new RoutePathAuditSubscriber($logger, $storage);
        $subscriber->onPathsChanged(new RoutePathsChangedEvent(
            new RoutePathDefinition('app_about', 'en', '/about'),
        ));
    }

    public function testDoesNothingWithoutLoggerAndExposesEvents(): void
    {
        self::assertSame(
            [RoutePathsChangedEvent::class => 'onPathsChanged'],
            RoutePathAuditSubscriber::getSubscribedEvents(),
        );
        (new RoutePathAuditSubscriber())->onPathsChanged(new RoutePathsChangedEvent(
            new RoutePathDefinition('app_about', 'en', '/about'),
        ));
    }

    public function testLogsAnonymousWhenTokenMissing(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(
            'RoutingKit path {action}',
            self::callback(static fn (array $context): bool => $context['user'] === 'anonymous' && $context['action'] === 'deleted'),
        );
        $storage = $this->createMock(TokenStorageInterface::class);
        $storage->method('getToken')->willReturn(null);

        (new RoutePathAuditSubscriber($logger, $storage))->onPathsChanged(new RoutePathsChangedEvent(
            new RoutePathDefinition('app_about', 'en', '/about'),
            deleted: true,
        ));
    }

    public function testLogsTokenClassWhenUserIsNotUserInterface(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(
            'RoutingKit path {action}',
            self::callback(static fn (array $context): bool => str_starts_with((string) $context['user'], 'token:')),
        );

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);
        $storage = $this->createMock(TokenStorageInterface::class);
        $storage->method('getToken')->willReturn($token);

        (new RoutePathAuditSubscriber($logger, $storage))->onPathsChanged(new RoutePathsChangedEvent(
            new RoutePathDefinition('app_about', 'en', '/about'),
        ));
    }
}
