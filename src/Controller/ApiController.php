<?php

declare(strict_types=1);

namespace App\Controller;

use App\Input\SendInput;
use App\Service\NotificationService;
use App\Security\ApiUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_API_USER')]
final class ApiController extends AbstractController
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly string $defaultTopic,
    ) {}

    #[Route('/api/send', name: 'api_send', methods: ['POST'])]
    public function send(#[MapRequestPayload] SendInput $input): JsonResponse
    {
        $messageId = $this->notificationService->sendMessage(
            senderId: $this->requireApiUser()->getId(),
            topicName: trim($input->topic),
            content: trim($input->message),
        );

        return new JsonResponse(['message_id' => $messageId], 201);
    }

    #[Route('/api/messages', name: 'api_messages_default', methods: ['GET'])]
    #[Route('/api/messages/{topic}', name: 'api_messages_topic', methods: ['GET'])]
    public function messages(?string $topic = null, Request $request = new Request()): JsonResponse
    {
        $userId = $this->requireApiUser()->getId();

        $topicName = null !== $topic && '' !== trim($topic) ? trim($topic) : $this->defaultTopic;

        if (mb_strlen($topicName) > 255 || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $topicName)) {
            return new JsonResponse(['error' => 'Invalid topic'], 422);
        }

        $limit = $request->query->getInt('limit', 100);
        $limit = max(1, min(1000, $limit));

        $messages = $this->notificationService->getUnreadMessagesAndMarkRead($userId, $topicName, $limit);

        return new JsonResponse(['messages' => $messages]);
    }

    #[Route('/api/topics', name: 'api_topics', methods: ['GET'])]
    public function topics(): JsonResponse
    {
        return new JsonResponse(['topics' => $this->notificationService->listTopics()]);
    }

    private function requireApiUser(): ApiUser
    {
        $user = $this->getUser();

        if (!$user instanceof ApiUser) {
            throw $this->createAccessDeniedException('Unauthorized');
        }

        return $user;
    }
}
