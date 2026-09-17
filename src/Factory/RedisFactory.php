<?php

declare(strict_types=1);

namespace App\Factory;

final class RedisFactory
{
    public function create(string $host, int $port, ?string $password = null): \Redis
    {
        $redis = new \Redis();

        try {
            if (@$redis->connect($host, $port, 2.0)) {
                if (null !== $password && '' !== trim($password)) {
                    $redis->auth($password);
                }
            }
        } catch (\RedisException) {
            // Redis is only a cache. Service initialization must succeed even if Redis is unreachable.
        }

        return $redis;
    }
}
