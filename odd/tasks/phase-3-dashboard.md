# Phase 3 — Dashboard and Browser Flow

## Goal

Implement Phase 3 of the `mvp-url-shortener` SDD change: a minimal unauthenticated React dashboard that lists links with their click and expiration state, creates links from a form, and surfaces API validation errors — with Playwright E2E evidence and one manual browser check.

## Source of truth

The authoritative task list is `openspec/changes/mvp-url-shortener/tasks.md`, Phase 3 section (9 tasks, all unchecked). Specs live in `openspec/changes/mvp-url-shortener/specs/dashboard/spec.md`; the design section is "Frontend Design" plus "Testing Strategy → Frontend" in `design.md`. This file is a session-level resume pointer, not a second task authority.

## Scope finding (measured before planning)

The backend already satisfies every `dashboard` spec requirement:

- `GET /api/links` → 200, newest first, each item with `slug`, `url`, `shortUrl`, `clicks`, `createdAt`, `updatedAt`, `isExpired`
  (`LinkController::list()` + `serializeLink()`).
- `POST /api/links` → 201 with the same payload; errors as `{error: {code, message}}` on 400 (`invalid_url`, `invalid_slug`, `invalid_json`), 409 (`slug_conflict`), 500 (`slug_generation_failed`).

Therefore Phase 3 touches **frontend code and frontend tests only**: no PHP, no migration, no Messenger change, no compose change.

## Allowed edit surfaces

- `frontend/src/api.js` (new)
- `frontend/src/App.jsx`
- `frontend/src/components/**` (only if it improves readability)
- `frontend/tests/e2e/**`
- `openspec/changes/mvp-url-shortener/tasks.md`
- `openspec/changes/mvp-url-shortener/apply-progress.md`
- `odd/tasks/phase-3-dashboard.md`

Not writable in this phase: `backend/**`, `docker-compose.yml`, `.github/workflows/**`, `frontend/package.json` and `package-lock.json`, `openspec/config.yaml`, generated/local artifacts (`.pi/`, `.codegraph/`, `.engram/`, `.atl/`).

## Non-goals

- No client-side routing, authentication, ownership, editing, deletion, or rate limiting.
- No Vitest/RTL or any new frontend devDependency; Playwright stays the only frontend runner.
- No event-level deduplication, retry infrastructure, or CORS work.
- No backend, Messenger, or migration changes; no Phase 1/2 re-verification beyond one regression run.
- Do not commit or push unless separately authorized.

## Session decisions

- Execution mode: interactive. Artifact store: hybrid (OpenSpec files + Engram mirror `odd/phase-3-dashboard/tasks`).
- Strict TDD is active; declared runners are `npx playwright test` (frontend) and `php vendor/bin/phpunit` (backend regression read-only).
- **Delivery decision (2026-09-18):** the maintainer explicitly accepted `size:exception` for Phase 3 as a **single PR**. Chained slices 3a/3b/3c were offered and declined.
- **Test-seam decision (2026-09-18):** Playwright with `page.route` request fixtures only. No new tooling, no new dependencies.

## Review workload forecast

| Field | Value |
| --- | --- |
| Estimated changed lines | ~425 code+test |
| 400-line budget risk | High |
| Chained PRs recommended | No — maintainer accepted `size:exception` |
| Delivery strategy | single PR, `size:exception` recorded in `tasks.md` |
| Slice estimate | 3a read path ~210, 3b create flow ~165, 3c expiration/refactor ~50 |

## Tasks

- [x] RED: replace the scaffold assertion in `frontend/tests/e2e/basic.spec.ts` with dashboard coverage — load + list, empty state, valid creation, validation error, expired marker. RED must fail on contract, not syntax.
- [x] RED: add request fixtures/seams for loading, successful creation, and failed creation states via `page.route`, without coupling to RabbitMQ internals.
- [x] GREEN: `frontend/src/api.js` with `fetchLinks()` and `createLink(url, slug)`; JSON-aware, surfaces the backend `error.code`/`error.message` on non-2xx.
- [x] GREEN: dashboard state and form with loading, error, empty-list, create-success, click-count, and active/expired states.
- [x] GREEN: keep the existing Vite `/api` proxy; render short URLs as links; no client-side routing or authentication.
- [x] TRIANGULATE: `cd frontend && npm ci && npm run build` recorded as a successful production build.
- [x] TRIANGULATE: start the stack and run `cd frontend && npx playwright test`, recording create-and-display and validation-error evidence.
- [x] TRIANGULATE: one manual browser check of an expired link marker and an asynchronously updated click count after a redirect. Performed by the maintainer on 2026-09-18: link `manually-checking` was created from the dashboard, followed through `:8080` (`302`), and the dashboard then showed `clicks: 1`; after a 31-day `updated_at` backdate it rendered `expired` and the redirect returned `410`. Confirmed in the database (`clicks=1`, expired). This closes the item with human evidence instead of the scripted stand-in.
- [x] REFACTOR: only after E2E passes — isolate API calls, avoid duplicated state transitions, preserve accessible labels and error text.

## Verification commands

