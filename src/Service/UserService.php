<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

final readonly class UserService
{
    public function __construct(
        private Connection $connection,
        private \Redis $redis,
        private int $tokenTtlSeconds,
    ) {}

    /**
     * @return array{id:int,username:?string}
     */
    public function createUser(?string $username = null): array
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $id = (int) $this->connection->fetchOne(
            'INSERT INTO users(token_hash, username, created_at) VALUES(:token_hash, :username, NOW()) RETURNING id',
            [
                'token_hash' => $tokenHash,
                'username' => $username,
            ],
        );

        try {
            $this->redis->setex($this->tokenCacheKey($token), $this->tokenTtlSeconds, (string) $id);
        } catch (\RedisException) {
            // Redis is only an auth cache. User creation must still succeed.
        }

        return [
            'id' => $id,
            'token' => $token,
            'username' => $username,
        ];
    }

    private function tokenCacheKey(string $token): string
    {
        return sprintf('auth:token:%s', $token);
    }
}
