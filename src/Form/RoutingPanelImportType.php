<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Nowo\RoutingKitBundle\Controller\RoutingPanelController;
use Nowo\RoutingKitBundle\NowoRoutingKitBundle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Symfony form for signed route path imports.
 *
 * @extends AbstractType<array<string, mixed>|null>
 */
#[FormKitConfig('routing_kit')]
final class RoutingPanelImportType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addTextarea($builder, 'payload_json', [
            'label'    => 'panel.import_payload',
            'required' => false,
            'attr'     => [
                'rows'        => 6,
                'required'    => true,
                'placeholder' => 'panel.import_placeholder',
            ],
        ]);
        $this->addCheckbox($builder, 'replace_all', [
            'label'    => 'panel.import_replace_all',
            'required' => false,
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
        return 'routing_panel_import';
    }
}
