<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Redirect;

use Nowo\RoutingKitBundle\Redirect\LivePathCheckerInterface;
use Nowo\RoutingKitBundle\Redirect\LivePublicPaths;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class LivePublicPathsTest extends TestCase
{
    public function testRouterMatchIsLiveAndCheckersDecideSlugRoutes(): void
    {
        $context = new RequestContext(method: 'POST');
        $matcher = $this->matcher($context);
        $blog    = new class implements LivePathCheckerInterface {
            public function isLive(string $route, array $parameters): ?bool
            {
                return $route === 'blog_post' ? $parameters['slug'] === 'exists' : null;
            }
        };
        $failing = new class implements LivePathCheckerInterface {
            public function isLive(string $route, array $parameters): ?bool
            {
                if ($route === 'specialty') {
                    throw new RuntimeException('db down');
                }

                return null;
            }
        };
        $live = new LivePublicPaths($matcher, [$blog, $failing]);

        self::assertTrue($live->isLive('/contact'), 'GET-only route matches although the context says POST.');
        self::assertSame('POST', $context->getMethod(), 'Context method restored.');
        self::assertFalse($live->isLive('/nowhere'));
        self::assertTrue($live->isLive('/blog/exists'));
        self::assertFalse($live->isLive('/blog/deleted'), 'A slug route whose content is gone is not live.');
        self::assertTrue($live->isLive('/specialty/x'), 'A failing checker counts as live.');
        self::assertTrue($live->isLive('/shop/x'), 'Unknown slug routes count as live.');

        self::assertSame('blog_post', $live->matchedRoute('/blog/anything'));
        self::assertNull($live->matchedRoute('/nowhere'));
    }

    public function testMatchWithoutRouteNameIsLive(): void
    {
        $matcher = $this->createStub(UrlMatcherInterface::class);
        $matcher->method('getContext')->willReturn(new RequestContext());
        $matcher->method('match')->willReturn(['_controller' => 'x']);

        $live = new LivePublicPaths($matcher);

        self::assertTrue($live->isLive('/x'));
        self::assertNull($live->matchedRoute('/x'));
    }

    private function matcher(RequestContext $context): UrlMatcher
    {
        $routes = new RouteCollection();
        $routes->add('contact', new Route('/contact', methods: ['GET']));
        $routes->add('blog_post', new Route('/blog/{slug}'));
        $routes->add('specialty', new Route('/specialty/{slug}'));
        $routes->add('shop', new Route('/shop/{slug}'));

        return new UrlMatcher($routes, $context);
    }
}
