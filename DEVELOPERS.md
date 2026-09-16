# Developer Guide

## Цель

Правила поддержки и развития notification service.

## Актуальная архитектура

- [`src/Controller`](/home/decole/PhpstormProjects/uberserver-notification/src/Controller) — HTTP endpoints
- [`src/Service`](/home/decole/PhpstormProjects/uberserver-notification/src/Service) — бизнес-логика и orchestration
- [`src/Repository`](/home/decole/PhpstormProjects/uberserver-notification/src/Repository) — PostgreSQL access через интерфейсы
- [`src/Input`](/home/decole/PhpstormProjects/uberserver-notification/src/Input) — request DTO для `MapRequestPayload`
- [`src/Security`](/home/decole/PhpstormProjects/uberserver-notification/src/Security) — Symfony Security user + Bearer authenticator
- [`src/EventSubscriber/RequestRateLimitSubscriber.php`](/home/decole/PhpstormProjects/uberserver-notification/src/EventSubscriber/RequestRateLimitSubscriber.php) — rate limiting на `kernel.request`
- [`src/EventSubscriber/PayloadExceptionSubscriber.php`](/home/decole/PhpstormProjects/uberserver-notification/src/EventSubscriber/PayloadExceptionSubscriber.php) — нормализация ошибок `MapRequestPayload`
- [`src/Command`](/home/decole/PhpstormProjects/uberserver-notification/src/Command) — консольные команды
- [`migrations`](/home/decole/PhpstormProjects/uberserver-notification/migrations) — миграции БД
- [`docs/openapi.yaml`](/home/decole/PhpstormProjects/uberserver-notification/docs/openapi.yaml) — API contract
- [`tests/Api`](/home/decole/PhpstormProjects/uberserver-notification/tests/Api) — functional API tests
- [`tests/Unit`](/home/decole/PhpstormProjects/uberserver-notification/tests/Unit) — unit tests сервисов и subscriber-ов
- [`tests/Repository`](/home/decole/PhpstormProjects/uberserver-notification/tests/Repository) — integration tests репозиториев на PostgreSQL

Старого `ApiAuthSubscriber` в проекте больше нет. `/api/*` теперь живут через Symfony Security firewall и [`BearerTokenAuthenticator.php`](/home/decole/PhpstormProjects/uberserver-notification/src/Security/BearerTokenAuthenticator.php).

## Технический baseline

- PHP 8.4
- Symfony 8.0
- Doctrine DBAL 4
- Doctrine Migrations Bundle 4
- PHPUnit 13
- PostgreSQL 15
- Redis 7

## Ключевые правила

- `declare(strict_types=1);` во всех PHP-файлах
- новые изменения API всегда синхронизировать с [`docs/openapi.yaml`](/home/decole/PhpstormProjects/uberserver-notification/docs/openapi.yaml)
- любые изменения поведения должны сопровождаться тестами
- любые изменения схемы БД только через миграции
- не возвращать произвольные форматы ошибок для API, использовать `{"error":"..."}`
- SQL к PostgreSQL должен жить в [`src/Repository`](/home/decole/PhpstormProjects/uberserver-notification/src/Repository), не в сервисах и не в контроллерах

## Security модель

- `/api/*`:
  - Bearer token в `Authorization`
  - аутентификация через Symfony Security
  - роль `ROLE_API_USER`
- `/internal/register`:
  - не входит в security firewall
  - строго защищён секретом `X-Internal-Secret`
  - может быть полностью выключен через `INTERNAL_REGISTRATION_ENABLED=0`
- `/healthz`:
  - открытый диагностический эндпоинт состояния PostgreSQL и Redis
- payload validation:
  - `/api/send` использует [`SendInput.php`](/home/decole/PhpstormProjects/uberserver-notification/src/Input/SendInput.php)
  - `/internal/register` использует [`RegisterInput.php`](/home/decole/PhpstormProjects/uberserver-notification/src/Input/RegisterInput.php)
  - ошибки `MapRequestPayload` нормализуются через [`PayloadExceptionSubscriber.php`](/home/decole/PhpstormProjects/uberserver-notification/src/EventSubscriber/PayloadExceptionSubscriber.php)
- rate limiting:
  - `/api/*` — `120 req/min`
  - `/internal/register` — `10 req/min`

## Токены

Текущая модель:
- клиентам выдаётся и передаётся raw token
- в БД хранится только `token_hash`
- lookup работает только по `token_hash`
- Redis хранит сериализованный профиль (`id`, `username`) как best-effort cache для нулевой нагрузки на БД при cache-hit
- Redis не является source of truth

Не менять внешний контракт:
- клиенты не должны отправлять `token_hash`
- клиенты продолжают использовать `Authorization: Bearer <token>`

Operational rule:
- недоступность Redis не должна ломать регистрацию пользователя, консольную выдачу токена или auth lookup через БД

## Команды

Создать пользователя:

```bash
docker compose exec php php bin/console app:user:create alice
```

Очистить старые сообщения по политике Data Retention:

```bash
docker compose exec php php bin/console app:messages:cleanup --days=30
```

Перед изменениями команд проверять, что:
- команда видна в `bin/console list app`
- команда не ломает `cache:clear`
- `app:user:create` продолжает печатать token даже при сбое Redis

## Docker и окружение

Сетевая топология:
- `frontend_net`: Nginx <-> PHP
- `backend_net` (internal): PHP <-> PostgreSQL, Redis (БД и Redis изолированы от внешнего мира и Nginx)

Контейнеры:
- `php` — запущен от непривилегированного пользователя `www-data`
- `nginx` — настроены Security Headers, `server_tokens off`, таймауты и `client_max_body_size`
- `postgres` — Postgres 15
- `redis` — Redis 7 с опциональной авторизацией `REDIS_PASSWORD`

Важно:
- `postgres` и `redis` не должны публиковаться наружу без отдельной причины
- секреты нельзя хардкодить в codebase
- `APP_DEBUG` в обычном runtime должен быть `0`

Если `composer` внутри контейнера жалуется на `dubious ownership`, это решается:

```bash
docker compose exec php git config --global --add safe.directory /var/www/html
```

## Тестирование

Полный прогон:

```bash
docker compose exec php php vendor/bin/phpunit
```

Минимум перед merge:
- `phpunit` зелёный
- `cache:clear` проходит
- `doctrine:migrations:status` в ожидаемом состоянии
- `openapi.yaml` актуален
- README/DEVELOPERS обновлены, если изменилось поведение

Тестовые группы:
- [`tests/Api/NotificationApiTest.php`](/home/decole/PhpstormProjects/uberserver-notification/tests/Api/NotificationApiTest.php)
- [`tests/Api/RedisFailureApiTest.php`](/home/decole/PhpstormProjects/uberserver-notification/tests/Api/RedisFailureApiTest.php)
- [`tests/Unit/Service`](/home/decole/PhpstormProjects/uberserver-notification/tests/Unit/Service)
- [`tests/Unit/EventSubscriber`](/home/decole/PhpstormProjects/uberserver-notification/tests/Unit/EventSubscriber)
- [`tests/Repository`](/home/decole/PhpstormProjects/uberserver-notification/tests/Repository)

## Что не делать

- не возвращать клиентов на `token_hash`
- не менять контракт `/api/*` без обновления OpenAPI и functional tests
- не хардкодить секреты в коде или compose
- не публиковать `postgres` и `redis` наружу без явной причины
