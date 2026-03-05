<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\NotificationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ApiController
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly string $defaultTopic,
    ) {}

    #[Route('/api/send', name: 'api_send', methods: ['POST'])]
    public function send(Request $request): JsonResponse
    {
        $userId = $this->extractUserId($request);
        if (null === $userId) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $payload = $this->decodeJson($request);
        $topic = isset($payload['topic']) && is_string($payload['topic']) ? trim($payload['topic']) : '';
        $message = isset($payload['message']) && is_string($payload['message']) ? trim($payload['message']) : '';

        if ('' === $topic || mb_strlen($topic) > 255) {
            return new JsonResponse(['error' => 'Invalid topic'], 422);
        }

        if ('' === $message || mb_strlen($message) > 4096) {
            return new JsonResponse(['error' => 'Invalid message length'], 422);
        }

        $messageId = $this->notificationService->sendMessage($userId, $topic, $message);

        return new JsonResponse(['message_id' => $messageId], 201);
    }

    #[Route('/api/messages', name: 'api_messages_default', methods: ['GET'])]
    #[Route('/api/messages/{topic}', name: 'api_messages_topic', methods: ['GET'])]
    public function messages(Request $request, ?string $topic = null): JsonResponse
    {
        $userId = $this->extractUserId($request);
        if (null === $userId) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $topicName = null !== $topic && '' !== trim($topic) ? trim($topic) : $this->defaultTopic;

        if (mb_strlen($topicName) > 255) {
            return new JsonResponse(['error' => 'Invalid topic'], 422);
        }

        $messages = $this->notificationService->getUnreadMessagesAndMarkRead($userId, $topicName);

        return new JsonResponse(['messages' => $messages]);
    }

    #[Route('/api/topics', name: 'api_topics', methods: ['GET'])]
    public function topics(Request $request): JsonResponse
    {
        $userId = $this->extractUserId($request);
        if (null === $userId) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        return new JsonResponse(['topics' => $this->notificationService->listTopics()]);
    }

    private function extractUserId(Request $request): ?int
    {
        $value = $request->attributes->get('auth_user_id');

        return is_int($value) ? $value : null;
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeJson(Request $request): array
    {
        $raw = trim((string) $request->getContent());

        if ('' == $raw) {
            return [];
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }
}
