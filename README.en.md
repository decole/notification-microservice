# Notification Microservice

A high-performance notification microservice built with Symfony 8.0, PHP 8.4, PostgreSQL 15, and Redis 7, featuring Bearer token authentication, per-topic unread message tracking, sliding-window rate limiting, and containerized deployment.

[Читать на русском](README.md)

## Clients
- Linux desktop client: https://github.com/decole/notification-linux-client
- Android client: In development

## Tech Stack
- **PHP 8.4**
- **Symfony 8.0**
- **PostgreSQL 15** (Doctrine DBAL 4)
- **Redis 7** (Authentication cache with lazy fallback)
- **Nginx 1.27** (Alpine)
- **PHPUnit 13**
- **Docker & Docker Compose**

---

## Quick Start

```bash
docker compose up --build -d
```

The application will be available at `http://localhost:8080`.

**Key notes:**
- `postgres` and `redis` run in an isolated internal network (`backend_net`) and are not exposed directly to the host by default.
- Secrets (`APP_SECRET`, `INTERNAL_API_SECRET`, optional `REDIS_PASSWORD`) must be defined in environment variables.

---

## Environment Variables

See [`.env`](.env) for default values.

| Variable | Description | Recommended (Prod) |
|---|---|---|
| `APP_ENV` | Environment (`prod`, `dev`, `test`) | `prod` |
| `APP_DEBUG` | Debug mode | `0` |
| `APP_SECRET` | Symfony app secret | Random 32+ chars |
| `DATABASE_URL` | PostgreSQL connection DSN | `pgsql://app:secret@postgres:5432/notifications?serverVersion=13&charset=utf8` |
| `REDIS_HOST` | Redis host | `redis` |
| `REDIS_PORT` | Redis port | `6379` |
| `REDIS_PASSWORD` | Optional Redis password | Complex random string |
| `TOKEN_TTL_SECONDS` | Token auth cache TTL (seconds) | `2592000` (30 days) |
| `DEFAULT_TOPIC` | Default topic name | `default` |
| `INTERNAL_API_SECRET` | Secret for internal registration endpoint | Long random string |
| `INTERNAL_REGISTRATION_ENABLED` | Enable `/internal/register` endpoint | `0` (disabled in prod if not needed) |
| `TRUSTED_PROXIES` | Trusted reverse proxy IPs / CIDR | `127.0.0.1,REMOTE_ADDR` |

---

## Users and Authentication

Users obtain a raw API token once (during creation) and pass it as a Bearer token in the `Authorization` header:

```http
Authorization: Bearer <TOKEN>
```

**Security Architecture:**
- Clients always send the raw token; clients **never** compute or transmit hashes.
- PostgreSQL stores only `sha256(token)` in `users.token_hash`. Raw tokens are **never** stored in the database.
- Redis acts as a best-effort cache (`auth:token:<raw_token>` -> `{"id": int, "username": string}`).
- If Redis is down, authentication seamlessly falls back to querying PostgreSQL by `token_hash` without service disruption.

---

## User Registration

### Via CLI Console

```bash
docker compose exec php php bin/console app:user:create alice
```

Alias:

```bash
docker compose exec php php bin/console app:create-user alice
```

### Via Internal API

```bash
curl -X POST http://localhost:8080/internal/register \
  -H 'Content-Type: application/json' \
  -H 'X-Internal-Secret: <INTERNAL_API_SECRET>' \
  -d '{"username":"alice"}'
```

Response:

```json
{"token":"<RAW_64_CHAR_TOKEN>"}
```

- If `INTERNAL_REGISTRATION_ENABLED=0`, this endpoint returns `404 Not Found`.
- Header `X-Internal-Secret` is mandatory. Missing or incorrect secrets return `403 Forbidden`.

---

## API Endpoints

All `/api/*` endpoints require Bearer token authentication.

### 1. Send Message to Topic

