# Apply Progress — MVP URL Shortener

## Current status

Phase 1 / PR1 is formally closed as of 2026-09-16. The user approved re-slicing instead of accepting a review-budget size exception, and the approved review slices were delivered and verified:

- PR1a — domain persistence: entity, repository, and tests.
- PR1b — domain services: slug generation, expiration policy, and tests.
- PR1c — HTTP API: migration, routes, controller, and functional tests.

Phase 2 (Messenger/worker) is implemented and complete: all 12 Phase 2 tasks are checked off in `tasks.md`, and the Phase 2 slice was independently verified — `openspec/changes/mvp-url-shortener/verify-report.md` exists. The Phase 2 delivery decision was a maintainer-accepted `size:exception`, shipping Phase 2 as a single PR. Phase 3 (frontend `api.js`, dashboard state in `App.jsx`, Playwright E2E) has not started; its 9 tasks remain unchecked, so archive is not ready for the full change.

## Completed implementation tasks

The following Phase 1 implementation and contract-test tasks are complete in the worktree:

- Domain entity, repository, slug generator, and expiration policy.
- Unit and repository integration tests.
- Doctrine migration for the `link` table, unique slug constraint, timestamps, click counter, and created-at index.
- YAML routes for API create/list and catch-all redirect, with API routes declared first.
- `LinkController` for JSON parsing, URL/custom-slug validation, persistence, stable errors, list serialization, short URL generation, and `302`/`404`/`410` behavior.
- Functional tests for generated/custom slugs, empty and invalid input, reserved names, conflicts, response shape, ordering, redirects, unknown slugs, expiration, and route shadowing.

## Verification evidence

- PHP syntax checks pass for all new Phase 1 PHP source and test files.
- YAML parsing of `backend/config/routes.yaml` passes.
- `docker compose config --quiet` passes.
- Docker PHP 8.4.24 runtime includes the required database/XML/AMQP extensions.
- Symfony route debug, container lint, Doctrine mapping validation, migration Version20260909120000 execution, and post-migration schema validation pass.
- Initial PHPUnit execution exposed a test-infrastructure defect: DAMA's static transaction conflicted with `SchemaTool::createSchema()` in `DatabaseSchemaTestCase::setUpBeforeClass()`.
- `DatabaseSchemaTestCase` now temporarily disables DAMA static connections during idempotent schema drop/create and restores them for tests.
- PHPUnit passes in Docker: Integration 5/5 (9 assertions), Functional 17/17 (102 assertions), full suite 31/31 (121 assertions). Two consecutive full-suite runs also pass.
  *Note: an earlier evidence entry recorded 122 assertions; the closure entry is normalized to the latest observed result of 121 assertions.*
- Test configuration is aligned on `sqlite:////tmp/url-shortener-test.sqlite`; CI runs migrations and `doctrine:schema:validate --skip-sync` before PHPUnit instead of the redundant `doctrine:schema:create` step.
- PR1c is vertically split for delivery into schema migration, create/list API (including shared route declarations), and redirect API. The create/list unit is 357 added lines and the redirect unit is 102 added lines; shared functional helpers live in the test-harness unit.
- Final commit `ea081b2` is aligned with `origin/main`. Phase 1 / PR1 closure documentation reflects this pushed state.

## Phase 2 apply progress (2026-09-16)

Phase 2 (asynchronous click tracking and the dedicated Messenger worker) was implemented in strict TDD mode following Phase 1 closure. All 12 Phase 2 tasks are complete and checked off in `tasks.md`.

### Completed implementation tasks

RED — message and handler behavior:

- Handler unit suite `backend/tests/Unit/MessageHandler/LinkVisitedHandlerTest.php`: click increment, `updatedAt` refresh, repeat-visit increment, missing-link no-op, and (REFACTOR) no-op diagnostics logging.
- Functional extensions in `backend/tests/Functional/Controller/LinkControllerTest.php`: active redirect dispatches exactly one `LinkVisited` with the resolved slug; unknown-slug `404` and expired `410` dispatch nothing; broker publish failure logs an error and still returns `302` with the original URL and exactly one dispatch attempt.
- Integration assertion `backend/tests/Integration/Messenger/LinkVisitedRoutingTest.php`: dispatching `LinkVisited` through the real `messenger.bus.default` reaches the `async` sender (spy sender replaces `messenger.transport.async`).

