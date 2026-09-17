<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\NotificationRepositoryInterface;

final class NotificationRepositoryTest extends DatabaseRepositoryTestCase
{
    private NotificationRepositoryInterface $notificationRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notificationRepository = self::getContainer()->get(NotificationRepositoryInterface::class);
    }

    public function testFindOrCreateTopicIdReturnsExistingDefaultTopic(): void
    {
        $topicId = $this->notificationRepository->findOrCreateTopicId('default');

        self::assertSame(1, $topicId);
    }

    public function testFindOrCreateTopicIdCreatesNewTopic(): void
    {
        $topicId = $this->notificationRepository->findOrCreateTopicId('alerts');

        self::assertSame(2, $topicId);
        self::assertSame(2, $this->notificationRepository->findTopicIdByName('alerts'));
    }

    public function testCreateMessageAndReadUnreadFlow(): void
    {
        $senderId = (int) $this->connection->fetchOne(
            'INSERT INTO users(token_hash, username, created_at) VALUES(:token_hash, :username, NOW()) RETURNING id',
            ['token_hash' => hash('sha256', 'token-4'), 'username' => 'sender'],
        );
        $readerId = (int) $this->connection->fetchOne(
            'INSERT INTO users(token_hash, username, created_at) VALUES(:token_hash, :username, NOW()) RETURNING id',
            ['token_hash' => hash('sha256', 'token-5'), 'username' => 'reader'],
        );
        $topicId = $this->notificationRepository->findOrCreateTopicId('work');

        $messageId = $this->notificationRepository->createMessage($topicId, $senderId, 'Hello team');

        self::assertGreaterThan(0, $messageId);
        self::assertNull($this->notificationRepository->findLastReadMessageId($readerId, $topicId));

        $messages = $this->notificationRepository->findUnreadMessages($topicId, 0);

        self::assertCount(1, $messages);
        self::assertSame('Hello team', $messages[0]['content']);
        self::assertSame($senderId, $messages[0]['sender_id']);

        $this->notificationRepository->markTopicRead($readerId, $topicId, $messageId);

        self::assertSame($messageId, $this->notificationRepository->findLastReadMessageId($readerId, $topicId));
        self::assertSame([], $this->notificationRepository->findUnreadMessages($topicId, $messageId));

        // Test monotonic update: smaller message id should not overwrite higher message id
        $this->notificationRepository->markTopicRead($readerId, $topicId, $messageId - 1);
        self::assertSame($messageId, $this->notificationRepository->findLastReadMessageId($readerId, $topicId));
    }

    public function testFindUnreadMessagesRespectsLimit(): void
    {
        $senderId = (int) $this->connection->fetchOne(
            'INSERT INTO users(token_hash, username, created_at) VALUES(:token_hash, :username, NOW()) RETURNING id',
            ['token_hash' => hash('sha256', 'token-lim-repo'), 'username' => 'sender-repo'],
        );
        $topicId = $this->notificationRepository->findOrCreateTopicId('bulk');

        for ($i = 1; $i <= 5; ++$i) {
            $this->notificationRepository->createMessage($topicId, $senderId, sprintf('Bulk msg %d', $i));
        }

        $messages = $this->notificationRepository->findUnreadMessages($topicId, 0, 3);
        self::assertCount(3, $messages);
        self::assertSame('Bulk msg 1', $messages[0]['content']);
        self::assertSame('Bulk msg 3', $messages[2]['content']);
    }

    public function testListTopicsReturnsSortedTopicNames(): void
    {
        $this->notificationRepository->findOrCreateTopicId('zeta');
        $this->notificationRepository->findOrCreateTopicId('alpha');

        self::assertSame(['alpha', 'default', 'zeta'], $this->notificationRepository->listTopics());
    }

    public function testDeleteMessagesOlderThanDays(): void
    {
        $senderId = (int) $this->connection->fetchOne(
            'INSERT INTO users(token_hash, username, created_at) VALUES(:token_hash, :username, NOW()) RETURNING id',
            ['token_hash' => hash('sha256', 'token-old-repo'), 'username' => 'sender-old'],
        );
        $topicId = $this->notificationRepository->findOrCreateTopicId('history');

        $this->connection->executeStatement(
            "INSERT INTO messages(topic_id, user_id, content, created_at) VALUES(:topic_id, :user_id, 'old message', NOW() - INTERVAL '40 days')",
            ['topic_id' => $topicId, 'user_id' => $senderId],
        );
        $this->connection->executeStatement(
            "INSERT INTO messages(topic_id, user_id, content, created_at) VALUES(:topic_id, :user_id, 'new message', NOW())",
            ['topic_id' => $topicId, 'user_id' => $senderId],
        );

        $deleted = $this->notificationRepository->deleteMessagesOlderThanDays(30);
        self::assertSame(1, $deleted);

        $remaining = $this->notificationRepository->findUnreadMessages($topicId, 0);
        self::assertCount(1, $remaining);
        self::assertSame('new message', $remaining[0]['content']);
    }

    public function testTransactionMethodsCommitPersistedChanges(): void
    {
        $this->notificationRepository->beginTransaction();
        $topicId = $this->notificationRepository->findOrCreateTopicId('ops');
        $this->notificationRepository->commit();

        self::assertSame($topicId, $this->notificationRepository->findTopicIdByName('ops'));
    }
}
