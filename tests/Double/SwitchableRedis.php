<?php

declare(strict_types=1);

namespace App\Tests\Double;

final class SwitchableRedis extends \Redis
{
    private static bool $failMode = false;

    public static function create(string $host, int $port, ?string $password = null): self
    {
        $redis = new self();
        try {
            if (@$redis->connect($host, $port, 2.0)) {
                if (null !== $password && '' !== trim($password)) {
                    $redis->auth($password);
                }
            }
        } catch (\RedisException) {
        }

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

    public function ping(?string $message = null): \Redis|string|bool
    {
        if (self::$failMode) {
            throw new \RedisException('redis down');
        }

        return null !== $message ? parent::ping($message) : parent::ping();
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
