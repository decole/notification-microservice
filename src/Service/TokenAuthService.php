<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\UserRepositoryInterface;

final readonly class TokenAuthService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private \Redis $redis,
        private int $tokenTtlSeconds,
    ) {}

    /**
     * @return array{id:int,username:?string}|null
     */
    public function resolveUserByToken(string $token): ?array
    {
        $cacheKey = $this->tokenCacheKey($token);

        try {
            $cachedUserId = $this->redis->get($cacheKey);
        } catch (\RedisException) {
            $cachedUserId = false;
        }

        if (is_string($cachedUserId) && '' !== $cachedUserId) {
            $user = $this->userRepository->findUserById((int) $cachedUserId);

            if (null !== $user) {
                return $user;
            }
        }

        $tokenHash = hash('sha256', $token);
        $user = $this->userRepository->findUserByTokenHash($tokenHash);

        if (null === $user) {
            return null;
        }

        $userId = $user['id'];

        try {
            $this->redis->setex($cacheKey, $this->tokenTtlSeconds, (string) $userId);
        } catch (\RedisException) {
            // Redis is only an auth cache. DB lookup already succeeded.
        }

        return $user;
    }

    private function tokenCacheKey(string $token): string
    {
        return sprintf('auth:token:%s', $token);
    }
}
