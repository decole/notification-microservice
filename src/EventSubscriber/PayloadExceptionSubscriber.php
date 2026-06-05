<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Input\RegisterInput;
use App\Input\SendInput;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class PayloadExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 100],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api/') && '/internal/register' !== $path) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof BadRequestHttpException) {
            $event->setResponse(new JsonResponse(['error' => 'Invalid JSON'], 400));

            return;
        }

        if (!$exception instanceof UnprocessableEntityHttpException) {
            return;
        }

        $previous = $exception->getPrevious();

        if (!$previous instanceof ValidationFailedException) {
            $event->setResponse(new JsonResponse([
                'error' => 'Invalid request payload',
                'details' => $exception->getMessage(),
            ], 422));

            return;
        }

        $event->setResponse(new JsonResponse(['error' => $this->resolveValidationMessage($previous)], 422));
    }

    private function resolveValidationMessage(ValidationFailedException $exception): string
    {
        $violations = $exception->getViolations();
        $target = $exception->getValue();

        foreach ($violations as $violation) {
            if (!$violation instanceof ConstraintViolationInterface) {
                continue;
            }

            $path = $violation->getPropertyPath();

            if ($target instanceof SendInput) {
                return match ($path) {
                    'topic' => 'Invalid topic',
                    'message' => 'Invalid message length',
                    default => 'Invalid request payload',
                };
            }

            if ($target instanceof RegisterInput) {
                return 'Invalid username';
            }
        }

        return 'Invalid request payload';
    }
}
