# Archive Report — MVP URL Shortener

- **Change**: `mvp-url-shortener`
- **Archive date**: 2026-09-18
- **Archive status**: ✅ **PASS** (full archive, no partial-archive approval needed)
- **Branch**: `feat/dashboard-browser-flow` at `44dcf0e` (identical to `origin/feat/dashboard-browser-flow`; base `main` at `9d8b89a`)
- **Mode**: `openspec` file-backed archive with **parent-approved archive-time sync fallback** (no prior `sync-report.md` existed; the parent prompt explicitly directed composing the delta specs into `openspec/specs/` during archive)
- **Destructive merge**: none — the canonical spec store `openspec/specs/` was verified empty before composing, so all three domains composed additively as new full specs. No ADDED/MODIFIED/REMOVED delta headers existed in any delta spec (plain `## Requirements` structure); nothing was replaced or removed.

## Structured status consumed

- Native status: change `mvp-url-shortener`, state `ready`, `nextRecommended: archive`, `blockedReasons: []`, all artifacts `done`.
- `actionContext`: mode `repo-local`, workspaceRoot `/home/juan/Proyectos/url-shortener`, allowedEditRoots `[/home/juan/Proyectos/url-shortener]`. All archive writes (canonical specs, archive report, archive move) occurred inside the authoritative workspace. No blockers.

## Artifacts read before archive

- `openspec/changes/mvp-url-shortener/proposal.md`
- `openspec/changes/mvp-url-shortener/specs/{link-management,click-tracking,dashboard}/spec.md`
- `openspec/changes/mvp-url-shortener/design.md`
- `openspec/changes/mvp-url-shortener/tasks.md`
- `openspec/changes/mvp-url-shortener/verify-report.md` (Phase 2 PASS preserved verbatim + Phase 3 PASS with warnings, authoritative)
- `openspec/config.yaml` (archive rule: "Warn before merging destructive deltas" — satisfied: no destructive delta found; additive composition confirmed by inspection before any write)

## Final Task Completion Gate

- Re-read `tasks.md` immediately before sync and move: **zero unchecked `- [ ]` implementation tasks; 39/39 checked** (Phase 1: 19, Phase 2: 12, Phase 3: 9 — matching native status `taskProgress: total 39, completed 39, allComplete: true`).
- No stale-checkbox reconciliation was needed or performed; no checkbox bytes were altered. Historical task, apply-progress, and verify-report bytes are preserved untouched into the archive.

## Canonical spec sync (archive-time fallback, explicitly approved by the parent prompt)

| Domain | Delta source | Canonical target | Operation | Result |
| --- | --- | --- | --- | --- |
| `link-management` | `openspec/changes/mvp-url-shortener/specs/link-management/spec.md` | `openspec/specs/link-management/spec.md` | New full spec (store was empty) | ✅ byte-identical copy |
| `click-tracking` | `openspec/changes/mvp-url-shortener/specs/click-tracking/spec.md` | `openspec/specs/click-tracking/spec.md` | New full spec (store was empty) | ✅ byte-identical copy |
| `dashboard` | `openspec/changes/mvp-url-shortener/specs/dashboard/spec.md` | `openspec/specs/dashboard/spec.md` | New full spec (store was empty) | ✅ byte-identical copy |

**ADDED requirements (12 total, all new canonical requirements):**

- `link-management`: Create short links; Generate unique slugs; Redirect active links; Expire inactive links lazily
- `click-tracking`: Dispatch visit events asynchronously; Process visit events; Run a dedicated worker
- `dashboard`: List links for the dashboard; Create links from the dashboard; Display expiration status; Verify the primary user flow

**MODIFIED requirements: none. REMOVED requirements: none.** No destructive merge guard was triggered.

**Same-domain active change warnings: none.** `mvp-url-shortener` was the only active change; native status `sameDomainActiveChanges: []`.

## Verification findings recorded at close

