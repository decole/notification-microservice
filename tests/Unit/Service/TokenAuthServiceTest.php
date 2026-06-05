<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Repository\UserRepositoryInterface;
use App\Service\TokenAuthService;
use PHPUnit\Framework\TestCase;

final class TokenAuthServiceTest extends TestCase
{
    public function testResolveUserByTokenReturnsCachedUser(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->once())->method('get')->with('auth:token:token-1')->willReturn('7');
        $userRepository
            ->expects($this->once())
            ->method('findUserById')
            ->with(7)
            ->willReturn(['id' => 7, 'username' => 'alice']);

        $service = new TokenAuthService($userRepository, $redis, 3600);

        self::assertSame(['id' => 7, 'username' => 'alice'], $service->resolveUserByToken('token-1'));
    }

    public function testResolveUserByTokenUsesTokenHashFirst(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->once())->method('get')->willReturn(false);
        $userRepository
            ->expects($this->once())
            ->method('findUserByTokenHash')
            ->with(hash('sha256', 'token-2'))
            ->willReturn(['id' => 9, 'username' => 'bob']);
        $redis->expects($this->once())->method('setex')->with('auth:token:token-2', 3600, '9');

        $service = new TokenAuthService($userRepository, $redis, 3600);

        self::assertSame(['id' => 9, 'username' => 'bob'], $service->resolveUserByToken('token-2'));
    }

    public function testResolveUserByTokenReturnsNullWhenUserDoesNotExist(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->once())->method('get')->willReturn(false);
        $userRepository
            ->expects($this->once())
            ->method('findUserByTokenHash')
            ->willReturn(null);
        $redis->expects($this->never())->method('setex');

        $service = new TokenAuthService($userRepository, $redis, 3600);

        self::assertNull($service->resolveUserByToken('missing-token'));
    }

    public function testResolveUserByTokenFallsBackToDbWhenRedisIsUnavailable(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->once())->method('get')->willThrowException(new \RedisException('redis down'));
        $userRepository
            ->expects($this->once())
            ->method('findUserByTokenHash')
            ->with(hash('sha256', 'token-3'))
            ->willReturn(['id' => 11, 'username' => 'carol']);
        $redis->expects($this->once())->method('setex')->with('auth:token:token-3', 3600, '11');

        $service = new TokenAuthService($userRepository, $redis, 3600);

        self::assertSame(['id' => 11, 'username' => 'carol'], $service->resolveUserByToken('token-3'));
    }

    public function testResolveUserByTokenReturnsUserWhenRedisSetFails(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->once())->method('get')->willReturn(false);
        $userRepository
            ->expects($this->once())
            ->method('findUserByTokenHash')
            ->with(hash('sha256', 'token-4'))
            ->willReturn(['id' => 13, 'username' => 'dave']);
        $redis
            ->expects($this->once())
            ->method('setex')
            ->with('auth:token:token-4', 3600, '13')
            ->willThrowException(new \RedisException('redis down'));

        $service = new TokenAuthService($userRepository, $redis, 3600);

        self::assertSame(['id' => 13, 'username' => 'dave'], $service->resolveUserByToken('token-4'));
    }
}
