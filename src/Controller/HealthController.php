<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly \Redis $redis,
    ) {}

    #[Route('/healthz', name: 'healthz', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $dbOk = false;
        $redisOk = false;

        try {
            $this->connection->executeQuery('SELECT 1');
            $dbOk = true;
        } catch (\Throwable) {
            $dbOk = false;
        }

        try {
            $redisOk = true === $this->redis->ping() || '+PONG' === $this->redis->ping() || 'PONG' === (string) $this->redis->ping();
        } catch (\Throwable) {
            $redisOk = false;
        }

        if (!$dbOk) {
            return new JsonResponse([
                'status' => 'error',
                'database' => 'unavailable',
                'redis' => $redisOk ? 'connected' : 'unavailable',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new JsonResponse([
            'status' => $redisOk ? 'ok' : 'degraded',
            'database' => 'connected',
            'redis' => $redisOk ? 'connected' : 'unavailable',
        ], Response::HTTP_OK);
    }
}
