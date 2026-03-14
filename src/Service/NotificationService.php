<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

final readonly class NotificationService
{
    public function __construct(private Connection $connection) {}

    public function sendMessage(int $senderId, string $topicName, string $content): int
    {
        $topicId = $this->findOrCreateTopic($topicName);

        return (int) $this->connection->fetchOne(
            'INSERT INTO messages(topic_id, user_id, content, created_at) VALUES (:topic_id, :user_id, :content, NOW()) RETURNING id',
            [
                'topic_id' => $topicId,
                'user_id' => $senderId,
                'content' => $content,
            ],
        );
    }

    /**
     * @return array<int,array{id:int,content:string,created_at:string,sender_id:int|null}>
     */
    public function getUnreadMessagesAndMarkRead(int $userId, string $topicName): array
    {
        $this->connection->beginTransaction();

        try {
            $topicId = $this->connection->fetchOne(
                'SELECT id FROM topics WHERE name = :name',
                ['name' => $topicName],
            );

            if (false === $topicId) {
                $this->connection->commit();

                return [];
            }

            $topicId = (int) $topicId;

            $lastRead = $this->connection->fetchOne(
                'SELECT last_read_message_id FROM user_topic_read WHERE user_id = :user_id AND topic_id = :topic_id',
                [
                    'user_id' => $userId,
                    'topic_id' => $topicId,
                ],
            );

            $lastReadId = is_numeric($lastRead) ? (int) $lastRead : 0;

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

            $messages = array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'content' => (string) $row['content'],
                'created_at' => (string) $row['created_at'],
                'sender_id' => null !== $row['sender_id'] ? (int) $row['sender_id'] : null,
            ], $rows);

            if ([] !== $messages) {
                $maxId = $messages[array_key_last($messages)]['id'];

                $this->connection->executeStatement(
                    'INSERT INTO user_topic_read(user_id, topic_id, last_read_message_id, updated_at)
                     VALUES (:user_id, :topic_id, :last_read_message_id, NOW())
                     ON CONFLICT (user_id, topic_id)
                     DO UPDATE SET last_read_message_id = EXCLUDED.last_read_message_id, updated_at = NOW()',
                    [
                        'user_id' => $userId,
                        'topic_id' => $topicId,
                        'last_read_message_id' => $maxId,
                    ],
                );
            }

            $this->connection->commit();

            return $messages;
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }
    }

    /**
     * @return list<string>
     */
    public function listTopics(): array
    {
        $rows = $this->connection->fetchFirstColumn('SELECT name FROM topics ORDER BY name ASC');

        return array_map(static fn (mixed $name): string => (string) $name, $rows);
    }

    private function findOrCreateTopic(string $topicName): int
    {
        $topicId = $this->connection->fetchOne('SELECT id FROM topics WHERE name = :name', ['name' => $topicName]);

        if (false !== $topicId) {
            return (int) $topicId;
        }

        return (int) $this->connection->fetchOne(
            'INSERT INTO topics(name, created_at) VALUES(:name, NOW())
             ON CONFLICT (name) DO UPDATE SET name = EXCLUDED.name
             RETURNING id',
            ['name' => $topicName],
        );
    }
}
