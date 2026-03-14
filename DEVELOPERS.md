# Developer Guide

## Цель

Правила поддержки и развития notification service.

## Актуальная архитектура

- [`src/Controller`](/home/decole/PhpstormProjects/uberserver-notification/src/Controller) — HTTP endpoints
- [`src/Service`](/home/decole/PhpstormProjects/uberserver-notification/src/Service) — бизнес-логика, DBAL-запросы, auth lookup
- [`src/Security`](/home/decole/PhpstormProjects/uberserver-notification/src/Security) — Symfony Security user + Bearer authenticator
- [`src/EventSubscriber/RequestRateLimitSubscriber.php`](/home/decole/PhpstormProjects/uberserver-notification/src/EventSubscriber/RequestRateLimitSubscriber.php) — rate limiting на `kernel.request`
- [`src/Command`](/home/decole/PhpstormProjects/uberserver-notification/src/Command) — консольные команды
- [`migrations`](/home/decole/PhpstormProjects/uberserver-notification/migrations) — миграции БД
- [`docs/openapi.yaml`](/home/decole/PhpstormProjects/uberserver-notification/docs/openapi.yaml) — API contract
- [`tests/Api`](/home/decole/PhpstormProjects/uberserver-notification/tests/Api) — functional API tests
- [`tests/Unit`](/home/decole/PhpstormProjects/uberserver-notification/tests/Unit) — unit tests сервисов и subscriber-ов

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

## Security модель

- `/api/*`:
  - Bearer token в `Authorization`
  - аутентификация через Symfony Security
  - роль `ROLE_API_USER`
- `/internal/register`:
  - не входит в security firewall
  - защищён секретом `X-Internal-Secret` для non-localhost
  - может быть полностью выключен через `INTERNAL_REGISTRATION_ENABLED=0`
- rate limiting:
  - `/api/*` — `120 req/min`
  - `/internal/register` — `10 req/min`

## Токены

Текущий переходный режим:
- клиентам выдаётся и передаётся raw token
- в БД хранится:
  - `token`
  - `token_hash`
- lookup сейчас работает так:
  1. поиск по `token_hash`
  2. fallback по legacy `token`

Это сделано для обратной совместимости без ротации клиентских токенов.

Не менять внешний контракт:
- клиенты не должны отправлять `token_hash`
- клиенты продолжают использовать `Authorization: Bearer <token>`

## Команды

Создать пользователя:

```bash
docker compose exec php php bin/console app:user:create alice
```

Backfill legacy `token_hash`:

```bash
docker compose exec php php bin/console app:tokens:backfill-hash
```

Перед изменениями команд проверять, что:
- команда видна в `bin/console list app`
- команда не ломает `cache:clear`

## База данных

Схема сейчас описана миграциями:
- [`Version20260304112000.php`](/home/decole/PhpstormProjects/uberserver-notification/migrations/Version20260304112000.php)
- [`Version20260314150000.php`](/home/decole/PhpstormProjects/uberserver-notification/migrations/Version20260314150000.php)

Изменения схемы:
- только через новую migration
- без ручных правок production schema

Hot paths:
- unread/read flow в [`NotificationService.php`](/home/decole/PhpstormProjects/uberserver-notification/src/Service/NotificationService.php)
- token lookup в [`TokenAuthService.php`](/home/decole/PhpstormProjects/uberserver-notification/src/Service/TokenAuthService.php)

При изменении этих мест обязательно проверять индексы и влияние на latency.

## Docker и окружение

Контейнеры:
- `php`
- `nginx`
- `postgres`
- `redis`

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
- [`tests/Unit/Service`](/home/decole/PhpstormProjects/uberserver-notification/tests/Unit/Service)
- [`tests/Unit/EventSubscriber`](/home/decole/PhpstormProjects/uberserver-notification/tests/Unit/EventSubscriber)

## Что не делать

- не возвращать клиентов на `token_hash`
- не убирать fallback по `users.token`, пока не подтверждён полный backfill
- не менять контракт `/api/*` без обновления OpenAPI и functional tests
- не хардкодить секреты в коде или compose
- не публиковать `postgres` и `redis` наружу без явной причины
