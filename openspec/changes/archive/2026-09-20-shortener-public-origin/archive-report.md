# Archive Report — Shortener Public Origin

- **Change**: `shortener-public-origin`
- **Archive date**: 2026-09-20
- **Archive status**: ✅ **PASS** (full archive)
- **Base**: `main` at `3a16c66` (`Merge pull request #4 from juanantoniomoreno/fix/shortener-public-origin`). Source branch `fix/shortener-public-origin` at `675f405`, merged and deleted on the remote; local branch deleted after a clean fast-forward.
- **Workflow**: **ODD**, not SDD. This change was not run through the SDD phases; it has no `design.md`, `exploration.md`, `apply-progress.md`, or `verify-report.md`, and the native SDD status authority was **not consumed**. See "Gate authority" below for how the completion gate was established instead.
- **Mode**: `openspec` file-backed archive. No archive-time sync fallback was needed — the delta specs carried explicit `ADDED`/`MODIFIED` headers, so composition was mechanical rather than a full-spec copy.

## ⚠️ Destructive merge warning (config rule: `archive: Warn before merging destructive deltas`)

**This archive is NOT purely additive.** Unlike the previous archive (`mvp-url-shortener`, where the canonical store was empty and all domains composed as new full specs), this change contains one **MODIFIED** requirement, which replaces canonical text.

The rule requires a warning before merging, so it is recorded here explicitly:

| Domain | Requirement | Operation | Risk |
| --- | --- | --- | --- |
| `dashboard` | `Display expiration status` | **MODIFIED** — requirement body replaced, one scenario added | Replaces existing canonical text |

**Non-lossy proof (measured before writing, not asserted afterwards):**

- Exactly **one line was deleted** across the entire canonical composition. The deleted line was the old requirement body:

```text
The dashboard MUST distinguish active and expired links using the API's computed `isExpired` value and MUST display each link's click count and short URL.
```

- That exact sentence is preserved **verbatim as the first sentence** of the replacement body; the delta appends one further sentence. Nothing was reworded, weakened, or dropped.
- The existing scenario `Show an expired link as inactive` is retained byte-identically; the new scenario `Displayed short URL points at the shortener origin` is appended.
- Requirement count for `dashboard` is unchanged (4 → 4); scenario count 6 → 7. No requirement or scenario was removed anywhere.

## Gate authority

The native SDD status authority was not consumed, because this change ran under ODD. The completion gate was therefore established directly from the persisted artifact:

- `openspec/changes/shortener-public-origin/tasks.md` re-read immediately before the sync and the move: **11 checked (`- [x]`), 0 unchecked (`- [ ]`)** across all six sections.
- No stale-checkbox reconciliation was needed or performed; no checkbox bytes were altered.
- Order followed the established convention: **gate → sync → report → move.**

## Artifacts read before archive

- `openspec/changes/shortener-public-origin/proposal.md`
- `openspec/changes/shortener-public-origin/specs/link-management/spec.md`
- `openspec/changes/shortener-public-origin/specs/dashboard/spec.md`
- `openspec/changes/shortener-public-origin/tasks.md`
- `openspec/specs/{link-management,dashboard}/spec.md` (canonical targets)
- `openspec/config.yaml` (the `archive: Warn before merging destructive deltas` rule — satisfied by the warning and proof above)
- `odd/tasks/short-url-origin.md` (the ODD task doc: decision record, verified outcome, independent verification verdict, follow-ups)

## Canonical spec sync

| Domain | Delta source | Canonical target | Operation | Result |
| --- | --- | --- | --- | --- |
| `link-management` | `openspec/changes/shortener-public-origin/specs/link-management/spec.md` | `openspec/specs/link-management/spec.md` | **ADDED** 2 requirements, appended at end | ✅ requirements 4 → 6, scenarios 10 → 14, +36 lines, purely additive |
| `dashboard` | `openspec/changes/shortener-public-origin/specs/dashboard/spec.md` | `openspec/specs/dashboard/spec.md` | **MODIFIED** 1 requirement (body replaced, scenario added) | ✅ requirements 4 → 4, scenarios 6 → 7, +9/−1, non-lossy (see warning above) |

**ADDED requirements (2):**

- `link-management`: **Build the short URL from the configured shortener origin** — the `shortUrl` MUST be an absolute URL built from the configured `SHORTENER_BASE_URL`, independent of the request Host.
- `link-management`: **Short URL resolves through the redirect endpoint** — following a returned `shortUrl` MUST reach the redirect handler, returning `302` for an active link or `410` for an expired one, never the SPA.

**MODIFIED requirements (1):** `dashboard`: **Display expiration status** — extended so the displayed URL MUST be the API-returned absolute `shortUrl` at the configured shortener origin, rather than one derived from the dashboard's own origin or the proxied request host.

**REMOVED requirements: none.**

New requirements were appended at the end of `link-management/spec.md` rather than interleaved near their thematic neighbours, to keep the composition strictly additive and the diff minimal. Requirement order carries no semantic weight in this store.

**Same-domain active change warnings: none.** `shortener-public-origin` was the only active change.

## Specification gap this change closed

The canonical specs previously required only that the dashboard *display* a short URL — never that it be absolute, that it originate anywhere in particular, or that it resolve. That is precisely why the defect shipped behind a fully green suite: the contract omitted the property that mattered. The two ADDED requirements and the MODIFIED one now encode it, and two functional tests couple the generated `shortUrl` to the declared route so a divergence fails the suite.

