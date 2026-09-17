<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\PayloadExceptionSubscriber;
use App\Input\RegisterInput;
use App\Input\SendInput;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class PayloadExceptionSubscriberTest extends TestCase
{
    public function testHandlesBadRequestHttpExceptionAsInvalidJson(): void
    {
        $subscriber = new PayloadExceptionSubscriber();
        $request = Request::create('/api/send', 'POST');
        $event = $this->createExceptionEvent($request, new BadRequestHttpException('Syntax error'));

        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame('{"error":"Invalid JSON"}', $response->getContent());
    }

    public function testHandlesValidationFailedExceptionForSendInputTopic(): void
    {
        $subscriber = new PayloadExceptionSubscriber();
        $request = Request::create('/api/send', 'POST');

        $input = new SendInput();
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Invalid topic', '', [], $input, 'topic', 'invalid'),
        ]);
        $valException = new ValidationFailedException($input, $violations);
        $event = $this->createExceptionEvent($request, new UnprocessableEntityHttpException('Validation failed', $valException));

        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(422, $response->getStatusCode());
        self::assertSame('{"error":"Invalid topic"}', $response->getContent());
    }

    public function testHandlesValidationFailedExceptionForRegisterInput(): void
    {
        $subscriber = new PayloadExceptionSubscriber();
        $request = Request::create('/internal/register', 'POST');

        $input = new RegisterInput();
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Invalid username', '', [], $input, 'username', ''),
        ]);
        $valException = new ValidationFailedException($input, $violations);
        $event = $this->createExceptionEvent($request, new UnprocessableEntityHttpException('Validation failed', $valException));

        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(422, $response->getStatusCode());
        self::assertSame('{"error":"Invalid username"}', $response->getContent());
    }

    public function testIgnoresNonApiRoutes(): void
    {
        $subscriber = new PayloadExceptionSubscriber();
        $request = Request::create('/', 'GET');
        $event = $this->createExceptionEvent($request, new BadRequestHttpException('Bad request'));

        $subscriber->onKernelException($event);

        self::assertNull($event->getResponse());
    }

    private function createExceptionEvent(Request $request, \Throwable $throwable): ExceptionEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $throwable);
    }
}
