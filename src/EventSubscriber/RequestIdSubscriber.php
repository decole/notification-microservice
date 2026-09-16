<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class RequestIdSubscriber implements EventSubscriberInterface
{
    public const HEADER_NAME = 'X-Request-ID';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 200],
            KernelEvents::RESPONSE => ['onKernelResponse', -100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $requestId = $request->headers->get(self::HEADER_NAME);

        if (null === $requestId || '' === trim($requestId)) {
            $requestId = bin2hex(random_bytes(16));
            $request->headers->set(self::HEADER_NAME, $requestId);
        }

        $request->attributes->set('request_id', $requestId);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        $requestId = $request->attributes->get('request_id');

        if (is_string($requestId) && '' !== $requestId) {
            $response->headers->set(self::HEADER_NAME, $requestId);
        }
    }
}
