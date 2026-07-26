<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Model;

/**
 * One stored path row keyed by (routeName, locale).
 *
 * Canonical definition is always conceptually /{_locale} + $path.
 */
final class RoutePathDefinition
{
    public function __construct(
        public readonly string $routeName,
        public readonly string $locale,
        public readonly string $path,
        public readonly CanonicalStyle $canonicalStyle = CanonicalStyle::WithoutPrefix,
        public readonly TrailingSlashStyle $trailingSlash = TrailingSlashStyle::Omit,
        public readonly AliasMode $aliasMode = AliasMode::Redirect,
        public readonly bool $enabled = true,
        public readonly ?string $controller = null,
        public readonly ?string $id = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'route_name'      => $this->routeName,
            'locale'          => $this->locale,
            'path'            => $this->path,
            'canonical_style' => $this->canonicalStyle->value,
            'trailing_slash'  => $this->trailingSlash->value,
            'alias_mode'      => $this->aliasMode->value,
            'enabled'         => $this->enabled,
            'controller'      => $this->controller,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $canonical = CanonicalStyle::tryFrom((string) ($data['canonical_style'] ?? CanonicalStyle::WithoutPrefix->value))
            ?? CanonicalStyle::WithoutPrefix;
        $trailing = TrailingSlashStyle::tryFrom((string) ($data['trailing_slash'] ?? TrailingSlashStyle::Omit->value))
            ?? TrailingSlashStyle::Omit;
        $alias = AliasMode::tryFrom((string) ($data['alias_mode'] ?? AliasMode::Redirect->value))
            ?? AliasMode::Redirect;

        return new self(
            routeName: (string) ($data['route_name'] ?? ''),
            locale: (string) ($data['locale'] ?? ''),
            path: (string) ($data['path'] ?? '/'),
            canonicalStyle: $canonical,
            trailingSlash: $trailing,
            aliasMode: $alias,
            enabled: (bool) ($data['enabled'] ?? true),
            controller: isset($data['controller']) ? (string) $data['controller'] : null,
            id: isset($data['id']) ? (string) $data['id'] : null,
        );
    }

    public function withId(string $id): self
    {
        return new self(
            $this->routeName,
            $this->locale,
            $this->path,
            $this->canonicalStyle,
            $this->trailingSlash,
            $this->aliasMode,
            $this->enabled,
            $this->controller,
            $id,
        );
    }

    public function withoutController(): self
    {
        return new self(
            $this->routeName,
            $this->locale,
            $this->path,
            $this->canonicalStyle,
            $this->trailingSlash,
            $this->aliasMode,
            $this->enabled,
            null,
            $this->id,
        );
    }
}