GREEN — Messenger implementation:

- `backend/src/Message/LinkVisited.php`: `final readonly` message carrying the link slug.
- `backend/src/MessageHandler/LinkVisitedHandler.php`: `#[AsMessageHandler(method: 'handle')]`, loads by slug, `markVisited()` (clicks +1, `updatedAt` refreshed), flushes, safely ignores missing links, and (REFACTOR) logs the no-op at debug level.
- `backend/config/packages/messenger.yaml`: added `App\Message\LinkVisited: async` routing; existing `async`/`sync` transports preserved.
- `LinkRedirectController`: promoted `MessageBusInterface` and `LoggerInterface`; private `queueVisit()` wraps dispatch in `try/catch (Throwable)`, logging `error` with slug and reason while the `302` still returns. Broad `Throwable` catch is deliberate: the spec scenario requires redirects to stay available whenever publishing fails for any reason.

GREEN — worker infrastructure:

- `docker-compose.yml`: added the `worker` service reusing the `./backend` build context and Dockerfile, bind-mounting `./backend`, depending on healthy `postgres` and `rabbitmq`, inheriting `APP_ENV`/`MESSENGER_TRANSPORT_DSN`/`DATABASE_URL`, and running the bounded consumer `php bin/console messenger:consume async --time-limit=3600 --memory-limit=256M`.
- `backend/docker-entrypoint.sh` confirmed unchanged: migrations are gated behind `if [ "$1" = "php-fpm" ]` and the worker command (`php ...`) never triggers them; the script ends with `exec "$@"`.

TRIANGULATE — async evidence:

- Handler suite: RED 3 errors (class not found) → GREEN 3 tests/11 assertions → REFACTOR 4 tests/15 assertions.
- Functional suite: RED (0 dispatches on active redirect; 1 dispatch attempt expected vs 0 on broker failure) → GREEN 18 tests/97 assertions.
- Routing integration test: RED (spy sender received 0 envelopes before routing config) → GREEN 1 test/3 assertions.
- Full suite before REFACTOR: 39 tests/147 assertions. Full suite after REFACTOR: **40 tests/151 assertions, all green** (baseline was 31/121).
- `docker compose config --quiet` passes with the worker service present.
- Live-stack evidence (disposable local environment): started `worker` (cold image build ~10 min), created link `PG8vPbu` via `POST /api/links` (201, clicks 0), followed `GET /PG8vPbu` (302 → original URL), and the worker asynchronously incremented clicks `0 → 1` with `updatedAt` refreshed (21:06:02 → 21:06:08). A second redirect triangulated `1 → 2` (21:06:33). Worker logs confirm the bounded consumer started with the 256M/3600s limits. Worker was stopped after evidence collection; the pre-existing stack was left as found.

REFACTOR — reliability boundaries:

- Handler: added a debug-level diagnostics log for the missing-link no-op (RED test first, then GREEN). No event-level deduplication and no retry infrastructure added, per scope.
- Controller: reviewed publish-failure logging (error level, slug + exception reason); no further refactor needed.

### TDD Cycle Evidence

| Task | Test file | Layer | Safety net | RED | GREEN | TRIANGULATE | REFACTOR |
| ------ | ----------- | ------- | ------------ | ----- | ------- | ------------- | ---------- |
| Handler behavior | `tests/Unit/MessageHandler/LinkVisitedHandlerTest.php` | Unit | ✅ 31/31 | ✅ 3 errors (class not found) | ✅ 3/11 | ✅ 4 cases (increment, repeat visit, no-op, logged no-op) | ✅ 4/15 |
| Dispatch contracts | `tests/Functional/Controller/LinkControllerTest.php` | Functional | ✅ 31/31 | ✅ 0 dispatches vs 1 | ✅ 18/97 | ✅ 4 cases (active/unknown/expired/broker failure) | ➖ None needed |
| Routing to `async` | `tests/Integration/Messenger/LinkVisitedRoutingTest.php` | Integration | N/A (new) | ✅ 0 envelopes before routing | ✅ 1/3 | ➖ Single scenario | ➖ None needed |
| Worker infra | `docker-compose.yml` / entrypoint | Infra | N/A | ➖ Config-only | ✅ `config --quiet` + live worker | ✅ Live-stack 0→1→2 clicks | ➖ None needed |
| Reliability boundaries | handler diagnostics | Unit | ✅ 3/11 | ✅ debug not called | ✅ 4/15 | ✅ incl. full suite 40/151 | ✅ Clean |

