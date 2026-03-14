<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\UserRepositoryInterface;

final readonly class UserService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private \Redis $redis,
        private int $tokenTtlSeconds,
    ) {}

    /**
     * @return array{id:int,token:string,username:?string}
     */
    public function createUser(?string $username = null): array
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $id = $this->userRepository->createUser($tokenHash, $username);

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
