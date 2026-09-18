# Apply Progress — MVP URL Shortener

## Current status

Phase 1 / PR1 is formally closed as of 2026-09-16 (PR1a/PR1b/PR1c review slices delivered and verified). Phase 2 was implemented, verified, and delivered as PR #1 (merge commit `872c3e1`). Phase 3 (frontend `api.js`, dashboard state in `App.jsx`, Playwright E2E, manual browser checks) is now implemented and verified on branch `feat/dashboard-browser-flow` as of 2026-09-18 — all 9 Phase 3 tasks are checked in `tasks.md` with the Phase 3 section below recording the tabular TDD Cycle Evidence. Nothing is committed yet; the parent owns commits. Verify and archive are the next SDD phases.

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

Modified: `messenger.yaml` (+1), `LinkRedirectController.php` (+24), `LinkControllerTest.php` (+128), `docker-compose.yml` (+21). New: `LinkVisited.php` (21), `LinkVisitedHandler.php` (44), `LinkVisitedHandlerTest.php` (82), `LinkVisitedRoutingTest.php` (59). Total ≈ 380 changed lines (code+tests), inside the approved `size:exception` forecast of 400–480 and near the 400-line budget. Delivered as PR #1 in five commits: `77ff56a` message + handler + `async` routing + their tests, `24aab70` controller dispatch wiring + functional tests, `2d23e99` worker service + the `DEFAULT_URI` fix, `38398ae` OpenSpec artifacts and session tracker, `c09953a` local-artifact ignores.

### Phase 2 status

All 12 Phase 2 tasks are complete, and the Phase 2 slice was independently verified — `openspec/changes/mvp-url-shortener/verify-report.md` exists. Phase 3 (frontend `api.js`, dashboard state in `App.jsx`, Playwright E2E) was explicitly out of scope for this run and remained unstarted at the time of this record; it has since been implemented — see the Phase 3 apply progress section above.

### Phase 2 delivery record

- Delivered as PR #1 (`feat/async-click-tracking` → `main`), merged with merge commit `872c3e1`. The source branch was deleted after merge.
- Merge commit, not squash: the five commits keep their original SHAs in `main`'s history and `c09953a` remains an ancestor of `872c3e1`.
- 13 files, +690/−16. The merged tree was confirmed byte-identical to the verified commit `c09953a` (`git diff c09953a 872c3e1` is empty), so the verification evidence above applies unchanged to `main`.
- CI: the `backend` job already declared `amqp`, so the new suite needed no workflow change; the `docker` job now builds six services and pays a cold AMQP compile on the first run.
- The change is NOT archived. At the time of this record Phase 3's 9 unchecked tasks were the archive blocker, and the Phase 2 `size:exception` did not pre-authorize them. Phase 3 has since been implemented and all 39 tasks are now checked; the remaining gate is independent verification of the Phase 3 slice and then archive.

## Phase 3 apply progress (2026-09-18)

Phase 3 (dashboard and browser flow) was implemented in strict TDD mode on branch `feat/dashboard-browser-flow`, following the plan in `odd/tasks/phase-3-dashboard.md`. All 9 Phase 3 tasks are complete and checked off in `tasks.md`. Frontend only, as planned: no PHP, migration, Messenger, compose, or dependency-manifest change.

### Completed implementation tasks

RED — frontend behavior:

- `frontend/tests/e2e/basic.spec.ts` rewritten from the scaffold assertion to 9 dashboard tests across three describes: list rendering, create-link flow (both fixture-based), and the real-stack primary user flow.
- `page.route("**/api/links")` request fixtures for loading (delayed fulfill), successful creation (201), failed creation (409 `slug_conflict`), list rendering with differing click counts, empty list, expired marker, and 500 load failure.
- Real RED was captured before implementation: 9/9 tests failed on contract against the scaffold (missing list items, empty/loading/error text, form labels, alert), e.g. `expect(locator).toHaveCount(expected) failed / Received: 0` and `element(s) not found` for `/no links yet/i` and `/loading/i`.

GREEN — frontend implementation:

- `frontend/src/api.js` (new): `fetchLinks()` and `createLink(url, slug)`; every non-2xx throws an Error carrying the backend `error.code`/`error.message` contract plus HTTP status.
- `frontend/src/App.jsx`: dashboard with `LinkForm` (accessible `Original URL` / `Custom slug` labels, `Shorten` button) and `LinkItem` (short URL as `<a>`, original URL, `clicks: N`, `active`/`expired` labels); loading text, `role=alert` error text, and `No links yet` empty state; created links are prepended after success.
- Vite `/api` proxy and `nginx.conf` untouched; no client-side routing or authentication added.

### TDD Cycle Evidence

| Task | Test file | Layer | Safety net | RED | GREEN | TRIANGULATE | REFACTOR |
| ------ | ----------- | ------- | ------------ | ----- | ------- | ------------- | ---------- |
| Dashboard coverage replaces scaffold | `frontend/tests/e2e/basic.spec.ts` | E2E | N/A (only frontend runner) | ✅ 9/9 contract failures vs scaffold | ✅ 9/9 passed (8.3s) | ✅ 9/9 passed (6.7s) after final rebuild | ✅ still 9/9 passed (6.7s) post-refactor |
| Request fixtures (loading/create success/failure) | `frontend/tests/e2e/basic.spec.ts` | E2E | N/A | ✅ included in the 9 RED failures | ✅ fixture tests 7/7 | ✅ same run | ✅ No weakening of assertions |
| API wrappers | `frontend/src/api.js` | Frontend unit-under-E2E | ✅ E2E suite | ✅ alert/list assertions failed vs scaffold | ✅ 409 message asserted verbatim | ✅ real-stack 400 path (`not-a-url`) | ✅ Isolated in `api.js`, no duplication |
| Dashboard state/form | `frontend/src/App.jsx` | E2E | ✅ E2E 9/9 | ✅ same RED run | ✅ 9/9 | ✅ same run | ✅ Fixed empty/loading/error state overlap |
| Production build | `npm ci && npm run build` | Build | N/A | ➖ Config-only | ✅ build passes | ✅ `✓ 31 modules transformed`, `✓ built in 2.06s` | ✅ rebuilt post-refactor |
| Stack + real-stack E2E | `docker compose up -d --build frontend` + `npx playwright test` | E2E live | ✅ full suite | N/A | ✅ 9/9 (8.3s) | ✅ 9/9 (6.7s) with fresh image | ✅ clean |
| Manual expired marker + async click count | `frontend/tests/e2e/manual-check.mjs` (scripted browser, no mocks) | Browser | N/A | N/A | N/A | ✅ `expired` label via SQL backdate; clicks `0 → 1` (browser redirect via worker) → `2` (second redirect) | ➖ None needed |
| Gate correction (determinism) | `frontend/tests/e2e/basic.spec.ts` | E2E | ✅ full suite | ➖ Defect found by the parent gate, not a new RED | ➖ | ✅ Parent reproduced `1 flaky` and root-caused it to two pre-load `count()` snapshots | ✅ Both removed; `9 passed` twice plus `6 passed` at `--repeat-each=3`, zero flaky; two assertions added, none weakened |

### Verification commands and observed results

