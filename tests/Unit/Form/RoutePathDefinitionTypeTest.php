<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Form;

use Nowo\RoutingKitBundle\Form\RoutePathDefinitionType;
use Nowo\RoutingKitBundle\Tests\Support\FormKitTestSupport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class RoutePathDefinitionTypeTest extends TestCase
{
    public function testBuildsExpectedFields(): void
    {
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('getToken')->willReturn(new CsrfToken('nowo_routing_kit_panel', 'token-value'));

        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension($csrf))
            ->addType(FormKitTestSupport::withMerger(new RoutePathDefinitionType()))
            ->getFormFactory();

        $form = $factory->createNamed('', RoutePathDefinitionType::class, null, [
            'routables' => [
                ['route_name' => 'app_home', 'controller' => 'App\\Controller\\HomeController::index'],
            ],
            'locales'   => ['en', 'es'],
            'is_create' => true,
        ]);

        self::assertTrue($form->has('route_name'));
        self::assertTrue($form->has('locale'));
        self::assertTrue($form->has('path'));
        self::assertTrue($form->has('canonical_style'));
        self::assertTrue($form->has('trailing_slash'));
        self::assertTrue($form->has('alias_mode'));
        self::assertTrue($form->has('enabled'));
        self::assertFalse($form->has('controller'));
    }

    public function testAddsControllerFieldWhenOverrideAllowed(): void
    {
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('getToken')->willReturn(new CsrfToken('nowo_routing_kit_panel', 'token-value'));

        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension($csrf))
            ->addType(FormKitTestSupport::withMerger(new RoutePathDefinitionType()))
            ->getFormFactory();

        $form = $factory->createNamed('', RoutePathDefinitionType::class, null, [
            'routables' => [
                ['route_name' => 'app_home', 'controller' => 'App\\Controller\\HomeController::index'],
            ],
            'locales'                   => ['en'],
            'allow_controller_override' => true,
            'initial_route_name'        => 'app_home',
            'is_create'                 => false,
        ]);

        self::assertTrue($form->has('controller'));
    }

    public function testSkipsInvalidRoutablesAndLocalesWhenBuildingChoices(): void
    {
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('getToken')->willReturn(new CsrfToken('nowo_routing_kit_panel', 'token-value'));

        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension($csrf))
            ->addType(FormKitTestSupport::withMerger(new RoutePathDefinitionType()))
            ->getFormFactory();

        $form = $factory->createNamed('', RoutePathDefinitionType::class, null, [
            'routables' => [
                'not-an-array',
                ['route_name' => 123],
                ['controller' => 'App\\Controller\\HomeController::index'],
                [
                    'route_name' => 'app_home',
                    'controller' => 'App\\Controller\\HomeController::index',
                ],
                [
                    'route_name' => 'app_other',
                    'controller' => 42,
                ],
            ],
            'locales'                   => ['en', '', 12, null],
            'allow_controller_override' => true,
            'initial_route_name'        => 'app_home',
            'is_create'                 => false,
        ]);

        $routeChoices = $form->get('route_name')->getConfig()->getOption('choices');
        self::assertSame([
            'app_home'  => 'app_home',
            'app_other' => 'app_other',
        ], $routeChoices);

        $localeChoices = $form->get('locale')->getConfig()->getOption('choices');
        self::assertSame(['en' => 'en'], $localeChoices);

        $controllerChoices = $form->get('controller')->getConfig()->getOption('choices');
        self::assertArrayHasKey('App\\Controller\\HomeController::index', $controllerChoices);
        // Invalid controller type / missing controller must not appear as a choice value.
        self::assertNotContains(42, $controllerChoices);
        self::assertSame('', $controllerChoices['panel.form.controller_empty'] ?? null);
    }
}
