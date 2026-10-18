<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Integration\Locale\App\Controller;

use Nowo\RoutingKitBundle\Attribute\Routable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class LocalePageController
{
    #[Routable(name: 'rk_locale_page')]
    public function page(Request $request): Response
    {
        return new Response('page:' . $request->getLocale());
    }
}
