<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Support;

use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\RoutingKitBundle\Form\RoutePathDefinitionType;
use Nowo\RoutingKitBundle\Form\RoutingPanelActionType;
use Nowo\RoutingKitBundle\Form\RoutingPanelImportType;
use Nowo\RoutingKitBundle\Form\UrlRedirectType;
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
    /**
     * @param bool $plain true = profile without framework classes (host / UiKit theme decides; contract test)
     */
    public static function merger(bool $plain = false): FormOptionsMerger
    {
        $profile = [
            'translation_domain' => NowoRoutingKitBundle::TRANSLATION_DOMAIN,
            'auto_placeholder'   => false,
            'auto_help'          => false,
            'defaults'           => $plain ? ['attr' => [], 'row_attr' => []] : [
                'attr'     => ['class' => 'nowo-ui-input form-control'],
                'row_attr' => ['class' => 'mb-2'],
            ],
            'field_types' => [
                'hidden' => ['attr' => ['class' => ''], 'row_attr' => []],
            ],
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
    public static function withMerger(object $formType, bool $plain = false): object
    {
        if (!method_exists($formType, 'setFormOptionsMerger')) {
            return $formType;
        }

        call_user_func([$formType, 'setFormOptionsMerger'], self::merger($plain));

        return $formType;
    }

    public static function createFormFactory(CsrfTokenManagerInterface $csrfTokenManager, bool $plain = false): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(new CsrfExtension($csrfTokenManager))
            ->addType(self::withMerger(new RoutingPanelActionType(), $plain))
            ->addType(self::withMerger(new RoutingPanelImportType(), $plain))
            ->addType(self::withMerger(new RoutePathDefinitionType(), $plain))
            ->addType(self::withMerger(new UrlRedirectType(), $plain))
            ->getFormFactory();
    }
}
