<?php

declare(strict_types=1);

namespace App\Factory;

use RuntimeException;

class RedisFactory
{
    public function create(string $host, int $port): \Redis
    {
        $redis = new \Redis();

        if (!$redis->connect($host, $port, 2.0)) {
            throw new RuntimeException(sprintf('Cannot connect to Redis at %s:%d', $host, $port));
        }

        return $redis;
    }
}