```bash
curl -X POST http://localhost:8080/api/send \
  -H 'Authorization: Bearer <TOKEN>' \
  -H 'Content-Type: application/json' \
  -d '{"topic":"general","message":"Hello world"}'
```

Response (`201 Created`):
```json
{"message_id": 42}
```

Validation constraints:
- `topic`: non-empty string, max 255 chars, alphanumeric with dots, dashes, underscores (`/^[a-zA-Z0-9_\-\.]+$/`).
- `message`: non-empty string, max 4096 chars.

### 2. Get Unread Messages by Topic

```bash
curl -X GET 'http://localhost:8080/api/messages/general?limit=100' \
  -H 'Authorization: Bearer <TOKEN>'
```

Response (`200 OK`):
```json
{
  "messages": [
    {
      "id": 42,
      "content": "Hello world",
      "created_at": "2026-09-17 02:00:00",
      "sender_id": 1
    }
  ]
}
```

*Note: Calling this endpoint automatically marks returned messages as read up to the maximum message ID retrieved.*

### 3. Get Unread Messages from Default Topic

```bash
curl -X GET 'http://localhost:8080/api/messages?limit=100' \
  -H 'Authorization: Bearer <TOKEN>'
```

### 4. List All Topics

```bash
curl -X GET http://localhost:8080/api/topics \
  -H 'Authorization: Bearer <TOKEN>'
```

Response (`200 OK`):
```json
{
  "topics": ["alerts", "default", "general"]
}
```

### 5. Health Check

```bash
curl http://localhost:8080/healthz
```

Response (`200 OK` when healthy, `200 OK` with `status: degraded` if Redis is unreachable, `503 Service Unavailable` if database is down):
```json
{
  "status": "ok",
  "database": "connected",
  "redis": "connected"
}
```

### 6. Welcome Page

```bash
curl http://localhost:8080/
```

---

## Gatus Integration (Monitoring & Alerting)

This microservice can serve as a centralized notification delivery channel for [Gatus](https://github.com/TwiN/gatus) monitoring.

### 1. Generate Token for Gatus

```bash
docker compose exec php php bin/console app:user:create gatus-alerter
```

### 2. Configure Gatus (`config.yaml`)

```yaml
alerting:
  custom:
    url: "http://notification-server:8080/api/send"
    method: "POST"
    headers:
      Authorization: "Bearer <YOUR_GENERATED_TOKEN>"
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

### 3. Consume Alerts on Clients

Clients listen to the `alerts` topic:

```bash
curl -X GET http://localhost:8080/api/messages/alerts \
  -H 'Authorization: Bearer <CLIENT_TOKEN>'
```

---

## Security & Architecture Highlights

- **Rate Limiting:** Sliding-window policy (120 req/min for `/api/*`, 10 req/min for `/internal/register`).
- **Network Segmentation:** Split networks (`frontend_net` for Nginx <-> PHP, internal `backend_net` for PHP <-> PostgreSQL/Redis).
- **HTTP Hardening:** Nginx includes `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, and `server_tokens off`. PHP runs with `expose_php = Off`.
- **Request Tracing:** `X-Request-ID` is assigned in Nginx and propagated through Symfony responses.
- **Security Audit Logs:** Authentication failures, rate limit events, and forbidden internal access attempts are logged with client IP and User-Agent.

---

## Data Retention & Maintenance

Clean up messages older than the retention period:

```bash
docker compose exec php php bin/console app:messages:cleanup --days=30
```

---

## Database Migrations

Apply migrations:

```bash
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

Check status:

```bash
docker compose exec php php bin/console doctrine:migrations:status --no-interaction
```

---

## Running Tests

```bash
docker compose exec php php vendor/bin/phpunit
```

Run code style fixer:

```bash
docker compose exec php composer fix-cs
```

---

## OpenAPI Specification

The complete OpenAPI 3.0 specification is available at [`docs/openapi.yaml`](docs/openapi.yaml).
