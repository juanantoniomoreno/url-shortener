# Verify Report — MVP URL Shortener (Phase 2 + Phase 3 slices)

- **Change**: `mvp-url-shortener`
- **This file covers two independently verified slices**: Phase 2 (verified 2026-09-16, below) and Phase 3 — Dashboard and Browser Flow (verified 2026-09-18, in the [Phase 3 verification](#phase-3-verification--dashboard-and-browser-flow-slice) section at the end of this file).
- **Phase 3 slice result**: ✅ **PASS with warnings** — see the Phase 3 section.
- **Full-change archive**: ✅ **READY** — 39/39 tasks checked; both slices independently verified (see Phase 3 section for the current verdict and follow-ups).

> **Historical note (2026-09-18):** the Phase 2 section below is preserved verbatim as written on 2026-09-16. Its "9 Phase 3 tasks remain unchecked" claims were true then and are historical now: Phase 3 was implemented on `feat/dashboard-browser-flow` and is verified in the final section of this file.

---

## Phase 2 Verification (2026-09-16, preserved)

- **Change**: `mvp-url-shortener`
- **Verification scope**: Phase 2 only — asynchronous click tracking and the dedicated Messenger worker (12 tasks under `## Phase 2 — Asynchronous Tracking and Worker`)
- **Verification date**: 2026-09-16
- **Slice result**: ✅ **PASS** (Phase 2 slice) — with warnings listed below
- **Full-change archive (as of 2026-09-16)**: ❌ **NOT READY** — 9 Phase 3 implementation tasks remained unchecked (historical; resolved since)

---

## Executive summary

The Phase 2 slice is genuinely complete and its artifacts are truthful. Every numeric claim in `apply-progress.md` was re-executed and matched exactly: full suite **40 tests / 151 assertions** green, per-suite Unit 13/26, Integration 6/12, Functional 21/113, handler file 4/15, routing file 1/3, functional file 18/97. The live async loop was independently re-verified against the running Docker stack: a fresh redirect returned `302` with the correct `Location`, and the worker asynchronously incremented clicks `2 → 3` with `updatedAt` refreshed. All 12 Phase 2 tasks are checked and backed by real, behavioral tests; assertion quality is high with no tautologies, ghost loops, or smoke tests. The `size:exception` acceptance is explicitly recorded in `tasks.md`, the actual diff is ~379–380 code+test lines (inside the accepted 400–480 forecast), and the `DEFAULT_URI` compose addition is a maintainer-approved fix of a latent Phase 1 gap, correctly justified by `phpunit.xml.dist` masking. Strict TDD evidence is present and verifiable but in narrative form rather than the required tabular `TDD Cycle Evidence` format (WARNING). Archive of the full change remains blocked by the 9 unchecked Phase 3 tasks.

---

## Verification commands and exact results

All commands run from the repo root; PHPUnit inside the running Docker `php` service (PHP 8.4.24).

| Command | Result |
| --- | --- |
| `docker compose exec -T php php vendor/bin/phpunit` | ✅ `OK (40 tests, 151 assertions)` — matches apply-progress post-REFACTOR claim exactly |
| `docker compose exec -T php php vendor/bin/phpunit --testsuite Unit` | ✅ `OK (13 tests, 26 assertions)` |
| `docker compose exec -T php php vendor/bin/phpunit --testsuite Integration` | ✅ `OK (6 tests, 12 assertions)` |
| `docker compose exec -T php php vendor/bin/phpunit --testsuite Functional` | ✅ `OK (21 tests, 113 assertions)` |
| `docker compose exec -T php php vendor/bin/phpunit tests/Unit/MessageHandler/LinkVisitedHandlerTest.php` | ✅ `OK (4 tests, 15 assertions)` — matches claimed REFACTOR 4/15 |
| `docker compose exec -T php php vendor/bin/phpunit tests/Integration/Messenger/LinkVisitedRoutingTest.php` | ✅ `OK (1 test, 3 assertions)` — matches claimed GREEN 1/3 |
| `docker compose exec -T php php vendor/bin/phpunit tests/Functional/Controller/LinkControllerTest.php` | ✅ `OK (18 tests, 97 assertions)` — matches claimed GREEN 18/97 |
| `docker compose config --quiet` | ✅ exit 0, no errors (worker service present) |
| Live loop: `docker compose up -d worker` → `GET /PG8vPbu` (302 → original URL) → `GET /api/links` | ✅ Worker consumed `async` with the `256M`/`3600s` bounds logged; clicks `2 → 3`, `updatedAt` `21:06:33 → 21:17:43`. Worker stopped again; stack left as found. |

No verification command failed. No evidence was unavailable.

---

## Spec coverage — `click-tracking` (all 3 requirements, all 7 scenarios)

| Scenario | Coverage | Evidence |
| --- | --- | --- |
| Queue a visit after an active redirect | ✅ | `test_active_redirect_dispatches_one_link_visited_message`: 302 + exactly one `LinkVisited` with resolved slug, spy bus + `disableReboot()`; routing test proves async transport, and the controller has no DB-write path |
| Do not queue rejected visits | ✅ | `test_unknown_slug_redirect_does_not_dispatch_link_visited` (404, no dispatch) + `test_expired_link_redirect_does_not_dispatch_link_visited` (410, no dispatch) |
| Keep redirects available when the broker is unavailable | ✅ | `test_broker_publish_failure_is_logged_and_does_not_break_the_redirect`: `FailingMessageBusSpy` throws on every attempt; test asserts 302, original `Location`, exactly 1 attempt, and an error log containing `click tracking` |
| Increment a link from a consumed message | ✅ | Handler unit tests 1 & 2 (clicks 0→1 and 1→2, `updatedAt` refreshed) + independent live re-verification (clicks 2→3) |
| Ignore a visit for a deleted link | ✅ | `test_missing_link_is_ignored_without_creating_or_modifying_anything` (no flush, no persist, no exception) + `test_missing_link_no_op_is_logged_for_diagnostics` |
| Start the worker with the application stack | ✅ | `docker compose config --quiet` passes; live run: worker consumed `async` with bounded limits while `php`/`nginx` continued serving HTTP and API |
| Preserve migration ownership | ✅ | `backend/docker-entrypoint.sh`: migrations gated behind `if [ "$1" = "php-fpm" ]`, ends with `exec "$@"`; worker command starts with `php`, so it never takes the migration branch. Live worker startup logs show direct consumer start with no migration output |

Affected `link-management` scenarios also verified: "Redirect an active link" (302, Location = original URL, one dispatch) and "Return not found for an unknown slug" (404, no dispatch) — both covered by functional tests. "Keep a recently visited link active" is supported by the handler's `updatedAt` refresh plus the Phase 1 expiration policy.

Minor coverage note (SUGGESTION, not blocking): the spec scenario element "the persisted click count is unchanged before the worker handles the message" is guaranteed by architecture (message routed to `async`; controller performs no write) but has no direct test assertion on the DB counter immediately after a redirect.

---

## Task completion status

### Phase 2 — Asynchronous Tracking and Worker: 12/12 complete ✅

All 12 Phase 2 tasks (RED 3, GREEN Messenger 4, GREEN worker 2, TRIANGULATE 2, REFACTOR 1) are checked `- [x]` in `tasks.md` and each is backed by verified code, tests, or recorded evidence as audited above.

### Phase 3 — Dashboard and Browser Flow: 0/9 complete ❌ (remaining scope — archive blocker for the full change)

The 9 unchecked implementation tasks, exactly as they appear in `tasks.md`:

```markdown
- [ ] Replace the scaffold-only Playwright assertion in `frontend/tests/e2e/basic.spec.ts` with coverage for dashboard load, valid link creation, created short-link display, and API validation errors. <!-- sdd-owner: implementation -->
- [ ] Add focused frontend test seams or request fixtures for loading, successful creation, and failed creation states without coupling unit behavior to RabbitMQ internals. <!-- sdd-owner: implementation -->
- [ ] Add `frontend/src/api.js` with JSON-aware `GET /api/links` and `POST /api/links` wrappers that surface non-2xx errors. <!-- sdd-owner: implementation -->
- [ ] Implement the dashboard state and form in `frontend/src/App.jsx` (or focused components under `frontend/src/`) with loading, error, empty-list, create-success, click-count, and active/expired states. <!-- sdd-owner: implementation -->
- [ ] Keep the existing Vite API proxy and ensure the UI renders short URLs as links without introducing client-side routing or authentication. <!-- sdd-owner: implementation -->
- [ ] Run `cd frontend && npm ci && npm run build` and record a successful production build. <!-- sdd-owner: implementation -->
- [ ] Start the application stack and run `cd frontend && npx playwright test`, recording the create-and-display and validation-error evidence. <!-- sdd-owner: implementation -->
- [ ] Perform one manual browser check of an expired link marker and an asynchronously updated click count after a redirect. <!-- sdd-owner: implementation -->
- [ ] Refactor the dashboard only after E2E passes: keep API calls isolated, avoid duplicated state transitions, and preserve accessible labels and error text. <!-- sdd-owner: implementation -->
```

These 9 unchecked tasks are **known remaining scope** (the maintainer scoped this run to Phase 2 only). They are not an implementation or TDD failure of the Phase 2 work, and no Phase 3 implementation was attempted or leaked into this slice (confirmed: `frontend/` untouched in the worktree). However, per the completeness contract, the full-change archive is **not ready** while these remain unchecked.

---

## Structured status and actionContext findings

- Native status consumed: change `mvp-url-shortener`, state `ready`, nextRecommended `apply`, no blocked reasons. Verification is optional and grants no edit authority; this report does not change the recommended action.
- `actionContext`: mode `repo-local`, workspaceRoot `/home/juan/Proyectos/url-shortener`, allowedEditRoots `["/home/juan/Proyectos/url-shortener"]`. All implementation ownership verified inside the authoritative workspace. No blockers.
- This report was written only to `openspec/changes/mvp-url-shortener/verify-report.md`; no other file was modified.

---

## Strict TDD compliance

`openspec/config.yaml` declares `strict_tdd: true` with `apply.test_command: php vendor/bin/phpunit`. The strict-TDD verify support module was loaded and followed.

| Check | Result | Details |
| --- | --- | --- |
| TDD evidence reported | ✅ (with format WARNING) | Phase 2 evidence is present in `apply-progress.md` as structured RED→GREEN→TRIANGULATE→REFACTOR narrative per suite, **not** as a tabular `TDD Cycle Evidence` table. The substance is complete and verifiable; the format deviates from the expected table. |
| All tasks have tests | ✅ | All 12 Phase 2 tasks map to real test files or executable evidence; 3/3 reported RED test files exist in the codebase |
| RED confirmed (tests exist) | ✅ | `LinkVisitedHandlerTest.php`, `LinkControllerTest.php` dispatch extensions, `LinkVisitedRoutingTest.php` all exist and were executed |
| GREEN confirmed (tests pass) | ✅ | Every reported count re-executed and matched exactly (see commands table) |
| Triangulation adequate | ✅ | Handler: 4 cases incl. repeat-visit increment (0→1, 1→2) and missing-link no-op + logging; routing: spy sender asserts count, class, and slug; functional: dispatch / no-dispatch (404, 410) / broker-failure variance. Live loop triangulated 0→1→2 (apply) and 2→3 (verify) |
| Safety net for modified files | ✅ | Phase 1 baseline 31/121 recorded and green; apply recorded full-suite 39/147 pre-REFACTOR and 40/151 post-REFACTOR; modified `LinkControllerTest.php` extended on a green Phase 1 base |

**TDD compliance**: 6/6 substance checks passed; 1 format WARNING.

### RED claim audit (checked against the test files, not taken at face value)

- "Handler suite RED: 3 errors (class not found)" — **credible**. The RED tests reference `App\Message\LinkVisited` and `App\MessageHandler\LinkVisitedHandler`, both created only in this slice; before GREEN, PHPUnit necessarily errors with class-not-found. The 3 RED tests map exactly to the 3 initial handler behaviors required by the task (increment, `updatedAt` refresh, missing-link no-op); the 4th test (diagnostics logging) was the REFACTOR addition, consistent with 3 tests/11 assertions at GREEN → 4 tests/15 assertions after REFACTOR (both re-verified: current file runs 4/15).
- "Functional RED: 0 dispatches on active redirect; 1 dispatch attempt expected vs 0 on broker failure" — **credible and genuine behavioral RED**. Before controller wiring, `assertCount(1, $bus->dispatched)` fails with 0 vs 1 (the "actual size 0 matches expected 1"-style PHPUnit failure) and `assertSame(1, $bus->dispatchAttempts)` fails with 0. These are real failures of spec-mandated assertions, not smoke or compilation errors.
- "Routing RED: spy sender received 0 envelopes before routing config" — **credible**. Without the `App\Message\LinkVisited: async` routing line, dispatch reaches no sender; `assertCount(1, $sender->sent)` fails with 0.
- RED cannot be re-executed without reverting production code (outside this phase's write surface); the assessment above is dependency-based and internally consistent with all surviving counts.

### Test layer distribution (Phase 2 tests)

| Layer | Tests | Files | Tools |
| --- | --- | --- | --- |
| Unit | 4 | 1 (`LinkVisitedHandlerTest.php`) | PHPUnit 11.5, mocked repository/EM/logger |
| Integration | 1 | 1 (`LinkVisitedRoutingTest.php`) | PHPUnit + real `messenger.bus.default`, spy `SenderInterface` |
| Functional | 4 new (18 in file) | 1 (`LinkControllerTest.php`) | Symfony functional client, spy buses, Monolog `TestHandler` |
| E2E | 0 | 0 | Playwright — Phase 3 scope only |
| **Total Phase 2** | **9 backend tests** | **3 files** | |

### Changed file coverage

Coverage analysis skipped — no coverage tool configured (`openspec/config.yaml`: `coverage.available: false`).

### Quality metrics

Linter: ➖ Not available. Type checker: ➖ Not available. (Per `openspec/config.yaml` quality block.)

### Assertion quality

| File | Line | Observation | Severity |
| --- | --- | --- | --- |
| `LinkVisitedHandlerTest.php` | 47 | `assertGreaterThan($updatedAtBefore, $link->getUpdatedAt())` relies on microsecond clock resolution between construction and `markVisited()`; theoretically flake-prone, vanishingly unlikely | SUGGESTION |
| `LinkControllerTest.php` | broker-failure test | Monolog `TestHandler` is pushed onto the real `monolog.logger` and never removed; contained because it is the last test in the class and the suite is green in repeated runs, but a `popHandler` would be more robust | SUGGESTION |
| — | — | No explicit assertion that the persisted click count is unchanged immediately after a redirect (spec scenario element guaranteed by architecture, not asserted) | SUGGESTION |

**Assertion quality**: ✅ 0 CRITICAL, 0 WARNING, 3 SUGGESTION. No tautologies, no ghost loops, no type-only-only assertions, no smoke tests, no implementation-detail/CSS assertions. Mock/assert ratio is healthy (handler: 3 mocks vs 15 assertions). All assertions verify real spec-mandated behavior: status codes, `Location` value, dispatch counts/types/slug identities, click-counter values, log content, and sender routing.

---

## Review workload / PR boundary findings

- **Forecast record**: `tasks.md` carries `400-line budget risk: High` and `Chained PRs recommended: Yes` from the whole-change forecast. ✅ Present.
- **`size:exception` record**: present and explicit under `Phase 2 delivery decision`: "Resolved — the maintainer explicitly accepted `size:exception`, so Phase 2 ships as a single PR despite the 400-line budget… The `size:exception` acceptance applies to Phase 2 only and does not pre-authorize Phase 3." ✅ The record is the check; it passes. Chained-PR slicing was therefore not required for this slice, and the cached `stacked-to-main` strategy is explicitly recorded as not applied.
- **Actual diff size (re-measured)**: new files 21+44+82+59 = 206 lines; modified code+test files 173 insertions (`messenger.yaml` +1, `LinkRedirectController.php` +24, `LinkControllerTest.php` +128, `docker-compose.yml` +21). Total ≈ **379 code+test lines** — matches the apply-progress claim (~380) and sits inside the accepted 400–480 exception forecast, at the edge of the 400-line budget. ✅
- **Approved scope addition — `DEFAULT_URI`**: verified present on both `php` and `worker` services in `docker-compose.yml`, and correctly justified: `phpunit.xml.dist` injects `<env name="DEFAULT_URI" value="http://localhost:8080" force="true"/>`, which masked the missing compose env during Phase 1 tests; the first real HTTP redirect exposed `EnvNotFoundException`. The value matches the nginx public port (8080) used by `shortUrl` generation. This is a **deliberate, maintainer-approved compose change**, not unapproved scope creep. ✅
- **Scope creep check**: no frontend/Phase 3 files touched; no retry/dedup infrastructure added (per non-goals). ✅
- **Worktree housekeeping (SUGGESTION, not part of the 380-line slice)**: untracked artifacts exist — `backend/config/reference.php` (Symfony auto-generated reference file), `odd/tasks/phase-2-async-tracking.md` (session resume pointer), `.pi/`, `openspec/changes/mvp-url-shortener/.gentle-ai-instance`, and a `.gitignore` +1 entry (`.codegraph/`). These should be excluded or gitignored when committing the Phase 2 PR so they don't inflate review size.

---

## Blockers and findings

**Blockers (full-change archive):**

1. **CRITICAL (completeness / archive blocker, remaining scope — not a Phase 2 defect):** the 9 Phase 3 implementation tasks listed above remain unchecked. The full change cannot archive until Phase 3 is applied and verified.

**Warnings:**

1. **WARNING (TDD evidence format):** `apply-progress.md` reports Phase 2 TDD evidence as structured narrative rather than the required tabular `TDD Cycle Evidence` table. The substance fully satisfies the audit (all counts re-verified exactly), but the format should be reconciled.

**Suggestions (non-blocking):** the three assertion-quality notes above (microsecond-resolution timestamp assertion, Monolog `TestHandler` not popped, no explicit post-redirect click-count-unchanged assertion) and the worktree housekeeping note.

**Phase 2 slice verdict: PASS.** The 12 Phase 2 tasks are genuinely complete, tests are behavioral and green, the live async loop was independently reproduced, evidence claims are truthful, and the delivery decision records match reality.

---

## Key Learnings

- Every numeric TDD evidence claim in `apply-progress.md` (40/151, 13/26, 6/12, 21/113, 4/15, 1/3, 18/97) re-executed exactly on the first try — the apply phase recorded its numbers honestly, and per-file filtered runs were the key to cross-checking suite-level claims.
- `phpunit.xml.dist` `force="true"` env injection (e.g. `DEFAULT_URI`) can silently mask missing container environment variables; the first real HTTP request is what exposes the gap. Declaring such env in `docker-compose.yml` for both the web and worker services is the correct fix, and it was correctly justified in the artifacts.
- "Class not found" errors are the legitimate RED signature for new-class TDD in PHP: when the RED test references a class that does not yet exist, PHPUnit errors (not fails) — the apply's RED claims were verifiable by dependency analysis alone, since re-running RED would require reverting production code.
- `$client->disableReboot()` plus container overrides of both `messenger.bus.default` and the `message_bus` alias is the reliable pattern for asserting Messenger dispatch inside Symfony functional tests without test-transport config files.
- Re-verification of live async loops is cheap and high-value: starting the worker, following one existing redirect, and observing `clicks`/`updatedAt` change through the real broker/RabbitMQ path independently reproduced the apply's evidence (2→3 vs the apply's 0→1→2) in under a minute.
- The compose `worker` command (`php bin/console …`) naturally avoids the entrypoint's `php-fpm` migration branch — command-shape-based gating is a simple, verifiable migration-ownership mechanism, confirmed by both code inspection and live worker logs.
- Verification should distinguish slice truth from change completeness: a slice can be fully green and truthful while the change still cannot archive; the 9 unchecked Phase 3 tasks are an archive blocker, not a defect of the Phase 2 work.

---

## Phase 3 verification — Dashboard and Browser Flow slice

- **Change**: `mvp-url-shortener`
- **Verification scope**: Phase 3 only — dashboard and browser flow (9 tasks under `## Phase 3 — Dashboard and Browser Flow` in `tasks.md`), verified against the `dashboard` spec (4 requirements, 5 scenarios), the design's "Frontend Design" and "Testing Strategy → Frontend" sections, and the apply-progress Phase 3 record including its TDD Cycle Evidence table.
- **Verification date**: 2026-09-18
- **Branch**: `feat/dashboard-browser-flow` (diff vs `main`)
- **Slice result**: ✅ **PASS with warnings** (3 warnings, 0 critical — see "Blockers and findings")
- **Full-change archive**: ✅ **READY** — 39/39 tasks checked, Phase 1 closed, Phase 2 verified (above), Phase 3 verified here; native status `nextRecommended: archive`. One follow-up change is recommended (the `shortUrl` host/port divergence) and does not block archive.

---

## Executive summary

The Phase 3 slice is genuinely complete, and the parent gate's determinism correction is real. I rebuilt the frontend image from current source before every trusting run, then executed the full Playwright suite **five times** plus two full-suite `--repeat-each=3` stress runs (27 executions each) and the exact recorded `--grep "create-link flow" --repeat-each=3` command: **105 test executions, zero flaky, zero failed**. The gate defect (imperative `count()` baselines read before the mount-time GET settled) is confirmed corrected by direct code audit: the only remaining `count()` call (`basic.spec.ts:171`) is taken *after* an auto-waiting settle assertion on the seeded item, and the real-stack test now derives its baseline from `GET /api/links` — no other non-auto-waiting read feeds any assertion. Every numeric claim re-executed and matched (434 code+test lines, per-file line counts, build output including the identical content hash `index-wBYphdAB.js`, 9/9 suite, 40/151 backend regression, 0→1→2 async clicks) **except two imprecise evidence sentences in `tasks.md`** about the gate fix (detailed below). The `dashboard` spec's 4 requirements and 5 scenarios are all covered by real behavior, re-verified live (including an independent expired-marker + async-click browser check with fresh slugs and a SQL backdate). The `size:exception` record matches reality (434 ≈ ~425 forecast). Three warnings: two evidence-wording inaccuracies in `tasks.md`, and the "manual" check being a scripted browser script rather than a human click-through — honestly disclosed by the writer and independently reproduced by this verification, but the maintainer may still want the literal human pass. The `shortUrl` host/port divergence is confirmed empirically and classified as a **follow-up change**, not a Phase 3 defect.

---

## Verification commands and exact results

All commands run from the repo root on the live Docker stack (`postgres`/`rabbitmq` healthy, `php`, `nginx`, `frontend`, `worker` up).

| Command | Result |
| --- | --- |
| `cd frontend && npm run build` | ✅ `vite v5.4.21`, `✓ 31 modules transformed.`, `dist/assets/index-wBYphdAB.js 145.14 kB │ gzip: 46.79 kB`, `✓ built in 2.07s` — **byte-identical claim**: same content hash as the recorded build (recorded 2.06s, trivial timing variance) |
| `docker compose up -d --build frontend` | ✅ image rebuilt from current source; served page references `assets/index-wBYphdAB.js` (verified via `curl http://localhost:3000/`) — no stale `dist/` in any run below |
| `cd frontend && npx playwright test` (run 1) | ✅ `9 passed (6.4s)` |
| `cd frontend && npx playwright test` (runs 2–5) | ✅ `9 passed (6.5s)`, `9 passed (7.0s)`, `9 passed (5.4s)`, `9 passed (6.3s)` |
| `cd frontend && npx playwright test --grep "create-link flow" --repeat-each=3` | ✅ `6 passed (6.2s)` — same 6 executions as the recorded `6 passed (13.4s)`; only the duration differs (environment variance, count matches) |
| `cd frontend && npx playwright test --repeat-each=3` (×2) | ✅ `27 passed (16.3s)` and `27 passed (17.5s)` — full-suite stress runs beyond the parent's evidence |
| `docker compose exec -T php php vendor/bin/phpunit` | ✅ `OK (40 tests, 151 assertions)` — exactly the Phase 2 verified baseline; no backend regression from Phase 3 (expected: zero backend files in the diff) |
| `curl -s http://localhost:3000/api/links` vs `curl -s http://localhost:8080/api/links` | ⚠️ `shortUrl` divergence confirmed: `http://localhost/<slug>` via the frontend proxy vs `http://localhost:8080/<slug>` direct — see findings |
| Live ordering check: parse `GET /api/links` and compare `createdAt` sequence | ✅ 20 items, `newest-first: True` |
| Independent manual check (fresh slugs `verifyexp1`/`verifyclick1`): `POST /api/links` ×2 → SQL backdate `UPDATE link SET updated_at = now() - interval '31 days' WHERE slug='verifyexp1'` → scripted browser check (`/tmp/verify-manual.mjs`, real browser, no mocks) → `curl -o /dev/null -w` redirect | ✅ `UPDATE 1`; dashboard showed `clicks: 0expired` (not `active`) for the backdated link; browser redirect (backend `302`, `Location: https://example.com/verify/clicks` via curl) → dashboard async `clicks: 1` via the worker; a second (curl) redirect → `clicks= 2` via `GET /api/links` — the recorded 0→1→2 pattern reproduced exactly |

**Determinism totals: 105 Playwright test executions (5 full suites + 2 × 27 repeat-each + 6 grep repeat), zero flaky, zero failed.** Note: `playwright.config.ts` sets `retries: 1`, so any internally retried test would surface as `flaky` in the output — none did. `workers: 1`, `fullyParallel: false`.

No verification command was unavailable. One command failed for a reason attributable to the verifier, not the slice: my first `verify-manual.mjs` run exited 1 due to **my own** regex bug (`/clicks: 1\b/` fails against the rendered `clicks: 1active` because `1a` is a word-char run); the async update itself was already printed (`CLICK_ITEM_AFTER: …clicks: 1active`) and the remaining steps completed in a follow-up command. Nothing in the slice failed.

---

## Numeric evidence re-execution (every Phase 3 claim)

| Claim (source) | Re-measured | Match |
| --- | --- | --- |
| ~434 code+test added lines (apply-progress) | `git diff main...HEAD --numstat`: `api.js` +45, `basic.spec.ts` +222/−5, `manual-check.mjs` +44, `App.jsx` +123/−1 → **434** | ✅ exact |
| `api.js` 45 lines (apply-progress) | `wc -l` = 45; numstat +45 | ✅ |
| `basic.spec.ts` 225 lines (apply-progress) | `wc -l` = 225 (diff vs scaffold: +222/−5) | ✅ |
| `manual-check.mjs` 44 lines (apply-progress) | `wc -l` = 44 | ✅ |
| `App.jsx` +123/−1 (apply-progress) | numstat 123/1 | ✅ |
| Build: `✓ 31 modules transformed`, `index-wBYphdAB.js 145.14 kB │ gzip: 46.79 kB`, `2.06s` (tasks/apply-progress) | Re-ran: identical module count, byte-identical asset name/size, `2.07s` | ✅ (timing ±0.01s) |
| Suite `9 passed` post-GREEN/TRIANGULATE/REFACTOR (tasks/apply-progress) | 5 independent full runs: `9 passed` each | ✅ |
| Parent gate failure `1 flaky, 8 passed (13.1s)` (recorded) | Historical; cannot re-run the pre-fix file, but the root cause is confirmed present-then-absent by code audit (see determinism section) | ✅ credible record |
| Post-fix `9 passed (5.5s)`, `9 passed (7.0s)`, `6 passed (13.4s)` at `--repeat-each=3` (apply-progress) | Reproduced the same commands: `9 passed (6.4s)`, `(6.5s)`, `(7.0s)`, `(5.4s)`, `(6.3s)`; `6 passed (6.2s)` | ✅ counts exact; durations vary (13.4s→6.2s on the grep repeat is duration-only, no count mismatch) |
| Manual check: expired marker, clicks `0 → 1` (browser) `→ 2` (second redirect), `302` via curl, `UPDATE 1` backdate (apply-progress) | Independently reproduced with fresh slugs: identical outcomes, including `manualexp1`/`manualclick1` still present in the DB in exactly the recorded end state (expired/0 clicks; 2 clicks) | ✅ |
| Backend regression 40/151 (implied baseline) | `OK (40 tests, 151 assertions)` | ✅ |
| **"the fix removed both snapshots while adding an assertion per test that the rejected URL never appears in the list"** (tasks.md, REFACTOR evidence; also apply-progress TDD table) | **Partially false.** Only the real-stack test gained the rejected-URL assertion (`filter({ hasText: "not-a-url" })).toHaveCount(0)`). The fixture-side failed-creation test gained **no** such assertion — it retains `await expect(page.getByRole("listitem")).toHaveCount(itemCount)` only, with no filter on `https://example.com/conflict`. And its `count()` snapshot was not *removed* — it was made safe by moving it after the settle assertion (apply-progress describes this accurately; the tasks.md sentence does not) | ❌ **mismatch (WARNING)** |

The single ❌ is a record-wording inaccuracy in `tasks.md` (the apply-progress RED-diagnosis section describes the same fix accurately). The **substance** of the gate fix is fully verified: no assertion was weakened (both tests still assert the list is unchanged after a rejected creation; the real-stack test additionally asserts the rejected URL never appears), and no unsafe `count()` remains.

---

## Determinism assessment (the open risk)

The parent gate's flake (`basic.spec.ts:171`, `Expected: 0 / Received: 1`) stemmed from imperative `count()` baselines captured before the mount-time GET settled. Independent confirmation that the correction is real, by direct audit of `frontend/tests/e2e/basic.spec.ts`:

- `grep` for non-auto-waiting reads: the **only** `count()` call is at line 171, inside the fixture-side failed-creation test, and it is preceded by `await expect(seeded).toHaveCount(1)` (line 169) — an auto-waiting settle on the seeded item — so the baseline can no longer be captured mid-load.
- The real-stack validation test no longer snapshots the DOM at all: it derives its baseline from `GET /api/links` via `page.request.get` (an API read, not a UI race) and asserts `toHaveCount(apiLinks.length)`, which auto-waits.
- `page.request.get` (line 213) is the only other imperative read and does not race the UI.
- No `textContent()`/`evaluate()` reads feed any assertion in the spec file (the `textContent()` reads in `manual-check.mjs` all follow `waitFor()` calls and are scripted evidence anyway).
- One leftover of the insufficient *first* fix remains: `await expect(page.getByText(/loading/i)).toBeHidden()` at the head of the first real-stack test passes vacuously when the loading indicator has not rendered yet. It feeds no downstream assertion (all of that test's assertions auto-wait), so it is dead weight rather than a hazard — SUGGESTION, not a risk.

Repeated-run evidence (all on a freshly rebuilt image, live stack, worker up):

| Run | Result |
| --- | --- |
| Full suite ×5 | `9 passed` at 6.4s / 6.5s / 7.0s / 5.4s / 6.3s — zero flaky |
| Full suite `--repeat-each=3` ×2 | `27 passed` (16.3s) and `27 passed` (17.5s) — zero flaky |
| `--grep "create-link flow" --repeat-each=3` | `6 passed` (6.2s) — zero flaky |

**Determinism verdict: stable.** 105 executions with zero flaky and zero failed, exceeding the parent's three-run evidence. This confirms the gate correction rather than merely trusting the recorded claim.

---

## Spec coverage — `dashboard` (4 requirements, 5 scenarios, verified against real behavior)

| Requirement / Scenario | Coverage | Concrete test or command |
| --- | --- | --- |
| **R1: List links** — `GET /api/links` 200, newest first, full item shape | ✅ | Backend `LinkControllerTest` list tests (green in 40/151) + live check: 20 items, `createdAt` strictly newest-first |
| R1 / S1: List links with current analytics | ✅ | Backend ordering test + live ordering check + fixture test "dashboard loads, lists links with click counts and active status" (asserts both items' click counts `4`/`7` and `active`). *Note: no frontend assertion on DOM order (newest-first is backend-owned and backend-tested; live-checked here)* |
| R1 / S2: List an empty collection | ✅ | Backend empty-list test + fixture test "dashboard shows an empty state when no links exist" (`No links yet`, 0 list items) |
| **R2: Create links from the dashboard** — form → `POST /api/links`, error display, successful item added | ✅ | `LinkForm` with accessible `Original URL`/`Custom slug (optional)` labels; `createLink()` posts `{url, slug}` |
| R2 / S3: Create a link successfully from the UI | ✅ | Fixture test "successful creation prepends the created link with zero clicks" (201 fixture, item prepended, `clicks: 0`, short URL rendered as `<a href>` with exact href) + real-stack test "creates a link and displays its short URL with zero clicks" (live `POST` 201 → item with slug, `clicks: 0`, `active`, `href` ending `/<slug>`) |
| R2 / S4: Display a creation error | ✅ | Fixture test 409 `slug_conflict` → `role=alert` with the exact backend message `The requested slug is already in use.`, list unchanged + real-stack test 400 `invalid_url` → alert `The URL must be an absolute HTTP or HTTPS URL.`, list unchanged, rejected URL absent. "UI remains usable" is covered implicitly (form and list still present and asserted); no explicit re-fill assertion — SUGGESTION |
| **R3: Display expiration status** — `isExpired`-driven active/expired labels + clicks + short URL | ✅ | `LinkItem` derives the label from `link.isExpired`; fixture test "dashboard labels an expired link as expired" (asserts `expired` present and `active` absent) |
| R3 / S5: Show an expired link as inactive | ✅ | Fixture test above + **live verification**: SQL backdate (`UPDATE 1`) on `verifyexp1` → dashboard renders `clicks: 0expired`, not `active`; `GET /{slug}` on an expired link is backend-owned (410, Phase 1 functional tests green) |
| **R4: Verify the primary user flow** — E2E create-and-display; backend contracts covered independently | ✅ | Playwright real-stack test (loads dashboard, submits, sees created link) + backend suite `OK (40 tests, 151 assertions)` covering API and async click-tracking contracts independently |
| R4 / S5: Complete the create-and-display flow | ✅ | Real-stack test "creates a link and displays its short URL with zero clicks" — live API 201, item appears with short URL, zero clicks, `active` |

**Uncovered elements: none.** Two coverage notes (SUGGESTION, not gaps): no frontend-level DOM-order assertion (R1 ordering is backend-owned and live-verified); "UI remains usable" after an error is implied by the unchanged form/list assertions rather than an explicit re-fill assertion.

### Design conformance ("Frontend Design" + "Testing Strategy → Frontend")

- `api.js` with small `fetch` wrappers for `GET`/`POST /api/links` — ✅ implemented exactly; non-2xx surfaces `error.code`/`error.message` + status.
- Dashboard loads list on mount — ✅ (`useEffect` → `refresh()`).
- Submits URL and optional slug — ✅ (slug omitted from payload when empty).
- Shows loading and API error states — ✅ (`loading` text; `role=alert` error; both fixture-tested).
- Prepends the created link after success — ✅ (`[created, ...current]`), and the load-sequence ref prevents an in-flight GET from clobbering it (the RED-surfaced app defect, fixed).
- Shows short URL, original URL, clicks, active/expired — ✅ (`LinkItem` renders all four; fixture tests assert each).
- Short URLs render as links — ✅ (`toHaveAttribute("href", …)` asserted in fixture and real-stack tests).
- No client-side routing or authentication — ✅ (none present; grep confirms no router/auth imports).
- Vite `/api` proxy untouched — ✅ (`vite.config.js`, `nginx.conf`, `package.json`, `package-lock.json` absent from the `main...HEAD` diff).
- Testing strategy (build + Playwright load/create/short-URL/error) — ✅ all present and green.

---

## Task completion status

### Phase 3 — Dashboard and Browser Flow: 9/9 complete ✅

All 9 Phase 3 tasks are checked `- [x]` in `tasks.md`, each backed by real code, tests, or independently reproduced evidence as audited above. A grep for `^\s*- \[ \]` across `tasks.md` returns **zero unchecked implementation tasks**.

### Full change: 39/39 ✅

Native status reports `total: 39, completed: 39, allComplete: true` — confirmed against the artifact. No `- [ ]` markers remain. The unchecked lines quoted in the Phase 2 section of this report are historical; all nine are now checked with inline evidence.

Note: `odd/tasks/phase-3-dashboard.md` (the session resume pointer, not a task authority) still shows its own 9 boxes unchecked; `tasks.md` is the authoritative locator per the plan's "Source of truth" section, and it is 39/39. No action required.

---

## Structured status and actionContext findings

- Native status consumed: change `mvp-url-shortener`, state `ready`, artifacts all `done`, `taskProgress` 39/39, `nextRecommended: archive`, `blockedReasons: []`. Verification is optional and grants no edit authority; this report does not change the recommended action.
- `actionContext`: mode `repo-local`, workspaceRoot `/home/juan/Proyectos/url-shortener`, allowedEditRoots `[/home/juan/Proyectos/url-shortener]`. Implementation ownership verified: all four frontend files and all artifacts live inside the workspace root; the diff vs `main` contains only in-scope files (4 frontend + 3 planning/artifact files). No blockers.
- Read-only scope respected: no source, test, backend, compose, workflow, or task/apply artifact was edited. Only `verify-report.md` (this file, owned by the verifier) was written, plus ephemeral `/tmp/verify-manual.mjs`.
- No child subagents were launched.

---

## Strict TDD compliance

`openspec/config.yaml` declares `strict_tdd: true`; the declared frontend runner is `npx playwright test` (Playwright 1.60) and the backend regression command is `php vendor/bin/phpunit`. The global strict-TDD verify support guidance was loaded and followed; no project-local override exists.

| Check | Result | Details |
| --- | --- | --- |
| TDD evidence reported | ✅ | Phase 3 section of `apply-progress.md` contains the required **tabular** `TDD Cycle Evidence` table (this fixes the Phase 2 format WARNING recorded above) |
| All tasks have tests | ✅ | 9/9 tasks map to real files or executable evidence (`basic.spec.ts`, `api.js`, `App.jsx`, build, stack runs, `manual-check.mjs`) |
| RED confirmed (tests exist) | ✅ (dependency-verified) | RED cannot be re-executed without reverting production code (outside this phase's write surface). Credibility confirmed independently: `main`'s `App.jsx` is scaffold-only ("Scaffolding ready. Start building!") and `main`'s `basic.spec.ts` is a single title assertion — the rewritten 9 tests (list items, `/no links yet/i`, `/loading/i`, form labels, alerts) necessarily fail on contract against that scaffold, matching the recorded RED signatures |
| GREEN confirmed (tests pass) | ✅ | 9/9 pass now; re-executed 5× full plus 2× `--repeat-each=3` (105 executions, zero flaky) |
| Triangulation adequate | ✅ | Build re-executed with byte-identical output; real-stack E2E re-executed against the live API; the manual-check claims reproduced independently with fresh slugs (expired marker, 0→1→2 clicks through the real worker); real-stack 400 error path triangulates the 409 fixture |
| Safety net for modified files | ✅ (with note) | `App.jsx` was modified on a green base with no frontend unit runner existing (config declares Playwright as the only frontend runner — no unit layer is possible without adding tooling, which the test-seam decision forbade); the backend regression `40/151` green confirms zero backend impact |
| REFACTOR evidence | ✅ (inspection-verified) | API calls isolated in `api.js`; single status state machine with the empty-state gating fix (`status.kind === "idle"`) and its explanatory comment; accessible labels and verbatim backend error text preserved |

**TDD compliance**: 7/7 checks passed; 0 CRITICAL. The writer's earlier false "race fixed" claim is now accurately recorded in `apply-progress.md` as a correction ("That attempt was insufficient and the claim recorded here was wrong") — the record is self-critical and matches the code's actual state.

### Test layer distribution (Phase 3 tests)

| Layer | Tests | Files | Tools |
| --- | --- | --- | --- |
| Unit (frontend) | 0 | 0 | Not configured (declared runner: Playwright only; adding a unit runner was explicitly decided against in the test-seam decision) |
| Integration | 0 | 0 | — |
| E2E | 9 (+1 scripted check) | `basic.spec.ts` (9) + `manual-check.mjs` | Playwright 1.60, `page.route` request fixtures + real stack |
| Backend regression | 40 | (Phase 1/2 files, unmodified) | PHPUnit 11.5 |

No tool outside the declared capabilities is used; no new devDependency was added (`package.json`/`package-lock.json` untouched).

### Changed file coverage

Coverage analysis skipped — no coverage tool configured (`openspec/config.yaml`: `coverage.available: false`). Not a failure; per instructions, none was introduced.

### Quality metrics

Linter: ➖ Not available. Type checker: ➖ Not available. Formatter: ➖ Not available. (Per `openspec/config.yaml` quality block; none introduced, per instructions.)

### Assertion quality

| File | Line | Assertion | Observation | Severity |
| --- | --- | --- | --- | --- |
| `basic.spec.ts` | ~189 | `expect(getByText(/loading/i)).toBeHidden()` at the head of the first real-stack test | Passes vacuously before React renders the loading indicator (the known first-fix leftover); feeds no downstream assertion — dead weight, not a hazard | SUGGESTION |
| `basic.spec.ts` | 121–126 | load-failure test asserts only `role=alert` visibility | Behavioral (alert appears on 500) but does not assert the alert's message text; adding the generic `Request failed with HTTP status 500.` text would strengthen it | SUGGESTION |
| `basic.spec.ts` | 46–78 | list-rendering fixture test asserts both items but not DOM order | Newest-first ordering is backend-owned and backend-tested (and live-verified here); a frontend order assertion would be redundant but defensible | SUGGESTION |

**Assertion quality**: ✅ 0 CRITICAL, 0 WARNING, 3 SUGGESTION. No tautologies, no ghost loops, no type-only-only assertions, no smoke-only tests (every test asserts rendered content, attributes, or behavior), no implementation-detail/CSS assertions (locators use roles, labels, and text). Fixture count vs assertion count is healthy (7 fixture routes across 9 tests with ~40 behavioral assertions). The gate-corrected tests were audited line-by-line: the remaining `count()` is settle-guarded, and the real-stack baseline is API-derived and auto-waiting.

---

## Review workload / PR boundary findings

- **`size:exception` record**: ✅ present and explicit in `tasks.md` ("Phase 3 delivery decision: Resolved (2026-09-18) — the maintainer explicitly accepted `size:exception` for Phase 3, shipping it as a single PR… chained delivery as PR3a/PR3b/PR3c was offered and declined"), with the acceptance scoped to Phase 3 only. The record is the check; it passes.
- **Actual size vs forecast**: forecast ~425 code+test lines (read path ~210, create flow ~165, expiration/refactor/manual ~50); measured **434** — marginally above forecast and consistent with the recorded claim. Phase 2's accepted range was 400–480; 434 sits inside it. ✅
- **Chain strategy**: single PR per the accepted exception; chained slices not required. The branch carries ordered work-unit commits matching the suggested units: `0c70ab0` (docs/plan), `86bd6e9` (feat: `api.js` + `App.jsx` + `basic.spec.ts`), `0d31221` (test: `manual-check.mjs`), `7985fb3` (docs: apply progress + gate correction). ✅
- **Scope creep check**: ✅ none. The diff vs `main` contains exactly 4 frontend files + 3 planning/artifact files. No PHP, migration, Messenger, compose, CI, dependency-manifest, or config change. (`9d8b89a fix(ci)` appears in the branch's history below the Phase 3 commits but is already in `main` — not part of this slice.)
- **Worktree housekeeping**: `frontend/dist/`, `frontend/test-results/`, `frontend/node_modules/`, `frontend/playwright-report` (if generated) are build artifacts that must be excluded/gitignored when the parent commits the PR so they don't inflate review size — same housekeeping note as Phase 2.

---

## The scripted "manual" browser check — what it does and does not prove

**What it is:** `frontend/tests/e2e/manual-check.mjs` drives a real Chromium browser against the real stack (dashboard at `:3000`, redirect at `:8080`) with **no route mocks**, asserting the expired marker for the SQL-backdated `manualexp1` and polling for the worker-driven click update on `manualclick1` after a browser-followed redirect. The apply record and session plan both disclose honestly that this is scripted evidence, not a human click-through.

**What it proves:** the expired marker and the asynchronous click update are real, observable behaviors in a real browser against the real stack, worker included. This verification **independently reproduced** the same evidence with fresh slugs (`verifyexp1`, `verifyclick1`) and identical outcomes — including the persistent DB end state of the apply's own run (`manualexp1` expired/0 clicks; `manualclick1` at exactly the recorded 2 clicks), which corroborates the recorded run actually happened as described.

**What it does not prove:** nothing about human-perceived usability (layout, affordances, accessibility in practice). A scripted pass is not a substitute for the task's literal "manual browser check". Classifying this as a **WARNING** (disclosed by the writer; behavior independently confirmed; the maintainer may still want the one-time human click-through — it cannot be automated retroactively).

Note on re-running the original script: it cannot be re-run verbatim today because it asserts `clicks: 0` for `manualclick1` before the redirect, and that link is now at 2 clicks from the apply run. That is a property of the evidence's preconditions, not a defect.

---

## `shortUrl` host/port divergence — classification

**Empirically confirmed:** `GET /api/links` through the frontend container returns `shortUrl: http://localhost/<slug>`; the same request direct through nginx (`:8080`) returns `http://localhost:8080/<slug>`. Root cause as recorded: `frontend/nginx.conf` proxies `/api/` with `proxy_set_header Host $host`, and nginx's `$host` excludes the port, so the backend's `DEFAULT_URI`-based short-URL generation receives a port-stripped Host. The Vite dev proxy preserves the port, so the divergence is specific to the static-container topology.

**Impact:** short URLs displayed/clicked from the dashboard served at `:3000` point at `http://localhost/<slug>` (port 80), which this stack does not expose — the generated link is not followable as displayed. The E2E deliberately does not hardcode the host (it asserts `href` ends with `/<slug>`), which is why the suite is green despite the divergence.

**Classification: follow-up change, not a Phase 3 defect.** Reasoning: (1) the fix lives in backend/proxy surfaces (`LinkController` short-URL generation or `frontend/nginx.conf`) that were outside this phase's allowed edit roots — `nginx.conf` and `backend/**` were both explicitly not writable; (2) the divergence was pre-existing infrastructure behavior, first observable the moment a dashboard read `shortUrl` — it was not introduced by this slice's code; (3) the writer surfaced it proactively in both `apply-progress.md` and the session plan rather than hiding it. It should be tracked as a small follow-up change (backend base-URI derivation from an explicit header/env, or `proxy_set_header Host $host:$server_port` with a backend adjustment) and prioritized before any real deployment, because the displayed short URLs are the product's primary output. It does **not** block archive of this change: the `dashboard` spec requires displaying the API-returned `shortUrl`, which the UI does faithfully.

---

## Blockers and findings

**Blockers: none.** Zero CRITICAL findings. Zero unchecked implementation tasks.

**Warnings (3):**

1. **Evidence-wording inaccuracy in `tasks.md`** (also echoed in the apply-progress TDD table row): "the fix removed both snapshots while adding an assertion per test that the rejected URL never appears in the list" is not accurate for the fixture-side failed-creation test — its `count()` snapshot was retained (made safe by the settle guard), and it gained no rejected-URL assertion (only the real-stack test did). The apply-progress RED-diagnosis section describes the same fix correctly. Substance verified safe; the record sentence should be corrected by the artifact owner.
2. **Scripted, not human, "manual" check** — disclosed by the writer, independently reproduced here; the maintainer may still want the literal human click-through (see dedicated section).
3. **`shortUrl` host/port divergence** — confirmed; out of Phase 3's edit surfaces; classified as a follow-up change with a recommended home (backend base-URI derivation or the frontend proxy `Host` header). Non-blocking for archive.

**Suggestions (non-blocking):** remove the vacuous `toBeHidden()` leftover; assert the 500 alert's message text; optionally assert DOM order; ensure build artifacts (`dist/`, `test-results/`, `node_modules/`) are excluded from the PR diff.

**Environment side effects of this verification (disclosed):** the frontend image was rebuilt (mandatory per the no-bind-mount gotcha); `php` was recreated by compose and the nginx upstream re-validated healthy; two verification links (`verifyexp1` — backdated, now expired; `verifyclick1` — 2 clicks) were created in the local dev database and left in place, alongside the apply's own `manualexp1`/`manualclick1` and prior verification links. The stack was left up and healthy.

---

## Verdict

- **Phase 3 slice: PASS with warnings** (3 warnings, 0 critical; all warnings are record-wording, disclosure, or out-of-scope follow-up items — none concern the behavior of the delivered code, which is fully verified).
- **Full change archive: READY.** 39/39 tasks checked across Phases 1–3; Phase 1 closed, Phase 2 verified PASS (preserved above), Phase 3 verified PASS with warnings; backend regression green (40/151); frontend suite stable across 105 executions; native status `nextRecommended: archive` with no blocked reasons. Recommended follow-ups after archive: the `shortUrl` divergence change and the optional human click-through.

---

## Key Learnings

- A content-hashed build output (`index-wBYphdAB.js`) is the cheapest possible freshness proof in a no-bind-mount container topology: rebuilding locally and comparing the served asset name proves the recorded build ran against the identical source, eliminating the stale-`dist/` false-green risk without touching the container.
- `expect(...).toBeHidden()` is Playwright's most dangerous vacuous assertion: it passes immediately when the element has never rendered, which is exactly the state you are trying to wait out. Settling on a *visible* precondition (`toHaveCount(1)` on a seeded item) or deriving baselines from the API (`GET /api/links`) are the two safe replacements for imperative `count()` snapshots — and the only remaining `count()` in this suite is settle-guarded.
- `retries: 1` in a Playwright config converts would-be failures into visible `flaky` results, which makes "zero flaky across N runs" a meaningful determinism claim rather than a silent mask — worth keeping when repeatability matters.
- Re-executing an evidence claim means checking its *substance*, not just its numbers: the recorded "removed both snapshots / added an assertion per test" sentence was numerically untestable but line-level code audit exposed it as partially false even though the fix itself was sound. Artifact prose about tests must be audited against the test file as strictly as test counts.
- nginx's `$host` variable strips the port by design (`$http_host` preserves it) — a one-line `proxy_set_header` difference that surfaces as a product defect (non-followable short URLs) only when a frontend starts *displaying* server-generated URLs. Proxies that construct URLs from forwarded `Host` need an explicit base-URI decision, not implicit header inheritance.
- A scripted browser check reproduces a human flow faithfully against the real stack (expired marker, worker-driven async click 0→1→2 all reproduced with fresh slugs), but it cannot substitute for the human-perceived half of "manual"; the honest pattern here — disclose the scripting in the record, let the verifier reproduce independently, leave the human pass to the maintainer — is the right one.
- Verification DB writes (created links, SQL backdates) are sometimes the only way to reach a state (no API ages a link), and leaving them in place with disclosure is acceptable in a local dev database — the persistent end state of a prior run (`manualclick1` at exactly 2 clicks) even served as corroboration that the recorded run happened.
