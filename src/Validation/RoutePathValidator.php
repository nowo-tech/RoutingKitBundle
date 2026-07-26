<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Validation;

use Nowo\RoutingKitBundle\Attribute\RouteParam;
use Nowo\RoutingKitBundle\Discovery\RoutableControllerDiscovery;
use Nowo\RoutingKitBundle\Locale\LocaleProviderInterface;
use Nowo\RoutingKitBundle\Routing\SafePublicPath;

use function array_key_exists;
use function array_unique;
use function implode;
use function in_array;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function str_contains;

/**
 * Validates stored path patterns against #[Routable] parameter declarations.
 */
final class RoutePathValidator
{
    public function __construct(
        private readonly RoutableControllerDiscovery $discovery,
        private readonly ?LocaleProviderInterface $locales = null,
    ) {
    }

    /**
     * @return list<string> Validation error messages (empty = valid)
     */
    public function validate(string $routeName, string $path, ?string $locale = null, ?string $controller = null): array
    {
        $errors = [];

        if ($routeName === '') {
            $errors[] = 'Route name is required.';
        } elseif ($this->discovery->findByRouteName($routeName) === null) {
            $errors[] = sprintf('Route "%s" is not marked #[Routable].', $routeName);
        }

        if ($locale !== null) {
            if ($locale === '') {
                $errors[] = 'Locale is required.';
            } elseif ($this->locales instanceof LocaleProviderInterface
                && !in_array($locale, $this->locales->getLocales(), true)
            ) {
                $errors[] = sprintf('Locale "%s" is not in configured locales.', $locale);
            }
        }

        if (!SafePublicPath::isSafeStoredPath($path)) {
            $errors[] = 'Path must be an absolute public path starting with "/" (no "//", schemes, or control characters).';
        }

        if (str_contains($path, '{_locale}')) {
            $errors[] = 'Do not include {_locale} in the stored path; it is always applied by the loader.';
        }

        if ($controller !== null && $controller !== '') {
            $allowed = $this->allowedControllersFor($routeName);
            if ($allowed === [] || !in_array($controller, $allowed, true)) {
                $errors[] = sprintf(
                    'Controller override "%s" is not allowed; use the #[Routable] controller for "%s".',
                    $controller,
                    $routeName,
                );
            }
        }

        if ($errors !== [] && !SafePublicPath::isSafeStoredPath($path)) {
            // Still check placeholders when path shape is otherwise ok — skip if unsafe.
            return $errors;
        }

        $placeholders = $this->extractPlaceholders($path);
        $declared     = $this->discovery->paramsForRoute($routeName);
        $declaredMap  = [];
        foreach ($declared as $param) {
            $declaredMap[$param->name] = $param;
        }

        foreach ($placeholders as $name) {
            if ($name === '_locale') {
                continue;
            }
            if (!isset($declaredMap[$name])) {
                $errors[] = sprintf('Unknown path placeholder "{%s}" is not declared on #[Routable] for "%s".', $name, $routeName);
            }
        }

        foreach ($declaredMap as $name => $param) {
            if ($param->required && !in_array($name, $placeholders, true)) {
                $errors[] = sprintf('Required parameter "{%s}" is missing from path.', $name);
            }
        }

        return $errors;
    }

    /**
     * @param array<string, string> $values Concrete values for placeholders (optional runtime check)
     *
     * @return list<string>
     */
    public function validateValues(string $routeName, array $values): array
    {
        $errors   = [];
        $declared = $this->discovery->paramsForRoute($routeName);

        foreach ($declared as $param) {
            $has = array_key_exists($param->name, $values);
            if ($param->required && !$has) {
                $errors[] = sprintf('Missing required parameter "%s".', $param->name);
                continue;
            }
            if (!$has) {
                continue;
            }
            $errors = [...$errors, ...$this->validateParamValue($param, (string) $values[$param->name])];
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    public function allowedControllersFor(string $routeName): array
    {
        $item = $this->discovery->findByRouteName($routeName);
        if ($item === null) {
            return [];
        }

        return [(string) $item['controller']];
    }

    /**
     * @return list<string>
     */
    private function validateParamValue(RouteParam $param, string $value): array
    {
        $errors = [];

        if ($param->enum !== null && $param->enum !== [] && !in_array($value, $param->enum, true)) {
            $errors[] = sprintf(
                'Parameter "%s" must be one of: %s.',
                $param->name,
                implode(', ', $param->enum),
            );
        }

        if ($param->requirement !== null && $param->requirement !== '') {
            $pattern = sprintf('#^%s$#', $param->requirement);
            if (@preg_match($pattern, $value) !== 1) {
                $errors[] = sprintf('Parameter "%s" does not match requirement "%s".', $param->name, $param->requirement);
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function extractPlaceholders(string $path): array
    {
        preg_match_all('/\{([a-zA-Z_]\w*)\}/', $path, $m);

        return array_values(array_unique($m[1]));
    }
}
