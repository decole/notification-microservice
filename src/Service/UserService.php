<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

class UserService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly \Redis $redis,
        private readonly int $tokenTtlSeconds,
    ) {}

    /**
     * @return array{id:int,token:string,username:?string}
     */
    public function createUser(?string $username = null): array
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $id = (int) $this->connection->fetchOne(
            'INSERT INTO users(token, token_hash, username, created_at) VALUES(:token, :token_hash, :username, NOW()) RETURNING id',
            [
                'token' => $token,
                'token_hash' => $tokenHash,
                'username' => $username,
            ],
        );

        $this->redis->setex($this->tokenCacheKey($token), $this->tokenTtlSeconds, (string) $id);

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
