<?php

declare(strict_types=1);

namespace App\Security;

use App\Service\TokenAuthService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class BearerTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly TokenAuthService $tokenAuthService,
        private readonly ?LoggerInterface $securityAuditLogger = null,
    ) {}

    public function supports(Request $request): ?bool
    {
        return str_starts_with($request->getPathInfo(), '/api');
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization', '');

        if (!preg_match('/^Bearer\\s+(.+)$/', $authHeader, $matches)) {
            $this->securityAuditLogger?->warning('Authentication failed: missing or malformed Bearer header', [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'path' => $request->getPathInfo(),
            ]);

            throw new AuthenticationException('Missing or invalid Authorization header.');
        }

        $token = trim($matches[1]);
        $user = $this->tokenAuthService->resolveUserByToken($token);

        if (null === $user) {
            $this->securityAuditLogger?->warning('Authentication failed: invalid token provided', [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'token_prefix' => substr($token, 0, 6) . '...',
                'path' => $request->getPathInfo(),
            ]);

            throw new AuthenticationException('Invalid token.');
        }

        return new SelfValidatingPassport(
            new UserBadge(
                (string) $user['id'],
                static fn () => new ApiUser($user['id'], $user['username']),
            ),
        );
    }

    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
    }
}
