# Developer Guide

## Purpose
Guidelines, architecture standards, and operational rules for developing and maintaining the notification service.

[Читать на русском](DEVELOPERS.md)

---

## Architecture Overview

- [`src/Controller`](src/Controller) — HTTP endpoints (API, Internal, Health, Home)
- [`src/Service`](src/Service) — Domain business logic and cache orchestration
- [`src/Repository`](src/Repository) — PostgreSQL data access via Doctrine DBAL interfaces
- [`src/Input`](src/Input) — Request DTOs with Symfony Validator constraints (`MapRequestPayload`)
- [`src/Security`](src/Security) — Symfony Security user model and Bearer token authenticator
- [`src/EventSubscriber/RequestRateLimitSubscriber.php`](src/EventSubscriber/RequestRateLimitSubscriber.php) — Sliding-window rate limiter
- [`src/EventSubscriber/PayloadExceptionSubscriber.php`](src/EventSubscriber/PayloadExceptionSubscriber.php) — Uniform JSON error formatting for validation failures
- [`src/EventSubscriber/RequestIdSubscriber.php`](src/EventSubscriber/RequestIdSubscriber.php) — `X-Request-ID` correlation ID tracing
- [`src/Command`](src/Command) — Console commands (`app:user:create`, `app:messages:cleanup`)
- [`migrations`](migrations) — Database schema migrations
- [`docs/openapi.yaml`](docs/openapi.yaml) — OpenAPI 3.0 API specification contract
- [`tests/Api`](tests/Api) — Functional web API test suites
- [`tests/Unit`](tests/Unit) — Unit test suites for services, commands, subscribers, and factories
- [`tests/Repository`](tests/Repository) — PostgreSQL integration tests for repositories

---

## Technical Baseline

- **PHP 8.4** (`declare(strict_types=1);` mandatory across all PHP files)
- **Symfony 8.0**
- **Doctrine DBAL 4** (Raw SQL queries with parameterized statements; no ORM overhead)
- **Doctrine Migrations Bundle 4**
- **PHPUnit 13**
- **PostgreSQL 15**
- **Redis 7**

---

## Core Engineering Rules

1. **Strict Types:** Every PHP file must begin with `declare(strict_types=1);`.
2. **API Contract First:** Any API behavioral change must be synchronized with [`docs/openapi.yaml`](docs/openapi.yaml) and accompanied by functional tests.
3. **Database Schema:** Schema modifications must be performed exclusively via Doctrine migrations (`bin/console doctrine:migrations:migrate`).
4. **Error Consistency:** All API error responses must adhere to the standard JSON structure: `{"error": "Description"}`.
5. **Separation of Concerns:** SQL statements must reside exclusively within [`src/Repository`](src/Repository), never in controllers or services.
6. **Final Classes:** Services, DTOs, controllers, and subscribers should be declared `final` or `final readonly`.

---

## Security Model

- **`/api/*` Endpoints:**
  - Authenticated via Bearer token in the `Authorization` header.
  - Resolved by [`BearerTokenAuthenticator.php`](src/Security/BearerTokenAuthenticator.php) with role `ROLE_API_USER`.
  - Rate limited to `120 req/min` per client IP (sliding window).
- **`/internal/register` Endpoint:**
  - Dedicated internal route outside of the main security firewall.
  - Protected by `X-Internal-Secret` header validation using `hash_equals`.
  - Can be disabled globally via `INTERNAL_REGISTRATION_ENABLED=0`.
  - Rate limited to `10 req/min` per client IP.
- **`/healthz` Endpoint:**
  - Public diagnostics endpoint verifying PostgreSQL connectivity (`SELECT 1`) and Redis availability (`PING`).

---

## Token Lifecycle and Caching

- **Issuance:** A 64-character hexadecimal raw token is generated using `random_bytes(32)`.
- **Database Storage:** The database stores `token_hash = sha256(raw_token)` in `users.token_hash`. Raw tokens are never persisted in the database.
- **Cache Layer:** Redis caches user profile data (`auth:token:<raw_token>` -> `{"id": int, "username": string}`) with configurable TTL.
- **Cache-Hit Performance:** When cached, authentication completes entirely in memory without touching PostgreSQL.
- **Fault Tolerance:** If Redis is down or unreachable, authentication automatically falls back to PostgreSQL lookup by `token_hash`.

---

## Data Retention & Commands

- Create user:
  ```bash
  docker compose exec php php bin/console app:user:create <username>
  ```
- Purge old messages:
  ```bash
  docker compose exec php php bin/console app:messages:cleanup --days=30
  ```

---

## CI/CD Pipeline

The GitHub Actions pipeline (`.github/workflows/ci.yaml`) runs:
- `composer validate --strict`
- `composer audit` (Dependency CVE vulnerability scanning)
- `composer fix-cs` (PER-CS2 / Symfony style check)
- Automated PostgreSQL migrations
- Full test suite via PHPUnit
