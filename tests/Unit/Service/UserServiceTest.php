<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\UserService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class UserServiceTest extends TestCase
{
    public function testCreateUserStoresTokenHashAndCachesToken(): void
    {
        $connection = $this->createMock(Connection::class);
        $redis = $this->createMock(\Redis::class);

        $connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with(
                'INSERT INTO users(token, token_hash, username, created_at) VALUES(:token, :token_hash, :username, NOW()) RETURNING id',
                $this->callback(static function (array $params): bool {
                    return is_string($params['token'])
                        && 64 === strlen($params['token'])
                        && hash('sha256', $params['token']) === $params['token_hash']
                        && 'alice' === $params['username'];
                }),
            )
            ->willReturn('15');

        $redis
            ->expects($this->once())
            ->method('setex')
            ->with(
                $this->callback(static fn (string $key): bool => str_starts_with($key, 'auth:token:')),
                3600,
                '15',
            );

        $service = new UserService($connection, $redis, 3600);
        $result = $service->createUser('alice');

        self::assertSame(15, $result['id']);
        self::assertSame('alice', $result['username']);
        self::assertIsString($result['token']);
        self::assertSame(64, strlen($result['token']));
    }
}
