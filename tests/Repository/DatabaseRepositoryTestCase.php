<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class DatabaseRepositoryTestCase extends KernelTestCase
{
    protected Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);

        $this->resetStorage();
    }

    protected function resetStorage(): void
    {
        $this->connection->executeStatement('TRUNCATE TABLE user_topic_read, messages, topics, users RESTART IDENTITY CASCADE');
        $this->connection->executeStatement("INSERT INTO topics(name, created_at) VALUES('default', NOW())");
    }
}
