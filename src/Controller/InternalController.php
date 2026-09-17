<?php

declare(strict_types=1);

namespace App\Controller;

use App\Input\RegisterInput;
use App\Service\UserService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class InternalController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly string $internalApiSecret,
        private readonly bool $internalRegistrationEnabled,
        private readonly ?LoggerInterface $securityAuditLogger = null,
    ) {}

    #[Route('/internal/register', name: 'internal_register', methods: ['POST'])]
    public function register(Request $request, #[MapRequestPayload] RegisterInput $input): JsonResponse
    {
        if (!$this->internalRegistrationEnabled) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $providedSecret = $request->headers->get('X-Internal-Secret');

        if (null === $providedSecret || !hash_equals($this->internalApiSecret, $providedSecret)) {
            $this->securityAuditLogger?->warning('Forbidden internal access attempt: invalid secret', [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'path' => $request->getPathInfo(),
            ]);

            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $user = $this->userService->createUser($input->username);

        return new JsonResponse([
            'token' => $user['token'],
        ], 201);
    }
}
