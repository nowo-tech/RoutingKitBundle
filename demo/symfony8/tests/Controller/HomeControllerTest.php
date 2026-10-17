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

    /**
     * REQ-UI-003: every admin page renders on its UiKit scaffold inside the demo host layout.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function adminPages(): iterable
    {
        yield 'paths list' => ['/_routing/', 'list'];
        yield 'path form' => ['/_routing/new', 'form'];
        yield 'redirects list' => ['/_routing/redirects', 'list'];
        yield 'redirect form' => ['/_routing/redirects/new', 'form'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('adminPages')]
    public function testAdminPagesUseTheUiKitScaffolds(string $uri, string $pageType): void
    {
        $client = static::createClient();
        $client->request('GET', $uri);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-nowo-ui-page="' . $pageType . '"]');
        self::assertSelectorExists('#nowo-ui-content[data-nowo-ui-bundle="NowoRoutingKitBundle"]');
        self::assertSelectorExists('nav.demo-admin-nav');
        self::assertStringNotContainsString('cdn.', (string) $client->getResponse()->getContent());
    }
}
