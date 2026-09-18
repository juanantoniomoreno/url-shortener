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
Phase 2 delivery decision: Resolved — the maintainer explicitly accepted `size:exception`, so Phase 2 shipped as a single PR despite the 400-line budget. Forecast for the Phase 2 slice was 400–480 changed lines; actual delivery was 379 code+test lines. The cached `stacked-to-main` chain strategy was not applied to this slice. Delivered as PR #1, merged into `main` in merge commit `872c3e1`. The `size:exception` acceptance applied to Phase 2 only and does not pre-authorize Phase 3.

Phase 3 delivery decision: Resolved (2026-09-18) — the maintainer explicitly accepted `size:exception` for Phase 3, shipping it as a single PR. Slice forecast was ~425 code+test lines (dashboard read path ~210, create flow ~165, expiration display/refactor/manual evidence ~50); chained delivery as PR3a/PR3b/PR3c was offered and declined. The `size:exception` acceptance applies to Phase 3 only and does not pre-authorize any later change.
Phase 3 frontend test seam: Resolved (2026-09-18) — Playwright with `page.route` request fixtures only, covering loading, successful creation, and failed creation states. No Vitest/RTL and no new frontend devDependency, keeping the declared `frontend.runner: Playwright` in `openspec/config.yaml` authoritative.
Phase 3 scope finding: the `dashboard` spec's four requirements are already satisfied by the Phase 1 backend (`GET /api/links`, `POST /api/links`, `isExpired`, and `{error: {code, message}}` responses), so Phase 3 changes frontend code and frontend tests only — no PHP, migration, Messenger, or compose change.

The estimate includes a Doctrine entity and migration, API controllers and routes, Messenger message and handler, worker infrastructure, frontend state and API integration, and unit/integration/functional/E2E tests. The user approved smaller Phase 1 review units rather than accepting a `size:exception`; Phase 2 and Phase 3 remain separate delivery slices.

## Phase 1 — Domain and HTTP API (suggested PR 1)

> **Phase 1 closure status:** Formally closed as of 2026-09-16. All GREEN, RED, TRIANGULATE, and REFACTOR tasks for PR1a/PR1b/PR1c are complete and verified. At that closure no Phase 2 or Phase 3 implementation existed. Phase 2 has since been implemented, verified, and delivered as PR #1 (merge commit `872c3e1`), and Phase 3 has since been implemented on branch `feat/dashboard-browser-flow`; independent verification of the Phase 3 slice is the next phase.

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

> **Phase 2 delivery status:** Implemented, verified, and delivered as PR #1 (merge commit `872c3e1`). All 12 Phase 2 tasks below are complete. Phase 3 has since been implemented — its 9 tasks below are now complete.

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

> **Phase 3 delivery status:** Implemented on branch `feat/dashboard-browser-flow` on 2026-09-18 and delivered as ordered work-unit commits on that branch. All 9 Phase 3 tasks below are complete, with evidence inline and in `apply-progress.md` (including the tabular TDD Cycle Evidence table). Suite: 9/9 Playwright tests, zero flaky across three consecutive runs. Independent `sdd-verify` verification of this slice is still pending; the parent gate's determinism correction is recorded in the TRIANGULATE and REFACTOR evidence below and in `apply-progress.md`.

### RED — Frontend behavior

- [x] Replace the scaffold-only Playwright assertion in `frontend/tests/e2e/basic.spec.ts` with coverage for dashboard load, valid link creation, created short-link display, and API validation errors. Evidence: real RED run observed before implementation — 9/9 tests failed on contract against the scaffold (list items, empty/loading/error states, form labels, and alerts all missing), e.g. `expect(locator).toHaveCount(expected) failed / Received: 0` for list items and `element(s) not found` for `/no links yet/i` and `/loading/i`. Post-GREEN final suite: 9/9 passed in 6.7s. <!-- sdd-owner: implementation -->
- [x] Add focused frontend test seams or request fixtures for loading, successful creation, and failed creation states without coupling unit behavior to RabbitMQ internals. Evidence: `page.route("**/api/links")` fixtures in `frontend/tests/e2e/basic.spec.ts` cover loading (delayed fulfill), successful creation (201 fixture), failed creation (409 `slug_conflict`), list rendering, empty list, expired marker, and 500 load failure; no RabbitMQ coupling and no new dependencies (`package.json` untouched). <!-- sdd-owner: implementation -->

### GREEN — Frontend implementation

