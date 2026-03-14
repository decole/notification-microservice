<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Service\UserService;
use App\Tests\Double\SwitchableRedis;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RedisFailureApiTest extends WebTestCase
{
    private Connection $connection;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        SwitchableRedis::disableFailureMode();
        self::ensureKernelShutdown();
        $this->client = self::createClient(['environment' => 'test', 'debug' => true]);

        $container = self::getContainer();
        $this->connection = $container->get(Connection::class);
        $this->resetStorage();
    }

    protected function tearDown(): void
    {
        SwitchableRedis::disableFailureMode();

        parent::tearDown();
    }

    public function testInternalRegisterStillReturnsTokenWhenRedisIsUnavailable(): void
    {
        SwitchableRedis::enableFailureMode();

        $this->client->request(
            'POST',
            '/internal/register',
            server: ['HTTP_X_INTERNAL_SECRET' => 'internal-secret', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode(['username' => 'alice'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);

        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertArrayHasKey('token', $payload);
        self::assertIsString($payload['token']);

        $row = $this->connection->fetchAssociative('SELECT username, token_hash FROM users WHERE username = :username', ['username' => 'alice']);
        self::assertIsArray($row);
        self::assertSame(hash('sha256', $payload['token']), $row['token_hash']);
    }

    public function testApiAuthenticationFallsBackToDatabaseWhenRedisIsUnavailable(): void
    {
        SwitchableRedis::enableFailureMode();

        /** @var UserService $userService */
        $userService = self::getContainer()->get(UserService::class);
        $user = $userService->createUser('api-user');

        $this->client->request('GET', '/api/topics', server: [
            'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $user['token']),
            'CONTENT_TYPE' => 'application/json',
        ]);

        self::assertResponseIsSuccessful();

        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['default'], $payload['topics']);
    }

    private function resetStorage(): void
    {
        $this->connection->executeStatement('TRUNCATE TABLE user_topic_read, messages, topics, users RESTART IDENTITY CASCADE');
        $this->connection->executeStatement("INSERT INTO topics(name, created_at) VALUES('default', NOW())");
    }
}
