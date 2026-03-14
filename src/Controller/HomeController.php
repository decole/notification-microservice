<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly Environment $twig,
    ) {}

    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        return new Response($this->twig->render('home/index.html.twig', [
            'message' => 'Welcome to notification server!',
        ]));
    }
}