- [x] Add `frontend/src/api.js` with JSON-aware `GET /api/links` and `POST /api/links` wrappers that surface non-2xx errors. Evidence: `fetchLinks()` and `createLink(url, slug)` implemented; non-2xx responses throw an Error carrying the backend `error.code`/`error.message` contract plus HTTP status; the 409-fixture test asserts the exact backend message (`The requested slug is already in use.`) renders in the alert. <!-- sdd-owner: implementation -->
- [x] Implement the dashboard state and form in `frontend/src/App.jsx` (or focused components under `frontend/src/`) with loading, error, empty-list, create-success, click-count, and active/expired states. Evidence: `App.jsx` renders loading text, `role=alert` error text, `No links yet` empty state, prepended created link, `clicks: N` counters, and `active`/`expired` status labels; all six states are exercised by the fixture tests, which passed 9/9 after GREEN (8.3s run). <!-- sdd-owner: implementation -->
- [x] Keep the existing Vite API proxy and ensure the UI renders short URLs as links without introducing client-side routing or authentication. Evidence: `vite.config.js` and `nginx.conf` untouched (`/api` proxy preserved); short URLs render as `<a href={link.shortUrl}>` asserted by `toHaveAttribute("href", …)`; no router or auth code added. <!-- sdd-owner: implementation -->

### TRIANGULATE — Frontend evidence

- [x] Run `cd frontend && npm ci && npm run build` and record a successful production build. Evidence: `vite v5.4.21 building for production… ✓ 31 modules transformed`, output `dist/index.html 0.32 kB`, `dist/assets/index-wBYphdAB.js 145.14 kB │ gzip: 46.79 kB`, `✓ built in 2.06s`. <!-- sdd-owner: implementation -->
- [x] Start the application stack and run `cd frontend && npx playwright test`, recording the create-and-display and validation-error evidence. Evidence: full stack up (`postgres`/`rabbitmq` healthy, `php`, `nginx`, `frontend`, `worker`), static image rebuilt with `docker compose up -d --build frontend` before the run; `cd frontend && npx playwright test` → `9 passed (6.7s)` including the real-stack create-and-display test (custom slug `e2e-mu7fne6z`, zero clicks, active) and the real-stack validation-error test (`The URL must be an absolute HTTP or HTTPS URL.` shown, no list item added). Parent gate re-run later produced **`1 flaky, 8 passed (13.1s)`** in the fixture-side failed-creation test (`basic.spec.ts:171`, `Expected: 0 / Received: 1`), root-caused to two pre-load `count()` baselines; after both were made settle-safe (the fixture test keeps its snapshot but only behind a settle assertion on the seeded item; the real-stack test replaces its snapshot with an API-derived baseline), three consecutive zero-flaky runs passed: `9 passed (5.5s)`, `9 passed (7.0s)`, and `6 passed (13.4s)` at `--repeat-each=3`. <!-- sdd-owner: implementation -->
- [x] Perform one manual browser check of an expired link marker and an asynchronously updated click count after a redirect. Evidence (scripted browser via `frontend/tests/e2e/manual-check.mjs`, no route mocks): link `manualexp1` backdated with `docker compose exec -T postgres psql -U shortener -d shortener -c "UPDATE link SET updated_at = now() - interval '31 days' WHERE slug='manualexp1';"` → `UPDATE 1`; dashboard rendered `clicks: 0expired` (not `active`) for it. Link `manualclick1`: browser follow of `http://localhost:8080/manualclick1` → backend `302` to `https://example.com/manual/click-check` (confirmed with `curl -o /dev/null -w` → `redirect_status=302`), dashboard click count asynchronously updated `0 → 1` via the worker (`clicks: 1active` after poll), and a second redirect produced `clicks= 2` in `GET /api/links`. <!-- sdd-owner: implementation -->

### REFACTOR — Reviewable UI

- [x] Refactor the dashboard only after E2E passes: keep API calls isolated, avoid duplicated state transitions, and preserve accessible labels and error text. Evidence: after E2E passed, fixed a state-overlap defect where the `No links yet` empty state could render simultaneously with loading or error text (empty state now gated to `status.kind === "idle"`); list writes were already guarded against stale in-flight GET responses via a load-sequence ref. `npm run build` passes (`✓ built in 2.06s`) and the full Playwright suite stays green post-refactor: `9 passed (6.7s)`. Accessible labels (`Original URL`, `Custom slug`) and backend error text unchanged. The parent gate then found the residual determinism defect in the E2E suite (two pre-load `count()` snapshots; see the TRIANGULATE evidence above), reproduced it as `1 flaky`, and the fix made both sites settle-safe: the real-stack validation test replaced its imperative snapshot with an API-derived baseline and gained two assertions (the rendered count matches `GET /api/links`, and the rejected URL never appears in the list), while the fixture-side failed-creation test kept its `count()` snapshot behind an auto-waiting settle assertion on the seeded item and gained no new assertion there. `sdd-verify` audited this diff line by line and corrected an earlier overstatement in this record that claimed a snapshot was removed and an assertion added in both tests. <!-- sdd-owner: implementation -->

## Final Planning Gate

- [x] Re-read all capability specs and `design.md`, confirm every requirement has at least one implementation task and test path, and update task wording before any code is written. <!-- sdd-owner: implementation -->
- [x] Recalculate the changed-line forecast after the first implementation slice and stop for the delivery decision if the plan still exceeds the 400-line review budget. <!-- sdd-owner: implementation -->
