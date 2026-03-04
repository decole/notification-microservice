<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\TokenAuthService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiAuthSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TokenAuthService $tokenAuthService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $authHeader = $request->headers->get('Authorization', '');

        if (!preg_match('/^Bearer\\s+(.+)$/', $authHeader, $matches)) {
            $event->setResponse(new JsonResponse(['error' => 'Unauthorized'], 401));

            return;
        }

        $token = trim($matches[1]);
        $user = $this->tokenAuthService->resolveUserByToken($token);

        if ($user === null) {
            $event->setResponse(new JsonResponse(['error' => 'Unauthorized'], 401));

            return;
        }

        $request->attributes->set('auth_user_id', $user['id']);
        $request->attributes->set('auth_username', $user['username']);
    }
}
