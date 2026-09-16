# MVP URL Shortener Implementation Tasks

## Approved Review Slices

The user approved re-slicing Phase 1 instead of accepting a `size:exception` over the 400-line review budget. Keep these as separate reviewable work units:

    - **PR1a — Domain persistence:** `Link` entity, `LinkRepository`, and their tests.
    - **PR1b — Domain services:** `SlugGenerator`, `LinkExpirationPolicy`, and their tests.
    - **PR1c — HTTP API:** vertically split into three behavior units:
      - **PR1c-schema:** Doctrine migration for the `Link` table.
      - **PR1c-create-list:** shared API/catch-all route declarations, `LinkController` create/list actions, and create/list functional tests.
      - **PR1c-redirect:** `LinkRedirectController` and redirect-focused functional tests.

    Do not begin Phase 2 or Phase 3 until the Phase 1 slices are independently verified.

## Review Workload Forecast

| Field | Value |
| --- | --- |
| Estimated changed lines | 650–850 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR1a: domain persistence → PR1b: domain services → PR1c: HTTP API → PR2: async tracking/worker → PR3: dashboard/E2E |
| Delivery strategy | ask-on-risk; user approved re-slicing instead of a size exception |
| Chain strategy | stacked-to-main for the broader three-slice delivery |

Decision needed before apply: Resolved — re-slice Phase 1 into PR1a/PR1b/PR1c
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High
Phase 2 delivery decision: Resolved — the maintainer explicitly accepted `size:exception`, so Phase 2 ships as a single PR despite the 400-line budget. Forecast for the Phase 2 slice is 400–480 changed lines. The cached `stacked-to-main` chain strategy is not applied to this slice. The `size:exception` acceptance applies to Phase 2 only and does not pre-authorize Phase 3.

The estimate includes a Doctrine entity and migration, API controllers and routes, Messenger message and handler, worker infrastructure, frontend state and API integration, and unit/integration/functional/E2E tests. The user approved smaller Phase 1 review units rather than accepting a `size:exception`; Phase 2 and Phase 3 remain separate delivery slices.

## Phase 1 — Domain and HTTP API (suggested PR 1)

> **Phase 1 closure status:** Formally closed as of 2026-09-16. All GREEN, RED, TRIANGULATE, and REFACTOR tasks for PR1a/PR1b/PR1c are complete and verified. No implementation exists for Phase 2 or Phase 3; Phase 2 is the next workstream.

### RED — Domain behavior

- [x] Add `backend/tests/Unit/Service/SlugGeneratorTest.php` covering seven-character alphanumeric output, reserved names, collision retry, and the ten-attempt exhaustion failure. Use a deterministic candidate seam so tests do not depend on random chance. <!-- sdd-owner: implementation -->
- [x] Add `backend/tests/Unit/Service/LinkExpirationPolicyTest.php` covering active, expired, and boundary-safe timestamps. <!-- sdd-owner: implementation -->
- [x] Add `backend/tests/Integration/Repository/LinkRepositoryTest.php` covering slug lookup, uniqueness, and newest-first ordering against the configured test database. <!-- sdd-owner: implementation -->

### GREEN — Domain persistence and services

- [x] Implement `backend/src/Domain/Entity/Link.php` with strict types, Doctrine attributes, unique slug mapping, click counter, and immutable timestamps. <!-- sdd-owner: implementation -->
- [x] Implement `backend/src/Repository/LinkRepository.php` with slug lookup, existence, and creation-time ordering queries. <!-- sdd-owner: implementation -->
- [x] Implement `backend/src/Service/SlugGenerator.php` with secure seven-character generation, reserved-name rejection, uniqueness checks, and a ten-attempt cap. <!-- sdd-owner: implementation -->
- [x] Implement `backend/src/Service/LinkExpirationPolicy.php` with the thirty-day `updatedAt` rule and no persistence side effects. <!-- sdd-owner: implementation -->
- [x] Generate and review the Doctrine migration under `backend/migrations/` for the `Link` table, indexes, timestamp columns, and unique slug constraint. <!-- sdd-owner: implementation -->

### RED — HTTP contracts

- [x] Add `backend/tests/Functional/Controller/LinkControllerTest.php` covering create success, generated/custom slugs, invalid input, slug conflicts, list ordering, active redirects, unknown slugs, and expired links. <!-- sdd-owner: implementation -->
- [x] Add assertions that API responses contain the documented fields and consistent JSON error codes without exposing internal exceptions. <!-- sdd-owner: implementation -->

### GREEN — HTTP API

- [x] Create `backend/config/routes.yaml` with named API create/list routes and the slug redirect route in catch-all-safe order. <!-- sdd-owner: implementation -->
- [x] Implement `backend/src/Controller/LinkController.php` with JSON parsing, URL/custom-slug validation, entity persistence, response mapping, list ordering, expiration handling, and `302`/`404`/`410` behavior. <!-- sdd-owner: implementation -->
- [x] Implement short-URL generation from the named redirect route and the configured application base URI. <!-- sdd-owner: implementation -->

### TRIANGULATE — Backend API evidence

