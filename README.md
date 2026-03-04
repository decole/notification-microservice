# Notification Microservice (Symfony + Docker)

Микросервис публичных каналов с отправкой сообщений и выдачей непрочитанных сообщений с авто-обновлением статуса прочтения.

## Стек
- PHP 8.3 (совместимо с 8.1+)
- Symfony 6.4 LTS
- PostgreSQL 15 (совместимо с 13+)
- Redis 7 (совместимо с 6+)

## Запуск

```bash
docker compose up --build -d
```

Сервис: `http://localhost:8080`.

## Основные команды

```bash
make up
make down
make migration
make prepare-test
make test
```

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
  -H 'X-Internal-Secret: internal-secret' \
  -d '{"username":"alice"}'
```

Ответ:
```json
{"token":"..."}
```

## API

### Отправить сообщение
```bash
curl -X POST http://localhost:8080/api/send \
  -H 'Authorization: Bearer <TOKEN>' \
  -H 'Content-Type: application/json' \
  -d '{"topic":"general","message":"Всем привет!"}'
```

### Получить непрочитанные сообщения
```bash
curl -X GET http://localhost:8080/api/messages/general \
  -H 'Authorization: Bearer <TOKEN>'
```

Канал по умолчанию:
```bash
curl -X GET http://localhost:8080/api/messages \
  -H 'Authorization: Bearer <TOKEN>'
```

### Список каналов
```bash
curl -X GET http://localhost:8080/api/topics \
  -H 'Authorization: Bearer <TOKEN>'
```

## OpenAPI

Спецификация лежит в файле:
- `docs/openapi.yaml`

## Автотесты

Интеграционные тесты API:
- `tests/Api/NotificationApiTest.php`

Запуск:
```bash
make prepare-test
make test
```

`make` использует отдельную test-базу `notifications_test`.

## Переменные окружения
- `DATABASE_URL`
- `REDIS_HOST`
- `REDIS_PORT`
- `TOKEN_TTL_SECONDS` (по умолчанию 30 дней)
- `DEFAULT_TOPIC` (`default`)
- `INTERNAL_API_SECRET`
- `INTERNAL_REGISTRATION_ENABLED` (`0/1`)

## Схема БД
Создаётся миграцией `migrations/Version20260304112000.php`:
- `users`
- `topics`
- `messages`
- `user_topic_read`

Индексы:
- `messages(topic_id, id)`
- `user_topic_read(user_id, topic_id)`
