<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\NotificationRepositoryInterface;

final readonly class NotificationService
{
    public function __construct(private NotificationRepositoryInterface $notificationRepository) {}

    public function sendMessage(int $senderId, string $topicName, string $content): int
    {
        return $this->notificationRepository->createMessage(
            topicId: $this->notificationRepository->findOrCreateTopicId($topicName),
            senderId: $senderId,
            content: $content,
        );
    }

    /**
     * @return array<int,array{id:int,content:string,created_at:string,sender_id:int|null}>
     */
    public function getUnreadMessagesAndMarkRead(int $userId, string $topicName): array
    {
        $this->notificationRepository->beginTransaction();

        try {
            $topicId = $this->notificationRepository->findTopicIdByName($topicName);

            if (null === $topicId) {
                $this->notificationRepository->commit();

                return [];
            }

            $lastReadId = $this->notificationRepository->findLastReadMessageId($userId, $topicId) ?? 0;
            $messages = $this->notificationRepository->findUnreadMessages($topicId, $lastReadId);

            if ([] !== $messages) {
                $maxId = $messages[array_key_last($messages)]['id'];

                $this->notificationRepository->markTopicRead($userId, $topicId, $maxId);
            }

            $this->notificationRepository->commit();

            return $messages;
        } catch (\Throwable $exception) {
            $this->notificationRepository->rollBack();

            throw $exception;
        }
    }

    /**
     * @return list<string>
     */
    public function listTopics(): array
    {
        return $this->notificationRepository->listTopics();
    }
}
