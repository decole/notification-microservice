<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

class TokenAuthService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly \Redis $redis,
        private readonly int $tokenTtlSeconds,
    ) {}

    /**
     * @return array{id:int,username:?string}|null
     */
    public function resolveUserByToken(string $token): ?array
    {
        $cacheKey = $this->tokenCacheKey($token);
        $cachedUserId = $this->redis->get($cacheKey);

        if (is_string($cachedUserId) && '' !== $cachedUserId) {
            $user = $this->connection->fetchAssociative(
                'SELECT id, username FROM users WHERE id = :id',
                ['id' => (int) $cachedUserId],
            );

            if (is_array($user)) {
                return [
                    'id' => (int) $user['id'],
                    'username' => null !== $user['username'] ? (string) $user['username'] : null,
                ];
            }
        }

        $tokenHash = hash('sha256', $token);
        $user = $this->connection->fetchAssociative(
            'SELECT id, username FROM users WHERE token_hash = :token_hash',
            ['token_hash' => $tokenHash],
        );

        if (!is_array($user)) {
            return null;
        }

        $userId = (int) $user['id'];
        $this->redis->setex($cacheKey, $this->tokenTtlSeconds, (string) $userId);

        return [
            'id' => $userId,
            'username' => null !== $user['username'] ? (string) $user['username'] : null,
        ];
    }

    private function tokenCacheKey(string $token): string
    {
        return sprintf('auth:token:%s', $token);
    }
}
