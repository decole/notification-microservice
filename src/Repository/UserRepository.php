<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

final readonly class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function createUser(string $tokenHash, ?string $username): int
    {
        return (int) $this->connection->fetchOne(
            'INSERT INTO users(token_hash, username, created_at) VALUES(:token_hash, :username, NOW()) RETURNING id',
            [
                'token_hash' => $tokenHash,
                'username' => $username,
            ],
        );
    }

    public function findUserById(int $id): ?array
    {
        $user = $this->connection->fetchAssociative(
            'SELECT id, username FROM users WHERE id = :id',
            ['id' => $id],
        );

        if (!is_array($user)) {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            'username' => null !== $user['username'] ? (string) $user['username'] : null,
        ];
    }

    public function findUserByTokenHash(string $tokenHash): ?array
    {
        $user = $this->connection->fetchAssociative(
            'SELECT id, username FROM users WHERE token_hash = :token_hash',
            ['token_hash' => $tokenHash],
        );

        if (!is_array($user)) {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            'username' => null !== $user['username'] ? (string) $user['username'] : null,
        ];
    }
}
