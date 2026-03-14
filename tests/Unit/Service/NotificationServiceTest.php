<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\NotificationService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class NotificationServiceTest extends TestCase
{
    public function testSendMessageUsesExistingTopicId(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnCallback(static function (string $query, array $params): string {
                return match ($query) {
                    'SELECT id FROM topics WHERE name = :name' => '7',
                    'INSERT INTO messages(topic_id, user_id, content, created_at) VALUES (:topic_id, :user_id, :content, NOW()) RETURNING id' => '15',
                    default => throw new \LogicException('Unexpected query: '.$query),
                };
            });

        $service = new NotificationService($connection);

        self::assertSame(15, $service->sendMessage(42, 'work', 'Hello team'));
    }

    public function testSendMessageCreatesTopicWhenMissing(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(3))
            ->method('fetchOne')
            ->willReturnCallback(static function (string $query, array $params): string|false {
                return match ($query) {
                    'SELECT id FROM topics WHERE name = :name' => false,
                    'INSERT INTO topics(name, created_at) VALUES(:name, NOW())
             ON CONFLICT (name) DO UPDATE SET name = EXCLUDED.name
             RETURNING id' => '8',
                    'INSERT INTO messages(topic_id, user_id, content, created_at) VALUES (:topic_id, :user_id, :content, NOW()) RETURNING id' => '21',
                    default => throw new \LogicException('Unexpected query: '.$query),
                };
            });

        $service = new NotificationService($connection);

        self::assertSame(21, $service->sendMessage(7, 'alerts', 'Created topic'));
    }

    public function testGetUnreadMessagesReturnsEmptyArrayWhenTopicDoesNotExist(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT id FROM topics WHERE name = :name', ['name' => 'missing'])
            ->willReturn(false);
        $connection->expects($this->once())->method('commit');
        $connection->expects($this->never())->method('fetchAllAssociative');
        $connection->expects($this->never())->method('executeStatement');

        $service = new NotificationService($connection);

        self::assertSame([], $service->getUnreadMessagesAndMarkRead(10, 'missing'));
    }

    public function testGetUnreadMessagesReturnsMessagesAndMarksRead(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection
            ->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnCallback(static function (string $query, array $params): string {
                return match ($query) {
                    'SELECT id FROM topics WHERE name = :name' => '4',
                    'SELECT last_read_message_id FROM user_topic_read WHERE user_id = :user_id AND topic_id = :topic_id' => '11',
                    default => throw new \LogicException('Unexpected query: '.$query),
                };
            });
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                'SELECT id, content, created_at, user_id AS sender_id
                 FROM messages
                 WHERE topic_id = :topic_id AND id > :last_read
                 ORDER BY id ASC',
                [
                    'topic_id' => 4,
                    'last_read' => 11,
                ],
            )
            ->willReturn([
                ['id' => '12', 'content' => 'Hello', 'created_at' => '2026-01-01 10:00:00', 'sender_id' => '5'],
                ['id' => '13', 'content' => 'World', 'created_at' => '2026-01-01 11:00:00', 'sender_id' => null],
            ]);
        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                'INSERT INTO user_topic_read(user_id, topic_id, last_read_message_id, updated_at)
                     VALUES (:user_id, :topic_id, :last_read_message_id, NOW())
                     ON CONFLICT (user_id, topic_id)
                     DO UPDATE SET last_read_message_id = EXCLUDED.last_read_message_id, updated_at = NOW()',
                [
                    'user_id' => 20,
                    'topic_id' => 4,
                    'last_read_message_id' => 13,
                ],
            );
        $connection->expects($this->once())->method('commit');

        $service = new NotificationService($connection);

        self::assertSame([
            ['id' => 12, 'content' => 'Hello', 'created_at' => '2026-01-01 10:00:00', 'sender_id' => 5],
            ['id' => 13, 'content' => 'World', 'created_at' => '2026-01-01 11:00:00', 'sender_id' => null],
        ], $service->getUnreadMessagesAndMarkRead(20, 'team'));
    }

    public function testGetUnreadMessagesReturnsEmptyArrayWhenNothingNewWasFound(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection
            ->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnCallback(static function (string $query, array $params): string {
                return match ($query) {
                    'SELECT id FROM topics WHERE name = :name' => '4',
                    'SELECT last_read_message_id FROM user_topic_read WHERE user_id = :user_id AND topic_id = :topic_id' => '11',
                    default => throw new \LogicException('Unexpected query: '.$query),
                };
            });
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);
        $connection->expects($this->never())->method('executeStatement');
        $connection->expects($this->once())->method('commit');

        $service = new NotificationService($connection);

        self::assertSame([], $service->getUnreadMessagesAndMarkRead(20, 'team'));
    }

    public function testGetUnreadMessagesRollsBackTransactionWhenFetchingFails(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT id FROM topics WHERE name = :name', ['name' => 'team'])
            ->willThrowException(new \RuntimeException('DB failure'));
        $connection->expects($this->once())->method('rollBack');
        $connection->expects($this->never())->method('commit');

        $service = new NotificationService($connection);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB failure');

        $service->getUnreadMessagesAndMarkRead(1, 'team');
    }

    public function testListTopicsCastsValuesToStrings(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->with('SELECT name FROM topics ORDER BY name ASC')
            ->willReturn(['alpha', 100, null]);

        $service = new NotificationService($connection);

        self::assertSame(['alpha', '100', ''], $service->listTopics());
    }
}
