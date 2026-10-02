# A02 — Automation schema (migration order 10; conventions per `../02-database-schema.md`)

All tables: `id BIGINT UNSIGNED`, `workspace_id FK workspaces CASCADE`, timestamps, enums as VARCHAR(32)+CHECK.

### `workflows`
| column | type | notes |
|---|---|---|
| public_id | CHAR(26) UNQ | |
| team_id | FK teams RESTRICT | owning team (AD9) |
| name | VARCHAR(160) | |
| slug | VARCHAR(80) | `UNQ(workspace_id, slug)` |
| description | TEXT NULL | |
| status | enum `WorkflowStatus`: draft, active, paused, archived | `active` = has a published version and trigger enabled |
| visibility | enum: team, workspace | |
| trigger_kind | enum `TriggerKind`: event, schedule, manual, webhook | denormalised from the published trigger step for listing/indexing |
| trigger_event | VARCHAR(80) NULL | e.g. `task.status_changed`; `IDX(workspace_id, trigger_event, status)` — the dispatcher's lookup |
| published_version_id | FK workflow_versions NULL | |
| draft_version_id | FK workflow_versions NULL | the editable one |
| concurrency | enum: parallel, per_subject, serial | default per_subject |
| ignore_own_events | BOOL DEFAULT 1 | |
| on_failure_notify | JSON | `{team_lead: true, users: [], channel: 'notification'|'email'}` |
| retention_days | SMALLINT DEFAULT 90 | run retention override (NULL = workspace setting) |
| capabilities | JSON | computed at publish: list of capability slugs |
| last_run_at, last_run_status | | denormalised for the Hub |
| runs_count, failed_runs_count_30d | INT | maintained by `RunFinished` listener |
| created_by_id, updated_by_id | FK users NULL | |
| archived_at, deleted_at (SD) | | |
Legacy: `workflows` (name, is_active → status, trigger_event).

### `workflow_versions`
`id`, `workflow_id FK CASCADE`, `number SMALLINT` (`UNQ(workflow_id, number)`), `status` enum `VersionStatus`: draft, published, superseded, `tree JSON` (**the full step tree as saved by the builder** — canonical for editing and for the runner; steps are also normalised into `workflow_steps` for querying/validation), `layout JSON` (canvas positions per step key — UI only, never read by the engine), `validation JSON NULL` (issues at last validate), `published_at`, `published_by_id`, `change_note VARCHAR(255) NULL`, `checksum CHAR(64)`.
Rule: runs reference a version; a published version is immutable; editing creates/updates the draft.

