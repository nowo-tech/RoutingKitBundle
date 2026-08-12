<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Support;

use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\RoutingKitBundle\Form\RoutePathDefinitionType;
use Nowo\RoutingKitBundle\Form\RoutingPanelActionType;
use Nowo\RoutingKitBundle\Form\RoutingPanelImportType;
use Nowo\RoutingKitBundle\NowoRoutingKitBundle;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function call_user_func;

/**
 * Injects a minimal FormOptionsMerger (profile {@code routing_kit}) into Form Kit form types under test.
 */
final class FormKitTestSupport
{
    public static function merger(): FormOptionsMerger
    {
        $profile = [
            'translation_domain' => NowoRoutingKitBundle::TRANSLATION_DOMAIN,
            'auto_placeholder'   => false,
            'auto_help'          => false,
            'defaults'           => [
                'attr'     => ['class' => 'nowo-ui-input form-control'],
                'row_attr' => ['class' => 'mb-2'],
            ],
            'field_types' => [],
        ];

        return new FormOptionsMerger(
            [
                'routing_kit' => $profile,
                'default'     => $profile,
            ],
            'routing_kit',
            new ConstraintDefinitionFactory(),
        );
    }

    /**
     * @template T of object
     *
     * @param T $formType
     *
     * @return T
     */
    public static function withMerger(object $formType): object
    {
        if (!method_exists($formType, 'setFormOptionsMerger')) {
            return $formType;
        }

        call_user_func([$formType, 'setFormOptionsMerger'], self::merger());

        return $formType;
    }

    public static function createFormFactory(CsrfTokenManagerInterface $csrfTokenManager): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(new CsrfExtension($csrfTokenManager))
            ->addType(new RoutingPanelActionType())
            ->addType(self::withMerger(new RoutingPanelImportType()))
            ->addType(self::withMerger(new RoutePathDefinitionType()))
            ->getFormFactory();
    }
}
