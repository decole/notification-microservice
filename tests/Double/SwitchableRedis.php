<?php

declare(strict_types=1);

namespace App\Tests\Double;

final class SwitchableRedis extends \Redis
{
    private static bool $failMode = false;

    public static function create(string $host, int $port): self
    {
        $redis = new self();
        $redis->connect($host, $port);

        return $redis;
    }

    public static function enableFailureMode(): void
    {
        self::$failMode = true;
    }

    public static function disableFailureMode(): void
    {
        self::$failMode = false;
    }

    public function get(mixed $key): mixed
    {
        if (self::$failMode) {
            throw new \RedisException('redis down');
        }

        return parent::get($key);
    }

    public function setex($key, $expire, $value): bool|\Redis
    {
        if (self::$failMode) {
            throw new \RedisException('redis down');
        }

        return parent::setex($key, $expire, $value);
    }
}
