# Tasks: Shortener Public Origin

## 1. Contract test (RED)

- [x] 1.1 Add a functional test in `backend/tests/Functional/Controller/LinkControllerTest.php` issuing a request with Host `evil.example.com:9999` and asserting `shortUrl` equals the configured `https://short.test` origin plus the slug. Captured RED failure: actual `http://evil.example.com:9999/<slug>`, expected `https://short.test/<slug>` — contract failure, not syntax.

## 2. Configuration (GREEN)

- [x] 2.1 Add `SHORTENER_BASE_URL` to `backend/.env` (dev default `http://localhost:8080`), `backend/.env.example`, and `docker-compose.yml` on both the `php` and `worker` services, following the `DEFAULT_URI` precedent.
- [x] 2.2 Add a distinctive test value `https://short.test` (with `force="true"`) to `backend/phpunit.xml.dist`.

## 3. Controller behavior (GREEN)

- [x] 3.1 Inject `string $shortenerBaseUrl` into `LinkController` via a `_defaults.bind` for `%env(SHORTENER_BASE_URL)%` in `backend/config/services.yaml`; generate `shortUrl` as the configured base plus `/` plus the slug.

## 4. Verification (TRIANGULATE)

- [x] 4.1 Focused test green; full suite green: 41 tests, 156 assertions. Confirmed no other assertion encoded the old request-derived host (`assertStringEndsWith('/'.slug, ...)` at the previous lines 66 and 88 survives unchanged).
- [x] 4.2 Live verification on the running stack: `GET /api/links` through `:3000` and `:8080` both return `http://localhost:8080/<slug>`; following the returned `shortUrl` returns `410 application/json` (the seeded link is expired); `:3000/<slug>` still returns `200 text/html` from the SPA — accepted behavior per the maintainer decision.

## 5. Cleanup and docs (REFACTOR)

- [x] 5.1 Remove the unused `UrlGeneratorInterface` constructor dependency and import from `LinkController` (verified: its only use was the removed `shortUrl` generation).
- [x] 5.2 Add this OpenSpec change directory: `proposal.md`, `tasks.md`, and delta specs for `link-management` and `dashboard`.

## 6. Independent verification findings (closure)

- [x] 6.1 Regression guard: `test_returned_short_url_resolves_through_the_link_redirect_route` and `test_returned_short_url_of_an_expired_link_resolves_through_the_link_redirect_route` create a link through the API, derive the expected path from the `link_redirect` route definition in the test container (`Router::getRouteCollection()`), assert the returned `shortUrl`'s path matches it, and follow that path to assert `302` + `Location` (active) or `410` (expired). These tests cannot be RED today because the composition and the route agree; they fail if `backend/config/routes.yaml` changes the `link_redirect` path (e.g. `path: /{slug}` → `path: /r/{slug}`) while `LinkController` keeps composing `/{slug}`.
- [x] 6.2 Delta specs (`specs/link-management/spec.md`, `specs/dashboard/spec.md`): the explanatory/quoted canonical text moved out of the requirement bodies into an end-of-file "Review notes (non-normative — not requirement content)" section, so composed canonical specs receive only requirement text.
- [x] 6.3 `LinkController::serializeLink` now carries a comment stating the composed path shape must stay in sync with the `link_redirect` route in `config/routes.yaml`, guarded by the new tests. Comment only, no behavior change.