- **Phase 3 slice verdict**: PASS with warnings (0 critical). **Phase 2 slice verdict**: PASS. **Full change: archive READY** per `verify-report.md`.
- **Resolved warning (not open):** verification flagged that `tasks.md`/`apply-progress.md` overstated the gate fix ("removed both snapshots and added an assertion per test"). This was corrected in commit `44dcf0e` ("docs(sdd): correct gate-fix wording flagged by verification") to match the diff exactly: the real-stack test replaced its imperative snapshot with an API-derived baseline and gained two assertions; the fixture-side test kept its `count()` behind an auto-waiting settle assertion and gained none. **No code changed in that commit**; the substantive fix that verification confirmed is unchanged. Recorded here as **resolved**, not open.
- **Determinism evidence at close**: three consecutive parent-observed zero-flaky runs (`9 passed (5.5s)`, `9 passed (7.0s)`, `6 passed (13.4s)` at `--repeat-each=3`), plus independent verification of 105 test executions across five full suites and two `--repeat-each=3` stress runs — zero flaky, zero failed. Backend regression held at the Phase 2 baseline: `OK (40 tests, 151 assertions)`.
- **Measured size at close**: `frontend/src/api.js` 45 lines (new), `frontend/src/App.jsx` +123/−1 vs scaffold, `frontend/tests/e2e/basic.spec.ts` 225 lines, `frontend/tests/e2e/manual-check.mjs` 44 lines (new) — ≈434 code+test added lines, delivered as a single PR under an explicitly accepted `size:exception` (forecast ~425; Phase 2's accepted range was 400–480). No new frontend dependency; `frontend/package.json`, `package-lock.json`, `docker-compose.yml`, `backend/**`, `.github/**` and `openspec/config.yaml` were never modified in Phase 3.

## Open findings carried forward verbatim as explicit follow-ups (not fixed during archive)

1. **`shortUrl` host/port divergence (real product defect).** The frontend container's nginx sets `proxy_set_header Host $host`, which strips the port, so the backend's generated `shortUrl` is `http://localhost/<slug>` when served through the `:3000` dashboard, where the SPA's `try_files` returns `index.html` for `/<slug>` — the displayed short link is therefore not followable from its own origin. The Vite dev proxy (`:8080`) preserves the port, so the defect is topology-dependent. The fix belongs to backend/proxy surfaces that were outside Phase 3's edit roots. The maintainer has decided to open this as a **separate change after archiving**.
2. **The "manual browser check" is scripted, not a human click-through.** `frontend/tests/e2e/manual-check.mjs` reproduces the expired marker (SQL-backdated `updated_at`) and the asynchronous click count (`0 → 1 → 2` through the worker) with real redirects and no route mocks. Disclosed honestly in the artifacts; the literal human pass remains available to the maintainer and was not performed.

**Environment note (disclosed, not a repository concern):** local verification left two test links (`verifyexp1`, `verifyclick1`) in the local dev database; disclosed in the verification report.

## Worktree handling

- **`openspec/changes/mvp-url-shortener/.gentle-ai-instance`** (untracked, never-committed local session pointer, content `sdd-2a1e1911eea850212f687adce63743c6`): deleted explicitly before the move so `git mv` could not carry it into the archive and it cannot be committed. It was a transient session pointer with no audit value.
- **`backend/config/reference.php`** (pre-existing untracked Symfony-generated file, outside this change): left untouched, untracked.
- Per parent instruction: **no commit and no push were performed by archive** — the working-tree changes (canonical specs + archive move) are left for the parent to commit as one work unit.

## Archived location

- `openspec/changes/mvp-url-shortener/` → **`openspec/changes/archive/2026-09-18-mvp-url-shortener/`** (moved via `git mv`; no collision found; archive directory was empty before the move). Archived contents: proposal, design, exploration, tasks, apply-progress, verify-report, this archive report, and all three delta specs — task truth and historical reports intact, byte-preserved.

## Memory persistence note

Artifact store resolved to `openspec` (authoritative native status); no Engram memory tools were available in this session, so this file plus the canonical specs are the durable record. Nothing was claimed as persisted to memory.

## Key Learnings

- When the canonical spec store is empty, delta specs compose additively as full domain specs; verifying emptiness and the absence of MODIFIED/REMOVED delta headers before any write satisfies the "warn before merging destructive deltas" rule by construction.
- `git mv` of a change directory can silently carry untracked local files (e.g. `.gentle-ai-instance`) into the archive; explicitly removing or relocating them first keeps the archive clean and prevents accidental commits of local-only files.
- Archive-time sync fallback is safe when the parent explicitly approves it and the Final Task Completion Gate (zero `- [ ]` in the persisted tasks artifact) passes first — order matters: gate → sync → report → move.
- A verification warning can be a resolved record-wording issue rather than an open defect; the archive report must label it resolved (with the correcting commit) so future readers don't reopen it.