| Purpose | Command | Observed result |
| --- | --- | --- |
| Local test deps | `cd frontend && npm ci` | installed; manifests untouched |
| Playwright browser | `npx playwright install chromium` | Chrome Headless Shell 151.0.7922.34 downloaded (the `--with-deps` variant failed with exit code 1; plain install succeeded) |
| RED | `cd frontend && npx playwright test` (scaffold App) | `9 failed` on contract: 0 list items, missing `/no links yet/i`, `/loading/i`, `getByLabel(/original url/i)` timeout |
| GREEN | rebuild + `cd frontend && npx playwright test` | `9 passed (8.3s)` after two RED-diagnosed fixes |
| Production build | `cd frontend && npm ci && npm run build` | `✓ 31 modules transformed`, `dist/assets/index-wBYphdAB.js 145.14 kB │ gzip: 46.79 kB`, `✓ built in 2.06s` |
| Stack | `docker compose up -d postgres rabbitmq php nginx frontend worker` | postgres/rabbitmq healthy; php, nginx, frontend, worker up |
| Static image refresh | `docker compose up -d --build frontend` | rebuilt before every Playwright run (no source bind-mount) |
| Final E2E | `cd frontend && npx playwright test` | `9 passed (6.7s)` |
| Backdate expired link | `docker compose exec -T postgres psql -U shortener -d shortener -c "UPDATE link SET updated_at = now() - interval '31 days' WHERE slug='manualexp1';"` | `UPDATE 1` |
| Redirect | `curl -o /dev/null -w … http://localhost:8080/manualclick1` | `redirect_status=302 location=https://example.com/manual/click-check` |
| Async clicks | dashboard poll + `GET /api/links` | `manualclick1` `clicks 0 → 1` after browser redirect; `2` after a second redirect |
| Parent gate re-run (independent) | `cd frontend && npm run build`; `docker compose up -d --build frontend`; `cd frontend && npx playwright test` | ❌ **Gate failed:** `1 flaky, 8 passed (13.1s)` — `basic.spec.ts:171` expected 0 list items, received 1 |
| Post-correction determinism | `cd frontend && npx playwright test` (twice) | ✅ `9 passed (5.5s)` and `9 passed (7.0s)` — zero flaky in both |
| Post-correction repeat | `cd frontend && npx playwright test --grep "create-link flow" --repeat-each=3` | ✅ `6 passed (13.4s)` — zero flaky |

### RED-diagnosis fixes (test bugs and one app defect)

- Test bug: the expired-marker fixture did not override `shortUrl`, so the item text never contained the slug being filtered on; fixed by overriding `shortUrl` alongside `slug`.
- Test bug: real-stack tests raced the initial list load (`before` count taken at 0 items while a 3-item load was in flight). First fix attempt: wait for the loading indicator to hide before interacting. **That attempt was insufficient and the claim recorded here was wrong.** A `toBeHidden()` wait passes vacuously when the indicator has not rendered yet, so two tests still took an imperative `count()` snapshot before the mount-time GET settled: the fixture-side failed-creation test and the real-stack validation-error test. The parent gate caught the first as **flaky** on an independent re-run (`1 flaky, 8 passed (13.1s)`; `Expected: 0 / Received: 1` at `basic.spec.ts:171`, where the `count()` at line 163 had captured 0 mid-load). Both sites were corrected by removing the pre-load snapshot: the fixture test now waits on the seeded item (`toHaveCount(1)`) before snapshotting, and the real-stack test derives its baseline from `GET /api/links` and asserts `toHaveCount(apiLinks.length)`, which auto-waits. Both tests gained an assertion (the rejected URL must not appear) rather than losing one. Verified by three consecutive zero-flaky runs: `9 passed (5.5s)`, `9 passed (7.0s)`, and `6 passed (13.4s)` with `--repeat-each=3`.
- App defect (race): a create succeeded (`POST 201`) while the mount-time GET was still in flight; the late GET response clobbered the prepended link. Fixed with a load-sequence ref — every list write invalidates older in-flight loads. This matches the design's "prepend or refresh the created link after success" without adding retry infrastructure.

### REFACTOR — reviewable UI

- Fixed the flagged state overlap: the `No links yet` empty state previously rendered alongside loading text and error alerts; it is now gated to `status.kind === "idle"` so only one list-state message shows at a time. Comment explains the invariant.
- API calls remain isolated in `api.js`; no duplicated state transitions; accessible labels and backend error text preserved.
- Post-refactor: production build passes and the full Playwright suite is green (`9 passed (6.7s)`).

### Findings and deviations

- `shortUrl` host depends on the serving proxy: the frontend nginx passes `Host $host`, which strips the port, so the backend generates `http://localhost/<slug>` instead of `http://localhost:8080/<slug>` through the static container. The Vite dev proxy (port 8080) preserves the port. Backend behavior is out of Phase 3 scope (no backend edits allowed), so the E2E asserts the UI renders the API-returned `shortUrl` (`href` ends with `/<slug>`) rather than hardcoding a host. Flagged for the parent as a future backend/proxy decision.
- `example.com` returns HTTP 404 for non-root paths, so the browser redirect evidence shows a final 404 from the target site after the backend's `302`; the `302` itself was confirmed separately with `curl`.
- Local `node_modules` were absent, so `npm ci` plus `npx playwright install chromium` were needed once before the first Playwright run; `frontend/package.json` and `package-lock.json` remain untouched.
- An external abort interrupted one `docker compose up -d --build frontend && npx playwright test` run; the worktree was re-verified and all steps re-run to completion. Nothing invented.