### `workflow_steps` (normalised projection of `versions.tree`, rebuilt on save)
`id`, `version_id FK CASCADE`, `key VARCHAR(60)` (`UNQ(version_id, key)`), `type` enum `StepType` (A03), `parent_key VARCHAR(60) NULL`, `branch` enum `BranchKind` NULL: then, else, body, approved, rejected, on_failure, `sort SMALLINT`, `name VARCHAR(120)`, `config JSON`, `guard TEXT NULL` (per-step `if` expression), `on_error` enum `OnError`: halt, continue, branch (default halt), `retry JSON NULL` (`{max, backoff_seconds}`), `timeout_seconds INT NULL`, `capability VARCHAR(80) NULL` (computed), `references JSON` (step keys this step's expressions read — for validation and impact analysis). Legacy: `workflow_steps` (step_config, condition_rules, `_parent_id`/`_branch`, step_order, delay_minutes → a `delay` step or `delay_seconds` on the step).

### `workflow_schedules`
`id`, `workflow_id FK CASCADE`, `kind` enum: simple, cron, `every` enum NULL: hourly, daily, weekly, monthly, `at TIME NULL`, `weekdays JSON NULL`, `day_of_month TINYINT NULL`, `cron VARCHAR(100) NULL`, `timezone VARCHAR(64)`, `is_active BOOL`, `next_run_at TIMESTAMP NULL` (`IDX(is_active, next_run_at)`), `last_run_at NULL`, `last_run_id FK NULL`. Legacy: `schedules` rows with `scheduled_item_type=workflow`.

### `trigger_subscriptions` (derived index for the dispatcher)
`workflow_id FK CASCADE`, `event VARCHAR(80)`, `filter JSON NULL` (compiled rule list), `is_active BOOL`. PK `(workflow_id, event)`, `IDX(workspace_id, event, is_active)`. Rebuilt on publish/pause.

### `webhook_triggers`
`id`, `workflow_id FK CASCADE`, `slug VARCHAR(60) UNQ`, `secret_hash CHAR(64)`, `allowed_ips JSON NULL`, `last_received_at`, `received_count`.

### `workflow_runs`
| column | type | notes |
|---|---|---|
| public_id | CHAR(26) UNQ | shown as `Run #ABC123` |
| workflow_id | FK CASCADE | |
| version_id | FK RESTRICT | |
| occurrence_id | VARCHAR(191) | `UNQ(version_id, occurrence_id)` |
| trigger_kind | enum | |
| trigger_event | VARCHAR(80) NULL | |
| subject_type / subject_id | morph NULL | the triggering record (`IDX`) |
| actor_type / actor_id | morph | `automation` + workflow id, or the user for manual runs |
| status | enum `RunStatus`: queued, running, waiting, succeeded, failed, cancelled, timed_out, loop_detected | `IDX(workflow_id, status, created_at)`, `IDX(status, updated_at)` |
| cursor | JSON NULL | resume point |
| trigger_payload | JSON | redacted copy of the trigger payload (A05 §3) |
| vars | JSON NULL | current `vars.*` |
| step_count, succeeded_count, failed_count, skipped_count | SMALLINT | |
| tokens_in, tokens_out, ai_cost | INT/INT/DECIMAL(12,6) | |
| started_at, finished_at, waiting_since, resume_at | TIMESTAMP NULL | `IDX(resume_at)` for delays |
| error_summary | VARCHAR(500) NULL | first failing step + message |
| parent_run_id / parent_step_key | FK runs NULL / VARCHAR(60) | for sub-workflows |
| depth | TINYINT | automation chain depth |
| retention_until | DATE NULL | `IDX` — set at finish from workflow/workspace policy |
Legacy: no equivalent (`execution_logs` grouped by `execution_id`).

### `step_runs`
`id`, `run_id FK CASCADE`, `step_key VARCHAR(60)`, `step_type`, `attempt SMALLINT DEFAULT 1`, `loop_path VARCHAR(120) NULL` (e.g. `loop_leads[3]` or `loop_a[2]/loop_b[0]`), `status` enum `StepRunStatus`: started, succeeded, failed, skipped, suspended, resumed, cancelled, `started_at`, `finished_at`, `duration_ms INT NULL`, `inputs JSON NULL` (the *resolved* config values — what the step actually used, redacted), `outputs JSON NULL` (typed per `outputSchema`, size-capped 64 KB with `truncated:true` and an attachment for the full payload), `context_delta JSON NULL`, `decision VARCHAR(32) NULL` (conditions: then/else; approvals: approved/rejected), `error_code VARCHAR(60) NULL`, `error TEXT NULL`, `ai_review_id FK ai_reviews NULL`, `http JSON NULL` (`{method,url,status,duration}`, no bodies), `idempotency_key VARCHAR(191) NULL`. `IDX(run_id, started_at)`, `IDX(step_key, status)`.
Legacy: `execution_logs` (input_context full copy → `context_delta`; raw_output/parsed_output → outputs; token_usage → run totals + ai_review).

### `run_effects` (what a run changed — the "which automation touched me" link)
`id`, `run_id FK CASCADE`, `step_key`, `subject_type/subject_id` morph, `effect` enum: created, updated, deleted, submitted, notified, called_api, `summary VARCHAR(255)`, `created_at`. `IDX(subject_type, subject_id)` — shown on task/thread/contact pages as "Automation: <workflow> did X".

### `run_events` (timeline annotations)
`id`, `run_id FK CASCADE`, `at TIMESTAMP(3)`, `level` enum: info, warning, error, `message VARCHAR(500)`, `data JSON NULL`. Used for warnings that are not failures (empty loop, filtered token, deprecated field).

### `credentials`
`id`, `workspace_id`, `team_id FK NULL` (NULL = workspace-wide, L3+), `name VARCHAR(80)` (`UNQ(workspace_id, name)`), `kind` enum: bearer, basic, header, oauth2_client, `secret TEXT` (encrypted cast), `allowed_hosts JSON` (e.g. `["api.bugherd.com"]`), `last_used_at`, `created_by_id`, `rotated_at`. Never returned by the API after write.

### `workflow_templates`
`id`, `workspace_id NULL` (NULL = built-in), `name`, `description`, `category`, `tree JSON`, `layout JSON`, `required_capabilities JSON`. Seeded from the migrated legacy workflows generalised (A06 §4).

### `automation_daily_stats`
`workspace_id, workflow_id, date, runs, succeeded, failed, waiting, avg_duration_ms, tokens_in, tokens_out, ai_cost` PK `(workflow_id, date)`.

### Retention & cascade
- `workflow_runs.retention_until` = finished_at + (`workflows.retention_days` ?? `settings.automation.retention_days` (default 90)); failed runs keep 2× the period; `PruneRunsJob` nightly deletes runs (cascades step_runs/effects/events) and writes counts to `automation_daily_stats` first so history survives.
- Deleting a workflow = archive (soft); hard delete only when no runs remain.
- `workflow_versions` never deleted while referenced by runs.
