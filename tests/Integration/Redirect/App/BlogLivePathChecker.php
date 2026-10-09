<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Integration\Redirect\App;

use Nowo\RoutingKitBundle\Redirect\LivePathCheckerInterface;

/**
 * Only the post "published" exists; any other slug matched by blog_post is a retired URL.
 */
final class BlogLivePathChecker implements LivePathCheckerInterface
{
    public function isLive(string $route, array $parameters): ?bool
    {
        return $route === 'blog_post' ? ($parameters['slug'] ?? null) === 'published' : null;
    }
}
