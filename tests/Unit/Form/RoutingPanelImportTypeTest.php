<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Form;

use Nowo\RoutingKitBundle\Controller\RoutingPanelController;
use Nowo\RoutingKitBundle\Form\RoutingPanelImportType;
use Nowo\RoutingKitBundle\Tests\Support\FormKitTestSupport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class RoutingPanelImportTypeTest extends TestCase
{
    public function testBuildsExpectedFieldsAndCsrfOptions(): void
    {
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('getToken')->willReturn(new CsrfToken(RoutingPanelController::CSRF_TOKEN_ID, 'token-value'));

        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension($csrf))
            ->addType(FormKitTestSupport::withMerger(new RoutingPanelImportType()))
            ->getFormFactory();

        $form = $factory->createNamed('', RoutingPanelImportType::class);

        self::assertTrue($form->has('payload_json'));
        self::assertTrue($form->has('replace_all'));
        self::assertSame('_csrf_token', $form->getConfig()->getOption('csrf_field_name'));
        self::assertSame(RoutingPanelController::CSRF_TOKEN_ID, $form->getConfig()->getOption('csrf_token_id'));
        self::assertSame('routing_panel_import', $form->getConfig()->getType()->getInnerType()->getBlockPrefix());
        $attr = $form->get('payload_json')->getConfig()->getOption('attr');
        self::assertSame(6, $attr['rows'] ?? null);
        self::assertTrue($attr['required'] ?? false);
        self::assertSame('{"version":1,"payload":[],"signature":"..."}', $attr['placeholder'] ?? null);
        self::assertSame('nowo-ui-input form-control', $attr['class'] ?? null);
        self::assertFalse($form->get('payload_json')->getConfig()->getOption('required'));
        self::assertFalse($form->get('replace_all')->getConfig()->getOption('required'));
    }
}
