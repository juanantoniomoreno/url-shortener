# MVP URL Shortener Design

## Context

The repository is a Symfony 7 / PHP 8.4 backend, React 18 / Vite frontend, PostgreSQL database, and RabbitMQ transport. The current codebase is scaffold-only: there is no domain entity, API controller, migration, worker, or dashboard implementation. This design turns the approved MVP proposal and capability specs into a concrete implementation plan without adding authentication, rate limiting, advanced analytics, or link administration.

## Design Goals

- Keep redirect latency independent from click-counter persistence.
- Keep the domain model small enough for the MVP.
- Preserve YAML routing and Symfony autowire/autoconfigure conventions.
- Make expiration a derived rule rather than a scheduled state transition.
- Make API responses stable enough for the React dashboard and E2E tests.
- Make all important behavior executable through PHPUnit and Playwright.

## Architecture

```text
React dashboard
    │
    │ HTTP /api/links
    ▼
Nginx → PHP-FPM → LinkController → Doctrine → PostgreSQL
                         │
                         └── LinkVisited → Messenger async → RabbitMQ
                                                     │
                                                     ▼
                                              Worker container
                                                     │
                                                     └── LinkVisitedHandler → PostgreSQL
```

The redirect path is intentionally write-free. It reads the link, applies the expiration policy, attempts to publish a visit message, and returns the redirect. The worker performs the click-counter write separately.

## Domain Model

### `Link` entity

Create `backend/src/Domain/Entity/Link.php` with Doctrine attribute mapping:

| Field | Type | Rules |
| --- | --- | --- |
| `id` | integer, generated | Internal primary key |
| `slug` | string, max 64, unique | Generated slugs are exactly 7 alphanumeric characters; custom slugs use letters, numbers, hyphens, or underscores |
| `originalUrl` | string/text | Required absolute HTTP or HTTPS URL |
| `clicks` | integer | Defaults to `0`; incremented only by the worker |
| `createdAt` | immutable datetime | Set on construction |
| `updatedAt` | immutable datetime | Set on construction and refreshed after a processed visit |

The database uniqueness constraint on `slug` is authoritative. Application-level checks improve error messages but do not replace the constraint.

### Repository

Create `backend/src/Repository/LinkRepository.php` with methods for:

- lookup by slug;
- checking slug existence for generation and custom-slug validation;
- retrieving all links ordered by `createdAt DESC`.

The repository remains persistence-focused; expiration is evaluated by an application policy so the same rule can be used by redirect and dashboard serialization.

### Expiration policy

Create a small `LinkExpirationPolicy` in `backend/src/Service/` (or an equivalent private service) that evaluates `updatedAt < now - 30 days`. The policy MUST be used by both redirect handling and API response mapping. It MUST NOT mutate database state.

The initial implementation may use `DateTimeImmutable` at the application boundary. Tests should set explicit historical timestamps far enough from the thirty-day boundary to avoid clock-sensitive assertions.

## API Contracts

### `POST /api/links`

Request:

```json
{
  "url": "https://example.com/article",
  "slug": "optional-custom-slug"
}
```

Response `201`:

```json
{
  "slug": "aB3xYz9",
  "url": "https://example.com/article",
  "shortUrl": "http://localhost:8080/aB3xYz9",
  "clicks": 0,
  "createdAt": "2026-01-01T12:00:00+00:00",
  "updatedAt": "2026-01-01T12:00:00+00:00",
  "isExpired": false
}
```

Validation errors use a consistent JSON shape:

```json
{
  "error": {
    "code": "invalid_url",
    "message": "The URL must be an absolute HTTP or HTTPS URL."
  }
}
```

Use HTTP `400` for malformed JSON or invalid fields, and HTTP `409` for a custom-slug collision. A unique database violation must be translated to the same `409` contract.

### `GET /api/links`

Return HTTP `200` and a JSON array using the same item shape as creation. Order by `createdAt DESC`. Return `[]` when no links exist.

### `GET /{slug}`

Return:

- `302` with `Location` for an active link;
- `404` when the slug does not exist;
- `410` when the link is expired.

The catch-all slug route MUST be declared after the `/api/links` routes, and reserved slugs MUST prevent accidental interception of framework/API paths.

## Routing and Controller Design

Create `backend/config/routes.yaml` with named YAML routes:

- `api_links_create`: `POST /api/links`;
- `api_links_list`: `GET /api/links`;
- `link_redirect`: `GET /{slug}`.

Create `backend/src/Controller/LinkController.php`. Keep request parsing, validation orchestration, response mapping, redirect handling, and message dispatch in the controller/application layer. Keep slug generation and expiration decisions in services so they can be unit-tested independently.

The controller should depend on promoted constructor properties for the repository, entity manager, slug generator, message bus, URL generator, logger, and expiration policy as needed. It MUST catch Messenger transport failures around the publish operation, log the failure, and still return the successful redirect response.

