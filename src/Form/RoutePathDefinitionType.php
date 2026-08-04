<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Nowo\RoutingKitBundle\Controller\RoutingPanelController;
use Nowo\RoutingKitBundle\Model\AliasMode;
use Nowo\RoutingKitBundle\Model\CanonicalStyle;
use Nowo\RoutingKitBundle\Model\TrailingSlashStyle;
use Nowo\RoutingKitBundle\NowoRoutingKitBundle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function is_array;
use function is_string;

/**
 * Symfony form for the routing panel create/edit path row.
 *
 * @extends AbstractType<array<string, mixed>|null>
 */
#[FormKitConfig('routing_kit')]
final class RoutePathDefinitionType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $routeChoices = [];
        foreach ($options['routables'] as $item) {
            if (!is_array($item) || !isset($item['route_name']) || !is_string($item['route_name'])) {
                continue;
            }
            $routeChoices[$item['route_name']] = $item['route_name'];
        }

        $localeChoices = [];
        foreach ($options['locales'] as $locale) {
            if (is_string($locale) && $locale !== '') {
                $localeChoices[$locale] = $locale;
            }
        }

        $this->addChoice($builder, 'route_name', [
            'label'    => 'panel.col.route',
            'choices'  => $routeChoices,
            'required' => true,
        ]);
        $this->addChoice($builder, 'locale', [
            'label'    => 'panel.col.locale',
            'choices'  => $localeChoices,
            'required' => true,
        ]);
        $this->addText($builder, 'path', [
            'label'    => 'panel.col.path',
            'required' => true,
            'attr'     => ['placeholder' => '/about'],
        ]);
        $this->addChoice($builder, 'canonical_style', [
            'label'   => 'panel.col.canonical',
            'choices' => $this->enumChoices(CanonicalStyle::class, 'panel.form.canonical_style'),
        ]);
        $this->addChoice($builder, 'trailing_slash', [
            'label'   => 'panel.form.trailing_slash',
            'choices' => $this->enumChoices(TrailingSlashStyle::class, 'panel.form.trailing_slash_style'),
        ]);
        $this->addChoice($builder, 'alias_mode', [
            'label'   => 'panel.form.alias_mode',
            'choices' => $this->enumChoices(AliasMode::class, 'panel.form.alias_mode_choice'),
        ]);

        if ($options['allow_controller_override']) {
            $this->addChoice($builder, 'controller', [
                'label'       => 'panel.form.controller',
                'choices'     => $this->controllerChoices($options),
                'required'    => false,
                'placeholder' => false,
            ]);
        }

        $this->addCheckbox($builder, 'enabled', [
            'label'    => 'panel.col.enabled',
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'                => null,
            'translation_domain'        => NowoRoutingKitBundle::TRANSLATION_DOMAIN,
            'csrf_field_name'           => '_csrf_token',
            'csrf_token_id'             => RoutingPanelController::CSRF_TOKEN_ID,
            'routables'                 => [],
            'locales'                   => [],
            'allow_controller_override' => false,
            'is_create'                 => true,
            'initial_route_name'        => null,
        ]);
        $resolver->setAllowedTypes('routables', 'array');
        $resolver->setAllowedTypes('locales', 'array');
        $resolver->setAllowedTypes('allow_controller_override', 'bool');
        $resolver->setAllowedTypes('is_create', 'bool');
        $resolver->setAllowedTypes('initial_route_name', ['null', 'string']);
    }

    public function getBlockPrefix(): string
    {
        return 'route_path_definition';
    }

    /**
     * @param class-string $enumClass
     *
     * @return array<string, string>
     */
    private function enumChoices(string $enumClass, string $labelPrefix): array
    {
        $choices = [];
        foreach ($enumClass::cases() as $case) {
            $choices[$labelPrefix . '.' . $case->value] = $case->value;
        }

        return $choices;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, string>
     */
    private function controllerChoices(array $options): array
    {
        $choices   = ['panel.form.controller_empty' => ''];
        $routeName = $options['initial_route_name'];
        $isCreate  = (bool) $options['is_create'];

        foreach ($options['routables'] as $item) {
            if (!is_array($item) || !isset($item['controller'], $item['route_name'])) {
                continue;
            }
            if (!is_string($item['controller']) || !is_string($item['route_name'])) {
                continue;
            }
            if ($isCreate || $routeName === null || $routeName === '' || $item['route_name'] === $routeName) {
                $choices[$item['controller']] = $item['controller'];
            }
        }

        return $choices;
    }
}
