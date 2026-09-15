# Apply Progress — MVP URL Shortener

## Current status

Phase 1 / PR1 is implemented through the HTTP API slice. The user approved re-slicing instead of accepting a review-budget size exception:

- PR1a — domain persistence: entity, repository, and tests.
- PR1b — domain services: slug generation, expiration policy, and tests.
- PR1c — HTTP API: migration, routes, controller, and functional tests.

Phase 2 (Messenger/worker) and Phase 3 (frontend/dashboard) have not started.

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
- Docker PHP 8.4.25 image builds successfully and includes the required database/XML/AMQP extensions.
- Symfony route debug, container lint, Doctrine mapping validation, migration execution, and post-migration schema validation pass.
- Initial PHPUnit execution exposed a test-infrastructure defect: DAMA's static transaction conflicted with `SchemaTool::createSchema()` in `DatabaseSchemaTestCase::setUpBeforeClass()`.
- `DatabaseSchemaTestCase` now temporarily disables DAMA static connections during idempotent schema drop/create and restores them for tests.
- PHPUnit passes in Docker: Integration 5/5 (9 assertions), Functional 17/17 (102 assertions), full suite 31/31 (122 assertions). Two consecutive full-suite runs also pass.
- Test configuration is aligned on `sqlite:////tmp/url-shortener-test.sqlite`; CI runs migrations and `doctrine:schema:validate --skip-sync` before PHPUnit instead of the redundant `doctrine:schema:create` step.
- PR1c is vertically split for delivery into schema migration, create/list API (including shared route declarations), and redirect API. The create/list unit is 357 added lines and the redirect unit is 102 added lines; shared functional helpers live in the test-harness unit.

## Remaining Phase 1 tasks

- Perform the post-test refactor pass, if evidence identifies duplication or clarity issues.
- Recalculate the review forecast and keep the approved PR1a/PR1b/PR1c boundaries for delivery.

## Scope guard

No Messenger message/handler, transport routing, worker service, frontend API wrapper, dashboard, or browser-flow implementation was added.
