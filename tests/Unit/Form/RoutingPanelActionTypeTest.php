<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Form;

use Nowo\RoutingKitBundle\Controller\RoutingPanelController;
use Nowo\RoutingKitBundle\Form\RoutingPanelActionType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class RoutingPanelActionTypeTest extends TestCase
{
    public function testUsesSharedCsrfConfiguration(): void
    {
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('getToken')->willReturn(new CsrfToken(RoutingPanelController::CSRF_TOKEN_ID, 'token-value'));

        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension($csrf))
            ->addType(new RoutingPanelActionType())
            ->getFormFactory();

        $form = $factory->createNamed('', RoutingPanelActionType::class);

        self::assertSame('_csrf_token', $form->getConfig()->getOption('csrf_field_name'));
        self::assertSame(RoutingPanelController::CSRF_TOKEN_ID, $form->getConfig()->getOption('csrf_token_id'));
        self::assertSame('routing_panel_action', $form->getConfig()->getType()->getInnerType()->getBlockPrefix());
        self::assertCount(1, $form);
        self::assertTrue($form->has('confirmed'));
    }
}
