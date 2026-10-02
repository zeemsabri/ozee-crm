# A05 — Runs, logs, retention, observability

Owner's gaps: "how clear the log is and how long log stays". Legacy facts (survey §8): full context copied per step, no run entity, statuses `failed` vs `error`, siblings mis-parented under AI steps, delayed steps logged twice, pagination broken (30 newest rows only), token usage rendered `[object Object]`, no retention, no redaction, any authenticated user could read all contexts.

## 1. Clarity rules (enforced by the schema and the UI)
1. **One run = one row.** Status, trigger, subject, actor, version, timing, counts, error summary — the Hub and notifications read the run, never fold step rows.
2. **One step attempt = one `step_runs` row**, nested by `loop_path` and ordered by `started_at`. Retries are attempts 2..n of the same row group; suspend/resume is one row (`suspended` → `resumed` timestamps), not two.
3. **Every row says why**: conditions store the evaluated rules with both sides' values; skips store the guard expression and its value; failures store `error_code` (stable vocabulary: `validation`, `permission_denied`, `not_found`, `timeout`, `http_4xx`, `http_5xx`, `ai_failed`, `ai_cost_cap`, `expression`, `loop_detected`, `cancelled`, `unknown`) plus a human message and, where known, a **suggestion**.
4. **English summaries everywhere**: `StepSummary`/`TriggerSummary` produce "Fetched 12 leads", "Created task OZ-1290", "Sent 'Follow-up' for approval", "Waited 3 minutes", "Approved by Zeeshan" — the timeline reads like a story; JSON is one click away, not the default.
5. **Effects are explicit**: `run_effects` rows link every record touched; the record's page shows the automation that touched it.
6. **Inputs are the resolved values** the step used (post-expression), so "why did it do that?" is answered without mentally evaluating templates; the raw config is available in the version.
7. **Context is reconstructable, not duplicated**: `context_delta` per step; the UI folds deltas on demand ("Context at this step").
8. **Warnings are visible**: `run_events` (empty loop, truncated output, deprecated field, drift) appear inline in the timeline with amber markers; a run with warnings shows "Succeeded with 2 warnings".
9. **Live**: running runs stream step updates over Echo (`private-automation.run.{id}`); the Hub shows a "running" pulse.
10. **Pagination and filters work** (server-side, cursor pagination; tested).

## 2. Retention
- Settings: `automation.retention_days` (workspace default 90), per-workflow override, failed runs kept 2×, test runs 7 days, `run_effects` kept as long as the run.
- Nightly `PruneRunsJob`: aggregates to `automation_daily_stats` then deletes by `retention_until` in chunks of 1,000; step outputs larger than 64 KB are stored as `attachments(purpose=automation_output, expires_at=retention)` and pruned with them.
- Size caps: outputs 64 KB inline; trigger payload 32 KB inline (rest attached); `context_delta` excludes fields flagged sensitive; a run's total storage capped at 5 MB (beyond → outputs truncated with a warning).
- Legal hold: `workflows.retention_days = 0` means "keep forever" (L3+ only), used for finance automations.

## 3. Redaction
- Fields marked `sensitive` in the ModelCatalogue (payout details, credentials, PIN hashes, tokens, `id_number`) are replaced by `"[redacted]"` before persistence in `trigger_payload`, `inputs`, `outputs`, `context_delta`.
- HTTP bodies are never stored (sizes and status only); headers with `Authorization`/`*-Key` are dropped.
- AI prompts/responses are stored in `ai_reviews` (Ai module policy) and referenced, not duplicated; message bodies inside AI inputs are truncated to 4,000 chars in the stored copy (the model still receives the configured amount).
- Access: `RunPolicy::view` = `automations.view` on the owning team (or `view_all`); step inputs/outputs additionally require `automations.author`; contexts of workflows touching finance/people require L3.

## 4. Notifications and alerts
- On run failure: in-app + optional email to the workflow's `on_failure_notify` (default team lead), one notification per failure burst (grouped per workflow per 15 min), deep link to the run.
- Workflow health: `failed_runs_count_30d` and a "failing" badge when failure rate > 20% over ≥5 runs; auto-pause option after N consecutive failures (`settings.automation.auto_pause_after`, default 10) with notification.
- Stuck detection: runs `waiting` past their timeout or `running` > 30 min → `timed_out` + alert; scheduler drift alert if `DispatchScheduledRunsJob` misses > 2 minutes.
- AI cost cap per run and per day per workspace (`ai_cost_cap`); exceeding → step fails with `ai_cost_cap`, workflow paused, owner notified.
- Metrics dashboard (Hub header + `/automations/insights`): runs/day, success rate, p95 duration, AI cost by workflow, top failing steps — charts via `OzeeChart`.

## 5. Operational commands
`automation:prune`, `automation:replay {run} [--dry]`, `automation:validate-all` (re-validates published versions after a module change — reports drift such as a removed field), `automation:export {workflow}`, `automation:stats:rebuild`, `automation:pause-all` / `resume-all`.
