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

    public function testRequestsFromDifferentClientIpsUseDifferentBuckets(): void
    {
        $subscriber = new RequestRateLimitSubscriber(
            $this->createLimiterFactory(1),
            $this->createLimiterFactory(10),
        );

        $firstEvent = $this->createRequestEvent('/api/topics', '10.0.0.50');
        $subscriber->onKernelRequest($firstEvent);
        self::assertNull($firstEvent->getResponse());

        $secondEvent = $this->createRequestEvent('/api/topics', '10.0.0.51');
        $subscriber->onKernelRequest($secondEvent);
        self::assertNull($secondEvent->getResponse());
    }

    public function testTrustedProxyUsesForwardedClientIpForRateLimitKey(): void
    {
        Request::setTrustedProxies(['10.0.0.1'], Request::HEADER_X_FORWARDED_FOR);

        try {
            $subscriber = new RequestRateLimitSubscriber(
                $this->createLimiterFactory(1),
                $this->createLimiterFactory(10),
            );

            $firstEvent = $this->createRequestEvent('/api/topics', '10.0.0.1', 'GET', '198.51.100.10');
            $subscriber->onKernelRequest($firstEvent);
            self::assertNull($firstEvent->getResponse());

            $secondEvent = $this->createRequestEvent('/api/topics', '10.0.0.1', 'GET', '198.51.100.11');
            $subscriber->onKernelRequest($secondEvent);
            self::assertNull($secondEvent->getResponse());
        } finally {
            Request::setTrustedProxies([], -1);
        }
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

    private function createRequestEvent(string $path, string $remoteAddr, string $method = 'GET', ?string $forwardedFor = null): RequestEvent
    {
        $server = ['REMOTE_ADDR' => $remoteAddr];
        if (null !== $forwardedFor) {
            $server['HTTP_X_FORWARDED_FOR'] = $forwardedFor;
        }

        $request = Request::create($path, $method, server: $server);
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
