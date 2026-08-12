<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Form;

use Nowo\RoutingKitBundle\Controller\RoutingPanelController;
use Nowo\RoutingKitBundle\NowoRoutingKitBundle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Symfony form for toolbar actions that only need CSRF protection.
 *
 * @extends AbstractType<array<string, mixed>|null>
 */
final class RoutingPanelActionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('confirmed', HiddenType::class, [
            'data' => '1',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => null,
            'translation_domain' => NowoRoutingKitBundle::TRANSLATION_DOMAIN,
            'csrf_field_name'    => '_csrf_token',
            'csrf_token_id'      => RoutingPanelController::CSRF_TOKEN_ID,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'routing_panel_action';
    }
}
