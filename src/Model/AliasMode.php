<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Model;

enum AliasMode: string
{
    /** Non-canonical twin redirects to canonical (301/302). */
    case Redirect = 'redirect';

    /** Both /foo and /{locale}/foo return 200. */
    case Alias = 'alias';
}
