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
            $cachedValue = $this->redis->get($cacheKey);
        } catch (\RedisException) {
            $cachedValue = false;
        }

        if (is_string($cachedValue) && '' !== $cachedValue) {
            try {
                $decoded = json_decode($cachedValue, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded) && isset($decoded['id'])) {
                    return [
                        'id' => (int) $decoded['id'],
                        'username' => isset($decoded['username']) && is_string($decoded['username']) ? $decoded['username'] : null,
                    ];
                }
                if (is_int($decoded) || is_numeric($decoded)) {
                    $user = $this->userRepository->findUserById((int) $decoded);
                    if (null !== $user) {
                        return $user;
                    }
                }
            } catch (\JsonException) {
                if (is_numeric($cachedValue)) {
                    $user = $this->userRepository->findUserById((int) $cachedValue);
                    if (null !== $user) {
                        return $user;
                    }
                }
            }
        }

        $tokenHash = hash('sha256', $token);
        $user = $this->userRepository->findUserByTokenHash($tokenHash);

        if (null === $user) {
            return null;
        }

        try {
            $payload = json_encode(['id' => $user['id'], 'username' => $user['username']], JSON_THROW_ON_ERROR);
            $this->redis->setex($cacheKey, $this->tokenTtlSeconds, $payload);
        } catch (\RedisException|\JsonException) {
            // Redis is only an auth cache. DB lookup already succeeded.
        }

        return $user;
    }

    private function tokenCacheKey(string $token): string
    {
        return sprintf('auth:token:%s', $token);
    }
}
