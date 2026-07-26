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
