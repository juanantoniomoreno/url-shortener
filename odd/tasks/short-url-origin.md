# Shortener Public Origin

## Goal

Make the `shortUrl` returned by the API resolve from the origin that displays it, by generating it from an explicitly configured public base URL instead of the incoming request's Host header.

## Problem (verified, 2026-09-20)

The dashboard displays a short link that does not navigate. Two independent root causes, both in `frontend/nginx.conf`:

1. **Origin bug** — `frontend/nginx.conf:19` `proxy_set_header Host $host;` strips the port, so the backend generates `http://localhost/<slug>` through the `frontend` container.
2. **Namespace collision** — `frontend/nginx.conf:13` `location / { try_files $uri $uri/ /index.html; }` claims the whole single-segment namespace, so `/<slug>` can never reach the backend through `:3000`.

Observed evidence:

```text
curl -s http://localhost:3000/api/links  → "shortUrl":"http://localhost/manually-checking"   (no port)
curl -s http://localhost:8080/api/links  → "shortUrl":"http://localhost:8080/manually-checking"
curl -o /dev/null -w '%{http_code} %{content_type}' http://localhost:3000/manually-checking → 200 text/html (the SPA)
curl -o /dev/null -w '%{http_code}' http://localhost:8080/manually-checking → 410 (correct)
```

`DEFAULT_URI` cannot fix this: `backend/config/packages/routing.yaml:3` wires it to `router.request_context`, and `RouterListener::onKernelRequest()` calls `RequestContext::fromRequest()` on every HTTP request, overwriting host and ports from the live request. `DEFAULT_URI` governs non-HTTP contexts (worker, CLI) only.

## Decision record (2026-09-20, maintainer)

**The shortener has its own public origin.** The backend generates `shortUrl` from an explicitly configured base URL, independent of the request Host.

Rejected alternatives and why:

- *Client-side origin* — the frontend would build the URL from `window.location.origin`; the data stops being authoritative and it is still not followable.
- *`Host $http_host` in the frontend nginx* — fixes the port only; the SPA catch-all still answers `/<slug>`.
- *Same origin, slugs win the namespace* — a URL shortener serving links at the root of its own SPA origin always collides on the single-segment namespace; every future SPA route would be silently proxied to the backend.
- *Relative path from the API* — changes the contract shape and is still not followable without the routing fix.

Consequence of the decision: root cause 2 becomes **moot rather than fixed**. The dashboard links point at the shortener origin, so nobody navigates `/<slug>` on the SPA origin. `frontend/nginx.conf` is therefore **not modified** by this change, and the residual `/<slug>` → SPA behavior on `:3000` is accepted, documented behavior, not a defect.

## Scope

Generate the public short URL from a configured base, and encode that contract in the canonical specs.

## Allowed edit surfaces

- `backend/src/Controller/LinkController.php`
- `backend/config/services.yaml`
- `backend/.env`
- `backend/.env.example`
- `backend/phpunit.xml.dist`
- `docker-compose.yml`
- `backend/tests/Functional/Controller/LinkControllerTest.php`
- `openspec/changes/shortener-public-origin/**` (new)
- `odd/tasks/short-url-origin.md`

Not writable in this change: `frontend/**` (no nginx change, no SPA change), `backend/src/Service/SlugGenerator.php`, `backend/migrations/**`, `.github/workflows/**`, `openspec/specs/**` (canonical specs are composed at archive time, not edited directly), `openspec/changes/archive/**`, generated/local artifacts (`.pi/`, `.codegraph/`, `.engram/`, `.atl/`, `.odd/`).

## Non-goals

- Do not modify `frontend/nginx.conf`, `frontend/Dockerfile`, or `frontend/src/**`.
- Do not collapse the two containers into a single-origin topology.
- Do not change the `shortUrl` contract shape: it stays an absolute URL.
- Do not add a frontend dependency, linter, or type-checker.
- Do not touch the reserved-slug list, slug generation, or expiration behavior.
- Do not commit or push unless separately authorized.

## Risks and gotchas

- **`phpunit.xml.dist` uses `force="true"`** on its env entries (`:16-21`). The test environment must set a *distinctive* `SHORTENER_BASE_URL` (not `http://localhost:8080`) so the assertion proves the value came from configuration and not from `DEFAULT_URI`, the request, or a fallback. A value that coincides with the request Host would make the test vacuous.
- **Env resolution is lazy.** `%env(SHORTENER_BASE_URL)%` resolves when the service is built, so a missing value fails loudly on the first HTTP request rather than at container compile time. That is the desired behavior, but it means a missing value in a non-request context stays silent.
- **Existing tests may encode the old behavior.** `backend/tests/Functional/Controller/LinkControllerTest.php` asserts `assertStringEndsWith('/'.slug, shortUrl)` at `:66` and `:88`, which survives this change. Any assertion of an exact full URL must be updated and disclosed, not deleted.
- **E2E fixtures already expect the target value.** `frontend/tests/e2e/basic.spec.ts:18,51,57,108,135` hardcode `http://localhost:8080/<slug>`, while the real-stack test is host-agnostic (`:201`, `RegExp('/'+slug+'$')`). After this change the fixtures pass for the right reason. Playwright does not run in CI, so E2E evidence is local only.
- **Compose consistency.** `DEFAULT_URI` is set on both the `php` and `worker` services. Follow that precedent so the two containers cannot drift, even though only `php` instantiates the controller.
- **Deploy-time override.** In a real deployment the operator must set `SHORTENER_BASE_URL` to the public shortener origin; the committed `backend/.env` value is a dev default, not a production value.

