<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Redirect;

use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Throwable;

use function is_string;

/**
 * Whether a public path is served by a live page right now, as a visitor's GET would see it
 * (REQ-REDIR-003): the router matches it, and no {@see LivePathCheckerInterface} says the matched
 * content is gone.
 */
final class LivePublicPaths
{
    /**
     * @param iterable<LivePathCheckerInterface> $checkers
     */
    public function __construct(
        private readonly UrlMatcherInterface $router,
        private readonly iterable $checkers = [],
    ) {
    }

    public function isLive(string $path): bool
    {
        $match = $this->match($path);
        if ($match === null) {
            return false;
        }

        $route = $match['_route'] ?? null;
        if (!is_string($route)) {
            return true;
        }

        foreach ($this->checkers as $checker) {
            try {
                $live = $checker->isLive($route, $match);
            } catch (Throwable) {
                // Content source unavailable (DB down): let routing answer instead of redirecting.
                return true;
            }
            if ($live !== null) {
                return $live;
            }
        }

        return true;
    }

    /**
     * Route a GET on $path resolves to today, whatever the slug.
     */
    public function matchedRoute(string $path): ?string
    {
        $route = $this->match($path)['_route'] ?? null;

        return is_string($route) ? $route : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function match(string $path): ?array
    {
        $context = $this->router->getContext();
        $method  = $context->getMethod();
        // @igor-ignore - Restored in finally; RouterListener resets the context from each request anyway.
        $context->setMethod('GET');
        try {
            /** @var array<string, mixed> $match */
            $match = $this->router->match($path);

            return $match;
        } catch (RoutingException) {
            return null;
        } finally {
            $context->setMethod($method); // @igor-ignore - Restores the method changed above.
        }
    }
}
