<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Discovery;

use Nowo\RoutingKitBundle\Attribute\Routable;
use Nowo\RoutingKitBundle\Attribute\RouteParam;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Finder\Finder;

use function class_exists;
use function is_dir;
use function sprintf;
use function trim;

/**
 * Scans configured directories for #[Routable] controller actions.
 */
final class RoutableControllerDiscovery
{
    /**
     * @param list<string> $scanDirs Absolute directories to scan for *Controller.php
     */
    public function __construct(
        private readonly array $scanDirs,
    ) {
    }

    /**
     * @return list<array{
     *     route_name: string,
     *     label: string|null,
     *     controller: string,
     *     class: string,
     *     method: string,
     *     params: list<array{
     *         name: string,
     *         required: bool,
     *         requirement: string|null,
     *         type: string|null,
     *         enum: list<string>|null,
     *         default: mixed
     *     }>
     * }>
     */
    public function discover(): array
    {
        $out = [];

        foreach ($this->scanDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $finder = (new Finder())->files()->name('*Controller.php')->in($dir);
            foreach ($finder as $file) {
                $class = $this->guessClassName($file->getRealPath(), $dir);
                if ($class === null || !class_exists($class)) {
                    continue;
                }

                $ref = new ReflectionClass($class);
                foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                    $attr = $this->resolveRoutable($ref, $method);
                    if ($attr === null) {
                        continue;
                    }

                    $params = [];
                    foreach ($attr->params as $param) {
                        $params[] = [
                            'name'        => $param->name,
                            'required'    => $param->required,
                            'requirement' => $param->requirement,
                            'type'        => $param->type,
                            'enum'        => $param->enum,
                            'default'     => $param->default,
                        ];
                    }

                    $out[] = [
                        'route_name' => $attr->name,
                        'label'      => $attr->label,
                        'controller' => sprintf('%s::%s', $class, $method->getName()),
                        'class'      => $class,
                        'method'     => $method->getName(),
                        'params'     => $params,
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * @return array{
     *     route_name: string,
     *     label: string|null,
     *     controller: string,
     *     class: string,
     *     method: string,
     *     params: list<array{
     *         name: string,
     *         required: bool,
     *         requirement: string|null,
     *         type: string|null,
     *         enum: list<string>|null,
     *         default: mixed
     *     }>
     * }|null
     */
    public function findByRouteName(string $routeName): ?array
    {
        foreach ($this->discover() as $item) {
            if ($item['route_name'] === $routeName) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return list<RouteParam>
     */
    public function paramsForRoute(string $routeName): array
    {
        $item = $this->findByRouteName($routeName);
        if ($item === null) {
            return [];
        }

        $params = [];
        foreach ($item['params'] as $p) {
            $params[] = new RouteParam(
                name: $p['name'],
                required: $p['required'],
                requirement: $p['requirement'],
                type: $p['type'],
                enum: $p['enum'],
                default: $p['default'],
            );
        }

        return $params;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function resolveRoutable(ReflectionClass $class, ReflectionMethod $method): ?Routable
    {
        $methodAttrs = $method->getAttributes(Routable::class, ReflectionAttribute::IS_INSTANCEOF);
        if ($methodAttrs !== []) {
            return $methodAttrs[0]->newInstance();
        }

        // Class-level attribute only applies to __invoke
        if ($method->getName() !== '__invoke') {
            return null;
        }

        $classAttrs = $class->getAttributes(Routable::class, ReflectionAttribute::IS_INSTANCEOF);
        if ($classAttrs === []) {
            return null;
        }

        return $classAttrs[0]->newInstance();
    }

    private function guessClassName(string $filePath, string $scanDir): ?string
    {
        // Prefer Composer classmap via token parse when available; fall back to PSR-4-ish guess from App\
        $contents = @file_get_contents($filePath);
        if ($contents === false) {
            return null; // @codeCoverageIgnore
        }

        if (preg_match('/namespace\s+([^;]+);/', $contents, $ns) !== 1) {
            return null;
        }
        if (preg_match('/class\s+(\w+)/', $contents, $cls) !== 1) {
            return null;
        }

        return trim($ns[1]) . '\\' . $cls[1];
    }
}
