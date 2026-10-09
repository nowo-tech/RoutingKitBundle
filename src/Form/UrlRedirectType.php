<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Nowo\RoutingKitBundle\Controller\UrlRedirectPanelController;
use Nowo\RoutingKitBundle\Model\UrlRedirect;
use Nowo\RoutingKitBundle\NowoRoutingKitBundle;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectValidator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_combine;
use function array_map;

/**
 * Panel form for an operator URL redirect (REQ-REDIR-006). Array data; rules live in
 * {@see UrlRedirectValidator} (no symfony/validator dependency).
 *
 * @extends AbstractType<array<string, mixed>|null>
 */
#[FormKitConfig('routing_kit')]
final class UrlRedirectType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addText($builder, 'source_path', [
            'label'    => 'redirects.col.source',
            'required' => true,
            'help'     => 'redirects.form.source_help',
            'attr'     => ['placeholder' => '/old-page', 'maxlength' => UrlRedirect::SOURCE_MAX_LENGTH],
        ]);
        $this->addText($builder, 'target_url', [
            'label'    => 'redirects.col.target',
            'required' => true,
            'help'     => 'redirects.form.target_help',
            'attr'     => ['placeholder' => '/new-page', 'maxlength' => UrlRedirect::TARGET_MAX_LENGTH],
        ]);
        $this->addChoice($builder, 'status_code', [
            'label'                     => 'redirects.col.status',
            'choices'                   => array_combine(array_map(strval(...), UrlRedirect::STATUS_CODES), UrlRedirect::STATUS_CODES),
            'choice_translation_domain' => false,
            'required'                  => true,
        ]);
        $this->addCheckbox($builder, 'enabled', [
            'label'    => 'redirects.col.enabled',
            'required' => false,
        ]);
        $this->addText($builder, 'note', [
            'label'      => 'redirects.col.note',
            'required'   => false,
            'empty_data' => '',
            'attr'       => ['maxlength' => UrlRedirect::NOTE_MAX_LENGTH],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => null,
            'translation_domain' => NowoRoutingKitBundle::TRANSLATION_DOMAIN,
            'csrf_field_name'    => '_csrf_token',
            'csrf_token_id'      => UrlRedirectPanelController::CSRF_TOKEN_ID,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'url_redirect';
    }
}
