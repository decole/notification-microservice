<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\UserRepositoryInterface;

final class UserRepositoryTest extends DatabaseRepositoryTestCase
{
    private UserRepositoryInterface $userRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepository = self::getContainer()->get(UserRepositoryInterface::class);
    }

    public function testCreateUserPersistsTokenHashAndUsername(): void
    {
        $userId = $this->userRepository->createUser(hash('sha256', 'token-1'), 'alice');

        self::assertSame(1, $userId);

        $row = $this->connection->fetchAssociative('SELECT id, username, token_hash FROM users WHERE id = :id', ['id' => $userId]);

        self::assertIsArray($row);
        self::assertSame('alice', $row['username']);
        self::assertSame(hash('sha256', 'token-1'), $row['token_hash']);
    }

    public function testFindUserByIdReturnsNormalizedUser(): void
    {
        $userId = $this->userRepository->createUser(hash('sha256', 'token-2'), 'bob');

        self::assertSame(
            ['id' => $userId, 'username' => 'bob'],
            $this->userRepository->findUserById($userId),
        );
    }

    public function testFindUserByTokenHashReturnsNormalizedUser(): void
    {
        $tokenHash = hash('sha256', 'token-3');
        $userId = $this->userRepository->createUser($tokenHash, null);

        self::assertSame(
            ['id' => $userId, 'username' => null],
            $this->userRepository->findUserByTokenHash($tokenHash),
        );
    }

    public function testFindUserMethodsReturnNullWhenUserDoesNotExist(): void
    {
        self::assertNull($this->userRepository->findUserById(999));
        self::assertNull($this->userRepository->findUserByTokenHash(hash('sha256', 'missing')));
    }
}
