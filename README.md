# Notification Microservice

Микросервис уведомлений на Symfony с Bearer-аутентификацией, хранением непрочитанных сообщений по темам и Docker-окружением.

[Read in English](README.en.md)

## Clients:
 - linux client – https://github.com/decole/notification-linux-client
 - android client – not yet.E

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
Payload для `/internal/register` обрабатывается через DTO `RegisterInput` и `MapRequestPayload`.

## API

Все маршруты `/api/*` защищены Bearer token.

### Отправить сообщение

```bash
curl -X POST http://localhost:8080/api/send \
  -H 'Authorization: Bearer <TOKEN>' \
  -H 'Content-Type: application/json' \
  -d '{"topic":"general","message":"Hello"}'
```

Если JSON невалидный, API возвращает:

```json
{"error":"Invalid JSON"}
```

Если payload не проходит валидацию DTO `SendInput`, API возвращает `422` и `{"error":"..."}`.

### Получить непрочитанные сообщения по теме

```bash
curl -X GET 'http://localhost:8080/api/messages/general?limit=100' \
  -H 'Authorization: Bearer <TOKEN>'
```

Параметр `limit` опционален (по умолчанию `100`, макс. `1000`).

### Получить непрочитанные сообщения по теме по умолчанию

```bash
curl -X GET 'http://localhost:8080/api/messages?limit=100' \
  -H 'Authorization: Bearer <TOKEN>'
```

### Получить список тем

```bash
curl -X GET http://localhost:8080/api/topics \
  -H 'Authorization: Bearer <TOKEN>'
```

### Health check

```bash
curl http://localhost:8080/healthz
```

Ответ:

```json
{"status":"ok","database":"connected","redis":"connected"}
```

### Welcome page

```bash
curl http://localhost:8080/
```

## Интеграция с Gatus (Monitoring & Alerting)

Микросервис может использоваться как единая точка доставки алертов из системы мониторинга [Gatus](https://github.com/TwiN/gatus) для десктопных и мобильных клиентов.

### 1. Создание токена для Gatus

Сгенерируйте сервисный токен для Gatus:

```bash
docker compose exec php php bin/console app:user:create gatus-alerter
```

Скопируйте полученный `<TOKEN>`.

### 2. Настройка Gatus (`config.yaml`)

Настройте секцию `alerting.custom` в конфигурационном файле Gatus:

```yaml
alerting:
  custom:
    url: "http://notification-server:8080/api/send"
    method: "POST"
    headers:
      Authorization: "Bearer <TOKEN>"
      Content-Type: "application/json"
    body: |
      {
        "topic": "alerts",
        "message": "[ALERT_TRIGGERED_OR_RESOLVED] | [ENDPOINT_GROUP]/[ENDPOINT_NAME] is [ENDPOINT_STATUS] (HTTP [STATUS], [RESPONSE_TIME]ms). Details: [ALERT_DESCRIPTION]"
      }

endpoints:
  - name: backend-api
    group: core
    url: "http://backend:8080/healthz"
    interval: 30s
    conditions:
      - "[STATUS] == 200"
      - "[BODY].status == ok"
    alerts:
      - type: custom
        enabled: true
        failure-threshold: 3
        success-threshold: 2
        send-on-resolved: true
        description: "Backend health check failed"
```

### 3. Получение алертов клиентами

Клиентское приложение (Linux/Android) запрашивает непрочитанные алерты по топику `alerts`:

```bash
curl -X GET http://localhost:8080/api/messages/alerts \
  -H 'Authorization: Bearer <CLIENT_TOKEN>'
```

Все подписчики топика `alerts` получают уведомления о сбоях и восстановлении сервисов в реальном времени.

## Ограничения и безопасность

- `/api/*` ограничены rate limit: `120 req/min` на клиентский IP
- `/internal/register` ограничен rate limit: `10 req/min` на клиентский IP
- ошибки API возвращаются в формате `{"error":"..."}`
- payload validation для `/api/send` и `/internal/register` выполняется через `MapRequestPayload` + DTO
- внутренний endpoint `/internal/register` строго требует секрет в заголовке `X-Internal-Secret`
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
