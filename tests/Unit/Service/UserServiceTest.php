<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Repository\UserRepositoryInterface;
use App\Service\UserService;
use PHPUnit\Framework\TestCase;

final class UserServiceTest extends TestCase
{
    public function testCreateUserStoresTokenHashAndCachesToken(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $redis = $this->createMock(\Redis::class);

        $userRepository
            ->expects($this->once())
            ->method('createUser')
            ->with(
                $this->callback(static fn (string $tokenHash): bool => 64 === strlen($tokenHash)),
                'alice',
            )
            ->willReturn(15);

        $redis
            ->expects($this->once())
            ->method('setex')
            ->with(
                $this->callback(static fn (string $key): bool => str_starts_with($key, 'auth:token:')),
                3600,
                '15',
            );

        $service = new UserService($userRepository, $redis, 3600);
        $result = $service->createUser('alice');

        self::assertSame(15, $result['id']);
        self::assertSame('alice', $result['username']);
        self::assertIsString($result['token']);
        self::assertSame(64, strlen($result['token']));
    }

    public function testCreateUserStillReturnsTokenWhenRedisIsUnavailable(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $redis = $this->createMock(\Redis::class);

        $userRepository
            ->expects($this->once())
            ->method('createUser')
            ->willReturn(15);

        $redis
            ->expects($this->once())
            ->method('setex')
            ->willThrowException(new \RedisException('redis down'));

        $service = new UserService($userRepository, $redis, 3600);
        $result = $service->createUser('alice');

        self::assertSame(15, $result['id']);
        self::assertSame('alice', $result['username']);
        self::assertIsString($result['token']);
        self::assertSame(64, strlen($result['token']));
    }
}