- [x] Run `cd backend && php vendor/bin/phpunit` with the project test configuration and record evidence for unit, integration, and functional suites. Evidence: Docker PHP 8.4.24, PHPUnit 31/31 with 121 assertions. <!-- sdd-owner: implementation -->
- [x] Run the Symfony route and schema checks in the project runtime, confirm the migration applies cleanly, and verify that the catch-all redirect route does not shadow the API endpoints. Evidence: routes, Doctrine mapping/schema, migration Version20260909120000, container lint, and schema checks pass. <!-- sdd-owner: implementation -->

### REFACTOR — Domain/API clarity

- [x] Refactor only after tests pass: keep controller mapping and validation readable, preserve strict types and promoted constructor injection, and remove duplicated expiration/serialization logic. Review conclusion: no additional refactor was required; the implementation is clear and free of duplicated expiration/serialization logic. <!-- sdd-owner: implementation -->

## Phase 2 — Asynchronous Tracking and Worker (suggested PR 2)

### RED — Message and handler behavior

- [x] Add `backend/tests/Unit/MessageHandler/LinkVisitedHandlerTest.php` covering click increment, `updatedAt` refresh, and no-op handling for a missing link. <!-- sdd-owner: implementation -->
- [x] Extend `backend/tests/Functional/Controller/LinkControllerTest.php` to assert that active redirects dispatch `LinkVisited`, rejected redirects do not dispatch it, and broker publish failures do not break the `302` response. <!-- sdd-owner: implementation -->
- [x] Add an integration assertion that `LinkVisited` is routed to the `async` transport. <!-- sdd-owner: implementation -->

### GREEN — Messenger implementation

- [x] Implement immutable `backend/src/Message/LinkVisited.php` carrying the link slug. <!-- sdd-owner: implementation -->
- [x] Implement `backend/src/MessageHandler/LinkVisitedHandler.php` to increment clicks, refresh `updatedAt`, flush changes, and safely ignore missing links. <!-- sdd-owner: implementation -->
- [x] Update `backend/config/packages/messenger.yaml` to route `App\\Message\\LinkVisited` to `async` while preserving existing transports. <!-- sdd-owner: implementation -->
- [x] Wrap asynchronous publication in the controller with logging and non-blocking transport-failure handling. <!-- sdd-owner: implementation -->

### GREEN — Worker infrastructure

- [x] Add the `worker` service to `docker-compose.yml` with the PHP image, health-checked PostgreSQL/RabbitMQ dependencies, matching environment, and bounded Messenger consumer command. <!-- sdd-owner: implementation -->
- [x] Confirm `backend/docker-entrypoint.sh` runs migrations only for `php-fpm` and leaves the worker command untouched. <!-- sdd-owner: implementation -->

### TRIANGULATE — Async evidence

- [x] Run the handler and functional PHPUnit tests, then validate `docker compose config --quiet` with the worker service present. <!-- sdd-owner: implementation -->
- [x] Start the required Docker services in a disposable local environment, create a link, follow its redirect, and verify that the worker increments clicks asynchronously. <!-- sdd-owner: implementation -->

### REFACTOR — Reliability boundaries

- [x] Refactor logging and message handling for clear failure diagnostics without adding event-level deduplication or out-of-scope retry infrastructure. <!-- sdd-owner: implementation -->

## Phase 3 — Dashboard and Browser Flow (suggested PR 3)

### RED — Frontend behavior

- [ ] Replace the scaffold-only Playwright assertion in `frontend/tests/e2e/basic.spec.ts` with coverage for dashboard load, valid link creation, created short-link display, and API validation errors. <!-- sdd-owner: implementation -->
- [ ] Add focused frontend test seams or request fixtures for loading, successful creation, and failed creation states without coupling unit behavior to RabbitMQ internals. <!-- sdd-owner: implementation -->

### GREEN — Frontend implementation

- [ ] Add `frontend/src/api.js` with JSON-aware `GET /api/links` and `POST /api/links` wrappers that surface non-2xx errors. <!-- sdd-owner: implementation -->
- [ ] Implement the dashboard state and form in `frontend/src/App.jsx` (or focused components under `frontend/src/`) with loading, error, empty-list, create-success, click-count, and active/expired states. <!-- sdd-owner: implementation -->
- [ ] Keep the existing Vite API proxy and ensure the UI renders short URLs as links without introducing client-side routing or authentication. <!-- sdd-owner: implementation -->

### TRIANGULATE — Frontend evidence

- [ ] Run `cd frontend && npm ci && npm run build` and record a successful production build. <!-- sdd-owner: implementation -->
- [ ] Start the application stack and run `cd frontend && npx playwright test`, recording the create-and-display and validation-error evidence. <!-- sdd-owner: implementation -->
- [ ] Perform one manual browser check of an expired link marker and an asynchronously updated click count after a redirect. <!-- sdd-owner: implementation -->

### REFACTOR — Reviewable UI

- [ ] Refactor the dashboard only after E2E passes: keep API calls isolated, avoid duplicated state transitions, and preserve accessible labels and error text. <!-- sdd-owner: implementation -->

## Final Planning Gate

- [x] Re-read all capability specs and `design.md`, confirm every requirement has at least one implementation task and test path, and update task wording before any code is written. <!-- sdd-owner: implementation -->
- [x] Recalculate the changed-line forecast after the first implementation slice and stop for the delivery decision if the plan still exceeds the 400-line review budget. <!-- sdd-owner: implementation -->
