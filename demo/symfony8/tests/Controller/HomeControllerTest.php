<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    public function testHomepageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Routing Kit Bundle');
    }

    public function testHealthEndpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');
        self::assertResponseIsSuccessful();
        self::assertSame('ok', $client->getResponse()->getContent());
    }

    public function testAboutPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/about');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'About');
    }

    public function testRoutingPanelIsReachable(): void
    {
        $client = static::createClient();
        $client->request('GET', '/_routing/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Routing Kit');
    }
}
