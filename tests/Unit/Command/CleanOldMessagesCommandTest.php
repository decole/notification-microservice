<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\CleanOldMessagesCommand;
use App\Repository\NotificationRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CleanOldMessagesCommandTest extends TestCase
{
    public function testExecuteSuccess(): void
    {
        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())->method('deleteMessagesOlderThanDays')->with(30)->willReturn(42);

        $command = new CleanOldMessagesCommand($repository);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['--days' => '30']);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Deleted 42 message(s)', $tester->getDisplay());
    }

    public function testExecuteRejectsZeroDays(): void
    {
        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->never())->method('deleteMessagesOlderThanDays');

        $command = new CleanOldMessagesCommand($repository);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['--days' => '0']);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('positive integer', $tester->getDisplay());
    }
}
