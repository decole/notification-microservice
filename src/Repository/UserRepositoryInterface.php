<?php

declare(strict_types=1);

namespace App\Repository;

interface UserRepositoryInterface
{
    public function createUser(string $tokenHash, ?string $username): int;

    /**
     * @return array{id:int,username:?string}|null
     */
    public function findUserById(int $id): ?array;

    /**
     * @return array{id:int,username:?string}|null
     */
    public function findUserByTokenHash(string $tokenHash): ?array;
}
