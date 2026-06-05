<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final readonly class RequestRateLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RateLimiterFactory $apiRequestLimiter,
        private RateLimiterFactory $internalRegisterLimiter,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (str_starts_with($path, '/api/')) {
            $this->consume($event, $this->apiRequestLimiter, sprintf('api:%s', $this->resolveClientKey($request)));

            return;
        }

        if ('/internal/register' === $path) {
            $this->consume($event, $this->internalRegisterLimiter, sprintf('internal:%s', $this->resolveClientKey($request)));
        }
    }

    private function consume(RequestEvent $event, RateLimiterFactory $factory, string $key): void
    {
        $headers = [];
        $limit = $factory->create($key)->consume(1);

        if ($limit->isAccepted()) {
            return;
        }

        $retryAfter = $limit->getRetryAfter();

        $headers['Retry-After'] = (string) max(1, $retryAfter->getTimestamp() - time());

        $event->setResponse(new JsonResponse(['error' => 'Too Many Requests'], 429, $headers));
    }

    private function resolveClientKey(Request $request): string
    {
        return $request->getClientIp() ?? 'unknown';
    }
}
