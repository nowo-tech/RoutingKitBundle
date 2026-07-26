<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Model;

enum CanonicalStyle: string
{
    /** Public canonical URL without locale prefix (typically default locale). */
    case WithoutPrefix = 'without_prefix';

    /** Public canonical URL includes /{locale} prefix. */
    case WithPrefix = 'with_prefix';
}
