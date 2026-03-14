<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\HomeController;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

final class HomeControllerTest extends TestCase
{
    public function testIndexRendersTwigTemplate(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig
            ->expects($this->once())
            ->method('render')
            ->with('home/index.html.twig', ['message' => 'Welcome to notification server!'])
            ->willReturn('<html lang="en"><body><h1>Welcome to notification server!</h1></body></html>');

        $controller = new HomeController($twig);
        $response = $controller->index();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Welcome to notification server!', $response->getContent() ?: '');
    }
}
