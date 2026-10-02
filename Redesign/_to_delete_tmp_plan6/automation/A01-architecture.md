# A01 — Architecture of `Modules\Automation`

## 1. Module layout
```
modules/Automation/
  Models/          Workflow, WorkflowVersion, WorkflowStep, WorkflowSchedule, WorkflowRun, StepRun, RunEvent, Credential, TriggerSubscription
  Enums/           WorkflowStatus, VersionStatus, TriggerKind, StepType, RunStatus, StepRunStatus, OnError, BranchKind
  Engine/          Runner (executes a run from its cursor), Cursor, Context, StepExecutor (dispatch to handlers), Suspension (AI/HTTP/delay/approval), Resumer
  Engine/Steps/    one handler per StepType implementing StepHandler { validate(config, schema): Issues; execute(StepContext): StepResult; outputSchema(config): JsonSchema }
  Expressions/     Parser, Evaluator, Filters (date, string, number, list), PathResolver, SchemaBuilder (what tokens exist at a given step → for the picker and for publish-time validation)
  Triggers/        EventCatalogue (all publishable events with payload schemas), EventListener (one listener bound to Platform's DomainEventBus), ScheduleDispatcher, ManualTrigger
  Actions/         (module actions used by the UI) CreateWorkflow, SaveDraft, PublishVersion, ArchiveWorkflow, DuplicateWorkflow, ImportWorkflow, ExportWorkflow, RunNow, RetryRun, CancelRun, ReplayRun (dry), UpsertSchedule, StoreCredential
  Catalogue/       ActionCatalogue (published module actions callable from steps, with input/output schemas), ModelCatalogue (models/fields/relations readable by fetch/create/update steps, permission-filtered)
  Jobs/            ExecuteRunJob, ResumeRunJob, DispatchScheduledRunsJob, AiStepJob, HttpStepJob, PruneRunsJob
  Http/            Controllers (Inertia pages + /api/v1/automation/*), Requests, Resources
  Policies/        WorkflowPolicy, RunPolicy, CredentialPolicy
  Database/        migrations (order 10), seeders (built-in templates), factories
  Tests/
```
Frontend: `resources/js/features/automation/**` (React Flow canvas, properties panel, pickers, run timeline), pages under `pages/Automation/*`.

## 2. Engine design

### 2.1 Definitions
- A **published version** is an immutable tree: `steps[]` each `{key, type, config, on_error, retry, parent_key, branch, sort}`. Root steps in order; a `condition` owns `then`/`else` branches; a `loop` owns `body`; a `wait_approval` owns `approved`/`rejected` branches.
- A **run** = `(version, trigger occurrence)`. Runs are queued (`ExecuteRunJob`, queue `automation`), never executed inside a web request (manual "Run now" queues too; the UI polls/echoes the run).
- **Context** is a JSON document built as the run progresses: `trigger` (typed payload from the event catalogue, e.g. `trigger.task.title`, `trigger.changes.status.from/to`), `steps.<key>.<output>` (typed per handler `outputSchema`), `vars.<name>`, `loop.item|index|first|last|parent`, `actor`, `workspace`, `now`. Context is **immutable per step**: a step receives the context and returns outputs; the runner merges `steps.<key> = outputs`. No handler mutates `trigger`.
- **Cursor** = `{step_key, loop_stack:[{step_key,index}], phase}` persisted on the run; the runner is a pure function `(version, context, cursor) → next`; any process can resume.

