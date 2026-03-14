<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomePageTest extends WebTestCase
{
    public function testHomePageIsPublicAndRendersHtml(): void
    {
        $client = static::createClient(['environment' => 'test', 'debug' => true]);
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/html; charset=UTF-8');
        self::assertSelectorTextContains('h1', 'Welcome to notification server!');
    }

    public function testHomePageRejectsPostRequests(): void
    {
        $client = static::createClient(['environment' => 'test', 'debug' => true]);
        $client->request('POST', '/');

        self::assertResponseStatusCodeSame(405);
    }

    public function testHomePageDoesNotRequireAuthorizationHeader(): void
    {
        $client = static::createClient(['environment' => 'test', 'debug' => true]);
        $client->request('GET', '/', server: ['HTTP_AUTHORIZATION' => 'Bearer fake-token']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Welcome to notification server!');
    }
}
