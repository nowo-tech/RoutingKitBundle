<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Validation;

use Nowo\RoutingKitBundle\Attribute\RouteParam;
use Nowo\RoutingKitBundle\Discovery\RoutableControllerDiscovery;

use function array_key_exists;
use function array_unique;
use function implode;
use function in_array;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function str_starts_with;

/**
 * Validates stored path patterns against #[Routable] parameter declarations.
 */
final class RoutePathValidator
{
    public function __construct(
        private readonly RoutableControllerDiscovery $discovery,
    ) {
    }

    /**
     * @return list<string> Validation error messages (empty = valid)
     */
    public function validate(string $routeName, string $path): array
    {
        $errors = [];

        if ($path === '' || !str_starts_with($path, '/')) {
            $errors[] = 'Path must be absolute and start with "/".';
        }

        if (str_contains($path, '{_locale}')) {
            $errors[] = 'Do not include {_locale} in the stored path; it is always applied by the loader.';
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

        // Static segments: if a param has enum/requirement and appears as fixed value — skip (path uses placeholders)

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
