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
    }

    public function testListTopicsReturnsSortedTopicNames(): void
    {
        $this->notificationRepository->findOrCreateTopicId('zeta');
        $this->notificationRepository->findOrCreateTopicId('alpha');

        self::assertSame(['alpha', 'default', 'zeta'], $this->notificationRepository->listTopics());
    }

    public function testTransactionMethodsCommitPersistedChanges(): void
    {
        $this->notificationRepository->beginTransaction();
        $topicId = $this->notificationRepository->findOrCreateTopicId('ops');
        $this->notificationRepository->commit();

        self::assertSame($topicId, $this->notificationRepository->findTopicIdByName('ops'));
    }
}
