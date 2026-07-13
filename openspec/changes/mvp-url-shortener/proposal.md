# Proposal: MVP URL Shortener

## Intent

Build the first working version of a URL shortener with async click tracking. Users create short links via a public API, get redirected through them, and see click counts on a dashboard — all without authentication. This establishes the core domain (Link entity, slug generation, event-driven analytics) that future features will extend.

## Scope

### In Scope
- `POST /api/links` — create short URL with optional custom slug
- `GET /{slug}` — 302 redirect with async `LinkVisited` event dispatch
- Worker service consuming `LinkVisited` to increment click counter
- React dashboard listing links with click counts and expiration status
- Lazy expiration: links inactive for 30 days return 410 Gone
- Docker `worker` service (6th container)
- PHPUnit tests (unit, integration, functional) + Playwright E2E

### Out of Scope
- Authentication / user accounts
- Rate limiting or abuse protection
- Advanced analytics (geo, IP, user-agent, referrer)
- Custom TTL per link, link editing, or deletion
- Mercure real-time updates
- Admin panel

## Capabilities

### New Capabilities
- `link-management`: Slug generation, link creation API, redirect with lazy expiration check
- `click-tracking`: `LinkVisited` message, async AMQP transport, worker handler incrementing counter
- `dashboard`: React frontend listing links with click counts, expiration status, and creation form

### Modified Capabilities
None — no existing specs.

## Approach

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Event flow | Controller → `LinkVisited` → AMQP `async` → Worker | Decouples redirect latency from DB write; follows event-driven pattern |
| Slug generation | 7-char random alphanumeric, 10-retry collision loop | ~78B keyspace, negligible collision risk, simple implementation |
| Expiration | Lazy check (`updatedAt < now - 30d`) on redirect + computed field in API | No cron/scheduler needed; purely derived state |
| Entity model | Single `Link` table with inline `clicks` counter | Sufficient for MVP; no separate analytics table |
| Worker | Separate Docker service reusing `php` image | Single-responsibility per container; scalable independently |

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `backend/src/Domain/Entity/Link.php` | New | Core entity: slug, originalUrl, clicks, createdAt, updatedAt |
| `backend/src/Controller/LinkController.php` | New | POST create + GET redirect endpoints |
| `backend/src/Service/SlugGenerator.php` | New | Random slug generation with collision retry |
| `backend/src/Message/LinkVisited.php` | New | Async message carrying slug |
| `backend/src/MessageHandler/LinkVisitedHandler.php` | New | Worker handler: increment clicks, update TTL |
| `backend/config/packages/messenger.yaml` | Modified | Route `LinkVisited` to `async` transport |
| `backend/config/routes.yaml` | Modified | Add API + redirect routes |
| `docker-compose.yml` | Modified | Add `worker` service |
| `frontend/src/` | Modified | Dashboard components, API client, link list + form |
| `backend/tests/` | New | Unit (SlugGenerator), Integration (Entity), Functional (endpoints) |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Public API abuse (no rate limit) | Med | Out of MVP scope; document as follow-up |
| Slug collision under load | Low | 10-retry loop + exception on exhaustion |
| CORS in production | Med | Vite proxy covers dev; add Symfony CORS headers before deploy |
| RabbitMQ unavailable at redirect time | Low | Messenger retry policy; redirect still works if dispatch fails (fire-and-forget) |

## Rollback Plan

All changes are additive (new files, new routes, new service). Rollback = revert the PR. No database migrations to reverse (Doctrine `schema:create` on fresh DB). Worker container stops cleanly on `docker compose down`.

## Dependencies

- RabbitMQ connection configured in `.env` (`MESSENGER_TRANSPORT_DSN`)
- PostgreSQL database accessible from `php` and `worker` containers

## Success Criteria

- [ ] `POST /api/links` creates a link and returns short URL with slug
- [ ] `GET /{slug}` redirects (302) to original URL and dispatches `LinkVisited`
- [ ] Worker increments click counter within 5 seconds of redirect
- [ ] Dashboard shows all links with accurate click counts
- [ ] Expired links (30 days no clicks) return 410 on redirect, show as inactive on dashboard
- [ ] All PHPUnit suites pass; Playwright E2E covers create → redirect → dashboard flow
