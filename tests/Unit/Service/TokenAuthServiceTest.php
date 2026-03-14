<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\TokenAuthService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class TokenAuthServiceTest extends TestCase
{
    public function testResolveUserByTokenReturnsCachedUser(): void
    {
        $connection = $this->createMock(Connection::class);
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->once())->method('get')->with('auth:token:token-1')->willReturn('7');
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with('SELECT id, username FROM users WHERE id = :id', ['id' => 7])
            ->willReturn(['id' => '7', 'username' => 'alice']);

        $service = new TokenAuthService($connection, $redis, 3600);

        self::assertSame(['id' => 7, 'username' => 'alice'], $service->resolveUserByToken('token-1'));
    }

    public function testResolveUserByTokenUsesTokenHashFirst(): void
    {
        $connection = $this->createMock(Connection::class);
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->once())->method('get')->willReturn(false);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with('SELECT id, username FROM users WHERE token_hash = :token_hash', ['token_hash' => hash('sha256', 'token-2')])
            ->willReturn(['id' => '9', 'username' => 'bob']);
        $redis->expects($this->once())->method('setex')->with('auth:token:token-2', 3600, '9');

        $service = new TokenAuthService($connection, $redis, 3600);

        self::assertSame(['id' => 9, 'username' => 'bob'], $service->resolveUserByToken('token-2'));
    }

    public function testResolveUserByTokenReturnsNullWhenUserDoesNotExist(): void
    {
        $connection = $this->createMock(Connection::class);
        $redis = $this->createMock(\Redis::class);

        $redis->expects($this->once())->method('get')->willReturn(false);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(false);
        $redis->expects($this->never())->method('setex');

        $service = new TokenAuthService($connection, $redis, 3600);

        self::assertNull($service->resolveUserByToken('missing-token'));
    }
}