## Verification findings recorded at close

- **Independent verification** (`gentle-ai-verify`, read-only): **PASS with warnings.** Every numeric claim was re-executed, and the arithmetic cross-check (41−1=40 tests, 156−5=151 assertions) matched the historical `HEAD` baseline exactly, proving the change added one test and nothing else. Scope compliance clean.
- **All three warnings closed** before delivery: (1) the new spec MUST had no automated coverage → two regression-guard tests added that derive the expected path from the route collection and follow the *returned* `shortUrl`; (2) explanatory blockquotes sat inside requirement bodies and could have been composed into `openspec/specs/**` → moved to non-normative review notes in the delta files; (3) the route coupling was undocumented in code → commented at the composition site.
- **Final measured state**: full suite `OK (43 tests, 170 assertions)` on PHP 8.4.25, re-run by the parent gate on the final bytes. Live: through `:3000` the API returns `http://localhost:8080/<slug>`, and following it returns `410 application/json` for the expired seeded link instead of `200 text/html`.
- **Measured size**: 12 files, +365/−3 versus the pre-change `main` (`35feabc`). Delivered as PR #4, all three CI checks green (backend 14s, docker 2m9s, frontend 15s).

## Open findings carried forward as explicit follow-ups (not fixed during archive)

1. **`SHORTENER_BASE_URL` shape is unvalidated.** A malformed value (`SHORTENER_BASE_URL=` or `short.example`) yields a non-absolute `shortUrl` that violates the newly added requirement, with HTTP 200 and no failing test. Where to fail — at boot (fail-fast) or at the first request — is an undecided design choice.
2. **`backend/Dockerfile` bakes the committed `backend/.env` into the image** (`COPY . .` with no `.dockerignore`), so a deployment that forgets to override `SHORTENER_BASE_URL` silently serves `http://localhost:8080/<slug>`. The fail-loud property holds only when `.env` is absent.
3. **Playwright E2E does not run in CI.** The `frontend` job performs only `npm ci` and `npm run build`, so the E2E suite never executes on pull requests. Note: it would **not** have caught this defect — the real-stack test compared the host-agnostically (`RegExp('/'+slug+'$')`), so an origin assertion would also have been needed.
4. **`/<slug>` on `:3000` still serves the SPA.** Accepted, documented behavior by the maintainer's decision, **not a defect of this change**: with short links pointing at the shortener origin, the SPA's single-segment namespace collision is moot rather than fixed. `frontend/nginx.conf` was deliberately left untouched.
5. **Neither `README.md` nor `AGENTS.md` mentions any environment variable**, so `SHORTENER_BASE_URL` is documented only in `backend/.env.example`. A deployment reading the README alone would not know it must set the public shortener origin.

### Corrected finding (do not reopen as a product decision)

A previously recorded finding — "a delayed visit revives an expired link; needs an explicit product decision" — was **re-derived from the code and found to be unreachable in normal operation**. `LinkExpirationPolicy::isExpired()` is pure derived state over `updatedAt`; `Link::markVisited()` moves `updatedAt` forward; and `LinkRedirectController::redirect()` dispatches `LinkVisited` only for a link that is already active. Consuming a visit can therefore only keep a link active. The behavior observed on 2026-09-18 required backdating `updated_at` by 31 days *while a message sat unconsumed in the queue* — an artifact of the manual test's hand-manipulated state, not a production sequence. The only reachable variant is a message delayed more than thirty days in transit. **Recorded as documented theoretical edge case, not an open decision.**

## Worktree handling

- The change directory contained **only four tracked files** and no untracked or hidden entries — in particular no `.gentle-ai-instance` session pointer, so `git mv` could not carry a local-only file into the archive.
- `backend/config/reference.php` (pre-existing untracked Symfony-generated file, outside this change) was left untouched.
- **No commit and no push were performed by this archive.** The working-tree changes (canonical specs, archive move, this report) are left for the parent to commit as one work unit.

## Archived location

- `openspec/changes/shortener-public-origin/` → **`openspec/changes/archive/2026-09-20-shortener-public-origin/`** (moved via `git mv`; no collision — the archive directory was otherwise empty before the move). Archived contents: `proposal.md`, `tasks.md`, both delta specs, and this archive report. Task truth and delta history intact, byte-preserved.

## Key Learnings

- **A non-additive archive needs its warning and its proof up front.** The config rule `Warn before merging destructive deltas` is satisfied by measuring the composition before writing (requirement/scenario counts and a deleted-line count) rather than by asserting safety afterwards. Counting deleted lines across the whole composition is the cheapest decisive proof: one deleted line, whose text survives verbatim inside the replacement, is non-lossy by construction.
- **An ODD-run change can still use the OpenSpec archive convention**, but it must not imply the native SDD authority was consulted. The gate has to be established from the persisted `tasks.md` and that substitution stated explicitly in the report.
- **Appending new requirements beats interleaving them.** Order carries no semantic weight in this store, and appending keeps the composition strictly additive — the safest possible diff against a canonical contract.
- **A defect that ships behind a green suite is usually a missing contract, not a missing test.** The specs here never required the short URL to resolve, so no test could be expected to assert it. Fixing the spec was the substantive part of the change.
