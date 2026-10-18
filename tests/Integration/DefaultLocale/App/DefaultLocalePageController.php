<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Integration\DefaultLocale\App;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Pages of the default-locale test app: each echoes its locale and the URLs it generates.
 */
final class DefaultLocalePageController
{
    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    public function page(Request $request): Response
    {
        return new Response('page:' . $request->getLocale() . ' link:' . $this->urls->generate('app_page') . ' other:' . $this->urls->generate('app_other'));
    }
}
