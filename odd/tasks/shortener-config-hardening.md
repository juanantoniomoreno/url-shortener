# Shortener Config Hardening

## Goal

Make the `SHORTENER_BASE_URL` configuration honest: reject malformed values at the first request that uses them, stop baking the committed dev `.env` into the Docker image, and document every service environment variable.

This closes the three follow-ups left open by the `shortener-public-origin` change (PR #4, archived 2026-09-20):

1. `SHORTENER_BASE_URL` has no shape validation: a malformed value (`SHORTENER_BASE_URL=short.example`) yields a non-absolute `shortUrl` that violates the canonical spec requirement, with HTTP 200 and no failing test.
2. `backend/Dockerfile` does `COPY . .` with no `.dockerignore`, so a deployment that forgets to override `SHORTENER_BASE_URL` silently serves `http://localhost:8080/<slug>` (the dev default baked into the image).
3. No environment variable is documented in `README.md` or `AGENTS.md`.

## Decision record (2026-09-22, maintainer)

**A malformed `SHORTENER_BASE_URL` fails at the first request that uses it**, with a clear 500 message naming the variable. Redirects and unrelated endpoints stay alive: the redirect flow (`/<slug>`, `LinkRedirectController`) does not depend on this variable; only `LinkController` (create/list) serializes short URLs.

Rejected alternatives:

- *Boot-time failure* — classic config fail-fast, but it would kill the redirect and list flows that do not need this variable; disproportionate impact.
- *Hybrid boot warning + request failure* — earlier signal in `docker logs` without downtime, but more code for little gain in a 5-service dev-grade stack.

Validation shape criteria (architect decision, no product trade-off):

- **Require**: absolute URL, scheme `http` or `https`, non-empty host.
- **Allow**: optional path (composes correctly via `rtrim(base,'/').'/'.$slug`), trailing slash (trimmed).
- **Reject**: empty string, missing scheme/host, non-http(s) schemes, query string, fragment.

## Scope

Three work units, one commit each, on branch `fix/shortener-config-hardening`:

| # | Work unit | Commit |
| --- | --- | --- |
| 1 | Request-time validation of `SHORTENER_BASE_URL` (strict TDD) | `fix(api): fail short URL serialization on a malformed SHORTENER_BASE_URL` |
| 2 | `backend/.dockerignore` (+ fold the pending `.gitignore` `.atl/` line) | `build(backend): keep the local .env out of the image` |
| 3 | Environment variable documentation | `docs: document service environment variables` |

## Allowed edit surfaces

- `backend/src/Controller/LinkController.php`
- `backend/src/Service/ShortenerBaseUrl.php` (new)
- `backend/tests/Unit/Service/ShortenerBaseUrlTest.php` (new)
- `backend/tests/Functional/Controller/LinkControllerTest.php`
- `backend/.dockerignore` (new)
- `backend/.gitignore`
- `README.md`
- `AGENTS.md`
- `odd/tasks/shortener-config-hardening.md`

Not writable in this change: `backend/config/**` (the `string $shortenerBaseUrl` binding stays as is; the controller validates the raw string it receives), `docker-compose.yml` (env plumbing is already correct), `backend/Dockerfile` (the fix is the ignore file, not the build), `frontend/**`, `openspec/**` (no spec change: this enforces an existing MUST), `backend/.env`, `backend/.env.example`.

## Non-goals

- No boot-time or kernel-level validation (decision above).
- No change to the `shortUrl` composition strategy (`rtrim(base,'/').'/'.$slug` stays).
- No change to `docker-compose.yml` env plumbing; no topology change.
- No env-var secrets management or production deployment guide.
- Do not touch the pre-existing indentation defect in `LinkControllerTest.php:121` (base-only; verified — the earlier "line 67" reference in project memory was wrong).
- Do not commit or push unless separately authorized; commits on the feature branch are part of this work unit, push/PR/merge are the maintainer's.

## Risks and gotchas

- **`%env()%` resolution is lazy** (verified in PR #4): the value resolves when `LinkController` is instantiated, so validation in the controller constructor fires exactly at the first request that uses the controller — matching the decision.
- **Functional test with a malformed value** needs a per-test env override (`putenv` + kernel reboot); it is desirable but may fight Symfony's container cache. If it proves unreliable, unit coverage of the shape criteria plus disclosure is acceptable; do not delete green coverage to force it.
- **`.dockerignore` and the bind mount**: the dev stack bind-mounts `backend/`, so runtime behavior is unaffected; the image is what deploy contexts consume. Excluding `/vendor/` is safe because `composer install` runs before `COPY . .` and `dump-autoload --optimize` runs after.
- **`docker-entrypoint.sh` does not read `.env`** (migrations use compose env); excluding `.env*` from the image breaks nothing in the local stack.
- **Image build is slow** (pecl `amqp` compiles from source); the build verification costs a few minutes.

## Session decisions

- Execution mode: interactive. Artifact store: hybrid — this file plus an Engram mirror at `odd/shortener-config-hardening/tasks`.
- Strict TDD (`openspec/config.yaml`); runner `php vendor/bin/phpunit` in the Docker PHP runtime.
- Workflow: ODD (SDD not selected; no SDD session preflight injected).
- Delivery: branch first (currently on `main`), one work-unit commit per task, recorded in this file as evidence. Push, PR, and merge remain the maintainer's decisions.

## Tasks

- [x] 1. RED: unit tests for every malformed shape of the `SHORTENER_BASE_URL` validator (12 shape cases + 3 composition cases) + functional 500 regression with a per-test env override (kept: the compiled test container keeps `%env()%` as a runtime placeholder, so `putenv` + superglobals + kernel reboot is reliable). Evidence: 15 unit errors (class not found) + functional `201 != 500` before GREEN. Commit `62dc901`.
- [x] 2. GREEN: `App\Service\ShortenerBaseUrl::fromString()` validates at `LinkController` construction; suite 59 tests / 200 assertions (baseline 43/170, +16/+30 exact), parent-gated on the final bytes. Commit `62dc901`.
- [x] 3. `backend/.dockerignore` (`.env*`, `/vendor/`, local runtime state; **`/var/` kept deliberately**: `docker-entrypoint.sh` assumes `var/` exists and excluding it without touching the Dockerfile risks a runtime mkdir permission failure for www-data) + folded the pending `backend/.gitignore` `.atl/` line. `docker compose build php worker` OK; image contains no `.env` (only `.env.example`), own `vendor/`, and `var/`. Commit `75da679`.
- [x] 4. Document the environment variables (`SHORTENER_BASE_URL`, `DATABASE_URL`, `MESSENGER_TRANSPORT_DSN`, `APP_ENV`, `DEFAULT_URI`, `POSTGRES_*`, `RABBITMQ_*`) in `README.md` (16 added lines), with a short pointer in `AGENTS.md` (12 added lines). Commit `01a724f`.
- [x] 5. Independent verification of the full diff (`gentle-ai-verify`): **PASS** with 4 non-blocking notes — the base-only indentation defect is at line 121 (not 67 as previously recorded), the README prod-mode wording was tightened in a follow-up commit, the functional 500 test covers create only (list is covered structurally via the shared constructor), and the base-suite 43/170 was verified by static count + arithmetic, not re-executed.

## Verification commands

| Purpose | Command |
| --- | --- |
| Backend suite | `docker compose exec -T php php vendor/bin/phpunit` |
| Build both images | `docker compose build php worker` |
| Image has no baked `.env` | `docker compose build php && docker run --rm --entrypoint sh $(docker compose build -q php) -c 'ls -la .env* 2>&1'` |
| Recreate after ignore-file change | `docker compose up -d --build php worker` |

## Verified outcome

Completed 2026-09-22. Three work-unit commits on `fix/shortener-config-hardening` (base `main` @ `7c8c36f`):

| Commit | Work unit |
| --- | --- |
| `62dc901` | fix(api): request-time SHORTENER_BASE_URL validation (15 unit + 1 functional tests; suite 59/200, baseline 43/170, +16/+30 exact) |
| `75da679` | build(backend): .dockerignore keeps the local .env out of the image (image verified: no `.env`, own `vendor/`, `var/` kept) |
| `01a724f` | docs: environment variable documentation (README +16 lines, AGENTS.md +12 lines) |

Parent gate on the final bytes: `OK (59 tests, 200 assertions)` re-executed by the parent before each commit; live stack checks passed (`:8080/api/links` → 200 after force-recreate from the new image).

Independent verification (`gentle-ai-verify`): **PASS** — scope compliance clean (8 paths, all inside the allowed surfaces), all numeric claims reproduced, image claims reproduced, semantic checks confirmed (constructor-time validation fires for create AND list; env-override test leak-free in a `finally` block; README prose matches the validator line-by-line; `.dockerignore` safe against the Dockerfile/entrypoint reality). Four non-blocking notes, all closed or recorded above (indentation defect re-located to line 121; README prod wording tightened; functional 500 test covers create only, list covered structurally; base-suite 43/170 verified statically).

Operational side-finding (closed during this work, not part of the diff): `backend/vendor` was missing on the host, crash-looping the `worker` container. Restored via `docker compose run --rm php composer install --no-interaction` (host PHP is 8.3; composer.json requires `^8.4`).

Follow-ups deliberately NOT done here: functional 500 coverage for the `list()` endpoint (structurally covered via the shared constructor), a boot-time warning for malformed config (rejected alternative), and the pre-existing `LinkControllerTest.php:121` indentation defect (base-only).
