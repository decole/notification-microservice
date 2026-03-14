<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\RequestRateLimitSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class RequestRateLimitSubscriberTest extends TestCase
{
    public function testApiRequestsReturn429AfterLimitIsExceeded(): void
    {
        $subscriber = new RequestRateLimitSubscriber(
            $this->createLimiterFactory(1),
            $this->createLimiterFactory(10),
        );

        $firstEvent = $this->createRequestEvent('/api/topics', '10.0.0.50');
        $subscriber->onKernelRequest($firstEvent);
        self::assertNull($firstEvent->getResponse());

        $secondEvent = $this->createRequestEvent('/api/topics', '10.0.0.50');
        $subscriber->onKernelRequest($secondEvent);

        $response = $secondEvent->getResponse();
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(429, $response->getStatusCode());
        self::assertSame('{"error":"Too Many Requests"}', $response->getContent());
        self::assertTrue($response->headers->has('Retry-After'));
    }

    public function testInternalRegisterReturns429AfterLimitIsExceeded(): void
    {
        $subscriber = new RequestRateLimitSubscriber(
            $this->createLimiterFactory(120),
            $this->createLimiterFactory(1),
        );

        $firstEvent = $this->createRequestEvent('/internal/register', '10.0.0.60', 'POST');
        $subscriber->onKernelRequest($firstEvent);
        self::assertNull($firstEvent->getResponse());

        $secondEvent = $this->createRequestEvent('/internal/register', '10.0.0.60', 'POST');
        $subscriber->onKernelRequest($secondEvent);

        $response = $secondEvent->getResponse();
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(429, $response->getStatusCode());
        self::assertSame('{"error":"Too Many Requests"}', $response->getContent());
        self::assertTrue($response->headers->has('Retry-After'));
    }

    private function createLimiterFactory(int $limit): RateLimiterFactory
    {
        return new RateLimiterFactory([
            'id' => 'test',
            'policy' => 'fixed_window',
            'limit' => $limit,
            'interval' => '1 minute',
        ], new InMemoryStorage());
    }

    private function createRequestEvent(string $path, string $remoteAddr, string $method = 'GET'): RequestEvent
    {
        $request = Request::create($path, $method, server: ['REMOTE_ADDR' => $remoteAddr]);
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