### 2.2 Execution algorithm
1. `ExecuteRunJob` loads run (lock `run:{id}`), sets `running`, walks from cursor.
2. For each step: validate preconditions (permissions of actor for the target action/model), evaluate `if` (optional per-step guard expression) → skip with `skipped`; call handler with timeout; write `step_runs` row (`started` → `succeeded|failed|skipped|suspended`), store `outputs`, `error`, `duration`, and `context_delta` (only the keys added by this step; the runner can reconstruct any step's full input by folding deltas).
3. On `failed`: apply `on_error` — `halt` (run `failed`, notify owners), `continue` (record and proceed), `branch` (jump to the step's `on_failure` branch). `retry: {max, backoff}` retries the same step with an idempotency key before applying `on_error`.
4. On `suspended` (AI, HTTP async, delay, wait_approval, wait_event): the run status becomes `waiting`, cursor stored, a resume token issued; the resumer job re-enters at the same cursor with the step's result injected. Siblings are never re-executed; loops continue from `loop_stack`.
5. Condition: evaluates its rule list once (`all`/`any`), records the decision as the step output `{result: true|false, evaluated: [...]}`, enters the branch; after the branch, continues with the next sibling of the condition (explicitly — legacy lost edges here).
6. Loop: `over` expression → list; per item runs `body` with `loop` scope; supports `break_if`, `max_items`, `concurrency` (AI steps in loops are batched), `collect` (outputs of a named body step → `steps.<loop_key>.items[]`). Empty/non-list → 0 iterations, `warning` recorded (not silent).
7. Run ends `succeeded | failed | cancelled | timed_out`; `RunFinished` event; counters; notifications per workflow settings (on failure always to the owning team lead).
8. Guards: max 500 step runs per run, max nesting 8, max 1,000 loop items, run wall-clock 30 min excluding waits, waits max 30 days.

### 2.3 Idempotency and concurrency
- Trigger occurrences carry an `occurrence_id` (`event:{uuid}` or `schedule:{id}:{minute}`); `UNQ(workflow_version_id, occurrence_id)` on runs prevents duplicates.
- Per-workflow `concurrency: parallel|per_subject|serial` — `per_subject` (default) queues a second run for the same subject (e.g. the same task) until the first finishes.
- Steps that create records write `steps.<key>.idempotency_key` = `run:{id}:step:{key}[:loop index]`; `create_record` actions accept it and no-op on repeat.

### 2.4 Suppression / feedback loops
- Every domain event carries `actor`. Workflows have `ignore_own_events: true` by default (events caused by this workflow's runs don't retrigger it) and a global depth limit: a chain of automation-caused events deeper than 5 stops with a `loop_detected` run.
- `Platform\DomainEventBus` provides `withoutAutomation(fn)` for migrations/imports.

## 3. Triggers
### 3.1 Event catalogue (published by modules; each has a JSON schema + sample payload)
Crm: `contact.created`, `contact.updated`, `contact.converted`, `contact.stage_changed`, `enquiry.created`, `deal.stage_changed`, `deal.won`, `deal.lost` · Work: `project.created`, `project.status_changed`, `milestone.submitted|approved|completed`, `task.created`, `task.updated` (with `changes`), `task.status_changed`, `task.completed`, `task.assigned`, `deliverable.submitted|reviewed`, `standup.submitted`, `meeting.created` · Comms: `message.received` (inbound, after ingestion + screening), `message.sent`, `message.returned`, `thread.needs_reply_breached`, `thread.moved` · Finance: `invoice.sent|paid|overdue`, `bill.submitted|approved|paid`, `proposal.submitted|accepted|rejected`, `payment.recorded` · Platform: `comment.added`, `approval.decided`, `attachment.stored` · Identity: `user.created`, `user.deactivated` · Automation: `workflow.run_failed` · Custom: `webhook.received:<slug>` (inbound webhook trigger with secret) · Schedule: `schedule` · Manual: `manual` (from UI/API with an optional subject).
Payload shape: `{event, occurred_at, actor:{type,id,name}, workspace_id, subject:{type,id}, <entity>: {...DTO...}, changes?: {field:{from,to}}, related?: {...}}`. Payload DTOs are the same `spatie/laravel-data` DTOs the pages use, so the picker's field list equals the real payload.
### 3.2 Filters on the trigger node
`filter` = rule list evaluated before creating a run (cheap, indexable where possible: e.g. `changes.status.to == 'done'`, `task.project.tier in [growth, partner]`). Filtered-out occurrences are counted (`workflow.skipped_count`) but not stored as runs, so a hot event like `task.updated` doesn't flood the run table.
### 3.3 Schedules
`workflow_schedules`: `kind simple|cron`, `every: hourly|daily|weekly|monthly`, `at`, `weekdays[]`, `timezone` (workspace default), `cron`, `next_run_at`, `last_run_at`, `is_active`. `DispatchScheduledRunsJob` every minute: `SELECT … WHERE next_run_at <= now FOR UPDATE SKIP LOCKED` → create run with `occurrence_id schedule:{id}:{minute}` → advance `next_run_at`. Schedule-triggered contexts start with `trigger.schedule{…}` and the first step is usually `fetch_records`.
### 3.4 Manual and API
`POST /api/v1/automation/workflows/{id}/run {subject?: {type,id}, input?: {...}}` (permission `automations.run`); the Hub "Run now" uses it; `trigger.manual.input` exposed.

## 4. Teams, permissions, actor (the "new structure" requirement)
- `workflows.workspace_id`, `workflows.team_id` (owner team), `workflows.visibility` `team|workspace`.
- Permissions (added to the actions catalogue, module `automation`): `automations.view` (see workflows/runs of teams you belong to; L2+), `automations.author` (create/edit drafts for own team; L2+), `automations.publish` (publish; L3+ when the version contains steps in modules `finance`, `dns`, `people`, `client_data`, else L2+), `automations.run` (run now / retry / cancel), `automations.manage_credentials` (L3+), `automations.view_all` (workspace-wide; L3+).
- **Actor model**: a run acts as `Actor::automation($workflow)`; its effective permissions = the owning team's *service account* permissions: the intersection of the team's modules with the workflow's declared `capabilities` (computed at publish from the steps: e.g. `work.tasks.write`, `crm.contacts.write`, `comms.messages.submit`, `finance.bills.read`). Publishing shows the capability list ("This automation can: create tasks, update contacts, submit emails for approval") and requires an approver of sufficient level for guarded capabilities (three-ceiling rules from master 09 §2). At runtime every action call passes through the same `Permissions::can(actor, action, subject)` as a human — a workflow cannot reach a business its team doesn't serve; violations fail the step with `permission_denied`.
- Reach: `fetch_records` queries are automatically scoped to what the actor can see (team clients, project membership), never raw table scans.
- Audit: every write performed by a run is activity-logged with `causer = automation:<workflow>#<run>`, and the run timeline links to the records it touched (`run_effects`).

## 5. Integration with Comms approval (D8) and other built-in pipelines
Built-in pipelines are code, not workflows: outbound approval (Comms), inbound screening (Comms), bill approval chains (Finance), deliverable review (Work). Automation **extends** them via events and actions: e.g. "when `message.received` from a contact with no project → AI classify → create enquiry" (WF18 replacement), "when `message.sent` to a lead → schedule follow-up in 3 days" (WF13/21). Actions exposed to steps by Comms: `comms.compose_message`, `comms.submit_message` (enters the approval pipeline; never sends directly), `comms.add_thread_note`, `comms.move_thread`, `comms.categorise_thread`, `comms.mark_private`. There is deliberately **no** `comms.send_now`.

## 6. Action catalogue (what a step can *do*)
Each module registers actions with `ActionCatalogue::register(new ActionDefinition(slug, label, module, capability, inputSchema, outputSchema, handler))`. R1 set: Crm `create_contact, update_contact, convert_contact, add_contact_note, create_enquiry, move_deal_stage`; Work `create_task, update_task, assign_task, transition_task, create_milestone, submit_deliverable, add_comment, create_standup_reminder, create_meeting`; Comms (§5); Finance `create_invoice_draft, add_ledger_entry, record_payment (L3 publish)`; Platform `add_comment, assign_terms, request_approval, send_notification (to users/roles/teams), send_internal_email, store_attachment_from_url, http_request (credential-scoped), log_message`; Ai `run_prompt (structured JSON), classify, summarise, extract_fields`; Automation `run_workflow (sub-workflow with inputs/outputs), set_variables, wait_for_approval, wait_for_event, delay`. Generic `create_record`/`update_record`/`fetch_records`/`sync_relationship` remain (admin-level, model catalogue permission-filtered) so nothing authorable today becomes impossible.

## 7. HTTP surface
Web (Inertia): `/automations` (Hub), `/automations/{id}` (Builder), `/automations/{id}/runs` (+ `/runs/{run}` timeline), `/automations/credentials`, `/automations/prompts` (→ Ai module pages), `/automations/templates`.
API v1 (`/api/v1/automation/*`, `auth:sanctum` + permissions): `workflows` CRUD, `workflows/{id}/versions` (list, `POST publish`, `POST revert`), `workflows/{id}/draft` (GET/PUT the editable tree), `workflows/{id}/validate`, `workflows/{id}/run`, `workflows/{id}/duplicate`, `workflows/{id}/export` / `POST import`, `workflows/{id}/schedules` CRUD, `runs` (filters: workflow, status, subject, date), `runs/{id}` (timeline with step runs + effects), `runs/{id}/retry|cancel|replay`, `catalogue/events`, `catalogue/actions`, `catalogue/models` (+ `/{model}/fields`, `/relations`), `catalogue/tokens?workflow=&step=` (tokens available at a step, from `SchemaBuilder`), `expressions/evaluate` (dry evaluation against a sample context), `credentials` CRUD (values write-only), `webhooks/{slug}` (inbound trigger, HMAC), `prompts` (proxy to Ai).

## 8. Cross-cutting
- All tables carry `workspace_id`; models use `BelongsToWorkspace`.
- Feature flag `automation.enabled`; kill switch `automation.pause_all` (runs queue but do not execute).
- Metrics: runs/day, failure rate, p95 duration, AI tokens/cost per workflow → `automation_daily_stats`.
