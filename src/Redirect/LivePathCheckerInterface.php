<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Redirect;

/**
 * Decides whether a matched slug route still serves a live page (REQ-REDIR-003).
 *
 * Routes such as `/blog/{slug}` match any slug, so a router match alone would call a deleted post
 * "live" and refuse the redirect that retires it. Register a checker (autoconfigured with the tag
 * `nowo_routing_kit.live_path_checker`) that answers for the routes it owns:
 *
 * - `true`  — the page exists (redirects are refused / skipped);
 * - `false` — the route matched but the content is gone (redirects apply);
 * - `null`  — not my route, ask the next checker.
 *
 * When no checker answers, a router match counts as live (conservative). A checker that throws
 * counts as live too, so a database outage never turns live pages into redirects.
 */
interface LivePathCheckerInterface
{
    public const TAG = 'nowo_routing_kit.live_path_checker';

    /**
     * @param string $route matched route name (`_route`)
     * @param array<string, mixed> $parameters router match result (`slug`, `_locale`, …)
     */
    public function isLive(string $route, array $parameters): ?bool;
}
