<?php

declare(strict_types=1);

namespace App\Tests\Unit\Factory;

use App\Factory\RedisFactory;
use PHPUnit\Framework\TestCase;

final class RedisFactoryTest extends TestCase
{
    public function testCreateReturnsRedisInstanceEvenIfHostIsUnreachable(): void
    {
        $factory = new RedisFactory();
        $redis = $factory->create('127.0.0.1', 65534);

        self::assertInstanceOf(\Redis::class, $redis);
    }
}
