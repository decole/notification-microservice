<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController
{
    #[Route('/')]
    public function index(): Response
    {
        return new JsonResponse([
            'message' => 'Welcome to notification server!',
        ]);
    }
}
