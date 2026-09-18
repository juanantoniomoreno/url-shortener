## Exploration: MVP URL Shortener

### Current State

The project is a freshly scaffolded Symfony 7 + React 18 application with Docker Compose orchestration. No business logic exists yet.

**Backend**: Symfony 7 (PHP 8.4 FPM), Doctrine ORM 3.6 with attribute entity mapping in `src/Domain/Entity`, Messenger with a single `async` AMQP transport (RabbitMQ), PHPUnit 11.5 + DAMA DoctrineTestBundle for transactional tests, YAML-based routing, autowire/autoconfigure DI. Only `Kernel.php` and an empty `Controller/` directory exist in `src/`.

**Frontend**: React 18 + Vite 5, minimal scaffold (`App.jsx`, `main.jsx`), Playwright 1.60 for E2E, Vite dev proxy to `/api` at `localhost:8080`.

**Docker**: 5 services — `postgres` (5433), `rabbitmq` (5673/15673), `php` (FPM), `nginx` (8080), `frontend` (3000). The `php` entrypoint only runs migrations when `CMD` is `php-fpm`, making it safe to reuse the image for workers with a different command.

**CI**: GitHub Actions runs PHPUnit with SQLite (`sqlite:////tmp/...`), frontend build, and `docker compose build`.

### Affected Areas

| Path | Why Affected |
|------|-------------|
| `backend/src/Controller/LinkController.php` | New — handles `POST /api/links` and `GET /{slug}` |
| `backend/src/Domain/Entity/Link.php` | New — core entity with slug, URL, clicks, timestamps |
| `backend/src/Message/LinkVisited.php` | New — async message dispatched on redirect |
| `backend/src/MessageHandler/LinkVisitedHandler.php` | New — worker increments click counter and resets TTL |
| `backend/src/Service/SlugGenerator.php` | New — random 7-char alphanumeric generation with collision retry |
| `backend/config/routes.yaml` or `config/routes/` | Add API + redirect routes |
| `backend/config/packages/messenger.yaml` | Route `LinkVisited` to `async` transport |
| `backend/docker-compose.yml` (project root) | Add dedicated `worker` service |
| `frontend/src/App.jsx` + new components | Build dashboard list, form, status display |
| `frontend/src/api.js` (new) | Minimal fetch wrapper for `/api/links` |
| `backend/tests/` | Unit tests for slug generator, integration for DB, functional for endpoints |

### Approaches

#### 1. Event Flow Architecture

**Option A: Controller → Message → Worker (Recommended)**
- `POST /api/links` → Controller validates, uses `SlugGenerator`, persists `Link` via Doctrine, returns JSON.
- `GET /{slug}` → Controller fetches `Link`, checks expiration lazily (30 days since `updatedAt`), dispatches `LinkVisited` to Messenger `async`, returns 302 redirect (or 410 if expired).
- Worker `LinkVisitedHandler` → increments `clicks`, updates `updatedAt`, flushes.

**Option B: Synchronous Click Update**
- `GET /{slug}` increments counter inline before redirect.
- **Rejected**: Defeats the async analytics purpose and adds DB write latency to every redirect.

#### 2. Slug Generation

**Option A: Retry Loop with Random Bytes**
- Charset: `a-zA-Z0-9`, length 7. Space ≈ 78 billion.
- Generate, check DB uniqueness + reserved list (`api`, `admin`, `dashboard`), retry up to 10 times.
- **Pros**: Simple, no external dependency, collision probability negligible.
- **Cons**: Under extreme load, theoretical collision (mitigated by retry limit + exception).

**Option B: Database Sequence / Snowflake**
- **Rejected**: Overkill for MVP; random slugs are expected behavior.

#### 3. Expiration / TTL

**Option A: Lazy Expiration Check (Recommended)**
- Redirect controller checks `updatedAt < now - 30 days` → 404/410.
- Dashboard query computes `isExpired` from `updatedAt` in DTO/serializer.
- **Pros**: No cron, no scheduler, no extra command. Purely derived.
- **Cons**: Expired rows remain in DB until manual cleanup (acceptable for MVP).

**Option B: Scheduled Symfony Command**
- `php bin/console links:expire` run via cron marks/links expired rows.
- **Rejected**: Adds infrastructure complexity (cron container or host cron) for a cosmetic dashboard feature.

#### 4. Docker Worker Service

**Option A: Separate `worker` Service (Recommended)**
- Add `worker` to `docker-compose.yml`, same image, command: `php bin/console messenger:consume async --time-limit=3600 --memory-limit=256M`.
- Entrypoint already skips migrations when `$1 != php-fpm`.
- **Pros**: Follows `event-driven-orders` pattern, scalable independently, clean logs.
- **Cons**: One more container (~0MB extra image cost since it reuses `php` image).

**Option B: `php` Container Dual Role**
- Run worker as a background process inside `php` container.
- **Rejected**: Violates single-responsibility per container; FPM process should not host workers.

### Recommendation

- **Event flow**: Controller → `LinkVisited` Message → `async` AMQP → Worker Handler.
- **Slug**: Random 7-char alphanumeric with 10-attempt retry loop against DB + reserved list.
- **Expiration**: Lazy check on redirect + computed field in dashboard API response.
- **Worker**: New `worker` service in `docker-compose.yml`.
- **Entity**: Single `Link` table with `clicks` counter (no separate analytics table for MVP).

### Risks

1. **Spam / abuse**: `POST /api/links` is public and unauthenticated. No rate limiting in scope; could fill DB. Mitigation: out of MVP scope, but should be noted for future hardening.
2. **Slug exhaustion**: Unlikely with 7 chars, but retry loop must have a hard cap and throw a clear exception.
3. **CORS in production**: Vite proxy handles dev; production deployment will need CORS headers on Symfony responses if frontend/backend are served from different origins.
4. **Messenger routing not configured**: `messenger.yaml` currently has empty `routing:`. We must add `App\Message\LinkVisited: async`.
5. **No validation beyond Symfony constraints**: Malformed URLs will be caught by `Url` validator, but no reachability check (acceptable for MVP).

### Ready for Proposal

**Yes.** The codebase is clean scaffolding with no conflicting logic. All patterns (Messenger, Doctrine attributes, YAML routing, autowire) are aligned with the stack. The orchestrator should proceed to `sdd-propose`.
