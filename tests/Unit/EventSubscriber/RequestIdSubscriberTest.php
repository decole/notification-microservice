<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\RequestIdSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class RequestIdSubscriberTest extends TestCase
{
    public function testGeneratesRequestIdWhenHeaderMissing(): void
    {
        $subscriber = new RequestIdSubscriber();
        $request = Request::create('/api/topics');
        $kernel = $this->createStub(HttpKernelInterface::class);

        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($requestEvent);

        self::assertTrue($request->headers->has('X-Request-ID'));
        self::assertNotEmpty($request->headers->get('X-Request-ID'));

        $response = new Response();
        $responseEvent = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($responseEvent);

        self::assertSame($request->headers->get('X-Request-ID'), $response->headers->get('X-Request-ID'));
    }

    public function testPreservesExistingRequestId(): void
    {
        $subscriber = new RequestIdSubscriber();
        $request = Request::create('/api/topics');
        $request->headers->set('X-Request-ID', 'custom-req-id-12345');
        $kernel = $this->createStub(HttpKernelInterface::class);

        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($requestEvent);

        self::assertSame('custom-req-id-12345', $request->headers->get('X-Request-ID'));

        $response = new Response();
        $responseEvent = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($responseEvent);

        self::assertSame('custom-req-id-12345', $response->headers->get('X-Request-ID'));
    }
}