| Purpose | Command |
| --- | --- |
| Production build | `cd frontend && npm ci && npm run build` |
| Rebuild the static frontend image | `docker compose up -d --build frontend` |
| Full stack for E2E | `docker compose up -d postgres rabbitmq php nginx frontend` |
| Worker for the async click check | `docker compose up -d worker` |
| E2E suite | `cd frontend && npx playwright test` |
| Backend regression | `docker compose exec -T php php vendor/bin/phpunit` |
| Force the expired-link case | `docker compose exec -T postgres psql -U <user> -d <db> -c "UPDATE link SET updated_at = now() - interval '31 days' WHERE slug = '<slug>';"` |

## Risks and gotchas (verified against the repo)

- `frontend/playwright.config.ts` targets `http://localhost:3000`, which is the **nginx static-build container**, not the Vite dev server. There is no HMR in the E2E loop: every code change requires `docker compose up -d --build frontend` before re-running Playwright. Batch changes per RED→GREEN cycle.
- The container's `/api/` proxy points at `http://nginx:80` on the Docker network; the Vite `/api` proxy (`http://localhost:8080`) is development-only. Do not mix them when diagnosing failures.
- `docker-compose.yml` `frontend` service has no source bind-mount, so a rebuild is mandatory — a green E2E against stale `dist/` is a false green.
- The async click count is only truthful with the `worker` service up; the spec must poll for the update, not sleep a fixed interval.
- The expired marker needs a 30-day-old `updatedAt`; there is no API to age a link, so the manual check requires the SQL above.
- `openspec/config.yaml` declares `frontend.runner: Playwright` and `quality: null`; this plan deliberately adds no tool that would invalidate that declaration.

## Verified outcome

Completed 2026-09-18 on branch `feat/dashboard-browser-flow`, delivered as PR #2 and merged into `main` (`48a1440`). All 9 Phase 3 tasks are checked in the change's `tasks.md` with inline evidence, and its `apply-progress.md` carries the tabular TDD Cycle Evidence table. Full change: 39/39 tasks, independently verified (PASS with warnings), then archived as `openspec/changes/archive/2026-09-18-mvp-url-shortener/` with its delta specs composed into `openspec/specs/`.

Observed results: real RED (9/9 contract failures vs scaffold, captured before implementation), GREEN 9/9, production build `✓ 31 modules transformed`, and a scripted manual browser check (no route mocks) showing the expired marker after SQL backdate (`UPDATE 1`) and the async click count `0 → 1` via the worker (the `302` confirmed separately with `curl`). That scripted check is evidence generated by a script, not a human click-through. The maintainer then performed the literal human pass on 2026-09-18 and every step returned the expected result, so this item is closed with human evidence (see the task list above).

Changed files: `frontend/src/api.js` (new, 45), `frontend/tests/e2e/basic.spec.ts` (rewritten, 225), `frontend/src/App.jsx` (+123/−1 vs scaffold), `frontend/tests/e2e/manual-check.mjs` (new, 44), plus the two OpenSpec artifacts. ≈ 434 code+test added lines — marginally above the ~425 forecast and inside an accepted `size:exception` range. No new frontend dependency.

### Gate corrections during this phase

1. A genuine app defect surfaced by RED: a still-in-flight mount-time GET could clobber a just-created link. Fixed with a load-sequence ref.
2. A genuine test defect surfaced by the parent gate, not by the writer: the parent's independent re-run produced `1 flaky, 8 passed (13.1s)` from pre-load `count()` baselines in two tests (the fixture-side failed-creation test and the real-stack validation test), and the writer's record falsely claimed the race had been fixed. The `toBeHidden()` wait it relied on passes vacuously before React renders. Both sites were corrected so no assertion reads a non-auto-waiting value before the load settles: the real-stack validation test replaced its imperative snapshot with an API-derived baseline and gained two assertions, while the fixture-side failed-creation test kept its `count()` behind a settle assertion on the seeded item and gained none. `sdd-verify` audited the diff and corrected an earlier overstatement here that claimed a snapshot was removed and an assertion added in both tests. Verified by three consecutive zero-flaky runs: `9 passed (5.5s)`, `9 passed (7.0s)`, `6 passed (13.4s)` at `--repeat-each=3`; `sdd-verify` added 105 further test executions with zero flaky.

### Findings carried forward

- `shortUrl` host/port divergence: the frontend container's nginx passes `Host $host`, stripping the port, so the backend generates `http://localhost/<slug>` through the static container while the Vite dev proxy preserves `:8080`. Out of Phase 3 scope; needs a backend/proxy decision in a later change.
- The E2E loop requires the stack up plus a rebuilt frontend image (no source bind-mount), and the async click check requires the `worker` service.
- **Worker supervision (found by the human manual check, fixed separately):** the worker runs a bounded consumer (`--time-limit=3600`), so it exits cleanly after an hour, and the service had no restart policy. Analytics stopped permanently and silently while redirects kept working, and a queued visit sat unconsumed with the counter stuck at 0. Fixed in `fix/worker-restart-policy` with `restart: unless-stopped` on the `worker` service only. No automated test could see this: in every automated run the worker was alive.
- **A delayed visit revives an expired link.** The handler deliberately performs no expiration check, and `markVisited()` refreshes `updatedAt`, so consuming a still-queued visit flips `isExpired` back to false. Consistent with both documented behaviors, but the combination was never decided explicitly — it needs an explicit product decision.
- Archived on 2026-09-18; the canonical specs now live in `openspec/specs/`.
