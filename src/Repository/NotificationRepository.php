<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

final readonly class NotificationRepository implements NotificationRepositoryInterface
{
    public function __construct(private Connection $connection) {}

    public function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    public function commit(): void
    {
        $this->connection->commit();
    }

    public function rollBack(): void
    {
        $this->connection->rollBack();
    }

    public function findTopicIdByName(string $topicName): ?int
    {
        $topicId = $this->connection->fetchOne(
            'SELECT id FROM topics WHERE name = :name',
            ['name' => $topicName],
        );

        return false === $topicId ? null : (int) $topicId;
    }

    public function findOrCreateTopicId(string $topicName): int
    {
        $topicId = $this->findTopicIdByName($topicName);

        if (null !== $topicId) {
            return $topicId;
        }

        return (int) $this->connection->fetchOne(
            'INSERT INTO topics(name, created_at) VALUES(:name, NOW())
             ON CONFLICT (name) DO UPDATE SET name = EXCLUDED.name
             RETURNING id',
            ['name' => $topicName],
        );
    }

    public function createMessage(int $topicId, int $senderId, string $content): int
    {
        return (int) $this->connection->fetchOne(
            'INSERT INTO messages(topic_id, user_id, content, created_at) VALUES (:topic_id, :user_id, :content, NOW()) RETURNING id',
            [
                'topic_id' => $topicId,
                'user_id' => $senderId,
                'content' => $content,
            ],
        );
    }

    public function findLastReadMessageId(int $userId, int $topicId): ?int
    {
        $lastRead = $this->connection->fetchOne(
            'SELECT last_read_message_id FROM user_topic_read WHERE user_id = :user_id AND topic_id = :topic_id',
            [
                'user_id' => $userId,
                'topic_id' => $topicId,
            ],
        );

        return is_numeric($lastRead) ? (int) $lastRead : null;
    }

    public function findUnreadMessages(int $topicId, int $lastReadId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, content, created_at, user_id AS sender_id
             FROM messages
             WHERE topic_id = :topic_id AND id > :last_read
             ORDER BY id ASC',
            [
                'topic_id' => $topicId,
                'last_read' => $lastReadId,
            ],
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'content' => (string) $row['content'],
            'created_at' => (string) $row['created_at'],
            'sender_id' => null !== $row['sender_id'] ? (int) $row['sender_id'] : null,
        ], $rows);
    }

    public function markTopicRead(int $userId, int $topicId, int $lastReadMessageId): void
    {
        $this->connection->executeStatement(
            'INSERT INTO user_topic_read(user_id, topic_id, last_read_message_id, updated_at)
             VALUES (:user_id, :topic_id, :last_read_message_id, NOW())
             ON CONFLICT (user_id, topic_id)
             DO UPDATE SET last_read_message_id = EXCLUDED.last_read_message_id, updated_at = NOW()',
            [
                'user_id' => $userId,
                'topic_id' => $topicId,
                'last_read_message_id' => $lastReadMessageId,
            ],
        );
    }

    public function listTopics(): array
    {
        $rows = $this->connection->fetchFirstColumn('SELECT name FROM topics ORDER BY name ASC');

        return array_map(static fn (mixed $name): string => (string) $name, $rows);
    }
}
