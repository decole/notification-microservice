<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Repository\NotificationRepositoryInterface;
use App\Service\NotificationService;
use PHPUnit\Framework\TestCase;

final class NotificationServiceTest extends TestCase
{
    public function testSendMessageUsesExistingTopicId(): void
    {
        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())->method('findOrCreateTopicId')->with('work')->willReturn(7);
        $repository->expects($this->once())->method('createMessage')->with(7, 42, 'Hello team')->willReturn(15);

        $service = new NotificationService($repository);

        self::assertSame(15, $service->sendMessage(42, 'work', 'Hello team'));
    }

    public function testSendMessageCreatesTopicWhenMissing(): void
    {
        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())->method('findOrCreateTopicId')->with('alerts')->willReturn(8);
        $repository->expects($this->once())->method('createMessage')->with(8, 7, 'Created topic')->willReturn(21);

        $service = new NotificationService($repository);

        self::assertSame(21, $service->sendMessage(7, 'alerts', 'Created topic'));
    }

    public function testGetUnreadMessagesReturnsEmptyArrayWhenTopicDoesNotExist(): void
    {
        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())->method('beginTransaction');
        $repository->expects($this->once())->method('findTopicIdByName')->with('missing')->willReturn(null);
        $repository->expects($this->once())->method('commit');
        $repository->expects($this->never())->method('findUnreadMessages');
        $repository->expects($this->never())->method('markTopicRead');

        $service = new NotificationService($repository);

        self::assertSame([], $service->getUnreadMessagesAndMarkRead(10, 'missing'));
    }

    public function testGetUnreadMessagesReturnsMessagesAndMarksRead(): void
    {
        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())->method('beginTransaction');
        $repository->expects($this->once())->method('findTopicIdByName')->with('team')->willReturn(4);
        $repository->expects($this->once())->method('findLastReadMessageId')->with(20, 4)->willReturn(11);
        $repository->expects($this->once())->method('findUnreadMessages')->with(4, 11)->willReturn([
            ['id' => 12, 'content' => 'Hello', 'created_at' => '2026-01-01 10:00:00', 'sender_id' => 5],
            ['id' => 13, 'content' => 'World', 'created_at' => '2026-01-01 11:00:00', 'sender_id' => null],
        ]);
        $repository->expects($this->once())->method('markTopicRead')->with(20, 4, 13);
        $repository->expects($this->once())->method('commit');

        $service = new NotificationService($repository);

        self::assertSame([
            ['id' => 12, 'content' => 'Hello', 'created_at' => '2026-01-01 10:00:00', 'sender_id' => 5],
            ['id' => 13, 'content' => 'World', 'created_at' => '2026-01-01 11:00:00', 'sender_id' => null],
        ], $service->getUnreadMessagesAndMarkRead(20, 'team'));
    }

    public function testGetUnreadMessagesReturnsEmptyArrayWhenNothingNewWasFound(): void
    {
        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())->method('beginTransaction');
        $repository->expects($this->once())->method('findTopicIdByName')->with('team')->willReturn(4);
        $repository->expects($this->once())->method('findLastReadMessageId')->with(20, 4)->willReturn(11);
        $repository->expects($this->once())->method('findUnreadMessages')->with(4, 11)->willReturn([]);
        $repository->expects($this->never())->method('markTopicRead');
        $repository->expects($this->once())->method('commit');

        $service = new NotificationService($repository);

        self::assertSame([], $service->getUnreadMessagesAndMarkRead(20, 'team'));
    }

    public function testGetUnreadMessagesRollsBackTransactionWhenFetchingFails(): void
    {
        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())->method('beginTransaction');
        $repository->expects($this->once())->method('findTopicIdByName')->with('team')->willThrowException(new \RuntimeException('DB failure'));
        $repository->expects($this->once())->method('rollBack');
        $repository->expects($this->never())->method('commit');

        $service = new NotificationService($repository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB failure');

        $service->getUnreadMessagesAndMarkRead(1, 'team');
    }

    public function testListTopicsCastsValuesToStrings(): void
    {
        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository
            ->expects($this->once())
            ->method('listTopics')
            ->willReturn(['alpha', '100', '']);

        $service = new NotificationService($repository);

        self::assertSame(['alpha', '100', ''], $service->listTopics());
    }
}
