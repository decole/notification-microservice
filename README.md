# Notification Microservice

Микросервис уведомлений на Symfony с Bearer-аутентификацией, хранением непрочитанных сообщений по темам и Docker-окружением.

## Стек
- PHP 8.4
- Symfony 8.0
- PostgreSQL 15
- Redis 7
- Twig
- PHPUnit 13

## Запуск

```bash
docker compose up --build -d
```

Приложение доступно на `http://localhost:8080`.

Важно:
- `postgres` и `redis` больше не публикуются наружу по умолчанию
- внутренний секрет и флаг регистрации должны задаваться через env

## Основные переменные окружения

См. [`.env`](/home/decole/PhpstormProjects/uberserver-notification/.env).

- `APP_ENV`
- `APP_DEBUG`
- `APP_SECRET`
- `DATABASE_URL`
- `REDIS_HOST`
- `REDIS_PORT`
- `TOKEN_TTL_SECONDS`
- `DEFAULT_TOPIC`
- `INTERNAL_API_SECRET`
- `INTERNAL_REGISTRATION_ENABLED`

Рекомендуемые значения по умолчанию:
- `APP_DEBUG=0`
- `INTERNAL_REGISTRATION_ENABLED=0`
- длинные случайные значения для `APP_SECRET` и `INTERNAL_API_SECRET`

## Пользователи и токены

Пользователь получает обычный токен один раз и затем использует его как Bearer token:

```http
Authorization: Bearer <TOKEN>
```

Клиенты не передают `token_hash`.

На сервере:
- raw token остаётся клиентским идентификатором
- в БД для авторизации используется `token_hash`
- raw `token` в БД больше не хранится
- Redis используется только как best-effort cache для auth lookup

## Регистрация пользователя

### Через консоль

```bash
docker compose exec php php bin/console app:user:create alice
```

Алиас:

```bash
docker compose exec php php bin/console app:create-user alice
```

### Через внутренний API

```bash
curl -X POST http://localhost:8080/internal/register \
  -H 'Content-Type: application/json' \
  -H 'X-Internal-Secret: <INTERNAL_API_SECRET>' \
  -d '{"username":"alice"}'
```

Ответ:

```json
{"token":"..."}
```

Если `INTERNAL_REGISTRATION_ENABLED=0`, endpoint возвращает `404`.
Если Redis недоступен, регистрация всё равно создаёт пользователя в БД и возвращает токен.

## API

Все маршруты `/api/*` защищены Bearer token.

### Отправить сообщение

```bash
curl -X POST http://localhost:8080/api/send \
  -H 'Authorization: Bearer <TOKEN>' \
  -H 'Content-Type: application/json' \
  -d '{"topic":"general","message":"Hello"}'
```

### Получить непрочитанные сообщения по теме

```bash
curl -X GET http://localhost:8080/api/messages/general \
  -H 'Authorization: Bearer <TOKEN>'
```

### Получить непрочитанные сообщения по теме по умолчанию

```bash
curl -X GET http://localhost:8080/api/messages \
  -H 'Authorization: Bearer <TOKEN>'
```

### Получить список тем

```bash
curl -X GET http://localhost:8080/api/topics \
  -H 'Authorization: Bearer <TOKEN>'
```

### Welcome page

```bash
curl http://localhost:8080/
```

## Ограничения и безопасность

- `/api/*` ограничены rate limit: `120 req/min` на клиентский IP
- `/internal/register` ограничен rate limit: `10 req/min` на клиентский IP
- ошибки API возвращаются в формате `{"error":"..."}`
- внутренний endpoint `/internal/register` требует секрет для не-localhost запросов
- при недоступности Redis `/api/*` продолжают аутентифицировать пользователя через PostgreSQL lookup по `token_hash`

## OpenAPI

Актуальная спецификация:
- [`docs/openapi.yaml`](/home/decole/PhpstormProjects/uberserver-notification/docs/openapi.yaml)

## Миграции

Текущие миграции:
- [`migrations/Version20260304112000.php`](/home/decole/PhpstormProjects/uberserver-notification/migrations/Version20260304112000.php)
- [`migrations/Version20260314150000.php`](/home/decole/PhpstormProjects/uberserver-notification/migrations/Version20260314150000.php)
- [`migrations/Version20260314154000.php`](/home/decole/PhpstormProjects/uberserver-notification/migrations/Version20260314154000.php)

Миграции по токенам:
- `Version20260314150000` добавляет `users.token_hash`
- `Version20260314154000` завершает переход: `users.token_hash` становится `NOT NULL`, `users.token` удаляется

Применить миграции:

```bash
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

Статус:

```bash
docker compose exec php php bin/console doctrine:migrations:status --no-interaction
```

## Команды

Создать пользователя:

```bash
docker compose exec php php bin/console app:user:create alice
```

Если Redis недоступен, команда всё равно создаёт пользователя в БД и печатает токен.

## Тесты

Полный прогон:

```bash
docker compose exec php php vendor/bin/phpunit
```

Test DB:
- `notifications_test`

## Полезно знать

Если внутри контейнера `composer` ругается на:

```text
fatal: detected dubious ownership in repository at '/var/www/html'
```

это git warning на смонтированную директорию. Его можно убрать так:

```bash
docker compose exec php git config --global --add safe.directory /var/www/html
```
