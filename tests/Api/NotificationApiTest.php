<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Service\UserService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class NotificationApiTest extends WebTestCase
{
    private Connection $connection;

    private \Redis $redis;

    private UserService $userService;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        self::ensureKernelShutdown();
        $this->client = self::createClient(['environment' => 'test', 'debug' => true]);

        $container = $this->client->getContainer();
        $this->connection = $container->get(Connection::class);
        $this->redis = $container->get(\Redis::class);
        $this->userService = $container->get(UserService::class);

        $this->resetStorage();
    }

    public function testInternalRegisterCreatesUserAndTokenInRedis(): void
    {
        $this->client->request(
            'POST',
            '/internal/register',
            server: ['HTTP_X_INTERNAL_SECRET' => 'internal-secret', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode(['username' => 'alice'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);

        $data = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertArrayHasKey('token', $data);
        self::assertIsString($data['token']);
        self::assertNotSame('', $data['token']);

        $row = $this->connection->fetchAssociative(
            'SELECT id, username, token_hash FROM users WHERE username = :username',
            ['username' => 'alice'],
        );
        self::assertIsArray($row);
        self::assertSame('alice', $row['username']);
        self::assertSame(hash('sha256', $data['token']), $row['token_hash']);

        $cachedUserId = $this->redis->get(sprintf('auth:token:%s', $data['token']));
        self::assertSame((string) $row['id'], $cachedUserId);
    }

    public function testInternalRegisterRejectsRemoteRequestWithoutSecret(): void
    {
        $this->client->request(
            'POST',
            '/internal/register',
            server: ['REMOTE_ADDR' => '10.10.10.10', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode(['username' => 'alice'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'Forbidden'], $this->decodeResponse());
    }

    public function testInternalRegisterRejectsRemoteRequestWithWrongSecret(): void
    {
        $this->client->request(
            'POST',
            '/internal/register',
            server: ['REMOTE_ADDR' => '10.10.10.10', 'HTTP_X_INTERNAL_SECRET' => 'wrong-secret', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode(['username' => 'alice'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'Forbidden'], $this->decodeResponse());
    }

    public function testSendAndReceiveUnreadMessagesWithMarkAsRead(): void
    {
        $senderToken = $this->registerUser('sender');
        $readerToken = $this->registerUser('reader');
        $this->client->request(
            'POST',
            '/api/send',
            server: $this->authServer($senderToken),
            content: json_encode(['topic' => 'work', 'message' => 'Hello team'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);
        $this->client->request('GET', '/api/messages/work', server: $this->authServer($readerToken));

        self::assertResponseIsSuccessful();
        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);

        self::assertCount(1, $payload['messages']);
        self::assertSame('Hello team', $payload['messages'][0]['content']);
        self::assertIsInt($payload['messages'][0]['id']);

        $this->client->request('GET', '/api/messages/work', server: $this->authServer($readerToken));
        self::assertResponseIsSuccessful();
        $secondPayload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([], $secondPayload['messages']);
    }

    public function testMessagesWithoutTopicUsesDefaultTopic(): void
    {
        $token = $this->registerUser('user-default');

        $this->client->request(
            'POST',
            '/api/send',
            server: $this->authServer($token),
            content: json_encode(['topic' => 'default', 'message' => 'Default message'], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(201);

        $this->client->request('GET', '/api/messages', server: $this->authServer($token));
        self::assertResponseIsSuccessful();

        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(1, $payload['messages']);
        self::assertSame('Default message', $payload['messages'][0]['content']);
    }

    public function testListTopicsEndpoint(): void
    {
        $token = $this->registerUser('topic-user');

        $this->client->request(
            'POST',
            '/api/send',
            server: $this->authServer($token),
            content: json_encode(['topic' => 'alpha', 'message' => 'A'], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(201);

        $this->client->request(
            'POST',
            '/api/send',
            server: $this->authServer($token),
            content: json_encode(['topic' => 'beta', 'message' => 'B'], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(201);

        $this->client->request('GET', '/api/topics', server: $this->authServer($token));
        self::assertResponseIsSuccessful();

        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertContains('default', $payload['topics']);
        self::assertContains('alpha', $payload['topics']);
        self::assertContains('beta', $payload['topics']);
    }

    public function testApiRejectsMissingAuthorizationHeader(): void
    {
        $this->client->request('GET', '/api/topics');

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'Unauthorized'], $this->decodeResponse());
    }

    public function testApiRejectsMalformedAuthorizationHeader(): void
    {
        $this->client->request('GET', '/api/topics', server: ['HTTP_AUTHORIZATION' => 'Token abc']);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'Unauthorized'], $this->decodeResponse());
    }

    public function testApiRejectsUnknownAuthorizationToken(): void
    {
        $this->client->request('GET', '/api/topics', server: ['HTTP_AUTHORIZATION' => 'Bearer missing-token']);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'Unauthorized'], $this->decodeResponse());
    }

    #[DataProvider('provideInvalidSendPayloads')]
    public function testSendRejectsInvalidPayloads(array $payload): void
    {
        $token = $this->registerUser('sender');

        $this->client->request(
            'POST',
            '/api/send',
            server: $this->authServer($token),
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function testSendRejectsInvalidJsonPayload(): void
    {
        $token = $this->registerUser('sender');

        $this->client->request(
            'POST',
            '/api/send',
            server: $this->authServer($token),
            content: '{invalid-json',
        );

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'Invalid topic'], $this->decodeResponse());
    }

    public function testMessagesRejectsTopicThatIsTooLong(): void
    {
        $token = $this->registerUser('reader');

        $this->client->request('GET', '/api/messages/'.str_repeat('a', 256), server: $this->authServer($token));

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'Invalid topic'], $this->decodeResponse());
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function provideInvalidSendPayloads(): iterable
    {
        yield 'missing topic' => [[]];
        yield 'blank topic' => [['topic' => '   ', 'message' => 'Hello']];
        yield 'topic too long' => [['topic' => str_repeat('a', 256), 'message' => 'Hello']];
        yield 'missing message' => [['topic' => 'work']];
        yield 'blank message' => [['topic' => 'work', 'message' => '']];
        yield 'message too long' => [['topic' => 'work', 'message' => str_repeat('m', 4097)]];
        yield 'non string topic' => [['topic' => 123, 'message' => 'Hello']];
        yield 'non string message' => [['topic' => 'work', 'message' => ['Hello']]];
    }

    private function registerUser(string $username): string
    {
        $user = $this->userService->createUser($username);

        return (string) $user['token'];
    }

    /**
     * @return array<string,string>
     */
    private function authServer(string $token): array
    {
        return [
            'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            'CONTENT_TYPE' => 'application/json',
        ];
    }

    private function resetStorage(): void
    {
        $this->connection->executeStatement('TRUNCATE TABLE user_topic_read, messages, topics, users RESTART IDENTITY CASCADE');
        $this->connection->executeStatement("INSERT INTO topics(name, created_at) VALUES('default', NOW())");

        $keys = $this->redis->keys('auth:token:*');
        if (is_array($keys) && [] !== $keys) {
            $this->redis->del($keys);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(): array
    {
        $content = $this->client->getResponse()->getContent();

        return json_decode($content ?: '{}', true, 512, JSON_THROW_ON_ERROR);
    }
}
