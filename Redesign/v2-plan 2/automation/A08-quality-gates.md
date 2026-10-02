# A08 — Quality gates specific to Automation (in addition to `../08-quality-gates.md`)

## 1. Engine semantics test suite (table-driven, `modules/Automation/Tests/Engine/`)
Each case = a small workflow tree (JSON) + trigger payload + expected `step_runs` sequence (status, decision, loop_path) + expected outputs + expected effects. Mandatory cases:
1. Sequential steps; step after condition runs exactly once regardless of branch taken.
2. Condition `all`/`any`, every operator × type (string/number/date/bool/null/list) including `"0"` not empty, `null != ''`, date vs string.
3. Loop: empty list (0 iterations, warning), 1,000 items cap, `break_if`, `collect`, nested loops with `loop.parent`, concurrency batching of AI steps.
4. `on_error` halt/continue/branch; retry with backoff then failure; idempotent create on retry (no duplicate record).
5. Suspension/resume for delay, AI, HTTP, approval, event — at root, inside `then`, inside a nested loop at index 3; resumed run produces identical `step_runs` to an uninterrupted run except `suspended/resumed` timestamps.
6. Process crash mid-step (simulated exception after DB write) → job retry resumes without duplicate effects.
7. Guards: `if` false → skipped; depth limit; loop detection (workflow that triggers itself) → `loop_detected`.
8. Permission denied inside a step (actor lacks capability) → `failed permission_denied`, no partial write.
9. Occurrence dedupe (same event delivered twice) → one run; `per_subject` concurrency queues the second run.
10. Redaction: sensitive fields never present in any persisted JSON (grep across `workflow_runs`, `step_runs`, `run_events`, attachments).
11. Retention prune keeps stats, removes rows, respects legal hold.
12. Version immutability: editing a draft while a run is mid-flight does not change the run's behaviour.

## 2. Expression fixtures
`tests/Fixtures/expressions/*.json`: ≥300 cases (path resolution, filters, literals, escaping, missing paths, type coercion, dates with timezone). Both the PHP evaluator and the TS mirror run the same fixtures in CI; any divergence fails the build.

## 3. Publish validation fixtures
One invalid workflow per rule in A03 §6 → `validate` returns the expected issue code and step key; one valid "kitchen sink" workflow using every step type publishes.

## 4. Replay fixtures from legacy
For each template in A06: 5 legacy trigger samples (converted) + expected effects. Run as dry runs in CI (AI/HTTP faked with recorded responses).

## 5. Performance budgets
- Dispatcher: event → run created ≤ 50 ms p95 at 10k events/hour; filtered-out events ≤ 5 ms.
- Runner: overhead per step (excluding handler work) ≤ 15 ms; a 50-step run without waits ≤ 3 s.
- Fetch step: query compiler must produce indexed queries for `where` on indexed columns; `EXPLAIN` check on 6 common templates.
- Run detail API for a 500-step run ≤ 400 ms; runs list ≤ 300 ms with filters.
- Canvas: 200 nodes interactive at 60 fps; property edit re-renders ≤ 2 nodes.
- Storage: average run ≤ 50 KB; step outputs capped 64 KB inline.

## 6. Security checklist
Credentials write-only and encrypted; HTTP allow-list enforced (test: URL to non-allowed host fails before any request); SSRF: private IP ranges and `localhost` blocked; webhook HMAC + timestamp window 5 min + replay cache; policies: view/author/publish/run/credentials matrix tested for L0–L4; actor cannot exceed team reach (test: fetch_records on a business the team doesn't serve returns nothing); logs redacted; export excludes credentials; import validates against catalogue and requires author permission; publish of guarded capabilities requires the level in master 09 §2.

## 7. Definition of done additions for automation tickets
Handler tickets: configSchema + outputSchema + validate + execute tests + `/dev/ds` form story. UI tickets: three-viewport screenshots (phone read mode), keyboard path for canvas actions, English summary snapshot tests. Migration tickets: dry-run report attached, zero secrets check, owner sign-off recorded in `docs/migration/automation-signoff.md`.
