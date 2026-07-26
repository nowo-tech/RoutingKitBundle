<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\RoutingKitBundle\Attribute\Routable;
use Nowo\RoutingKitBundle\Attribute\RouteParam;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route(path: '/', name: 'homepage', methods: ['GET'])]
    #[Routable(name: 'homepage')]
    public function home(): Response
    {
        return $this->render('home.html.twig');
    }

    #[Route(path: '/about', name: 'app_about', methods: ['GET'])]
    #[Routable(name: 'app_about')]
    public function about(): Response
    {
        return $this->render('about.html.twig');
    }

    #[Route(path: '/blog/{slug}', name: 'app_blog_show', methods: ['GET'], requirements: ['slug' => '[a-z0-9-]+'])]
    #[Routable(name: 'app_blog_show', params: [
        new RouteParam('slug', required: true, requirement: '[a-z0-9-]+'),
    ])]
    public function blogShow(string $slug): Response
    {
        return $this->render('blog_show.html.twig', ['slug' => $slug]);
    }

    #[Route(path: '/health', name: 'app_health', methods: ['GET'])]
    public function health(): Response
    {
        return new Response('ok', Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }
}
