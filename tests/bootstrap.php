<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists(dirname(__DIR__) . '/.env')) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env', 'test');
}

if (isset($_ENV['DATABASE_URL']) && str_contains((string) $_ENV['DATABASE_URL'], '/notifications?')) {
    $_ENV['DATABASE_URL'] = str_replace('/notifications?', '/notifications_test?', (string) $_ENV['DATABASE_URL']);
    $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'];
}