## Session decisions

- Execution mode: interactive. Artifact store: hybrid — this file plus an Engram mirror at `odd/short-url-origin/tasks`.
- Strict TDD is active (`openspec/config.yaml`); runner is `php vendor/bin/phpunit` executed in the Docker PHP runtime.
- Workflow: ODD (SDD not selected for this change; SDD requires an explicit request plus an injected session preflight).
- Delivery: branch first (currently on `main`), work-unit commits on the feature branch. Push, PR, and merge remain the maintainer's decisions.

## Tasks

- [x] RED: functional test asserting the `shortUrl` host comes from configured `SHORTENER_BASE_URL` and not from the request Host. Must fail on contract today, not on syntax.
- [x] GREEN: `SHORTENER_BASE_URL` plumbed through `backend/.env`, `backend/.env.example`, `docker-compose.yml` (`php` and `worker`), and `backend/phpunit.xml.dist` (distinctive test value).
- [x] GREEN: `LinkController` generates `shortUrl` from the configured base; request Host no longer influences it.
- [x] TRIANGULATE: full PHPUnit suite green; confirm no other assertion encoded the old request-derived host.
- [x] TRIANGULATE: live verification on the running stack — the API through `:3000` returns the configured origin, and that URL returns `302`/`410` (not `200 text/html`).
- [x] REFACTOR: remove the now-unused URL generator dependency if nothing else uses it; keep the route shape single-sourced or document the duplication.
- [x] Docs: OpenSpec change directory `openspec/changes/shortener-public-origin/` with the delta spec stating that `shortUrl` is absolute and resolves at the configured shortener origin.
- [x] Verify: independent verification of the diff against the delta spec and the TDD evidence. Verdict: PASS with warnings (see below); the three warnings were closed in a follow-up round.

## Verification commands

| Purpose | Command |
| --- | --- |
| Backend suite | `docker compose exec -T php php vendor/bin/phpunit` |
| API through the SPA origin | `curl -s http://localhost:3000/api/links` |
| API direct | `curl -s http://localhost:8080/api/links` |
| Follow the returned short URL | `curl -s -o /dev/null -w '%{http_code}\n' <shortUrl>` |
| Recreate containers after compose/env change | `docker compose up -d php worker` |

## Verified outcome

Completed 2026-09-20 by the delegated implementation writer.

