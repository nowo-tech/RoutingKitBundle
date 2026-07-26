<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Model;

enum TrailingSlashStyle: string
{
    case Omit           = 'omit';
    case Keep           = 'keep';
    case RedirectToOmit = 'redirect_to_omit';
    case RedirectToKeep = 'redirect_to_keep';
}
