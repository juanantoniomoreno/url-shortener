# Phase 2 — Asynchronous Tracking and Worker

## Goal

Implement Phase 2 of the `mvp-url-shortener` SDD change: publish `LinkVisited` from the redirect path, consume it in a dedicated Messenger worker, and keep redirects available when the broker fails.

## Source of truth

The authoritative task list is `openspec/changes/mvp-url-shortener/tasks.md`, Phase 2 section (12 pending tasks). Specs live in `openspec/changes/mvp-url-shortener/specs/click-tracking/spec.md`; the design section is "Asynchronous Click Tracking" plus "Docker Worker" in `design.md`. This file is a session-level resume pointer, not a second task authority.

## Scope

- `backend/src/Message/LinkVisited.php`
- `backend/src/MessageHandler/LinkVisitedHandler.php`
- `backend/config/packages/messenger.yaml`
- `backend/src/Controller/LinkRedirectController.php`
- `backend/tests/Unit/MessageHandler/LinkVisitedHandlerTest.php`
- `backend/tests/Functional/Controller/LinkControllerTest.php`
- routing assertion test for the `async` transport
- `worker` service in `docker-compose.yml`
- `openspec/changes/mvp-url-shortener/tasks.md` and `apply-progress.md`

## Non-goals

- Do not implement Phase 3: no frontend API wrapper, dashboard state, or Playwright E2E changes.
- Do not add event-level deduplication, retry infrastructure, rate limiting, or authentication.
- Do not modify pre-existing or generated local artifacts (`.pi/`, `.codegraph/`, `.engram/`, `.atl/`).
- Do not commit or push unless separately authorized.

## Session decisions

- Execution mode: interactive. Delivery strategy: `exception-ok` (maintainer accepted `size:exception` for the 400–480 line Phase 2 slice).
- Chain strategy cached as `stacked-to-main`, unused because Phase 2 ships as one PR.
- Artifact store: hybrid — OpenSpec files plus an Engram mirror under `sdd/mvp-url-shortener/*`.
- Strict TDD is active; runner is `php vendor/bin/phpunit` executed in the Docker PHP 8.4 runtime.

## Tasks

- [x] RED: handler unit test, functional dispatch/failure assertions, and `async` routing assertion.
- [x] GREEN: `LinkVisited` message and `LinkVisitedHandler`, with messenger routing.
- [x] GREEN: non-blocking publication with logging in the redirect controller.
- [x] GREEN: `worker` service in `docker-compose.yml` and entrypoint confirmation.
- [x] TRIANGULATE: focused PHPUnit evidence plus `docker compose config --quiet`.
- [x] TRIANGULATE: live stack check that a redirect increments clicks asynchronously.
- [x] REFACTOR: failure diagnostics without out-of-scope retry or deduplication.

## Verified outcome

- PHPUnit 40/40 with 151 assertions on Docker PHP 8.4.24, re-run and confirmed by the parent, not only reported by the writer.
- `LinkVisited` routes to `async`; `LinkVisitedHandler` uses the Phase 1 `Link::markVisited()` seam, so no entity change or migration was needed.
- Live stack evidence: redirect `302`, worker increments clicks `0→1→2` with `updatedAt` refreshed.
- Phase 3 remains untouched: 9 unchecked tasks in `tasks.md`.
- Scope addition beyond the Phase 2 task list: `DEFAULT_URI=http://localhost:8080` added to the `php` service as well as `worker`, fixing a latent Phase 1 live-stack gap (`EnvNotFoundException` on the first real redirect).