### Phase 3 changed-line summary

New: `frontend/src/api.js` (45 lines), `frontend/tests/e2e/basic.spec.ts` (225 lines), `frontend/tests/e2e/manual-check.mjs` (44 lines). Modified: `frontend/src/App.jsx` (+123/−1 vs scaffold). Total ≈ 434 code+test added lines after the gate correction — marginally above the ~425 forecast and inside an accepted `size:exception` range (Phase 2's accepted range was 400–480). Single PR; no chained slices. Suggested work-unit commits: (1) `feat(dashboard): api wrappers + list/create dashboard with fixture E2E` (`api.js`, `App.jsx`, `basic.spec.ts`), (2) `test(dashboard): scripted manual check for expired marker and async click count` (`manual-check.mjs`), (3) OpenSpec artifacts (`tasks.md`, `apply-progress.md`) — rollback boundary for each unit is the file set above; unit 1 is the only behavior-bearing unit and reverts cleanly alone.

### Phase 3 status

All 9 Phase 3 tasks are complete. The change's 39/39 tasks are now implementation-complete; verify-report and archive decisions belong to the next SDD phases. Nothing has been committed or pushed — the parent owns commits.

### Gate correction (2026-09-18)

The parent gatekeeper independently re-ran the build and the full E2E suite instead of accepting this record at face value, and the run was **not clean**: `1 flaky, 8 passed (13.1s)` in the fixture-side failed-creation test. Root cause: two tests still took an imperative `count()` baseline before the mount-time GET settled, and the recorded claim that the race had been fixed was true only for the two real-stack create/validation tests. The correction is recorded in the RED-diagnosis section and the TDD Cycle Evidence table above; three consecutive zero-flaky runs (two full suites plus a `--repeat-each=3` fixture repetition) back it.

The correction was applied by the parent, not by a phase rerun: after the tasks reached 39/39, native status moved to `apply: all_done` with `next_recommended: archive`, and the SDD selection gate hard-blocked a phase-`apply` executor (`SDD selection blocked: SDD selection native status blocks phase apply`). The edit stayed inside the phase's authorized edit surfaces (`frontend/tests/e2e/**` plus these artifacts).

## Phase 1 closure

- Phase 1 / PR1 is formally closed as of 2026-09-16.
- Closure verification: Docker PHP 8.4.24, PHPUnit 31/31 with 121 assertions, routes/schema/migration/container checks passing.
- Final review-slice boundaries: PR1a domain persistence, PR1b domain services, PR1c schema migration, PR1c create/list API, PR1c redirect API. Shared functional helpers remain in the test-harness unit.
- Pushed commit state: `ea081b2`, aligned with `origin/main`.
- No additional REFACTOR work was required; the post-test review found no duplicated expiration/serialization logic or clarity issues.
- At Phase 1 closure, no Phase 2 or Phase 3 implementation existed. Phase 2 (Messenger/worker) has since been implemented and independently verified (`openspec/changes/mvp-url-shortener/verify-report.md`), and Phase 3 has since been implemented on branch `feat/dashboard-browser-flow` (see the Phase 3 section above); independent verification of the Phase 3 slice is the next SDD phase.

## Scope guard

Historical note from the Phase 1 record: no Messenger message/handler, transport routing, worker service, frontend API wrapper, dashboard, or browser-flow implementation existed at that time. Phase 2 subsequently delivered the Messenger message, handler, `async` transport routing, worker service, and redirect dispatch wiring. Phase 3 has now delivered the frontend API wrapper (`src/api.js`), the dashboard (`src/App.jsx`), Playwright E2E coverage (`tests/e2e/basic.spec.ts`), and the scripted manual browser check (`tests/e2e/manual-check.mjs`). Local generated and unrelated worktree artifacts remain outside scope.
