# Installation

```bash
composer require nowo-tech/routing-kit-bundle
```

## Symfony Flex recipe

The Flex recipe (`.symfony/recipe/nowo-tech/routing-kit-bundle`) registers the bundle, copies `config/packages/nowo_routing_kit.yaml`, and imports panel routes via `config/routes/nowo_routing_kit.yaml`.

Without Flex, register manually:

```php
// config/bundles.php
return [
    Nowo\RoutingKitBundle\NowoRoutingKitBundle::class => ['all' => true],
];
```

## Routes

Import the **panel** routes and the **DB loader** (loader must come **after** application routes). The loader registers `{name}.{locale}` routes with `_canonical_route` so `path('name', {_locale: 'en'})` works:

```yaml
# config/routes.yaml
nowo_routing_kit_panel:
    resource: '@NowoRoutingKitBundle/Resources/config/routes.yaml'

app_controllers:
    resource:
        path: ../src/Controller/
        namespace: App\Controller
    type: attribute

nowo_routing_kit_db:
    resource: .
    type: nowo_routing_kit
```

## Protect the panel

The bundle does not ship authentication. Restrict `/_routing` (or your `panel.path_prefix`) with Symfony Security firewalls / access control.

## Assets

Run `assets:install` after installing the bundle so the panel CSS is available:

```bash
php bin/console assets:install --symlink
```

This copies (or symlinks) `src/Resources/public/` to `public/bundles/noworoutingkit/` when present. Panel look-and-feel CSS comes from **UiKitBundle** (`asset('css/nowo-ui.css', 'nowo_ui_kit')` → `/bundles/nowouikit/css/nowo-ui.css`). Run `assets:install` so both packages are published.

### Host layout integration (`css_framework: custom`)

Set `web_ui.css_framework: custom` and point `web_ui.layout_template` at your project shell. The intermediate `panel/base.html.twig` injects the bundle CSS via the `stylesheets` block, so your host shell receives it through `{{ parent() }}`:

```yaml
# config/packages/nowo_routing_kit.yaml
nowo_routing_kit:
    web_ui:
        layout_template: 'base.html.twig'  # your app layout
        css_framework: custom
    security:
        access_roles: [ROLE_ADMIN]
```

Override `--nowo-ui-*` CSS tokens in your own stylesheet to match your brand without forking any bundle template.

## Twig Extra Bundle (REQ-TWIG-004)

This package ships Twig templates. Host applications **must** install and enable Twig Extra:

```bash
composer require twig/extra-bundle twig/string-extra
```

Register `Twig\Extra\TwigExtraBundle\TwigExtraBundle` in `config/bundles.php` (Flex usually does this). Demos already include the same stack. The package `release-check` runs `make check-twig-extra` to guard this contract.
