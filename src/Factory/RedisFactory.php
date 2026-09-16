<?php

declare(strict_types=1);

namespace App\Factory;

final class RedisFactory
{
    public function create(string $host, int $port): \Redis
    {
        $redis = new \Redis();

        try {
            @$redis->connect($host, $port, 2.0);
        } catch (\RedisException) {
            // Redis is only a cache. Service initialization must succeed even if Redis is unreachable.
        }

        return $redis;
    }
}