### Deviations and discoveries

- Live-stack 500 during first evidence attempt: `EnvNotFoundException: DEFAULT_URI` — a latent Phase 1 environment gap (the `php` compose service never defined `DEFAULT_URI`; tests inject it via `phpunit.xml.dist`). Fixed by adding `DEFAULT_URI=http://localhost:8080` to the `php` and `worker` services in `docker-compose.yml` (within allowed edit surfaces). After nginx restart (stale cached upstream IP after the php recreation), the live flow worked end to end.
- The `worker` image required a one-time cold build (AMQP extension compiled from source; Docker layer cache was not reused).
- Functional dispatch tests use dedicated spy double classes (`RecordingMessageBusSpy`, `FailingMessageBusSpy`) plus a Monolog `TestHandler` against the real logger, with `$client->disableReboot()` and container overrides of `messenger.bus.default`/`message_bus` — no test-transport config files were needed.
- Handler entry point is `handle()` (declared in the RED test); wired via `#[AsMessageHandler(method: 'handle')]`.
- A stale pi-lens LSP flag (`Undefined type 'App\Message\LinkVisited'` at `LinkControllerTest.php` L237) persisted during the run despite repeated proof of ground truth: file exists on disk, `php -l` passes, `class_exists` returns true in the Docker runtime, the regenerated composer classmap maps it, the full suite executes behavioral assertions against the class, and the pi-lens delta cache itself reported 0 warnings after regeneration. Treated as a false positive of the stale LSP index; no code change was warranted.

### Phase 2 changed-line summary

Modified: `messenger.yaml` (+1), `LinkRedirectController.php` (+24), `LinkControllerTest.php` (+128), `docker-compose.yml` (+21). New: `LinkVisited.php` (21), `LinkVisitedHandler.php` (44), `LinkVisitedHandlerTest.php` (82), `LinkVisitedRoutingTest.php` (59). Total ≈ 380 changed lines (code+tests), inside the approved `size:exception` forecast of 400–480 and near the 400-line budget. Suggested single-PR work-unit commits: (1) message + handler + routing + tests; (2) controller dispatch wiring + functional tests; (3) worker service + compose validation.

### Phase 2 status

All 12 Phase 2 tasks are complete, and the Phase 2 slice was independently verified — `openspec/changes/mvp-url-shortener/verify-report.md` exists. Phase 3 (frontend `api.js`, dashboard state in `App.jsx`, Playwright E2E) has not been started and remains explicitly out of scope for this run.

## Phase 1 closure

- Phase 1 / PR1 is formally closed as of 2026-09-16.
- Closure verification: Docker PHP 8.4.24, PHPUnit 31/31 with 121 assertions, routes/schema/migration/container checks passing.
- Final review-slice boundaries: PR1a domain persistence, PR1b domain services, PR1c schema migration, PR1c create/list API, PR1c redirect API. Shared functional helpers remain in the test-harness unit.
- Pushed commit state: `ea081b2`, aligned with `origin/main`.
- No additional REFACTOR work was required; the post-test review found no duplicated expiration/serialization logic or clarity issues.
- At Phase 1 closure, no Phase 2 or Phase 3 implementation existed. Phase 2 (Messenger/worker) has since been implemented and independently verified (`openspec/changes/mvp-url-shortener/verify-report.md`); Phase 3 remains unstarted.

## Scope guard

This guard describes the state at the time of the Phase 1 record: no Messenger message/handler, transport routing, worker service, frontend API wrapper, dashboard, or browser-flow implementation was added. Phase 2 has since delivered the Messenger message, handler, `async` transport routing, worker service, and redirect dispatch wiring; the frontend API wrapper, dashboard, and browser-flow/E2E implementation remain unadded. Local generated and unrelated worktree artifacts remain outside scope.
