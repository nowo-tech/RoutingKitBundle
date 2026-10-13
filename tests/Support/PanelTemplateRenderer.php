<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Support;

use Symfony\Bridge\Twig\AppVariable;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

use function dirname;

use const ENT_QUOTES;

/**
 * Renders the panel templates with the real UiKit macros and translation catalogue, plus deterministic
 * stubs for routing and form helpers (markers such as {@code <form-row name="path">}). No kernel needed.
 */
final class PanelTemplateRenderer
{
    private readonly Environment $twig;

    /**
     * @param array<string, string> $hostTemplates extra templates (name => source) resolved before the bundle paths
     * @param array<string, mixed> $globals overrides for the bundle Twig globals
     */
    public function __construct(array $hostTemplates = [], array $globals = [], string $locale = 'en')
    {
        $root  = dirname(__DIR__, 2);
        $views = $root . '/src/Resources/views';

        $filesystem = new FilesystemLoader([], $root);
        $filesystem->addPath($views, 'NowoRoutingKitBundle');
        $filesystem->addPath($views, '!NowoRoutingKitBundle');
        $filesystem->addPath($root . '/vendor/nowo-tech/ui-kit-bundle/src/Resources/views', 'NowoUiKitBundle');

        $this->twig = new Environment(new ChainLoader([new ArrayLoader($hostTemplates), $filesystem]), [
            'strict_variables' => true,
            'autoescape'       => 'html',
            'cache'            => false,
        ]);

        $translator = new Translator($locale);
        $translator->addLoader('yaml', new YamlFileLoader());
        foreach (glob($root . '/src/Resources/translations/NowoRoutingKitBundle.*.yaml') ?: [] as $file) {
            $translator->addResource('yaml', $file, explode('.', basename($file))[1], 'NowoRoutingKitBundle');
        }
        $this->twig->addExtension(new TranslationExtension($translator));

        $requestStack = new RequestStack();
        $request      = Request::create('/_routing-kit/');
        $request->attributes->set('_route', 'nowo_routing_kit_panel');
        $request->attributes->set('_route_params', []);
        $requestStack->push($request);
        $app = new AppVariable();
        $app->setRequestStack($requestStack);
        $this->twig->addGlobal('app', $app);

        foreach (array_merge([
            'nowo_routing_kit_layout_template'       => '@NowoRoutingKitBundle/panel/layout.html.twig',
            'nowo_routing_kit_css_framework'         => 'custom',
            'nowo_routing_kit_icon_set'              => 'none',
            'nowo_routing_kit_url_redirects_enabled' => false,
            'nowo_ui_kit_css_framework'              => 'custom',
        ], $globals) as $name => $value) {
            $this->twig->addGlobal($name, $value);
        }

        $safe    = ['is_safe' => ['html']];
        $options = static fn (array $o): string => $o === [] ? '' : ' options="' . htmlspecialchars((string) json_encode($o), ENT_QUOTES) . '"';
        $this->twig->addFunction(new TwigFunction('path', static fn (string $route, array $params = []): string => '/r/' . $route . ($params === [] ? '' : '?' . http_build_query($params))));
        $this->twig->addFunction(new TwigFunction('asset', static fn (string $path, ?string $package = null): string => '/assets/' . ($package ?? 'app') . '/' . $path));
        $this->twig->addFunction(new TwigFunction('form_start', static fn (FormView $view, array $o = []): string => '<form-start name="' . $view->vars['name'] . '"' . $options($o) . '>', $safe));
        $this->twig->addFunction(new TwigFunction('form_end', static function (FormView $view, array $o = []) use ($options): string {
            $view->setRendered();

            return '<form-end name="' . $view->vars['name'] . '"' . $options($o) . '>';
        }, $safe));
        $this->twig->addFunction(new TwigFunction('form_rest', static fn (FormView $view): string => '<form-rest name="' . $view->vars['name'] . '">', $safe));
        $this->twig->addFunction(new TwigFunction('form_errors', static fn (FormView $view): string => '<form-errors name="' . $view->vars['name'] . '">', $safe));
        $this->twig->addFunction(new TwigFunction('form_row', static function (FormView $view, array $o = []) use ($options): string {
            $view->setRendered();

            return '<form-row name="' . $view->vars['name'] . '"' . $options($o) . '>';
        }, $safe));
    }

    /**
     * @param list<string> $children
     */
    public static function form(string $name, array $children = []): FormView
    {
        $view               = new FormView();
        $view->vars['name'] = $name;
        foreach ($children as $child) {
            $childView               = new FormView($view);
            $childView->vars['name'] = $child;
            $view->children[$child]  = $childView;
        }

        return $view;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context = []): string
    {
        return $this->twig->render($template, $context);
    }

    /**
     * Collapse whitespace so snapshots ignore indentation-only template edits.
     */
    public static function normalize(string $html): string
    {
        $html = (string) preg_replace('/\s+/', ' ', $html);
        $html = (string) preg_replace('/>\s+</', ">\n<", $html);
        $html = (string) preg_replace('/\s+>/', '>', $html);
        $html = (string) preg_replace('/="\s+/', '="', $html);
        $html = (string) preg_replace('/\s+"/', '"', $html);

        return trim($html) . "\n";
    }
}
