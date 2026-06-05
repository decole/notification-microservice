<?php

declare(strict_types=1);

namespace App\Repository;

interface NotificationRepositoryInterface
{
    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function findTopicIdByName(string $topicName): ?int;

    public function findOrCreateTopicId(string $topicName): int;

    public function createMessage(int $topicId, int $senderId, string $content): int;

    public function findLastReadMessageId(int $userId, int $topicId): ?int;

    /**
     * @return list<array{id:int,content:string,created_at:string,sender_id:int|null}>
     */
    public function findUnreadMessages(int $topicId, int $lastReadId): array;

    public function markTopicRead(int $userId, int $topicId, int $lastReadMessageId): void;

    /**
     * @return list<string>
     */
    public function listTopics(): array;
}
