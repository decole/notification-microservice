<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\CreateUserCommand;
use App\Repository\UserRepositoryInterface;
use App\Service\UserService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateUserCommandTest extends TestCase
{
    public function testExecuteWithArgument(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository
            ->expects($this->once())
            ->method('createUser')
            ->willReturn(1);

        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())->method('setex');
        $userService = new UserService($userRepository, $redis, 3600);

        $command = new CreateUserCommand($userService);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['username' => 'alice']);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('User created. Token:', $tester->getDisplay());
    }

    public function testExecutePromptsForUsernameWhenArgumentMissing(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository
            ->expects($this->once())
            ->method('createUser')
            ->willReturn(2);

        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())->method('setex');
        $userService = new UserService($userRepository, $redis, 3600);

        $command = new CreateUserCommand($userService);
        $tester = new CommandTester($command);
        $tester->setInputs(['bob']);
        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('User created. Token:', $tester->getDisplay());
    }
}
