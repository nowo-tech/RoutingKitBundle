<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Integration\Redirect\App;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PagesController
{
    public function page(Request $request): Response
    {
        return new Response('page:' . $request->attributes->getString('_route'));
    }
}