## Slug Generation

Create `backend/src/Service/SlugGenerator.php`:

1. Generate a seven-character candidate from `a-zA-Z0-9`.
2. Reject reserved names (`api`, `admin`, `dashboard`).
3. Ask `LinkRepository` whether the candidate exists.
4. Retry until a usable candidate is found or ten attempts have been consumed.
5. Throw a domain/application exception after exhaustion.

Use PHP's cryptographically secure random facilities. Keep the candidate-generation seam injectable or otherwise deterministic in unit tests so collision retries and exhaustion are testable without relying on random chance.

Custom slugs bypass random generation but use the same reserved-name, format, and uniqueness checks.

## Asynchronous Click Tracking

Create:

- `backend/src/Message/LinkVisited.php` — immutable message carrying the link slug;
- `backend/src/MessageHandler/LinkVisitedHandler.php` — loads by slug, ignores missing links, increments clicks, refreshes `updatedAt`, and flushes.

Update `backend/config/packages/messenger.yaml`:

```yaml
framework:
    messenger:
        routing:
            'App\\Message\\LinkVisited': async
```

The controller dispatches `LinkVisited` only for active redirects. The handler does not perform an expiration check because a visit was already accepted by the redirect endpoint; it records the accepted visit even if processing is delayed.

## Docker Worker

Add a `worker` service to the root `docker-compose.yml`:

- reuse the `backend` build context/image;
- mount the backend source like the PHP service;
- depend on healthy PostgreSQL and RabbitMQ;
- run `php bin/console messenger:consume async --time-limit=3600 --memory-limit=256M`;
- use the same database and Messenger environment variables;
- do not invoke the PHP-FPM migration branch.

The existing entrypoint already distinguishes `php-fpm` from worker commands, so no second migration path is needed.

## Frontend Design

Add `frontend/src/api.js` with small `fetch` wrappers for:

- `GET /api/links`;
- `POST /api/links`.

Keep the initial dashboard in `frontend/src/App.jsx` or split into focused components only when that improves readability. The UI MUST:

- load the list on mount;
- submit the URL and optional slug;
- show loading and API error states;
- prepend or refresh the created link after success;
- show short URL, original URL, clicks, and active/expired status.

No client-side routing or authentication is needed for this slice. Vite's existing `/api` proxy remains the development integration boundary.

## Testing Strategy

Strict TDD is enabled in `openspec/config.yaml`. Every implementation slice follows RED → GREEN → TRIANGULATE → REFACTOR.

### Backend

- Unit: slug format, reserved slugs, collision retry, exhaustion, and expiration policy.
- Integration: Doctrine mapping, unique slug constraint, timestamps, and repository queries.
- Functional: create/list API responses, validation, conflict handling, redirects, `404`, `410`, message dispatch, and broker-failure behavior.
- Handler test: click increment, timestamp refresh, and missing-link no-op.

Use SQLite for fast PHPUnit execution where compatible with the mapping. Keep PostgreSQL/RabbitMQ behavior covered by the Docker/CI integration path.

### Frontend

- Build with `npm run build`.
- Playwright E2E: load dashboard, create a valid link, see the generated short URL, and display API errors for invalid input.

## Rollout and Failure Handling

1. Apply the database migration before starting the worker in a deployed environment.
2. Deploy the API and worker from the same image revision.
3. Start RabbitMQ and PostgreSQL before PHP-FPM and worker services.
4. If RabbitMQ is temporarily unavailable, redirects continue to work but click analytics may be missing for that visit; the failure is logged for diagnosis.
5. If the worker is stopped, messages remain in RabbitMQ and are processed when it returns, subject to broker retention and normal Messenger retry behavior.

## Non-Goals and Known Risks

- No authentication, ownership, deletion, editing, custom TTL, rate limiting, or advanced analytics.
- The public creation endpoint can be abused and requires future rate limiting.
- The MVP does not add event-level deduplication; duplicate broker delivery can increment more than once.
- CORS is not solved because the intended development topology uses the Vite proxy; production cross-origin deployment needs a later decision.

## File Impact Summary

### New files

- `backend/src/Domain/Entity/Link.php`
- `backend/src/Repository/LinkRepository.php`
- `backend/src/Controller/LinkController.php`
- `backend/src/Service/SlugGenerator.php`
- `backend/src/Service/LinkExpirationPolicy.php`
- `backend/src/Message/LinkVisited.php`
- `backend/src/MessageHandler/LinkVisitedHandler.php`
- `backend/migrations/Version*.php`
- `backend/tests/Unit/*`
- `backend/tests/Integration/*`
- `backend/tests/Functional/*`
- `frontend/src/api.js`

### Modified files

- `backend/config/routes.yaml`
- `backend/config/packages/messenger.yaml`
- `docker-compose.yml`
- `frontend/src/App.jsx`
- `frontend/tests/e2e/basic.spec.ts`