- **RED (captured):** `test_short_url_is_built_from_configured_shortener_base_url_not_the_request_host` failed on contract: expected `https://short.test/690tEHD`, actual `http://evil.example.com:9999/690tEHD` — the URL provably came from the request Host. `Tests: 1, Assertions: 5, Failures: 1.`
- **GREEN (captured):** same test `OK (1 test, 5 assertions)` after plumbing `SHORTENER_BASE_URL` (distinctive `https://short.test` in `phpunit.xml.dist`) and composing the URL in `LinkController` as `rtrim(base,'/').'/'.$slug`.
- **Full suite:** `OK (41 tests, 156 assertions)`. The `assertStringEndsWith('/'.slug, ...)` assertions survived unchanged; no assertion encoded the old request-derived host, so nothing was removed or rewritten.
- **Live:** `:3000` and `:8080` both return `"shortUrl":"http://localhost:8080/manually-checking"`; following it returns `410 application/json` (the seeded link is expired); `:3000/<slug>` still returns `200 text/html` — accepted behavior per the decision record. No container recreation was needed: the running containers bind-mount `backend/`, so the committed `.env` fallback supplied the new var after `cache:clear`. The compose-level env values will apply at the next `up`.
- **Refactor:** `UrlGeneratorInterface` removed from `LinkController` — its only use was the replaced `shortUrl` generation (whole file read to verify). The `link_redirect` route itself is untouched; only its URL composition was duplicated into the controller as configured string concatenation.
- **DI choice:** `_defaults.bind string $shortenerBaseUrl: '%env(SHORTENER_BASE_URL)%'` — one binding for the whole `App\` tree, no per-service config, no controller attribute.
- **Docs:** `openspec/changes/shortener-public-origin/` with `proposal.md`, `tasks.md`, and delta specs for `link-management` (ADDED: absolute-URL-from-config + resolvability requirements) and `dashboard` (MODIFIED: Display expiration status, quoted canonical text).
- Not done: independent verification (final task box left open); Playwright E2E (out of scope per brief); no commits made.

## Independent verification closure (2026-09-20, delegated worker)

Three verification findings were closed without altering the `shortUrl` generation strategy (explicit `rtrim(base,'/') . '/' . $slug` kept; no `UrlGeneratorInterface` reintroduced):

1. **Coverage gap** — two regression-guard tests added to `LinkControllerTest.php`: `test_returned_short_url_resolves_through_the_link_redirect_route` (302 + Location) and `test_returned_short_url_of_an_expired_link_resolves_through_the_link_redirect_route` (410). Both derive the expected path from the `link_redirect` route collection in the test container and follow the returned `shortUrl`. No RED possible today (composition and route agree); a route path change in `routes.yaml` makes them fail. Full suite green: 43 tests, 170 assertions (baseline 41/156).
2. **Spec blockquotes** — in both delta files, quoted canonical text moved out of requirement bodies into end-of-file "Review notes (non-normative — not requirement content)" sections; quoted old text preserved.
3. **Route coupling** — comment added at the composition site in `LinkController::serializeLink` naming the sync requirement and the guarding tests.

The `Verify: independent verification` checkbox above remains open for the maintainer to tick after reviewing this report.

## Independent verification verdict (2026-09-20, `gentle-ai-verify`)

**PASS with warnings.** Every numeric claim was re-executed and confirmed: full suite `OK (41 tests, 156 assertions)`, focused test `OK (1 test, 5 assertions)`, and the arithmetic cross-check (41−1=40 tests, 156−5=151 assertions) matched the historical HEAD baseline exactly, proving the change added one test and nothing else. Whole-suite search confirmed no other assertion encoded the old request-derived host; `UrlGeneratorInterface` had no remaining consumer anywhere in `backend/src`, `backend/config`, or `backend/tests`. Live behavior reproduced: both `:3000` and `:8080` return the configured origin and following it returns `410 application/json`. Scope compliance clean — every changed path inside the allowed surfaces, no forbidden path touched.

The verifier also corrected the mechanism behind the no-recreation live check: `docker compose exec -T php env` shows `SHORTENER_BASE_URL` absent from the process environment, and the value is read at runtime from the bind-mounted `backend/.env` through `$container->getEnv('SHORTENER_BASE_URL')`.

### Warnings and their closure

| # | Warning | Closure |
| --- | --- | --- |
| 1 | The new spec MUST ("following a returned `shortUrl` MUST reach the redirect endpoint") had no automated coverage, and the `/{slug}` shape was duplicated between `routes.yaml` and the controller with nothing to catch divergence. | Closed: two regression-guard tests added that derive the expected path from the route collection and follow the **returned** `shortUrl`. A route path change now fails the suite. The composition stays explicit concatenation by decision — routing it through `UrlGeneratorInterface` would reintroduce the request context this change decouples from. |
| 2 | Explanatory blockquotes sat inside requirement bodies and could have been composed into `openspec/specs/**`. | Closed: moved to end-of-file non-normative review-notes sections in both delta files; requirement bodies are now pure requirement text. |
| 3 | The route coupling was undocumented in code. | Closed: comment at the composition site naming the sync requirement and the guarding tests. |

### Parent gate

After the closure round the parent re-ran the suite independently on the final bytes: `OK (43 tests, 170 assertions)` on PHP 8.4.25 — the writer's claimed totals confirmed, not accepted on report. The delta files and the new tests were read directly: the tests derive the expected path from `router->getRouteCollection()->get('link_redirect')->getPath()` with `{slug}` substituted, so they genuinely couple the generated URL to the declared route.

### Follow-ups deliberately NOT done here

- **`SHORTENER_BASE_URL` shape is unvalidated.** A malformed value (`SHORTENER_BASE_URL=` or `short.example`) yields a non-absolute `shortUrl` that violates the new spec requirement, with HTTP 200 and no failing test. Where to fail (boot vs first request) is a design decision left to the maintainer.
- **The committed `backend/.env` is baked into the image.** `backend/Dockerfile` does `COPY . .` with no `.dockerignore`, so a deployment that forgets to override `SHORTENER_BASE_URL` silently serves `http://localhost:8080/<slug>` instead of failing loudly. The fail-loud claim holds only when `.env` is absent.
- **Pre-existing indentation defect** at `backend/tests/Functional/Controller/LinkControllerTest.php:67` (a method at column 0). Base-only, unrelated to this change; deliberately left alone to keep the diff honest.
- **Playwright E2E** was not run (not in CI, out of scope). The fixtures already hardcode `http://localhost:8080/<slug>` and now pass for the right reason.
- No commits. Push, PR, and merge remain the maintainer's decisions.
