# Legacy Automation / Workflow System — Deep Reference for the Rewrite

Companion to `/home/claude/survey/services.md` §1 (engine) and §2 (scheduler). That survey gives the
inventory and the headline bugs; this document is the *exhaustive* behavioural reference: every step
type's config schema as actually read by the handlers, every token-resolution rule per implementation,
the trigger contract, the engine's control flow (nesting, delay, async AI, loops, resume), the
execution-log shape, the V2 builder UI, the prompt library, and a catalogue of the 19 live workflows
recovered from `workflow_steps.json`.

Everything below was read from source; where behaviour is inferred from data (the JSON dump) rather
than code it is marked **(inferred)**. Secrets found in step configs (a BugHerd API key stored in
plain text in `step_config.api_auth_username`) and personal e-mail addresses are deliberately not
reproduced here — see §13 and §14 for the redaction notes.

Source roots: `/home/claude/src/app/Services/WorkflowEngineService.php`,
`/home/claude/src/app/Services/StepHandlers/*.php`, `/home/claude/src/app/Jobs/{RunWorkflowJob,GenerateAiContentJob,RunScheduledItem}.php`,
`/home/claude/src/app/Listeners/*.php`, `/home/claude/src/app/Http/Controllers/Api/{Workflow*,Automation*,ModelData,Prompt}Controller.php`,
`/home/claude/src/resources/js/Pages/AutomationsV2/**`, `/home/claude/src/resources/js/Pages/Automation/Prompts/*.vue`,
`/home/claude/src/workflow_steps.json`, `/home/claude/src/docs/*.md`.

---

## 0. Table of contents

1. Data model (tables, columns, casts, relations)
2. Runtime topology (who calls whom)
3. Trigger system
4. Engine semantics
5. Token / variable system (all resolver implementations compared)
6. Step catalogue (config schema, resolution, outputs, errors, V2 UI form) — one subsection per type
7. Schema & metadata endpoints consumed by the builder
8. Execution logs
9. V2 UI (Hub, Builder, Canvas, Sidebar, pickers, store, API calls)
10. Prompt library
11. Value sets / dictionaries
12. Scheduling contract as used by workflows
13. Live workflow catalogue (from `workflow_steps.json`) with v2-migration flags
14. Consolidated defect register
15. Must-keep / must-fix / could-improve

---

## 1. Data model

### 1.1 `workflows`
| column | type | notes |
|---|---|---|
| id | bigint | |
| name | string | |
| description | text null | never shown in V2 UI |
| trigger_event | string | exact-match key used by `WorkflowTriggerListener` (case-sensitive, see §3) |
| is_active | bool default true | Hub toggle; listener filters `is_active = 1` |
| timestamps, deleted_at | | SoftDeletes; `deleting` hook soft-deletes steps |

Model: `App\Models\Workflow` — `steps()` hasMany ordered by `step_order`; `logs()`; `schedules()` morphMany
(`scheduledItem`); `runScheduled(Schedule)` dispatches `RunWorkflowJob` with the `schedule.run` context (§3.4).

### 1.2 `workflow_steps` (no timestamps)
| column | type | notes |
|---|---|---|
| id | bigint | referenced by tokens (`step_<id>`) and `_parent_id` — **ids are load-bearing** |
| workflow_id | FK cascade | |
| step_order | int | ordering within *its own sibling list* (top-level or container); duplicates allowed, tie-break by `id` |
| name | string | UI label only |
| step_type | string default 'AI_PROMPT' | see §6 |
| prompt_id | FK prompts nullOnDelete | **never set by the V2 UI**; `AiPromptStepHandler` checks `$step->prompt` first, then `step_config.promptRef.id` |
| step_config | json null, cast array | all behaviour lives here; includes structural keys `_parent_id`, `_branch`, plus UI-only `position`, `is_unlinked`, `responseStructure` |
| condition_rules | json null, cast array | legacy (v1) rule list; V2 writes rules into `step_config.rules`; handler falls back to this column |
| delay_minutes | int default 0 | honoured at step boundary (§4.4) |
| deleted_at | | SoftDeletes |

Relations on `WorkflowStep` (all query the JSON column — the reason every handler has a "manual scan" fallback):
```php
children()  -> hasMany(self, 'step_config->_parent_id')->where('step_config->_branch', null)->orderBy('step_order')
yes_steps() -> ... ->where('step_config->_branch', 'yes')
no_steps()  -> ... ->where('step_config->_branch', 'no')
```
Note `hasMany` with a JSON-path foreign key compares `JSON_EXTRACT(step_config,'$._parent_id')` to the parent's
integer id; on MySQL this works only when the JSON holds a number, not a string, which is why the fallback scan
uses loose `==`.

### 1.3 `execution_logs` (no timestamps; `executed_at` default CURRENT_TIMESTAMP)
| column | type | written by |
|---|---|---|
| id | bigint | |
| workflow_id | FK | engine |
| execution_id | string null, indexed (added 2026-03) | engine: UUID minted once per `execute()`/`executeFromStepId()`; propagated via `context._execution_id` |
| step_id | FK workflow_steps | engine |
| triggering_object_id | string null | **never written** (in `$fillable`, no writer) |
| parent_execution_log_id | self FK null | engine `executeSteps($parentLog)` — the container step's log |
| status | string | `started` → `success` / `failed`; `scheduled` (delay); `error` (from `GenerateAiContentJob` catch — a 4th spelling) |
| input_context | json | full context snapshot **at step start** (can be hundreds of KB inside loops) |
| raw_output | json | `$out['raw'] ?? $out['output']` — for AI: raw model text |
| parsed_output | json | `$out['parsed']` |
| error_message | text | exception message |
| duration_ms | int | |
| token_usage | json | Gemini `usageMetadata` (`promptTokenCount`, `candidatesTokenCount`, `totalTokenCount`) |
| cost | decimal(10,6) | always null today (`AIGenerationService` returns `cost => null`) |
| executed_at | timestamp | DB default only |

Casts: `input_context`, `raw_output`, `parsed_output` → array. Relations: `workflow`, `step`, `parentLog`, `childLogs`.

### 1.4 `prompts`
| column | notes |
|---|---|
| name, category, version (default 1), status (default 'active') | unique `(name, version)` enforced in controller and DB |
| system_prompt_text | Blade-less `{{ path }}` template rendered by `AIGenerationService::renderTemplate` against the prompt data |
| model_name | default `gemini-2.5-flash-preview-05-20`; empty → `config('services.gemini.model','gemini-flash-latest')` |
| generation_config | json → passed verbatim as Gemini `generationConfig`; `responseMimeType` forced to `application/json` if absent |
| template_variables | json string[] — editor chips only; **not used at runtime** |
| response_variables | json — field-builder schema (name/type/itemType/schema/options/example/validations) |
| response_json_template | json — same schema array (see §10); **appended to the system prompt as JSON** |

### 1.5 `schedules` — see services.md §2; the only workflow-relevant columns are `scheduled_item_type='workflow'`,
`scheduled_item_id`, `recurrence_pattern` (cron), `is_onetime`, `start_at/end_at`, `last_run_at`.

---

## 2. Runtime topology

```
Eloquent save on allow-listed model
  └─ GlobalModelEventSubscriber::handleModelEvent  (eloquent.created:* / eloquent.updated:*)
       └─ event(WorkflowTriggerEvent $name, $context, $objectId, $from))
            └─ WorkflowTriggerListener::handle  (sync listener; runs in the saving request/job)
                 └─ Workflow::where(is_active)->where(trigger_event = $name)  →  RunWorkflowJob::dispatch (per workflow)
                      └─ [queue]  RunWorkflowJob::handle → WorkflowEngineService::execute | executeFromStepId
                           ├─ per top-level step: ExecutionLog(started) → handler->handle() → ExecutionLog(success|failed)
                           ├─ CONDITION handler → engine->executeSteps(children of branch, parentLog=this log)
                           ├─ FOR_EACH handler  → for each item: engine->executeSteps(children, iteration ctx, parentLog)
                           ├─ AI_PROMPT handler → GenerateAiContentJob::dispatch(...) ; returns AI_JOB_DISPATCHED → engine BREAKS
                           │      └─ [queue] GenerateAiContentJob → Gemini → context[step_N] → engine->executeSteps(remaining siblings)
                           ├─ delay_minutes>0   → ExecutionLog(scheduled) + RunWorkflowJob(startStepId)->delay() → BREAK
                           └─ ACTION_PROCESS_EMAIL → ProcessDraftEmailJob::dispatch (send path, services.md §3.2)

Schedule: app:run-scheduler (every minute) → RunScheduledItem → Workflow::runScheduled → RunWorkflowJob(ctx {event:'schedule.run',...})
Manual:   POST /api/workflows/{id}/run  → engine->execute() SYNCHRONOUSLY in the HTTP request (no job, no unique lock)
Manual:   POST /api/workflows/triggers/{event} → event(WorkflowTriggerEvent) → normal queued path
```

Container: `WorkflowEngineService` is constructed with `AIGenerationService` only; it `new`s every handler in its
constructor (no container resolution, so handlers cannot be swapped by binding — only via `registerHandler()`, which
nothing calls). `CreateRecord`/`UpdateRecord`/`SendEmail`/`ProcessEmail` accept a nullable engine and carry a
private fallback resolver for the null case (never null in practice).

Queue: jobs are plain `ShouldQueue` on the default connection/queue. `RunWorkflowJob` is `ShouldBeUniqueUntilProcessing`
(`uniqueFor=60`, `tries=3`, `timeout=120`, `failOnTimeout`, backoff 60/300/900). `GenerateAiContentJob`: `tries=3`,
`timeout=180`, same backoff, **not unique** (a retry after a partial failure re-runs Gemini and re-executes sibling
steps → duplicate records). `ProcessDraftEmailJob`: see services.md.

`config/automation.php`:
```php
'global_model_events' => ['enabled' => env('AUTOMATION', true), 'verbs' => ['created','updated']],   // saved/deleted supported but off
'run_synchronously'   => env('AUTOMATION_RUN_SYNC', false),   // READ BY NOTHING
'models' => ['allow' => ['Task','Project','Email','Campaign','Lead','User','ProjectNote','UserProductivity'], 'deny' => ['ExecutionLog']],
// Task::defaultsOnCreate reads config('automation.defaults.task.task_type_id') — key does not exist in the file → null
```

---

## 3. Trigger system

### 3.1 Event names actually emitted
`GlobalModelEventSubscriber::handleModelEvent()`:
```php
$eventName = strtolower(class_basename($model)).'.'.strtolower($verb);   // "email.created", "projectnote.created", "userproductivity.created"
```
Only verbs listed in config are subscribed: `created`, `updated`. There is **no** `email.received`, `task.completed`,
`task.status_changed`, `task.assigned`, `project.completed`, `project.archived` — those appear in the builder's event
dropdown (`AutomationSchemaController::getModelEvents()`, §7.1) but nothing emits them. A workflow saved with
`task.completed` never fires.

Emittable set today (allow-list × verbs): `task|project|email|campaign|lead|user|projectnote|userproductivity` × `created|updated`.

Skip rules, in order: `$model->__automation_suppressed` truthy (property is **never set anywhere** in `app/` — dead
mechanism); class basename in `deny`; allow-list non-empty and basename not in it.

### 3.2 Context shape at emission
```php
$context = [
  strtolower(class_basename($model)) => $model->toArray(),   // e.g. 'email' => [...all attributes incl. loaded relations, casts applied, hidden removed...]
  'user' => ['id','name','email'] // only when Auth::check() — absent for console/queue-originated saves
];
event(new WorkflowTriggerEvent($eventName, $context, (string) $model->getKey(), $from));
```
`WorkflowTriggerListener` then enriches:
```php
$ctx['event'] = $eventName;
$ctx['trigger'] = $ctx;                     // snapshot BEFORE triggering_object_id is added
$ctx['triggering_object_id'] = (string) id;
```
So the context a workflow starts with is:
```json
{ "email": {...}, "user": {...}?, "event": "email.created",
  "trigger": { "email": {...}, "user": {...}?, "event": "email.created" },
  "triggering_object_id": "621" }
```
and `execute()` adds `_execution_id` (UUID) and, if `trigger` were missing, `trigger = context` (already present here).
Consequences:
* `{{trigger.email.subject}}` and `{{email.subject}}` both resolve. `{{trigger.subject}}` does **not** (see §5.6 — the V2
  token picker generates exactly that broken form).
* `toArray()` includes whatever relations happened to be loaded on the instance at save time — non-deterministic
  (e.g. WF10 uses `{{trigger.email.conversation.conversable.id}}` which only resolves if `conversation.conversable`
  was eager-loaded by the code that saved the email).
* Updates carry the **post-save** attributes only; there is no `original`/dirty diff, so "status changed from X to Y"
  conditions are impossible (WF22/26 approximate with `status == Done`, which re-fires on every later update while Done).

### 3.3 Allow/deny list and the case bug
`trigger_event` matching is `where('trigger_event', $eventName)` — exact, case-sensitive on most collations
(`utf8mb4_unicode_ci` would actually be case-insensitive on MySQL; the survey's "never fire" claim holds on PostgreSQL/SQLite and
on `_bin` collations — treat as environment-dependent). Live steps use lowercase everywhere except the dead
workflows 1–7, so this only affects the soft-deleted rows.

### 3.4 `schedule.run` contract
`Workflow::runScheduled(Schedule $s)`:
```php
RunWorkflowJob::dispatch($this->id, [
  'event' => 'schedule.run', 'schedule_id' => $s->id,
  'trigger' => ['event' => 'schedule.run', 'schedule_id' => $s->id, 'triggered_at' => iso8601],
]);
```
Note: `RunScheduledItem` checks `instanceof SchedulableAction` first (Workflow does **not** implement the interface),
then `method_exists('runScheduled')` → this method. The `instanceof Workflow` branch below it (which would dispatch
with an **empty** context) is therefore dead.

Engine guard (`execute()`): `if trigger_event === 'schedule.run' && empty($context)` → the first non-TRIGGER step must be
`FETCH_RECORDS`, else the run returns `['error' => 'Schedule-based workflow must start with a Fetch Records step.']`
with **no ExecutionLog row** (silent from the UI's perspective). Because `runScheduled` always sends a non-empty
context, this guard only fires for `POST /workflows/{id}/run` with no body — i.e. the guard is effectively dead for
real schedules. The builder never creates the `Schedule` row: `TriggerConfig.vue` stores `schedule_type`,
`schedule_time`, `cron_expression` into the TRIGGER step's `step_config`, and **nothing reads them**. The
`WorkflowController::store/update` accept an optional `schedule` payload (mode/time/days...) and persist it through
`HandlesSchedules::persistScheduleFromArray`, but `storeV2.js::saveWorkflow()` never sends it. Schedules for the
5 live schedule workflows must have been created through the generic Schedules UI (`ScheduleController`).

Uniqueness key for schedule runs: `RunWorkflowJob::uniqueId()` → `workflow:{id}|event:schedule.run|object:` (object
id from `context.trigger.id` → absent → `''`). Two schedule fires within 60 s collapse into one (fine); but a
schedule run and a manual `/run` of the same workflow do not collide (manual is synchronous, no lock).

### 3.5 Manual trigger endpoint
`POST /api/workflows/triggers/{event}` body `{context?: object, triggering_object_id?: string}` →
`event(new WorkflowTriggerEvent($event, $context, $objectId))` → JSON `{status:'queued', event}`. No validation that
a workflow exists; the listener enriches the context exactly as for model events, so the caller is expected to pass
`{ "<modelname>": {...} }`. Route is registered twice (once in the main auth group, once under "AI Automation Engine
routes"); both point at the same controller.

`POST /api/workflows/{workflow}/run` body `{context?: object}` → **synchronous** `engine->execute()`, returns the
`$results` array (`workflow_id, execution_id, steps[{step_id,status,duration_ms|error|delay_minutes}]`, optional
`error`). With an empty body on a `schedule.run` workflow the guard in §3.4 triggers. With an empty body on an
event workflow, `trigger = []`, so every `{{trigger.*}}` resolves to null and CONDITIONs evaluate `null == x`.

### 3.6 Uniqueness key
`WorkflowTriggerListener`: `'workflow:'.$id.'|event:'.$name.'|object:'.($objectId ?? '')`. Delayed resumes:
`'workflow:'.$id.'|resume_step:'.$stepId` (**not** per execution — two executions of the same workflow that reach the
same delayed step within 60 s share a key; the second `dispatch()` is silently dropped by `ShouldBeUniqueUntilProcessing`
and that execution never resumes). `GenerateAiContentJob` has no uniqueness.

### 3.7 Feedback-loop protection
* `UpdateRecordStepHandler` saves inside `Model::withoutEvents()` → no `*.updated` re-trigger, but also no observers,
  no model invariants (hence the hard-coded `Email::guardStatusRegression`).
* `CreateRecordStepHandler` saves **with** events → `*.created` fires normally. A workflow on `email.created` that
  creates an Email (WF12/13/19/20/21 all do) re-enters the trigger pipeline; the created Email's own conditions
  (`type == received`, `status == unknown`, `sender_type == lead`) are what stop the loop, not the engine.
* `SyncRelationshipStepHandler` `sync/attach/detach` fire pivot events only (not in allow-list).
* `InboxSavedController` writes `status='saved'` rows inside `Email::withoutEvents()` explicitly to hide them from
  automation — the pattern the whole app uses to opt out.

---

## 4. Engine semantics

### 4.1 Step ordering and what "top-level" means
```php
$steps = $workflow->steps()->orderBy('step_order')->orderBy('id')->get();          // ALL rows incl. nested
$topLevel = $steps->filter(fn($s) => empty($s->step_config['_parent_id']))->values();
foreach ($steps as $step) { if (!empty($cfg['_parent_id'])) continue; ... }        // iterate all, skip nested
```
* Ordering key is `(step_order, id)` **globally**, but nested steps are skipped, so top-level order is the
  `(step_order,id)` order among top-level rows. The V2 UI writes `step_order = index+1` within each sibling list on
  save, so a nested step can have `step_order=1` and a top-level step `step_order=4` — meaningless across scopes.
* TRIGGER steps are executed as a no-op handler that returns `parsed.trigger_event`; they produce an ExecutionLog
  row like any other step (so every run has ≥1 log row).
* A CONDITION/FOR_EACH container's children are found by the handler (relation → manual scan fallback), sorted by
  `step_order` only (no id tie-break in the relation; `orderBy('step_order')` on the fallback too).

### 4.2 Per-step protocol (identical in `execute`, `executeFromStepId`, `executeSteps`)
1. `ExecutionLog::create(status='started', input_context=$context, parent_execution_log_id=$parentLog?->id, execution_id)`.
2. `resolveHandler()`: `ACTION` → `'ACTION_'.strtoupper(action_type)`; unknown → `RuntimeException("No handler for step type X")` → log `failed`, continue.
3. `$ctxForHandler = $context + ['_resume_next_sibling_ids' => [ids of remaining siblings in this scope]]`.
4. `$out = $handler->handle($ctxForHandler, $step, $execLog)`.
5. If `$out['parsed']['status'] === 'AI_JOB_DISPATCHED'` → push `{status:'delegated_async'}` to results and **`break`**
   out of the loop (log row stays `started`; the AI job will update it).
6. Else log `success` with `raw_output = $out['raw'] ?? $out['output']`, `parsed_output = $out['parsed']`,
   `token_usage`, `cost`, `duration_ms`.
7. Context merge: `if (!empty($out['context'])) $context = array_replace_recursive($context, $out['context'])`.
8. Step namespace: `if (isset($out['parsed'])) { $context['step_'.$id] = $out['parsed']; $context['steps'][$id] = $out['parsed']; }`
   — note **`parsed` is stored directly**, there is no `.parsed` wrapper here. The `.parsed` wrapper exists only for AI
   steps, where `GenerateAiContentJob` writes `context['step_N'] = ['raw','parsed','token_usage','cost']` (§4.5). This is why every resolver has a `.parsed` fallback.
9. `catch (Throwable)` → log `failed` + `error_message`, push `{status:'failed'}`, **continue with next step**.
   No workflow-level status exists. A failed CONDITION means "no branch ran" and the next top-level step still runs.

`$out['logs']` (returned by ACTION alias, FETCH_API_DATA, SYNC_RELATIONSHIP) is ignored by the engine.

Context merge semantics (`array_replace_recursive`): keys from the step's `context` overwrite recursively. Since
`context` payloads are keyed by model basename (`'email' => $model->toArray()`), an UPDATE_RECORD on Email
*overwrites* `context['email']` (good: later steps see fresh data) but does **not** touch `context['trigger']['email']`
(the trigger snapshot is stale by design). A CREATE_RECORD on Email inside a loop iteration replaces `context['email']`
for the rest of that iteration only (iteration context is a copy). `FETCH_RECORDS` with `output_key` merges
`{output_key: parsed}`; `DEFINE_VARIABLE` merges `{variables: {...}}` — successive DEFINE_VARIABLE steps accumulate
(recursive replace keeps earlier keys). List-typed values merge index-wise (`[a,b]` replaced by `[c]` → `[c,b]`).

### 4.3 Nesting: `_parent_id` and `_branch`
* Stored in `step_config` of the **child**: `_parent_id: <parent step id|null>`, `_branch: 'yes'|'no'|null`.
* CONDITION children: `_branch` `yes`/`no`. FOR_EACH children: `_branch` null (`flattenSteps` writes `null`; relation
  `children()` uses `where('step_config->_branch', null)` which on MySQL becomes `JSON_EXTRACT(...) IS NULL` — true both
  for JSON null and missing key).
* Resolution order in ConditionStepHandler: `yes_steps()/no_steps()` relation → if empty, scan all workflow steps with
  `($cfg['_parent_id'] ?? null) == $step->id` and `strtolower($cfg['_branch'] ?? 'yes')`. **A child with a missing
  `_branch` under a CONDITION is treated as `yes`** in the fallback only.
* ForEachStepHandler uses `$step->children` if the attribute is set (it never is unless eager-loaded — `isset($step->children)`
  on an unloaded relation is false), so it always takes the manual scan path: `_parent_id == id && empty(_branch)`.
* Depth is unbounded in the data model; engine guards: `findTopLevelAncestorId` 50 hops, `isAncestorOf` 100 hops.
* The V2 `WorkflowController::syncWorkflowSteps` remaps temp ids to real ids in `_parent_id` (second pass) and in
  string tokens (third pass, §4.7).
* Steps whose `_parent_id` points at a non-existent step are neither top-level (they have a parent id) nor reachable
  (no parent executes them) → silently dead. WF9's step 44/47/52 chain hangs off a broken `sourceArray` and is the
  live example.

### 4.4 Delay (`delay_minutes`)
Checked **before** creating the `started` log, in all three loops:
```php
ExecutionLog::create([... 'status' => 'scheduled', 'input_context' => $context]);
RunWorkflowJob::dispatch($workflow->id, $context, $step->id, 'workflow:'.$id.'|resume_step:'.$step->id)->delay(now()->addMinutes($delay));
break;
```
* The whole current traversal stops (siblings, and — because the parent handler returns normally — the parent's
  parent continues!). Concretely: a delayed step inside a CONDITION branch stops the branch, the CONDITION returns
  `success`, and the **next top-level step runs immediately**, before the delayed step. Then, `delay_minutes` later,
  `executeFromStepId()` resumes from the delayed step *and re-runs every top-level step after its top-level ancestor*
  (see §4.6). Steps after the container therefore execute **twice**. WF20 step 231 (`PROCESS_EMAIL`, delay 3) is
  nested under 227 → yes; the steps after 227 in that scope (none) and after 225 at top level (none) — so it happens
  to be safe, but only by layout.
* `_resume_next_sibling_ids` is not used by the delay path.
* Inside FOR_EACH, a delayed child breaks that iteration's `executeSteps` and the loop proceeds to the next item;
  every iteration dispatches its own resume job, but all share the unique key `workflow:{id}|resume_step:{step}` →
  within `uniqueFor=60` only the first iteration's resume survives.
* The resumed job carries the full context (serialized into the `jobs` table payload) including
  `_resume_next_sibling_ids` from the parent scope (harmless) and `loop` (so `{{loop.item}}` still resolves after resume).

### 4.5 AI async break / resume
`AiPromptStepHandler::handle()` never calls Gemini. It resolves the prompt, gathers `promptData` (§6.3), dispatches
`GenerateAiContentJob($workflowId, $promptId, $stepId, $promptData, $context, $execLog, $nextSiblingIds)` and returns
`['parsed' => ['status' => 'AI_JOB_DISPATCHED'], 'context' => []]`. Because the prompt is passed by **id**, an inline
`step_config.prompt` string (a `new Prompt([...])` with no id) makes the job's `Prompt::find(null)` return null and the
job **silently returns** — the log row stays `started` forever. WF9 step 52 is an inline prompt → dead.

`GenerateAiContentJob::handle()`:
1. `$result = $aiService->generate($prompt, $promptData)`; `parsed` = JSON-decoded text (or decode of raw as second try).
2. `context['ai']['last_output'] = parsed ?? raw`
3. `context['step_N'] = ['raw','parsed','token_usage','cost']; context['steps'][N] = same` — **the `.parsed` wrapper**.
4. `if resumeNextSiblingIds: $siblings = steps whereIn(ids) orderBy(step_order,id); engine->executeSteps($siblings, $workflow, $context, $this->execLog)`
   — note `parentLog` = **the AI step's own log**, so the AI step's siblings are logged as *children of the AI step*
   in the tree (WorkflowLogsModalV2 renders them nested under it — misleading).
5. Computes `nextStepId = findNextTopLevelStepIdAfter(findTopLevelAncestorId(stepId))` — **the dispatch that would use
   it is commented out**. Consequence: after an AI step, execution continues through the *remaining siblings in the AI
   step's own scope* (which for a top-level AI step is the rest of the workflow, since `_resume_next_sibling_ids` at
   top level = remaining top-level ids), but for a nested AI step everything after the enclosing container at the
   outer scopes is **never executed**. All 19 live workflows either have the AI step top-level or put the enclosing
   container last, so nothing visibly breaks today, but any re-layout will.
6. Updates the AI step's log to `success` with raw/parsed/token_usage — **after** the siblings ran, so during sibling
   execution the AI log is still `started`, and the AI step's `duration_ms` includes all sibling execution time.
7. `catch Throwable` → log status `'error'` (sic, not `failed`) + `error_message`; siblings are **not** executed.
   Nothing else in the workflow runs. There is no retry-safe design: a `tries=3` retry after a failure in step 4
   re-runs Gemini and re-executes siblings (duplicate Emails/Contexts).

Inside FOR_EACH: the AI break exits only the iteration's `executeSteps`; the loop continues to the next item and
dispatches another AI job with that iteration's context. Result: N parallel AI jobs, each finishing the tail of its
iteration independently (WF12/13/21/25). Order of side effects across iterations is queue-dependent.

### 4.6 Resume from step (`executeFromStepId`)
```
executeFromStepId(wf, ctx, startId):
  if startStep is nested:  ctx['_resume_from_nested_step_id'] ??= startId;  return executeFromStepId(wf, ctx, parent_id)   // recurse to top-level ancestor
  iterate ALL steps in (step_order,id) order; skip until id == startId; skip nested; run remaining top-level steps
    - first step after resume: delay ignored (isFirstStepAfterResume)
    - later delayed step: schedule again and break
executeSteps(list, ...):
  resumeId = ctx['_resume_from_nested_step_id']; shouldSkip = (bool) resumeId
  foreach step: if shouldSkip:
       id == resumeId            → unset marker; shouldSkip=false; justResumed=true (delay ignored)
       isAncestorOf(step, resumeId) → shouldSkip=false (marker kept so the child scope keeps skipping); justResumed=true
       else                      → continue (skipped)
```
Net behaviour when resuming nested step S under top-level container C: C's handler is re-run from scratch
(CONDITION re-evaluates rules against the *saved* context — if the answer flipped, the resume lands in the other
branch and S never runs, and the marker is never cleared, so **every subsequent container scope in the run skips all
its children until it happens to contain S's ancestor**); FOR_EACH re-runs the **whole loop** (all iterations),
with the marker consumed on the first iteration that reaches S — later iterations run fully. `isAncestorOf` walks the
parent chain with one query per hop, per step, per scope.

### 4.7 Save-time id remapping (`WorkflowController::syncWorkflowSteps`) — engine-relevant because tokens embed ids
Pass 1 upsert rows (numeric id that exists → update; else create; map providedId → actualId). Pass 2 rewrite
`_parent_id` via the map. Pass 3 build `$replacements['step_'.$provided] = 'step_'.$actual` for every changed id and
apply **`str_replace(array_keys, array_values)` over every string in every step_config**. `str_replace` with arrays
applies replacements sequentially and un-anchored, so `step_temp_1` → `step_167` also rewrites the prefix of
`step_temp_12` → `step_1672`, and `step_16` → ... etc. The live data shows the damage: WF17 references
`step_1670`, `step_17381`, `step_17384`, `step_17389` (ids that do not exist; max id in the table is 316), WF9
references `step_temp_1757802064599` (never remapped because that temp id was not in the same save payload). Pass 4
deletes rows not in the payload (soft delete → their logs keep FK validity).

### 4.8 Sync vs queued
* Model-event and schedule triggers → queued `RunWorkflowJob`.
* `POST /workflows/{id}/run` → synchronous in the web request (120 s PHP limit applies; AI still goes async).
* `automation.run_synchronously` is never read; `WorkflowTriggerListener` always dispatches to the queue. With
  `QUEUE_CONNECTION=sync` in local envs the whole chain (including Gemini and sibling execution) runs inside the
  original HTTP request that saved the model.

### 4.9 Depth / loop guards
* No guard on FOR_EACH item count (FETCH_RECORDS caps at 1000 via `limit`, default 50; but `sourceArray` may be an
  AI-returned array of arbitrary size).
* No guard on nested FOR_EACH (WF12/13 nest two loops: campaigns × leads; every inner AI step is a job).
* No guard on re-triggering (a CREATE_RECORD of the trigger model inside the same workflow re-enters).
* `execute()` has no overall timeout beyond the job's `timeout=120`; a long loop is killed mid-way and retried up to
  3 times from the beginning (side effects repeat).

---

## 5. Token / variable system

### 5.1 Syntax accepted by the engine resolver (`WorkflowEngineService::getTemplatedValue/getFromContextPath`)
* Delimiters `{{ ... }}`; regex `/{{\s*([^}]+)\s*}}/` — anything up to the first `}` — so `{{a.b}}}` works, nested braces do not.
* Whole-string single token (`/^\s*{{\s*([^}]+)\s*}}\s*$/`) → returns the **native** value (array, int, bool, null).
  Otherwise interpolation: bool → `'true'|'false'`, scalar/null → `(string)`, array/object → `json_encode`.
* Path separators: `.` and `:` are equivalent (`str_replace(':', '.')`), so `trigger:email:subject` == `trigger.email.subject`.
  `Arr::get` also accepts numeric segments (`step_181.records.0.id`) but **no** `[0]` bracket syntax; `steps[N]`
  as written in the task brief is not parsed — the array is `steps.N`.
* Resolution order for path `p`:
  1. `Arr::get($ctx, p)` non-null → return.
  2. If `p` ends with `.count` or `.length`: resolve parent path recursively; if array/Countable → `count()`.
     (Only when the literal key is absent — a real `count` key, e.g. `step_181.count` from FETCH_RECORDS, wins.)
  3. If `p` starts with `step_`: with remainder R try `step_N.parsed.R`, `steps.N.R`, `steps.N.parsed.R` (skipping the
     `.parsed.` insertions when R already starts with `parsed.`); with no remainder try `steps.N`, `steps.N.parsed`.
  4. null.
* Roots available at runtime: `trigger.*`, `<modelname>.*` (trigger model and any model written by CREATE/UPDATE),
  `user.*`, `event`, `triggering_object_id`, `step_<id>.*`, `steps.<id>.*`, `loop.item|index|is_first|is_last`,
  `variables.<name>` (DEFINE_VARIABLE), `condition.<stepId>` (bool), `transform.step_<id>.result|cleaned_body`,
  `ai.last_output`, `<output_key>` (FETCH_RECORDS), `_execution_id`, `_resume_next_sibling_ids`, `_resume_from_nested_step_id`.
* No filters, no defaults, no expressions, no escaping. Literal text containing `{{` cannot be emitted.
* Function-like literals are handled **after** resolution, only by Create/UpdateRecord: `NOW()`, `CURRENT_TIMESTAMP`,
  `CURRENT_TIMESTAMP()` → `now()`; `TODAY()` → `now()->startOfDay()`; `NULL` → null (case-insensitive, whole value).
  Anything else (WF22's `DATE_ADD(CURDATE(), INTERVAL 1 DAY)`) is written as a literal string.

### 5.2 The six resolver implementations and how they differ
| # | Where | Single-token native return | Interpolation of arrays | `:` separator | `.parsed` fallback | `steps.N` fallback | `.count/.length` | Miss value | Eloquent walk | bool render | `'true'/'false'` literals |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | `WorkflowEngineService::getTemplatedValue` (used by Create/Update/SendEmail/ProcessEmail/SyncRel/FetchApi/Transform/ForEach/AiPrompt.freeText) | yes | json | yes | yes (step_N.parsed.R) | yes | yes | null (→ `''` in strings) | no | true/false | untouched |
| 2 | `ConditionStepHandler::applyTemplate` (literal side only; var side delegates to #1) | yes (`^{{...}}$`, no surrounding whitespace allowed) | json | via #1 | via #1 | via #1 | via #1 | null | no | true/false | untouched |
| 3 | `CreateRecordStepHandler::applyTemplate/getFromContextPath` (fallback, engine null) | yes | json | **no** | yes (`step_N.parsed.R` only) | no | no | null | yes (`Model->$part`) | true/false | untouched |
| 4 | `UpdateRecordStepHandler::*` (fallback) | yes | json | no | yes (**any** first segment: `X.parsed.R`) | no | no | null | no | true/false | untouched |
| 5 | `DefineVariableStepHandler::applyTemplate` (**always used** — never delegates to the engine) | yes (`^{{...}}$`, no whitespace) | json | **no** | yes (`step_N.parsed.R`) | no | no | null | no | true/false | untouched |
| 6 | `QueryDataStepHandler::applyTemplate` (FETCH_RECORDS condition values) | **no** — always string | json | yes (`preg_split('/\.|\:/')`) | **no** | no | no | **`''`** (empty string) | no | `(string)` → `'1'`/`''` | `'true'`→**1**, `'false'`→**0** (before templating) |
| 7 | `SendEmailStepHandler::applyTemplate` (fallback only) | no | json | yes | no | no | no | `''` | no | `(string)` | untouched |
| 8 | `AIGenerationService::renderTemplate` (system prompt text against **promptData**, not workflow context) | no | json | yes | n/a | n/a | no | `''` | no | true/false | untouched |
| — | dead `app/Services/ForEachStepHandler.php` | delegates to #1 | | | | | | | | | |

Practical differences that bite:
* A FETCH_RECORDS condition `column = {{loop.item.id}}` that misses yields `where(col,'=','')`, whereas a CONDITION
  rule on the same token compares against `null`. `{{step_94.next_follow_up_date}}` in a Lead UPDATE resolves via #1
  with the `.parsed` fallback; the same token in a FETCH_RECORDS condition (#6) would **not** find AI output at all
  (no `.parsed` fallback → `''`). No live FETCH_RECORDS references an AI step, which is presumably why nobody noticed.
* DEFINE_VARIABLE (#5) cannot read `step_X.records.count`, cannot use `:`; but *can* read AI output via `.parsed`.
* Condition literal side `" {{x}}"` (leading space) is interpolated to a string instead of native — `==` still works via
  loose string compare, `in`/`contains` behave differently.
* `AiPromptStepHandler::gatherPromptData` has its own **fourth notation** for `aiInputs`: `"source:path"` where source
  ∈ `trigger|loop` and path is looked up as `Arr::get($root, $baseModelKey.'.'.$path)` then `Arr::get($root, $path)`; the
  **last dot segment** becomes the prompt-data key (so `trigger:conversation.subject` → key `subject`).

### 5.3 Step-output namespaces (what each handler puts under `step_N`)
See §6 per step; summary:
| step | `parsed` (→ `step_N.*`) | extra context keys |
|---|---|---|
| TRIGGER | `trigger_event` | — |
| ACTION (alias) | — (`logs.action_type`) | — |
| AI_PROMPT | after job: `raw`, `parsed{…AI JSON…}`, `token_usage`, `cost` (access `step_N.<field>` via `.parsed` fallback) | `ai.last_output` |
| CONDITION | `condition: 'YES'|'NO'` | `condition.<id>: bool` |
| CREATE_RECORD | `id`, `new_record_id`, `model` (FQCN), `schema{new_record_id:'ID',id:'ID'}` | `<modelbasename>` = record array |
| UPDATE_RECORD | `id`, `model` | `<modelbasename>` = record array |
| SYNC_RELATIONSHIP | `action`, `relationship`, `record_id`, `synced_ids|attached_ids|detached_ids`, `count` | — |
| SEND_EMAIL | `to`, `subject` | — |
| PROCESS_EMAIL | `queued: true`, `email_id`, `job` | — |
| FETCH_API_DATA | `status` (HTTP), `parsed` (body or sub-key), `duration_ms` → fields via `step_N.<key>` **through the `.parsed` fallback** | — |
| QUERY_DATA / FETCH_RECORDS | `count`, `records[]`; single: + `record` | `<output_key>` = same parsed |
| FOR_EACH | `iterations` | — (iteration side-effects are lost; nothing from inside the loop is exported) |
| TRANSFORM_CONTENT | `type`, `result`, `cleaned_body`, `schema` | `transform.step_N.{result,cleaned_body}` |
| DEFINE_VARIABLE | `{name: value,…}` | `variables.{name}` |

### 5.4 Trigger-side helper notations in AI steps
`aiInputs` entries: `"trigger:<col>"` (default source when no `:`), `"loop:<col>"`. `relationships`:
`{base_model, roots[], nested{root:[child…]}, fields{root:[cols]|['*'], 'root.child':[cols]}}`.

### 5.5 Where tokens are *not* resolved
`FETCH_RECORDS.model`, `order`, `limit`, `with`, `relationships`, `single`; `CREATE/UPDATE.target_model`; `fields[].column`;
`SYNC_RELATIONSHIP.relationship/sync_mode`; `FETCH_API_DATA.api_method/api_auth_type/api_response_key`
(`api_auth_username/password/header_name` **are** templated); `PROCESS_EMAIL.on_queue`; `TRANSFORM.type`;
`CONDITION.operator/logic`; `AI.promptRef`.

### 5.6 What the V2 token picker generates (and whether it resolves)
`DataTokenInserter.vue` builds sources → `TokenPickerModalV2` emits `{{<sourceId>.<field>}}`:
| source id | fields | resolves at runtime? |
|---|---|---|
| `trigger` | trigger model's columns | **No** — emits `{{trigger.subject}}`; runtime path is `trigger.email.subject`. Every live workflow was hand-edited (or built in V1) to the correct 3-segment form. |
| `step_<id>` (FETCH_RECORDS) | `records`, `count` | yes |
| `step_<id>` (AI_PROMPT) | `responseStructure[].name` (top-level names only — the recursive nested-field support described in `docs/NESTED_FIELDS_FIX.md` was implemented in the **V1** `DataTokenInserter`, not V2) | yes via `.parsed` fallback |
| `step_<id>` (FETCH_API_DATA) | `responseStructure[].name` | never listed: `s.step_type` is `ACTION`, not `FETCH_API_DATA` — branch unreachable |
| `step_<id>` (DEFINE_VARIABLE) | variable names | yes |
| `step_<id>` (TRANSFORM/TRANSFORM_CONTENT) | `result` | yes |
| `loop` | `index`, `key`, `item.<col>` for the model of the referenced FETCH_RECORDS (or nearest preceding one) | `item.*`, `index` yes; `key` **does not exist** (`is_first/is_last` exist but are not offered) |
| `with` | `<relation>.<col>` for every relation of the trigger model | **No** — `with` exists only inside AI promptData, never in workflow context |
| — | `user.*`, `event`, `condition.*`, `variables.*`, `ai.last_output`, `steps.N`, `.count` | not offered |

`allStepsBefore` is computed by `PropertiesSidebar::collectBefore` as a **pre-order traversal of the whole tree up to
the selected node**, so it includes steps from sibling branches (e.g. the `no` branch's steps are offered inside the
`yes` branch) and steps inside earlier loops (whose `step_N` values never exist outside the loop).

---

## 6. Step catalogue

Conventions: "cfg" = `step_config` keys; "UI" = fields rendered by the V2 `StepConfigs/*.vue` panel; "Out" = what is
returned to the engine. Common to all steps: `name` (sidebar "Step Name"), `delay_minutes` (sidebar "Delay (min)",
hidden for triggers), `_parent_id`, `_branch`, `position {x,y}` (canvas), `is_unlinked` (canvas; set on add and on
edge removal, deleted on connect — **persisted to DB** and ignored by the engine).

### 6.1 `TRIGGER` (and UI-only `SCHEDULE_TRIGGER`)
cfg (event trigger, written by `TriggerConfig.vue`):
```json
{ "model": "Email", "event": "created", "trigger_event": "email.created" }
```
cfg (schedule): `{ "trigger_event": "schedule.run", "schedule_type": "daily|weekly|hourly|cron", "schedule_time": "08:00", "cron_expression": "" }`
— `schedule_*` keys are never read by any backend code (§3.4).
Runtime: no-op; Out `parsed.trigger_event`. The workflow's own `trigger_event` column (not the step) is what the
listener matches; `saveWorkflow()` derives it from `workflowSteps[0]` — so **the first node in the tree must be the
trigger**; a trigger dropped second is ignored and `trigger_event` becomes `null` → 422 from the API (`required`).
`normalizeFromServer()` repairs a historical bug where `trigger_event` was saved as `"[object Object]"`.
UI: model select (all schema models), event select (per-model list from §7.1; models with no list show a warning),
readonly `trigger_event` echo. Schedule: frequency select, time input, or cron text. Legacy `condition_rules`-style
triggers (`{"trigger_event":"new_lead_created"}`) survive as-is.

### 6.2 `ACTION` (alias) — `cfg.action_type` ∈ `SEND_EMAIL | PROCESS_EMAIL | CREATE_RECORD | UPDATE_RECORD | SYNC_RELATIONSHIP | FETCH_API_DATA | CHECK_MILESTONE_COMPLETION`
`resolveHandler` maps to `ACTION_<TYPE>`. `CHECK_MILESTONE_COMPLETION` is offered by `ActionConfig.vue` and
`StepPickerModalV2.vue` but **has no handler** → "No handler for step type ACTION" (message names the outer type, not
the action). The dead row `ACTION_AI_PROMPT` (deleted WF) is the same failure class. `setType()` in ActionConfig
resets the whole config to `{action_type}` (switching action type wipes fields — intended).

### 6.3 `AI_PROMPT` — `AiPromptStepHandler` + `GenerateAiContentJob` + `AIGenerationService`
cfg:
```json
{
  "promptRef": {"id": 7, "name": "AI Email Approval", "version": 1},   // required (or prompt_id column, or inline "prompt")
  "prompt": "inline system prompt text…",                              // legacy inline; DEAD at runtime (job needs an id) 
  "aiInputs": ["trigger:subject", "trigger:body", "loop:email", …],     // default source 'trigger'
  "freeText": "{{step_181.record}}\n{{loop.item}}",                     // tokens resolved by engine resolver → promptData.context_data
  "relationships": {"base_model": "Email", "roots": ["conversation"], "nested": {"conversation": []},
                     "fields": {"conversation": ["id","subject"], "sender": ["*"], "sender.contexts": ["summary","meta_data"]}},
  "responseStructure": [{"id":…, "name": "approval_required", "type": "Boolean", …}]   // UI copy of prompt.response_variables at selection time — NOT refreshed when the prompt changes
}
```
Prompt resolution: `$step->prompt` (FK) → `Prompt::find(promptRef.id)` → `new Prompt(['system_prompt_text'=>cfg.prompt])` → throw `Prompt not found for AI_PROMPT step`.
`promptData` assembly (`gatherPromptData`):
1. `freeText` non-empty → engine `getTemplatedValue`; non-string → `json_encode`; stored as `context_data`
   (the `body` key in the catch branch is only reached if the container cannot resolve the engine — effectively never).
2. `$baseModelName = resolveModelFromContext()`: first `trigger.*` entry whose `['id'] == triggering_object_id`
   (StudlyCase of the key), else Studly of the event prefix (`email.created` → `Email`). For `schedule.run` → `Schedule`
   (nonsense but harmless: `App\Models\Schedule` exists, `find($baseModelId)` with null id → no relations).
3. Each `aiInputs` entry `source:path` → `$dataRoot` = `loop.item` (if source `loop` and present) else `trigger`;
   value = `Arr::get($root, strtolower(base).'.'.$path)` (trigger only) ?? `Arr::get($root, $path)`; key = last dot segment.
   Missing → key present with `null`.
4. Relationships: if `base_model && baseModelId` (event path) → `gatherRelatedData`: `Model::with([root => fn q => q->select(fields+id)->with([child => select])])->find(id)->getRelations()` → `promptData.with`.
   **`select(fields)` without the foreign key breaks BelongsTo/HasMany hydration** unless the FK is in the field list
   (e.g. `fields.conversation = ['id','subject']` is fine for `Email belongsTo conversation`, but `project.milestones =
   ['id','project_id','name']` needed `project_id` explicitly — WF10 does include it; `users: ['id','name']` on a
   BelongsToMany is fine). `['*']` → no select.
   Else if `loop.item` and `roots` → take `loop.item[root]` (already eager-loaded by FETCH_RECORDS.relationships),
   filter to `fields[root]` (single object or list), `nested` ignored ("already present").
   Note the loop path requires `relationships.roots` names to exist as keys in the fetched record array (snake_case as
   `toArray()` renders them — `shareableResources` relation becomes key `shareable_resources`; WF12 requests
   `campaign.shareableResources` in fields but roots is `['campaign']` so it only filters the campaign fields).
5. Result: `{context_data?, <last segments…>, with?: {...}}`.
Gemini call (`AIGenerationService::generate`):
```
system = renderTemplate(prompt.system_prompt_text, promptData) . ' ' . json_encode(prompt.response_json_template)
payload = { contents:[{parts:[{text: json_encode(promptData)}]}], systemInstruction:{parts:[{text: system}]}, generationConfig: prompt.generation_config + {responseMimeType:'application/json' if absent} }
POST https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={config('services.gemini.key')}
```
Response: `candidates.0.content.parts.0.text` → `json_decode` → `parsed` (null if not JSON); `usageMetadata` → token_usage;
`cost` null. No explicit timeout on the `Http::post` (Laravel HTTP client default 30 s; job timeout 180 s). Failure → `RuntimeException`
with full Gemini body → log status `error`.
Out (after job): `context.step_N = {raw, parsed, token_usage, cost}`, `context.ai.last_output`; the AI log gets
`raw_output`, `parsed_output`, `token_usage`. Downstream tokens: `{{step_N.<field>}}`, `{{step_N.parsed.<field>}}`,
`{{step_N.raw}}`.
Errors: prompt missing → `failed` at dispatch; Gemini/API failure → `error` (job); bad JSON → `parsed=null`, siblings
run with nulls (conditions on `step_N.x == true` fall to NO).
UI (`AIConfig.vue`): prompt select (`name (vN)`, from `/api/prompts?per_page=100` — **paginated to 50 server-side**,
so >50 prompts are invisible); "Instruction Context (Data Payload)" summary chips + `RelatedDataPickerV2` slide-over
(base fields → `aiInputs` as `trigger:col`/`loop:col`; roots/nested/fields tree with "select all" = `['*']`);
"Instruction / Additional Context" textarea (`freeText`, TokenInputField); read-only "Expected Response JSON" list
from `responseStructure`. Base model = model of the FETCH_RECORDS referenced by the nearest preceding FOR_EACH's
`sourceArray` (only when the token is exactly `{{step_N.records}}`), else the trigger's model; `baseSource` = `loop`/`trigger`.

### 6.4 `CONDITION` — `ConditionStepHandler`
cfg:
```json
{ "logic": "AND|OR",
  "rules": [
    {"left": {"type":"var","path":"trigger.email.type"}, "operator": "==", "right": {"type":"literal","value":"received"}},
    {"left": {"type":"var","path":"{{loop.item.next_follow_up_date}}"}, "operator": "in_past"},      // path may be wrapped in {{ }}
    {"field": "status", "json_path": "meta.x", "operator": "equals", "value": "new"}                  // legacy v1 shape, normalised: left=var field[.json_path], right=literal value
  ],
  "sourceId": "trigger|step_70|61|loop"   // V1 UI leftover, ignored
}
```
Fallback: `rules` absent → `$step->condition_rules` column (v1). Empty rules → **TRUE** (UI text says "always FALSE" — wrong).
Side resolution: `var` → engine `getFromContextPath` (native); `literal` → `applyTemplate` (so literals may contain tokens;
`{"type":"literal","value":"{{step_x.y}}"}` works). Missing `right` → literal null.
Morph normalisation: if left path ends in `_type` and right is a string → `normalizeMorphType(right)`: class exists →
as-is; morphMap alias (case-insensitive) → class; `App\Models\<Studly>` exists → class; else unchanged. This is how
`sender_type == "client"` matches `App\Models\Client`.
Operators (`compareAny`, lower-cased/trimmed): `==`/`=` (looseEq), `!=`/`<>`, `>`,`>=`,`<`,`<=` (numeric coercion when
both numeric; otherwise PHP loose compare), `in`, `not in`/`not_in` (right array or CSV string), `contains` (string
`Str::contains`, array membership, enum→string), `empty`, `not_empty` (PHP `empty()` — `"0"` is empty),
`is_null`, `is_not_null`, `truthy`, `today`, `in_past`, `in_future` (Carbon parse of left; numeric = unix ts; unparsable → false),
default → **false**. `looseEq`: arrays → JSON compare after enum normalisation; left bool vs `'true'/'false'` string →
boolean compare; else `(string) === (string)` — so `1 == "1"` true, `null == ""` true, `true == "1"` true (`(string)true`
is `'1'`), `false == ""` true, `false == "0"` **false**, `null == "false"` **false** (the WF17 failure mode, §13).
UI (`ConditionConfig.vue`) offers `==, !=, >, <, >=, <=, contains, starts_with, ends_with, is_null, is_not_null, in` —
**`starts_with`/`ends_with` are not implemented** (→ default false); `empty/not_empty/in_past/in_future/today/truthy/not in`
used by live workflows are **not offered** (editing such a rule in V2 shows an empty operator select and saving
keeps the value only if untouched). Both sides are `TokenInputField`s; the UI serialises `{{x}}` as `{type:'var',path:'x'}`
and anything else as literal (`parseTokenString` — a literal containing a token in the middle stays literal; the
handler interpolates it anyway). Legacy `{field,value}` rules are mapped on load (store) to `trigger.<field>`.
Out: `parsed.condition 'YES'|'NO'`, `context.condition[id] = bool`. Children executed inline via `executeSteps`
with `parentLog` = this step's log. Errors inside children are logged on the children and do not affect the
CONDITION's own `success`. A CONDITION log's `parsed_output` is the only place the branch decision is recorded.

### 6.5 `ACTION_CREATE_RECORD` — `CreateRecordStepHandler`
cfg:
```json
{ "action_type": "CREATE_RECORD", "target_model": "Task",           // basename or FQCN; resolved App\Models\<basename> → FQCN → basename
  "fields": [ {"column": "name", "value": "Followup - {{trigger.task.name}}"},   // key: column | field | name
              {"column": "referencable_type", "value": "App\\Models\\Email"},
              {"column": "linkable_type", "value": "client"},                     // *_type → normalizeMorphType
              {"column": "deleted_at", "value": "NOW()"} ] }
```
Per field: engine `getTemplatedValue` → array/object → `json_encode` (so `{{step_94.ai_content}}` (an object) lands in
`Email.template_data` as a JSON **string**, which works only because the column is cast `array` — Laravel decodes
strings? No: assigning a JSON string to an `array`-cast attribute stores it double-encoded unless the model has a
mutator. Email.template_data / Task.additional_info behaviour must be checked per model in v2) → `processFunctions`
→ `normalizeMorphType` (only `str_ends_with(key,'_type')`) → `ValueSetValidator::validate(model, key, value)`
(soft: logs warning; hard when `value_sets.enforce_validation`/`values.enforce_validation` true).
Then `CreatableViaWorkflow::defaultsOnCreate($context)` fills **missing/null/empty** keys (Task: task_type_id,
milestone_id, assigned_to_user_id, priority, status from `trigger.task`/`task`, status default `To Do`; Context:
referencable from `trigger.email`; Category/CategorySet similar). `requiredOnCreate()` is **not enforced** at runtime
(UI seeds those fields as empty rows only). `fill()` respects `$fillable`/`$guarded` silently — unknown columns dropped;
if nothing dirty → `No valid/fillable fields set…`. `save()` fires events (→ triggers).
Out: `parsed {id, new_record_id, model, schema}`, `context[<basename lower>] = toArray()`.
Errors: missing target_model / model class / no fields / nothing fillable → `InvalidArgumentException|RuntimeException`;
DB errors (NOT NULL, FK, bad date string like WF22's due_date) → `failed` log.
UI (`ActionConfig.vue`): model select (schema models only — `Conversation`, `Context`, `Role`, `Milestone`, `Client`… are
present because the schema BFS follows relations), field rows: column select (labels from schema), value: dropdown when
the column has `allowed_values` (schema) or `/api/value-dictionaries/{model}/{field}` returns options **and the current
value has no `{{`**, else TokenInputField. Selecting a model appends rows for `required_on_create`.

### 6.6 `ACTION_UPDATE_RECORD` — `UpdateRecordStepHandler`
cfg: `{ "action_type":"UPDATE_RECORD", "target_model":"Email", "record_id":"{{trigger.email.id}}", "fields":[…same as create…] }`
`record_id` templated → `(string)` → `Model::query()->find($id)` (**global scopes apply**: soft-deleted rows not found →
`Record X not found`). Fields as in create (functions, morph normalisation incl. `able_type`/`type` keys, validator),
`fill()`, `Email::guardStatusRegression` if Email, `Model::withoutEvents(save)`. No `CreatableViaWorkflow` defaults.
`updated_at` is still touched (timestamps are not events). Out: `parsed {id, model}`, `context[<basename>]`.
Common live use: Email status transitions (`received`, `pending_approval`, `pending_approval_received`, `draft`),
`deleted_at = NOW()` (soft delete via update, bypassing `deleting` events), Lead status/`contacted_at`/`next_follow_up_date`,
Conversation `project_id`/`conversable_*`.
UI: model select, Record ID TokenInputField, fields (same component as create).

### 6.7 `ACTION_SYNC_RELATIONSHIP` — `SyncRelationshipStepHandler`
cfg: `{ "action_type":"SYNC_RELATIONSHIP", "target_model":"Email", "record_id":"{{trigger.email.id}}", "relationship":"categories", "sync_mode":"sync|attach|detach" (default sync), "related_ids":"{{step_189.category_ids}}" | "1,2,3" }`
`related_ids` templated: string → CSV → `intval` each; array → used as-is (**no intval, no flattening** — an AI array of
objects would be passed to `sync()` and fail); other → `[]`. Model class: `App\Models\<name>` then `<name>`.
`Model::find`, `method_exists`, relation instance must be `BelongsToMany|MorphToMany`. Modes: `sync()`,
`syncWithoutDetaching()`, `detach()`. Out: `{action, relationship, record_id, synced_ids|attached_ids|detached_ids, count}`.
UI: model select, record id, relationship select filtered to many-to-many from schema `relationships[].type`, sync mode, related ids.

### 6.8 `ACTION_SEND_EMAIL` — `SendEmailStepHandler`
cfg: `{ "action_type":"SEND_EMAIL", "to":"a@b", "subject":"…", "body":"…" }` all templated (engine). `to` empty →
`InvalidArgumentException`. Sends `Mail::raw($body, to+subject)` **if `config('mail.mailers')` is truthy**, inside a
`try {} catch (\Throwable) {}` that **swallows every error** → always `success`. Plain-text only, single recipient
(no CSV split — a CSV `to` throws inside the swallowed try). Not the Gmail pipeline; this is Laravel's default mailer.
Out: `{to, subject}`. UI: to/subject/body TokenInputFields (body textarea). Live use: internal notifications (WF20) and a test (WF11).

### 6.9 `ACTION_PROCESS_EMAIL` — `ProcessEmailStepHandler`
cfg: `{ "action_type":"PROCESS_EMAIL", "email_id":"{{step_214.new_record_id}}", "email_id_path":"legacy.dot.path", "on_queue":"emails" }`
Resolution order: `email_id` (templated, truthy → `(int)`) → `email_id_path` (`Arr::get`) → `email.id` → `trigger.email.id`
→ `triggering_object_id` (documented as "can be wrong" — e.g. a Lead id on `lead.created`, the bug in `docs/PROCESS_EMAIL_FIX.md`).
`Email::find` → `dispatch(new ProcessDraftEmailJob($email))` (`->onQueue(on_queue)` if set — note `onQueue` is called on
the `PendingDispatch` **after** `dispatch()` returned it; Laravel applies it at destruct, so it works). Out `{queued:true,
email_id, job}`. The job → `EmailProcessingService::processDraftEmail` (services.md §3.2) which sends only if the
email's status permits (draft/auto_send…) — a `pending`/`pending_approval` email is not sent; WF21 sets `pending` →
`draft` in the step *before* PROCESS_EMAIL for this reason.
UI: Email ID TokenInputField, Queue text.

### 6.10 `ACTION_FETCH_API_DATA` — `FetchApiDataStepHandler`
cfg:
```json
{ "action_type":"FETCH_API_DATA", "api_url":"https://…/{{step_278.bugherd_project_id}}/tasks.json", "api_method":"GET|POST|PUT|PATCH|DELETE" (default GET),
  "api_auth_type":"NONE|BEARER|BASIC|CUSTOM_HEADER", "api_auth_token":"…", "api_auth_username":"…", "api_auth_password":"…",
  "api_auth_header_name":"X-Api-Key", "api_auth_header_value":"…",
  "api_payload":"{\"status\": \"backlog\"}",       // templated as a STRING then json_decode → GET/DELETE: query params; others: JSON body; invalid JSON → warning, empty payload
  "api_response_key":"data.items",                 // Arr::get on the decoded body
  "responseStructure":[{"id":…,"name":"tasks","type":"Array of Objects","schema":[…]}] }   // UI-only
```
`Http::asJson()->acceptJson()`; auth applied when the templated value is non-empty; exceptions → `API Request failed: …`;
non-2xx → `RuntimeException("API Request returned status N: body")` (body logged and thrown). Default 30 s HTTP
timeout, no retries, no SSRF/allow-list — any URL with tokens from user data. Credentials live in `step_config` in
plain text (the BugHerd API key is the BASIC username in WF25/26 and is present in the committed JSON dump); the
`config` tab of the logs modal displays `step_config` verbatim, so anyone with `manage_projects` can read them.
Out: `parsed {status, parsed: <data>, duration_ms}`; downstream `{{step_N.tasks}}` resolves via the `.parsed` fallback
(`step_N.parsed.tasks`), `{{step_N.tasks.count}}` via virtual count.
UI: URL, method, auth type + conditional fields, payload textarea, response key, response-structure builder
(name/type: Text|Number|Boolean|Object|Array of Objects, one level of sub-fields).

### 6.11 `QUERY_DATA` / `FETCH_RECORDS` — `QueryDataStepHandler` (same instance for both names)
cfg:
```json
{ "model": "Lead" | "target_model": "…",                          // resolved: as-is, then App\Models\<name>; FQCN accepted
  "conditions": [ {"column"|"field": "campaign_id", "operator"|"op": "==", "value": "{{loop.item.id}}", "json_path": "bugherd_project_id"} ],
  "with": ["user.projects"],                                       // simple eager-load list (wins over relationships)
  "relationships": {"roots":["project"], "nested":{"project":["manager","users"]}, "fields":{"project":["id","name"], "project.users":["id","name"]}},
  "order": [{"field":"created_at","dir":"desc"}],                  // not exposed in UI
  "single": true | "mode": "single",  "count_only": false,  "limit": 50 (1..1000, else 50),  "output_key": "leads" }
```
Eager load from `relationships`: `roots[]` → `"root:col1,col2"` when `fields[root]` present (Laravel `with('rel:cols')`
syntax — **fails at hydration if the FK column is omitted**, same caveat as §6.3), plus `"root.child"` for each nested
child (nested field lists `fields['root.child']` are **ignored** here — only the AI handler honours them). Legacy flat
list form also accepted.
Conditions: `json_path` → `column->path` (dots → `->`); `normalizeOperatorAndValue`: value first through resolver #6
(`'true'`→1, `'false'`→0, tokens → string or `''`), then operator map: `in`/`not in`/`not_in` (array or CSV) →
`whereIn/whereNotIn`; `!=,>=,<=,>,<` → `where`; `is null|is_null|null` → `whereNull`; `is not null|is_not_null|not null|not_null`
→ `whereNotNull`; `older_than_hours|within_hours|older_than_days|within_days` → `where(col, '<'|'>=', now()-N)`;
**everything else (incl. `==`, `=`, `contains`, `like`) → `where(col, '=', value)`**. So the UI's `contains` is silently
equality (WF9 step 42 `goal contains "test"` → `goal = 'test'`). Global scopes apply (soft deletes hidden).
Execution: single → `first()`; else `limit(limit)->get()`; `count_only` → `count()` of a clone and empty records.
Records are `toArray()` (relations nested under snake_case keys, casts applied).
Out: `parsed {count, records[]}` (+ `record` when single; `records` = `[record]`), `context[output_key]` if set.
Errors: model missing → throw; SQL errors (bad column) → `failed`.
UI (`FetchRecordsConfig.vue`): model via `ModelPickerModalV2` (grouped: Core/People/Comms/System/Others, search);
conditions rows: column select, operator select (`==, !=, >, <, >=, <=, contains, is_null, is_not_null` — no `in`, no
`older_than_*`, no `json_path`), value TokenInputField; "Only fetch first matching record" toggle (`single`);
**no** relationships/eager-load picker in V2 (live configs with `relationships` were built in V1 — editing them in V2
preserves the key because `set()` spreads the existing config). No `limit`, `order`, `output_key`, `count_only` UI.

### 6.12 `FOR_EACH` — `StepHandlers/ForEachStepHandler`
cfg: `{ "sourceArray": "{{step_88.records}}" }` → engine resolver; string result → `json_decode` attempt; non-array →
`parsed.iterations = 0`, success (no error). Children: manual scan (`_parent_id == id && empty(_branch)`), order by
`step_order`. Per item: `$iterationContext = $context; ['loop' => ['item','index','is_first','is_last']]` →
`executeSteps(children, workflow, iterationContext, $execLog)`. Return values of iterations discarded; iteration
context discarded (nothing created in a loop is visible after it, except via DB). Out `{iterations: N}`.
Nested loops overwrite `loop` (inner shadows outer; no `loop.parent`). Associative source arrays iterate key→value
with `index` = key. UI: single TokenInputField "Array to loop over".
`app/Services/ForEachStepHandler.php` (root Services dir) is the dead duplicate: wrong PSR-4 path, `execute()` signature,
calls `engine->executeSteps($workflow, $childSteps, $ctx)` with arguments in the wrong order — would fatal if ever wired.

### 6.13 `TRANSFORM_CONTENT` — `TransformContentStepHandler`
cfg: `{ "type": "remove_after_marker|find_and_replace|remove_html", "source": "{{trigger.email.body}}", "marker"|"remove_after": "{{step_61.remove_after}}", "find": "…", "replace": "…" }`
Source templated; non-string → string/JSON. `remove_after_marker`: `trim(marker)` empty → source unchanged; `stripos`
(case-insensitive) → keep text **up to and including** the marker, trimmed (the name says "remove after" — the marker
itself is kept; `normalizeStringForComparison` is dead code). `find_and_replace`: `str_replace`. `remove_html`:
`strip_tags` + `html_entity_decode` + trim. Unknown type → throw. Out `{type, result, cleaned_body, schema}` +
`context.transform.step_N`. UI (`TransformConfig.vue`, also used for `DEFINE_VARIABLE`): type cards, source, marker or
find/replace. **The Builder library and StepPicker add this as `step_type: 'TRANSFORM'`**, which `flattenSteps` does
not translate and the engine has no handler for → a freshly built Transform step fails with "No handler for step type
TRANSFORM". Live rows are all `TRANSFORM_CONTENT` (built in V1). `AutomationSchemaController::getTransformOptions()`
lists only two types (no `remove_html`) and is not consumed by V2.

### 6.14 `DEFINE_VARIABLE` — `DefineVariableStepHandler`
cfg: `{ "variables": [ {"name": "bugherd_project_id", "value": "{{loop.item.integrations.bugherd_project_id}}"} ] }`
(legacy `{variable_name, value}` normalised by the UI on read, written back as `variables`). Name trimmed; empty
skipped; value via resolver #5 (native for single token — arrays allowed). Out `parsed {name: value}` and
`context.variables.{name}`. Tokens: `{{step_N.name}}` (as the UI hint says) or `{{variables.name}}`. UI: rows of
name (auto snake_case) + value TokenInputField.

---

## 7. Schema & metadata endpoints consumed by the builder

### 7.1 `GET /api/automation/schema` — `AutomationSchemaController::getSchema`
Seed models: `Task, Project, Email, Category, CategorySet, ProjectNote, Campaign, Lead, UserProductivity` (`User`
commented out). BFS over discovered relationships (max 99 iterations) pulls in every reachable model
(`Conversation, Context, Milestone, Client, User, Role, ShareableResource, TaskType, …`) — effectively most of the
schema; the response is built on **every request with no cache** (reflection + `Schema::getColumnListing` per model +
one query per `value_sets` model-sourced field). Response:
```json
{ "models": [ { "name": "Email", "full_class": "App\\Models\\Email",
      "columns": [ { "name": "status", "label": "Status", "type": "Text|Number|True/False|DateTime|Date|json|enum",
                     "allowed_values": [{"value":"draft","label":"Draft"}] | null, "is_required": false,
                     "description": null|"…", "ui": null|"morph_type" } ],
      "relationships": [ { "name": "conversation", "type": "BelongsTo", "model": "Conversation", "full_class": "App\\Models\\Conversation" } ],
      "events": [ {"value":"created","label":"is created"}, … ],          // hard-coded per model (§3.1) — includes events that never fire
      "required_on_create": ["name","task_type_id","status","priority"],  // CreatableViaWorkflow::requiredOnCreate
      "defaults_on_create": {…} } ],                                       // defaultsOnCreate(request.context) — request has no context → mostly {}
  "campaigns": [{id,name}],                                                // unused by V2 (store keeps it)
  "transforms": [{value,label}×2],                                          // unused by V2
  "morph_map": [{alias,class,label}] }                                      // store.morphMap — unused by any V2 component
```
Column type inference: casts (`bool→True/False`, `int/decimal/float→Number`, `array/json/collection→json`,
`datetime→DateTime`, `date→Date`) then name heuristics (`id`/`*_id`→Number, `is_*`→True/False, `*_at`→DateTime,
`*_date`→Date) else `Text`. `allowed_values` from `config('value_sets.models.<Model>.<field>')` with sources
`php_enum`, `model` (Eloquent class), `db` (table) — **`model_const` and `config` sources are not supported here** (they
are in `ValueDictionaryRegistry`, and conversely `model` is not supported there). `*_type` columns whose base name is a
`MorphTo` relation get `type: enum`, `ui: morph_type`, options = morphMap aliases (9 aliases for Task/Workflow/Email
only) — so the `sender_type` dropdown offers `task/workflow/email/App\Models\Task…` but **not** `client`/`lead`/`user`.
Relationship discovery: reflection over public, parameterless, non-static methods declared on the concrete class,
skipping `get*/set*/scope*`, *invoking each one* and keeping `Relation` results — a method with side effects would run
on every schema request.

### 7.2 `GET /api/models/available` — `ModelDataController::availableModels`
Scans `app/Models/**.php` (skips `Traits/`), returns `[{value: FQCN, label: basename}]`. Used by admin Category-Set
binding UI, **not** by AutomationsV2.

### 7.3 `GET /api/projects/{project}/model-data/{shortModelName}` and `GET /api/source-models/{modelName}`
Generic record lists (project-scoped / first 100). Used by email templates and other admin UIs, not by the automation
builder (the "related-data picker" in V2 is schema-driven, it never fetches records).

### 7.4 `GET /api/value-dictionaries[/{model}/{field}]` — `ValueDictionaryController`
`registry->all()` (cached 300 s) / `registry->for(model, field)` → `{type:'enum', values:[{value,label}], source, nullable, multi}`
or 404 `[]`. V2 `ActionConfig` calls the per-field endpoint for each configured field (silently ignores 404) and
caches in-component. Route carries `permission:manage_placeholder_definitions` while the automation page needs
`manage_projects` — a user with only the latter gets 403s (swallowed) and loses the dropdowns.

### 7.5 `GET /api/prompts` — paginated 50 (`per_page` ignored); V2 asks for 100 and receives 50.

### 7.6 `GET|POST|PUT|DELETE /api/workflows[/{id}]`, `POST /api/workflows/{id}/run`, `GET /api/workflows/{id}/logs`
* `index` → `Workflow::with('steps')->orderBy('id','desc')->paginate(50)` (Hub shows only the newest 50).
* `show` → workflow + flat `steps` (all rows incl. nested; soft-deleted excluded).
* `store/update` body `{name, description?, trigger_event, is_active?, steps?: [flat rows], schedule?: {...}}`;
  steps synced by `syncWorkflowSteps` (§4.7). Response = workflow + steps (+ `attached_schedule_id`).
* `destroy` → soft delete (cascade soft-delete steps via model hook).
* `logs` → `?per_page (1..200, default 50)&status&step_id&page`; `with('step')`, `orderByDesc('id')`; Laravel paginator JSON.
`WorkflowStepController` (`/api/workflow-steps` CRUD) exists but V2 never uses it.

---

## 8. Execution logs

### 8.1 What is stored per step
One `execution_logs` row per step *attempt*, created at `started` with the **entire context** (`input_context`) and
updated in place. Rows are grouped by `execution_id` (UUID minted per `execute()`; a delayed resume keeps the same
UUID because it travels in the context; an AI job keeps it too). Tree via `parent_execution_log_id`:
* CONDITION children → parent = the condition's log.
* FOR_EACH children → parent = the loop's log (all iterations flat under it; **no iteration index stored** — only
  `input_context.loop.index` distinguishes them).
* Siblings executed after an AI step → parent = **the AI step's log** (artefact of `GenerateAiContentJob` passing
  `$this->execLog`), so a top-level step following a top-level AI step appears nested under the AI step.
* Top-level steps → parent null (`execute()` passes `$parentLog = null` unless called with one — never in practice).
* Delayed step → an extra row with status `scheduled` and no output; the resumed run creates a second row `started→success`
  for the same step with the same `execution_id`.

Statuses in the wild: `started` (still running, or orphaned: AI job never ran / process died / inline prompt),
`success`, `failed`, `scheduled`, `error` (AI job failure — the only writer of this spelling).
`triggering_object_id` column is never populated; `cost` is never populated; `executed_at` is the DB default at
row creation (so ordering by `executed_at` = start time). No workflow-level "run" row exists — the run's status is a
UI-side fold over its step rows.

### 8.2 What `WorkflowLogsModalV2` shows
Opened from the Hub card (per workflow); `store.openLogs(id)` → `GET /workflows/{id}/logs?per_page=30&page=1`.
Grouping (client side, over the current page only): by `execution_id`, else legacy rows grouped under their local root
(`root-<id>`, labelled "Standalone Step"). Per group: `Run #<uuid[0:8]>`, start time (min `executed_at`), status fold
(`failed` > `scheduled` > `success` — `started`/`error` are treated as success-coloured/amber by CSS classes only),
`totalDuration` (sum), `totalCost` (sum, always 0 → chip hidden). Tree-ified via `parent_execution_log_id` inside the
group; a child whose parent is on another page becomes a root. Left pane (splitpanes): collapsible run groups →
recursive `StepItem` list (name from `step.name`, type, duration, status dot). Right pane: header (step name, status
badge, duration, `token_usage` rendered as `{{ selectedStep.token_usage }}` — an **object**, so it prints `[object Object] tokens`),
tabs `output` (JsonViewerV2 of `parsed_output` and `raw_output`), `input` (`input_context`), `config` (`step.step_config`
+ `condition_rules`), `error` (`error_message`). Pagination buttons read `store.logsMeta.current_page/last_page`, but
`fetchLogs` sets `logsMeta = r.meta || null` and Laravel's paginator puts `current_page/last_page` at the **top level**
(`meta` exists only for API Resources) → `logsMeta` is always null → prev/next disabled → **only the newest 30 rows are
ever visible**. Because grouping happens after pagination, a run with >30 step rows (any loop) is truncated, and runs
straddle page boundaries. No filters are exposed (the API supports `status`/`step_id`), no search, no auto-refresh,
no "re-run", no link from a canvas node to its last log.

`JsonViewerV2`: recursive tree, expands to depth 3, per-node collapse, search (highlights matching keys/values and
force-expands), copy-to-clipboard, string values parsed if they contain JSON. No truncation — a loop step's
`input_context` with 50 records × relations renders thousands of nodes.

### 8.3 Gaps
* Retention: none. Every step of every run stores a full context copy (loops multiply it); the table grows without
  bound and the FK `execution_logs.step_id → workflow_steps` (no cascade) makes hard-deleting steps impossible while logs exist.
* Correlation: no `run` entity, no trigger object id, no link to the created records (`parsed_output.new_record_id` only), no
  link from an Email/Lead back to "which automation touched me" — the Inbox docblocks resort to timing heuristics
  (`manual_approval_after_minutes`) to infer whether the automation ran.
* Clarity: status vocabulary inconsistent (`failed` vs `error`); orphan `started` rows indistinguishable from running;
  CONDITION decision only in `parsed_output`; AI siblings mis-parented; iteration index absent; delayed steps produce
  two rows; the `raw_output` for non-AI steps is `null` (engine stores `$out['raw'] ?? $out['output']`, which handlers
  never set) so the "Raw" panel is always empty except for AI.
* Size: `input_context` is a full snapshot at every step (context is append-only within a run, so the last step of a
  20-step run stores ~20× the trigger payload plus every FETCH result). Secrets in `api_auth_*` are not in the context,
  but `trigger.user.email`, full Lead/Client rows and e-mail bodies are, in every row.
* Visibility: `GET /workflows/{id}/logs` is only gated by the general auth group (any authenticated user with API
  access can read all contexts).

---

## 9. V2 UI (`resources/js/Pages/AutomationsV2`)

Route `GET /automations/v2` (`permission:manage_projects`) → Inertia `AutomationsV2/Index`. Stack: Vue 3, Pinia
(`storeV2.js`), `@vue-flow/core` + background/controls/minimap, `splitpanes`, heroicons + lucide, `vue3-toastify`,
Tailwind with a small design system in `index.css` (`.v2-input`, `.v2-select`, `.field-label`, `.v2-btn-primary|secondary|outline`,
`.glass`, `.animate-in`). The older `/automation` (V1 studio, `resources/js/Pages/Automation/**`) still exists and
shares the same API; several V2 behaviours (`normalizeFromServer`, legacy rule mapping, `variable_name` support,
`sourceId`, `field/value/operator` leftovers in CONDITION configs) exist to read V1-authored rows.

### 9.1 `Index.vue`
`view = 'hub' | 'builder'`; on mount `store.ensureSchema()` + `store.fetchWorkflows()`. `createNew()` →
`initNewWorkflow()`; `openWorkflow(id)` → `loadWorkflow(id)`; back → refetch list. Mounts `WorkflowLogsModalV2` globally.

### 9.2 `Hub.vue`
Header + "New Automation"; search (name/trigger, client-side over the 50 loaded); **two decorative selects**
("All Status/Active/Inactive", "Sort: Recent/Name") with no bindings; card grid: icon (clock for `schedule.run`, bolt
otherwise), Active/Draft badge (`is_active`), name (click → open), `trigger_event`, `steps.length` (flat count incl.
nested), a hard-coded `v1.0`, `Updated <updated_at>`; footer buttons: logs (`store.openLogs`), toggle active
(`PUT /workflows/{id} {is_active}` — full-object replace in the list), delete (`DELETE`, **no confirmation dialog**).
Empty state. No "run now", no duplicate, no last-run status, no failing-run indicator.

### 9.3 `Builder.vue`
Header: back, editable name (`store.workflowName`), Save (disabled while saving or `!isReadyForSave` = steps.length>0
&& name non-empty; trigger presence is **not** required by the button — `isTriggerConfigured` getter exists but is unused).
Left "Step Library": 9 draggable/clickable tiles — `TRIGGER, SCHEDULE_TRIGGER, CONDITION, ACTION, FETCH_RECORDS,
FOR_EACH, AI_PROMPT, TRANSFORM, DEFINE_VARIABLE` (drag sets `application/vueflow` data; click adds at canvas centre via
`useVueFlow('v2-canvas').project`). Both paths call `store.addStep(type, null, null, position)` — "STRICT RULE: new
blocks are always unlinked". Empty-state overlay. Centre `Canvas`, right `PropertiesSidebar`.
`StepPickerModalV2.vue` (categorised "Add Automation Step" modal that emits `{type, actionType}`) is **not imported
anywhere** in V2 — dead component.

### 9.4 `Canvas.vue` (vue-flow)
Constants `NODE_WIDTH 280, NODE_HEIGHT 130, V_GAP 80, H_GAP 80`. `buildElements(steps, parentNodeId, sourceHandle, startX, startY, stepsAbove)`
runs on every deep change of `store.workflowSteps` (`setNodes/setEdges` wholesale):
* node id `step-<id>`, type `custom`, position = `step_config.position` if saved else computed column layout;
  `data = {step, allStepsBefore}`.
* edges: first child of a container ← parent with `sourceHandle` `yes|no|children` (label TRUE/FALSE/LOOP, indigo);
  sibling `i>0` ← sibling `i-1` **only if the previous sibling is not a CONDITION/FOR_EACH** (containers have no
  plain `source` handle) — so a step placed *after* a CONDITION in the same list has **no incoming edge** and is
  visually indistinguishable from an unlinked node, although the engine will run it after the condition. Unlinked
  nodes (`is_unlinked`) get no incoming edge.
* CONDITION: `if_true` column at `x - 360`, `if_false` at `x + 360`, both starting below the node; FOR_EACH children
  in the same column; `currentY` continues below the deepest branch. No collision avoidance across sibling branches
  that both contain containers.
* `onNodeDragStop` → persists `position` into `step_config` (so `step_config.position` is saved to DB).
* `onConnect(params)`: branch = `sourceHandle.replace('source','')` → `'yes'|'no'|'children'|''`; removes vue-flow's
  phantom edge; `removeFromTree(tgt)`; `delete is_unlinked`; branch → `insertNested` (append to that branch), else
  `insertAfter(src)` (insert immediately after source in its sibling list). Connecting a node to a container's plain
  output is impossible (no handle), hence "after a condition" can only be authored by connecting the node to the
  *previous* node and dragging order — there is no explicit reorder UI except re-connecting.
* `onEdgesChange` removals → target detached to root with `is_unlinked = true` (its own subtree comes along).
* Drop from library → `addStep` at projected position. `fitView` on mount (200 ms). MiniMap coloured by type.
Save format: `flattenSteps(tree)` → flat rows `{id: number|'temp_<ts>', step_order: index+1 (per sibling list), name,
step_type (SCHEDULE_TRIGGER→TRIGGER + trigger_event), prompt_id?, delay_minutes, step_config{…, _parent_id, _branch,
position, is_unlinked}, condition_rules}` in pre-order. Unlinked nodes are saved as **top-level steps and will execute**
in `(step_order,id)` order — the canvas's "free-floating" affordance has no runtime meaning.
Load: `rebuildTree(flat)` (by `_parent_id`/`_branch`, sorted by `step_order`; orphans → top level) or
`normalizeFromServer` if the payload is already nested (never, with this API).

### 9.5 `CustomNode.vue`
Header gradient + icon per type, name, delete button (hidden for triggers; **deleting a container deletes its subtree**
with no confirm), body summary per type (trigger `Model → event`, action type, `N rule(s), AND`, `From: Model`,
`Over: {{…}}`, `Prompt: name`, transform type, `Var: name`/`N variables`), delay badge, TRUE/FALSE footer for
conditions, LOOP BODY footer for loops. Handles: `target` (top, not for triggers), `source` (bottom, non-containers),
`yes` (25%), `no` (75%), `children` (centre). `addStepAfter()` exists but is not wired to any button.

### 9.6 `PropertiesSidebar.vue`
420 px slide-over when `store.selectedNodeId`. Header, "Step Name", "Delay (min)" (non-trigger), then the per-type
panel: TRIGGER/SCHEDULE_TRIGGER→`TriggerConfig`, ACTION→`ActionConfig`, CONDITION→`ConditionConfig`,
FETCH_RECORDS→`FetchRecordsConfig`, FOR_EACH→`ForEachConfig`, AI_PROMPT→`AIConfig`, TRANSFORM/TRANSFORM_CONTENT/
DEFINE_VARIABLE→`TransformConfig`; others → "No configuration available" (a legacy `QUERY_DATA` row, for instance).
All panels receive `step` + `allStepsBefore` (pre-order prefix, §5.6) and emit whole-step replacements
(`store.updateStep` → `findAndReplace` rebuilds the tree → Canvas re-renders every node on every keystroke).
Every panel mutates through `emit('update:step', {...step, step_config: {...}})`; several bind `v-model="config.xyz"`
directly on a computed getter's object (e.g. `TokenInputField v-model="config[field]"`, `cond.value`, `field.value`) which
mutates the store object in place *and* emits — works because Pinia state is reactive, but bypasses the setter.

### 9.7 Token components
`TokenInputField` (input or textarea + `DataTokenInserter` "+" button; inserts at caret). `DataTokenInserter` builds
sources (§5.6) and opens `TokenPickerModalV2` (Teleport modal, z-500: left source list, search, grid of
`{{source.field}}` cards). `RelatedDataPickerV2` (AI only): left slide-over with base-field checkboxes (→ `aiInputs`),
relationship roots (checkbox → `roots`), per-root field checkboxes + "select all" (`fields[root] = ['*']`), nested
relations one level deep (`nested[root][]`, fields under `fields['root.child']`); Save writes both models atomically.
`ModelPickerModalV2` (FETCH_RECORDS only): grouped model chooser. `JsonViewerV2`: §8.2.

### 9.8 `storeV2.js` — state and API calls
State: `workflows[]`, `isLoadingWorkflows`, `activeWorkflow`, `workflowSteps` (nested tree), `workflowName`, `isSaving`,
`isLoadingWorkflow`, `automationSchema[]`, `campaigns[]`, `morphMap[]`, `prompts[]`, `isLoadingSchema`, `selectedNodeId`,
`logs[]`, `logsMeta`, `isLoadingLogs`, `showLogs`, `logsWorkflowId`, `alert` (unused).
Getters: `selectedStep`, `isTriggerConfigured` (unused), `isReadyForSave`.
Actions → endpoints (all via `Api/automationApi.js`, axios, `/api` base):
| action | call |
|---|---|
| `ensureSchema` | `GET /api/automation/schema` (once per page load) |
| `ensurePrompts` | `GET /api/prompts?per_page=100` (once) |
| `fetchWorkflows` | `GET /api/workflows` |
| `deleteWorkflow` | `DELETE /api/workflows/{id}` |
| `toggleActive` | `PUT /api/workflows/{id}` `{is_active}` |
| `loadWorkflow` | `GET /api/workflows/{id}` |
| `saveWorkflow` | `POST /api/workflows` or `PUT /api/workflows/{id}` with `{name, trigger_event, is_active, steps}` |
| `fetchLogs` | `GET /api/workflows/{id}/logs?per_page=30&page=N` |
| (ActionConfig) | `GET /api/value-dictionaries/{model}/{field}` |
| (automationApi, unused) | `createPrompt`, `updatePrompt`, `fetchValueDictionary` |
Not called by V2: `POST /workflows/{id}/run`, `POST /workflows/triggers/{event}`, `/workflow-steps`, schedule endpoints.
Tree helpers: `flattenSteps`, `rebuildTree`, `findStepById`, `findAndReplace`, `findAndDelete`, `addNested`,
`normalizeFromServer`, `defaultNameFor`. `addStep` ids are `temp_<Date.now()>` (two adds in the same ms collide).
No undo, no dirty tracking (navigating back discards silently), no validation before save (missing model/prompt/rules
all save fine and fail at runtime), no autosave, `description` never editable.

### 9.9 UX assessment
Keep: the split Hub/Builder; typed, colour-coded nodes with live summaries; single right-hand properties panel;
token insertion at the caret with a searchable, source-grouped picker; the relationship tree picker for AI payloads
(roots → fields → nested) — this is the most valuable authoring affordance in the system; grouped model picker;
per-run grouped log timeline with input/output/config/error tabs and a searchable JSON tree; toast feedback.
Fix: unlinked-node semantics (they run); no edge after containers; the `TRANSFORM` type mismatch; trigger token
paths; missing operators; pagination of logs/prompts/workflows; no delete confirmations; no validation; no run-now;
no schedule creation from the trigger node; positions and `is_unlinked` persisted into runtime config; ActionConfig
offering `CHECK_MILESTONE_COMPLETION`; the properties panel re-rendering the whole canvas per keystroke; no
keyboard/accessibility on canvas; a 420 px panel for a 15-field CREATE_RECORD is cramped.

---

## 10. Prompt library

### 10.1 Storage and runtime use
Table §1.4. Runtime consumers: `AiPromptStepHandler::resolvePrompt` (by `promptRef.id`), `AIGenerationService::generate`
(`system_prompt_text` templated against **promptData**, `response_json_template` JSON-appended to the system
instruction, `generation_config`, `model_name`). `response_variables` is consumed only by the UI (`AIConfig` copies it
into the step's `responseStructure` at prompt-selection time). `template_variables` is not used at runtime; the
editor's variable chips are documentation for the prompt author about which `{{…}}` keys the workflow will supply.
`status` (`active|draft|archived`) is not filtered anywhere — archived prompts remain selectable and executable.
Versioning: `(name, version)` unique; "Save as New Version" posts a copy with `version+1` (client strips `id`); steps
reference a specific `promptRef.id`, so a new version does not propagate — WF14 uses v2, WF17 v1, dead WFs v3 of
"Email Approval Analysis".

### 10.2 Editor (`resources/js/Pages/Automation/Prompts/*`, route `/prompt` and `/admin/prompts`, `auth+verified` only — no permission)
`Index.vue`: library table grouped by `name` (latest version shown, `allVersions` kept), status badge, "time since";
create/edit → `PromptEditor` → `PromptForm`. Save → `store.updatePrompt` / `store.createPrompt` (V1 `workflowStore`).
`PromptForm.vue`: left — System Prompt textarea and `ResponseBuilder`; right panel (toggle) — name, category, status,
template-variable chips (sanitised `[a-zA-Z0-9_]`), model name, generation config (Temperature slider 0–1 default 0.7,
Max output tokens default 2048, Response format `text/plain|application/json` default JSON, raw JSON editor toggle),
Cancel / Save / "Save as New Version (vN+1)". Defaults injected on load: `responseMimeType='application/json'`,
`maxOutputTokens=2048`. A duplicate `generateResponseJson` helper in PromptForm is unused (ResponseBuilder has its own).
`ResponseBuilder.vue` (two-way `responseVariables` + `responseJsonTemplate`): `FieldBuilder` form (rows: name, type
`Text|Number|Boolean|Date|Select|File|Object|Array`; Select → comma options; Array → itemType `Text|Number|Boolean|Date|Object`;
Object / Array-of-Object → recursive nested `FieldBuilder` under `schema`; per-field "AI Preview Value" example),
"Import JSON" (paste a schema array), read-only "Schema JSON (Raw)" (= `transformFormToJson(localSchema)`: `{name,type,
validations:{}, options[], itemType, schema[], example}`), read-only "AI JSON Preview" (`buildAiPreview`: example
values or placeholders like `"Enter your subject here."`, `0`, `true`, `[…]`). On mount and on every change it emits
**both** `response_variables` (form shape, options as CSV string) and `response_json_template` (normalised array) — they
are the same schema in two serialisations. `PromptResponseBuilder.vue` (older JSON-object-first builder that infers a
structure from a pasted JSON object) is **unused**.

### 10.3 How `response_json_template` drives parsed output
There is no schema validation anywhere. The mechanism is purely prompt-side: the system instruction ends with the
schema array (`[{"name":"approval_required","type":"Boolean",…}]`) and `responseMimeType=application/json` asks Gemini
for JSON; whatever JSON comes back is `json_decode`d and stored as `parsed`. Field names in `responseStructure`
(UI) → token suggestions `{{step_N.<name>}}`; nested paths (`step_212.summary.context_summary`) are typed by hand
(V2 picker offers top-level names only). Type drift between the schema and the actual response (e.g. `Tasks` vs
`tasks` in WF10: `responseStructure` says `Tasks`, the FOR_EACH iterates `{{step_61.tasks}}`) is undetectable until
runtime. Booleans arrive as JSON booleans, so `== "true"` comparisons rely on `looseEq`'s bool handling; strings
`"true"` from a text-typed field also match. Arrays of objects (tasks) are iterated by FOR_EACH and each item's keys
(`name, task_type_id, priority, description, due_date, assign_to_user_id`) are consumed by CREATE_RECORD Task — the
prompt text is the only contract for those key names.

---

## 11. Value sets / dictionaries

`config/value_sets.php` hints: Task.status (php_enum TaskStatus), Task.task_type_id (`source: model` TaskType — handled by
the schema controller, **ignored** by the registry/validator), Milestone.status, Project.status, Email.status
(EmailStatus, 13 cases), Email.sender_type (`source: model` Client — nonsense for a morph column; ignored by registry),
Email.type (EmailType), ProjectExpendable.status, BonusTransaction.status, Lead.status (LeadStatus). `cache_ttl 300`,
`enforce_validation false`, `log_channel stack`; `config/values.php` duplicates the two flags from env
(`VALUES_ENFORCE_VALIDATION`, `VALUES_LOG_CHANNEL`) — validator reads `value_sets.*` first then `values.*`.
`ValueDictionaryRegistry::buildAll()` also auto-detects enum casts on every model in `app/Models` (instantiates each
model once per cache miss). `ValueSetValidator::validate(model, field, value)`: unknown field → no-op; nullable/empty
→ ok; enum objects → `->value`; arrays/multi → each; invalid → warning log (or `ValidationException` when enforcing).
Called by CREATE_RECORD (fields + defaults) and UPDATE_RECORD (fields). Live configs are enum-clean except values
like `"To Do"` (valid TaskStatus) and Lead `processing/contacted/qualified/hot_incoming` (valid). Nothing validates
`task_type_id` (`source: model` unsupported) or FK existence.

---

## 12. Scheduling contract as used by workflows
Covered in services.md §2; the workflow-specific facts: the Schedule row must be created outside the builder;
`scheduled_item_type` is stored as `'workflow'` (morph alias registered in `AppServiceProvider`) ; `RunScheduledItem`
→ `Workflow::runScheduled` → `RunWorkflowJob` with the §3.4 context; `last_run_at` is written even when the workflow
job later fails; five live schedule workflows (9, 12, 13, 21, 25) all follow `TRIGGER → FETCH_RECORDS → FOR_EACH`.
A schedule workflow's FETCH_RECORDS conditions can only use literals and time operators (`older_than_hours`), since
the context has no model data.

---

## 13. Live workflow catalogue (from `workflow_steps.json`)

Method: 289 rows; 196 with `deleted_at = null` across workflow ids 8–14 and 17–28 (19 workflows). The `workflows`
table is **not** in the dump, so names, `is_active` and the actual `trigger_event` column are unknown — the trigger
below is the TRIGGER step's `step_config.trigger_event`, which `saveWorkflow()` copies into the column. Prompt names
come from `promptRef` snapshots in the steps. Trees are reconstructed from `_parent_id/_branch` and sorted by
`step_order` per scope, exactly as the engine does. "Broken" findings are from static analysis of the config against
the handlers; "(inferred)" marks behaviour that depends on runtime data.

Step counts: ACTION 70 (CREATE_RECORD 33, UPDATE_RECORD 23, SEND_EMAIL 4, PROCESS_EMAIL 4, FETCH_API_DATA 4,
SYNC_RELATIONSHIP 2), CONDITION 43, FETCH_RECORDS 27, TRIGGER 19, AI_PROMPT 18, FOR_EACH 12, TRANSFORM_CONTENT 4,
DEFINE_VARIABLE 3. Triggers: `email.created` ×6 (WF10,14,17,18,20,23), `schedule.run` ×5 (9,12,13,21,25),
`task.updated` ×3 (8,22,26), `projectnote.created` ×2 (24,28), `user.updated` (11), `lead.created` (19),
`userproductivity.created` (27). Only one step has a delay (WF20 #231, 3 min). No step uses `prompt_id`; all AI steps
use `promptRef`. Prompts referenced (id → name/version): 1 Email Approval Analysis v1, 2 AI Email Receiver – Approval
v1 *and* 2 Task Manager v1 (same id, two names — one snapshot is stale), 4 Email Approval Analysis v2, 7 AI Email
Approval, 8 Outgoing Email Verification *and* 8 Meeting Minutes Processor (same id, two names), 9 New Lead Cold Email,
10 Lead Follow-up, 11 New Unknown Email, 12 New Warm Incoming Lead, 13 Lead Continuous Communication, 14 Warm Lead
Follow Up, 15 Assigning Project to Email, 16 Meeting Minutes Handler, 17 Assign Bugherd Tasks, 18 Ai Productivity
Reporter. The duplicated ids mean prompts were renamed/replaced in place after the steps snapshotted them — the
`promptRef.name` shown on the canvas can lie.

Legend for v2 flags: **[E→M]** Email→Message, **[L/C→Contact]** Lead/Client→Contact, **[PN→Comment/Standup]**
ProjectNote, **[Task]** Task field changes, **[Project]** Project field changes, **[Context]** the polymorphic
`Context` "memory" model (summary/referencable/linkable/meta_data), **[Conv]** Conversation (conversable morph,
project_id), **[Morph]** literal morph strings (`App\Models\Lead`, `client`, `App\Models\User`) in configs.

### WF8 — "Follow-up task on task update" · trigger `task.updated` · 3 steps
Trigger → AI (prompt 2 "Task Manager"; inputs: 16 task columns incl. `previous_status`, `needs_approval`,
`project_deliverable_id`, `block_reason`; relationships `assignedTo.name`, `milestone.name`, `projectDeliverable.name,description`,
`taskType.name`) → CREATE Task `{name: "Followup - {{trigger.task.name}}", task_type_id: 4, status: "To Do", priority: "High", description: "{{step_55.description}} - {{step_55.context_summary}}"}`.
No condition at all: **every** task update spawns a follow-up task (and `Task::defaultsOnCreate` copies `milestone_id`/
`assigned_to_user_id` from the trigger). Almost certainly inactive; if active it is a task generator. **[Task]**
(status enum, `task_type_id=4` literal FK, `previous_status`, `needs_approval`, deliverable link).

### WF9 — schedule test (broken) · `schedule.run` · 6 steps
Trigger → FETCH Campaign (`is_active == true` → `=1`; `goal contains "test"` → **`goal = 'test'`**) → FOR_EACH over
`{{step_temp_1757802064599.records}}` (**unremapped temp id → resolves null → 0 iterations**) ⟶ [FETCH Lead by
`campaign_id = {{loop.item.id}}` → FOR_EACH → AI with an **inline `prompt` string** (dead at runtime, §4.5)].
Does nothing. Delete rather than migrate.

### WF10 — "Incoming client e-mail triage, memory + task extraction" · `email.created` · 19 steps
Top level: Trigger → FETCH Conversation (single, `id = {{trigger.email.conversation_id}}`, with `project` +
`project.manager/users` id,name) → FETCH Role (all) → COND `trigger.email.type == received`.
YES → COND `sender_type == client` (morph-normalised) →
 YES: AI (prompt 7 "AI Email Approval"; inputs subject/body/is_private/status; freeText = the conversation record) →
 TRANSFORM `remove_after_marker(body, {{step_61.remove_after}})` → COND `step_61.approval_required == true` →
   YES: UPDATE Email `{status: received, body: cleaned}` + CREATE Context (summary, referencable Email, `linkable_type: "client"`,
   `linkable_id: conversation_id` ← **wrong id** (conversation id used as client id), `meta_data` = free text);
   NO: UPDATE Email `{status: pending_approval_received, body: cleaned}` + CREATE Context (`linkable_id: {{trigger.email.conversation.conversable.id}}`,
   relies on the relation being loaded at save time).
   The YES/NO mapping reads inverted (approval *required* ⇒ `received`); verify prompt 7's semantics before porting.
 → FETCH Conversation again (with `project.milestones`) → FETCH Milestone (single, `name == support`, **no project filter**) →
 FOR_EACH `{{step_61.tasks}}` (responseStructure says `Tasks`; runtime key is whatever Gemini returns) → CREATE Task from
 loop item (`name, task_type_id, status To Do, priority, description, due_date, needs_approval`) with `milestone_id = {{step_180.record.id}}`.
 NO (not client): COND `sender_type == client` again (always false here) — dead.
NO (type = sent): COND `sender_type == client` → AI "Outgoing Email Verification" → COND `approval_required == false`
with **no children** — dead end.
Touches Email, Conversation, Context, Milestone, Task, Role. Overlaps WF17/18/20/23 on the same trigger (§14 #R1).
**[E→M][L/C→Contact][Context][Conv][Task][Morph]**

### WF11 — smoke test · `user.updated` · 4 steps
COND `trigger.user.id == 1` → SEND_EMAIL "yes"/"no" to a personal Gmail address (redacted). Delete.

### WF12 — "Cold outreach to new campaign leads" · `schedule.run` · 12 steps
Trigger → FETCH Campaign `is_active == true` → FOR_EACH campaigns ⟶ FETCH Lead `campaign_id = {{loop.item.id}} AND status = new`
→ FOR_EACH leads ⟶ COND `{{loop.item.next_follow_up_date}} in_past` (null date ⇒ **false** — a brand-new lead with no
date is never processed (inferred)) → YES: UPDATE Lead `status: processing` → AI (prompt 9 "New Lead Cold Email"; 32 loop
columns; relationships `campaign` name/target_audience/services_offered/goal/ai_persona/email_template +
`campaign.shareableResources` title/description/url/type — loop-path filtering only applies to `campaign`) → UPDATE Lead
`{status: contacted, next_follow_up_date: {{step_94.next_follow_up_date}}}` → CREATE Conversation `{subject, conversable_type: App\Models\Lead, conversable_id}`
→ CREATE Email `{sender_type: App\Models\User, sender_id: 1, to: lead email, subject, body: ai_content, status: draft, email_template: ai_lead_outreach_template, template_data: ai_content, conversation_id: {{step_96.new_record_id}}}`
→ CREATE Context (summary = ai_content, `referencable_id: {{loop.item.id}}` ← **lead id where an email id is expected**, linkable Lead).
The draft Email fires `email.created` → WF17 decides send vs `pending_approval` (chain dependency). `ai_content` is an
object → stored JSON-encoded in `body`/`template_data`. Each lead = one Gemini job in parallel. **[L/C→Contact][E→M][Conv][Context][Morph]**

### WF13 — "Follow-up to contacted leads" · `schedule.run` · 10 steps
Same skeleton as WF12 with `status = contacted` and `next_follow_up_date in_past` → AI (prompt 10 "Lead Follow-up";
relationships `contexts` summary/meta_data/created_at + `campaign`) → CREATE Conversation → CREATE Email (`draft`, `type: sent`,
`is_private: 1`) → CREATE Context (referencable = the new Email — correct here). **Nothing advances
`next_follow_up_date` or status**, so the same lead is followed up on every schedule run until something else changes it
(inferred). **[L/C→Contact][E→M][Conv][Context]**

### WF14 — "Strip quoted thread / signature on every e-mail" · `email.created` · 6 steps
Trigger → AI (prompt 4 "Email Approval Analysis v2"; body, subject) → COND `step_115.remove_after not_empty` →
YES: TRANSFORM `remove_after_marker(body, marker)` → UPDATE Email `{body: result, status: received}`; NO: UPDATE Email `{status: received}`.
Unconditional on type/status: if active it rewrites **every** created e-mail (including outgoing drafts) to
`status = received`, which would defeat WF17's approval gate and WF18's `unknown` handling. Must be inactive in
production, or superseded — verify. **[E→M]**

### WF17 — the outgoing approval gate + project-client inbound handling · `email.created` · 19 steps
Top level: Trigger → TRANSFORM `remove_html({{trigger.email.body}})` (#174) → COND `status == draft` (#175).
YES → COND `type == sent` (#159) →
 YES (outgoing draft): AI #160 (prompt 1 "Email Approval Analysis v1"; id/to/subject/body/status/template_data;
 relationships `conversation` id,subject) → COND #169 `step_1670.approval_required == false` — **`step_1670` does not
 exist** (id-remap corruption, §4.7; should be `step_160`) ⇒ left = null ⇒ `null == "false"` ⇒ **always NO** →
 UPDATE Email `status: pending_approval` (#162) + CREATE Context (#171, `linkable_type: App\Models\Client`,
 `linkable_id: {{trigger.email.id}}` ← wrong id). The YES path (#161 PROCESS_EMAIL → send, #170 Context) is unreachable.
 Net: **every outgoing draft is parked for a human; the AI verdict is ignored.** This matches the Inbox docblock's
 expectation ("the automation hands it back as pending_approval") but means the "auto-send if approved" capability
 documented in services.md §3.3 is not actually live (inferred from the dump; verify against production rows).
 NO (received but status draft — the Gmail path for known clients with a project): COND `sender_type == client` (#163) →
 AI #164 (prompt 2 "AI Email Receiver – Approval"; subject/type/template_data; freeText `{{step_17389.result}}` — corrupt,
 should be `{{step_174.result}}`, resolves to `"null"`) → COND #172 `step_17381.approval_required == false` (corrupt ⇒ NO) →
 UPDATE Email `status: pending_approval_received` (#168); the unreachable YES path would strip the quoted thread
 (#165/#166/#173 using `step_17384` — also corrupt) or set `received` (#167). Then CREATE Context #176
 (`linkable_id: {{trigger.email.conversation.conversable.id}}`).
Migration must **re-author** this workflow from intent, not copy it. **[E→M][L/C→Contact][Context][Morph]**

### WF18 — "Unknown-sender triage" · `email.created` · 23 steps
Trigger → COND `status == unknown` → YES (in order): FETCH CategorySet (single `name == Emails`, with `categories` id,name)
→ AI (prompt 11 "New Unknown Email"; sender_id/to/subject/body/template_data; freeText = category set records) returning
`approval_required, reason, context_summary, remove_after, is_private, category_ids, discard, new_category, name, lead, email`
→ COND `new_category not_empty` → CREATE Category `{name, category_set_id}` + SYNC Email.categories **attach** the new id
→ COND `category_ids not_empty` → SYNC Email.categories **sync** `category_ids` (replaces — drops the just-attached
category unless the AI included it) → COND `discard == true` → UPDATE Email `deleted_at: NOW()` (soft-delete by column;
every later `Email::find` in this run then fails "Record not found") → COND `is_private == true` → UPDATE `is_private: true`
→ COND `approval_required == true` → UPDATE `status: pending_approval_received` / else `status: received` → FETCH
Conversation (single, unused) → COND `lead == true` → YES: CREATE Lead `{first_name: {{step_189.name}}, status: hot_incoming, email: {{step_189.email}}, metadata: "{\"subject\": …}"}`
(→ fires `lead.created` → WF19) → UPDATE Email `{sender_type: App\Models\Lead, sender_id: {{step_203.id}}}` → UPDATE Conversation
`{conversable_type: App\Models\Lead, conversable_id}` → CREATE Context (linkable Lead, meta_data reason); NO: CREATE Context with
`linkable_type: null`, `linkable_id: {{trigger.context.linkable_id}}` (nonexistent path) → likely NOT NULL failure.
**[E→M][L/C→Contact][Conv][Context][Morph]** + Category/CategorySet.

### WF19 — "Warm inbound lead auto-reply" · `lead.created` · 16 steps
Trigger → COND `trigger.lead.status == hot_incoming` → YES: FETCH Email (`sender_id = lead.id AND sender_type = App\Models\Lead`, unused)
→ AI (prompt 12 "New Warm Incoming Lead"; first_name/last_name/email/status/metadata) → `subject, ai_content, action, summary{context_summary, reason}`
→ COND `action not_empty AND action == auto_send` →
 YES: CREATE Conversation → CREATE Email `{sender User/1, to: lead email, body & template_data: ai_content, status: auto_send, type: sent, subject, is_private: 1, email_template: ai_lead_outreach_template, conversation_id}`
 → PROCESS_EMAIL `{{step_214.new_record_id}}` (sends now) → UPDATE Lead `{status: contacted, contacted_at: NOW()}` → COND
 `summary.context_summary not_empty` → CREATE Context (referencable = new Email, linkable Lead, meta_data reason).
 NO: COND `action == review_required` → CREATE Conversation → CREATE Email `status: pending_approval` → COND → CREATE Context.
Nested-path tokens (`step_212.summary.context_summary`) hand-typed. **[L/C→Contact][E→M][Conv][Context][Morph]**

### WF20 — "Lead conversation continuation" · `email.created` · 18 steps
Trigger → COND `type == received AND sender_type == lead` → YES: FETCH Lead (single `id = sender_id`) → AI (prompt 13 "Lead
Continuous Communication"; subject/body/template_data/id/sender_id/sender_type; relationships `sender` `*` + `sender.contexts`
summary,meta_data) → `subject, ai_content, action, summary, incoming_summary` →
 COND `action == auto_send` → YES: CREATE Email (reply into `trigger.email.conversation_id`, `status: auto_send`, `type: sent`, to `{{step_229.record.email}}`)
 → CREATE Context (outgoing summary) → PROCESS_EMAIL `{{step_228.new_record_id}}` **delay 3 min** (the only delayed step);
 NO: COND `action == human_intervention_required` → YES: CREATE Email (no status → model default) → CREATE Context → SEND_EMAIL
 internal notice ("Email is Creating Waiting for Human Internention" [sic]) ; NO: COND `action == booking_confirmed_stop_communication`
 → SEND_EMAIL internal "Client confirmed booking…" → UPDATE Lead `{status: qualified, contacted_at: NOW()}`.
 → COND `incoming_summary.incoming_context_summary not_empty` → CREATE Context (incoming) → UPDATE Lead `contacted_at: NOW()`.
Because of §4.4/§4.6 the 3-minute resume replays the enclosing scope: steps after #227 in the `225/yes` list (#234 →
#235 Context, #255 Lead update) run **twice** per auto-send — duplicate incoming Context row (inferred from engine code).
Internal notification address is a company mailbox (redacted). **[E→M][L/C→Contact][Context][Conv]**

### WF21 — "24-hour follow-up for warm (non-campaign) leads" · `schedule.run` · 10 steps
Trigger → FETCH Lead (`contacted_at is_not_null AND campaign_id is_null AND status = contacted AND contacted_at older_than_hours 24`, with `contexts`)
→ FOR_EACH ⟶ AI (prompt 14 "Warm Lead Follow Up"; **no aiInputs**, freeText is a hand-built JSON-ish string of
`loop.item.first_name/metadata/company/contacted_at`) → FETCH Conversation (single, `conversable_type = App\Models\Lead AND conversable_id = loop id`)
→ CREATE Email (`status: pending`, `type: sent`, conversation `{{step_253.record.id}}`) → UPDATE Lead `contacted_at: NOW()` (resets the 24 h clock ⇒
one follow-up per day indefinitely while `contacted`) → COND `action == auto_send` → UPDATE Email `status: draft` (withoutEvents, so
WF17 never sees it) → PROCESS_EMAIL `{{step_248.new_record_id}}` (direct send, no approval). **[L/C→Contact][E→M][Conv][Morph]**

### WF22 — "Spawn QA task when a task is Done" · `task.updated` · 4 steps
COND `trigger.task.status == Done AND trigger.task.requires_qa == 1` → FETCH Milestone (single, with `project.*`, unused) → CREATE Task
`{name: "QA For - {{trigger.task.name}}", task_type_id: 2, status: To Do, priority, description, due_date: "DATE_ADD(CURDATE(), INTERVAL 1 DAY)"}`.
The `due_date` is a **literal SQL string** → invalid date → DB error → step `failed` → no QA task (or, on a lenient
MySQL mode, `0000-00-00`). Also re-fires on every further update of a Done task (no idempotency). **[Task]** (`requires_qa`, `task_type_id=2`).

### WF23 — "Attach client e-mail conversation to a project" · `email.created` · 6 steps
COND `sender_type == client` → FETCH Client (single `id = sender_id`, with `projects` id,name,description,website) → AI (prompt 15
"Assigning Project to Email"; 12 trigger columns; freeText = client record) → `project_id, reason` → COND `step_264.project_id >= 1`
→ UPDATE Conversation `{{trigger.email.conversation_id}}` `{project_id}`. **[L/C→Contact][E→M][Conv]**

### WF24 — "Meeting minutes → support tasks" · `projectnote.created` · 8 steps
Trigger → FETCH Project (single `id = projectnote.project_id`, with admin/client/manager/users id,name[,role_id]) → FETCH Role (all)
→ COND `trigger.projectnote.type == meeting_minutes` → YES: AI (prompt 16 "Meeting Minutes Handler"; note columns incl. `content`,
`noteable_*`, `chat_message_id`; freeText = project + roles records; relationships `project` id,name) → `context_summary, tasks[]`
→ FETCH Milestone (single `project_id AND name == support`) → FOR_EACH `{{step_268.tasks}}` ⟶ CREATE Task
`{name, task_type_id, status To Do, priority, assigned_to_user_id: {{loop.item.assign_to_user_id}}, description, due_date, milestone_id: {{step_272.record.id}}}`.
Relies on a milestone literally named "support" per project. **[PN→Comment/Standup][Task][Project]**

### WF25 — "BugHerd backlog import" · `schedule.run` · 15 steps
Trigger → FETCH Project (`integrations->bugherd_project_id is_not_null` via `json_path`, with admin/client/users/manager)
→ FOR_EACH projects ⟶ DEFINE `bugherd_project_id = {{loop.item.integrations.bugherd_project_id}}` → FETCH Milestone (single `name == support`,
**global**, and never used) → FETCH_API GET `https://www.bugherd.com/api_v2/projects/{{step_278.bugherd_project_id}}/tasks.json`
(`api_payload {"status":"backlog"}` → query string; BASIC auth, **API key stored in `step_config` — redacted here**) → `tasks`
→ COND `step_279.tasks.count >= 1` (virtual count) → YES: AI (prompt 17 "Assign Bugherd Tasks"; loop inputs `reporting_sites`,
`contract_details`, `profit_margin_percentage`; freeText = API tasks + whole project item; relationships roots users/manager/admin/clients
with `base_model: null` → loop-path pulls those keys from the project array) → `context_summary, tasks[]` → FOR_EACH `{{step_295.tasks}}`
(inner `loop` shadows the project) ⟶ DEFINE `taskId = {{loop.item.id}}` → FETCH Task (single `source_id = loop id`) → COND
`step_286.count < 1` → YES: FETCH_API GET task detail → CREATE Task `{name, task_type_id, status To Do, priority, additional_info: "{\"bugherd_info\": {{step_288.task}} }", source: bugherd, source_id, description, due_date, assigned_to_user_id}`
(**no milestone/project linkage** — the support milestone fetched earlier is unused and the project is out of scope) → FETCH_API PUT
task `status: todo`. **[Task]** (`source`, `source_id`, `additional_info` JSON) **[Project]** (`integrations`, `reporting_sites`,
`contract_details`, `profit_margin_percentage`).

### WF26 — "BugHerd done sync" · `task.updated` · 4 steps
COND `source == bugherd AND status == Done` → DEFINE `project_id = {{trigger.task.additional_info.bugherd_info.project_id}}` → FETCH_API PUT
`…/projects/{{step_293.project_id}}/tasks/{{trigger.task.source_id}}.json` `{"task":{"status":"done"}}` (BASIC auth, same key). Idempotent
re-fires while Done. **[Task]**

### WF27 — "AI productivity report" · `userproductivity.created` · 4 steps
Trigger → AI (prompt 18 "Ai Productivity Reporter"; all 11 columns incl. `stats_json`, `tasks_json`, `timeline_json`, `accuracy_json`)
→ COND `headline not_empty` (**no children — decorative**) → UPDATE UserProductivity `ai_report_json` = a hand-assembled JSON string
interpolating eight AI fields (unescaped strings ⇒ invalid JSON whenever the narrative contains a quote; arrays are
`json_encode`d correctly). Runs regardless of the condition (top-level sibling). **[Task/Project-adjacent: UserProductivity]**

### WF28 — near-duplicate of WF24 · `projectnote.created` · 9 steps
Same shape with prompt 8 "Meeting Minutes Processor" — whose snapshot `responseStructure` is `approval_required, reason, context_summary`
(**no `tasks`**), yet the loop iterates `{{step_305.tasks}}`; plus a NO-branch COND `type == comment` with no children.
If both WF24 and WF28 are active, every meeting-minutes note yields **two** sets of tasks. **[PN→Comment/Standup][Task][Project]**

### 13.1 Cross-workflow interactions that must be preserved or consciously redesigned
* **Fan-out on `email.created`**: WF10, 14, 17, 18, 20, 23 all receive every e-mail; each gates on
  `type`/`status`/`sender_type`. Received client e-mail with a project (`status draft`, `type received`,
  `sender_type client`): WF10 (type==received ✓), WF17 (status==draft & type!=sent & client ✓), WF23 (client ✓) → three
  jobs mutate the same Email (status/body) and Conversation (`project_id`) concurrently with `withoutEvents` saves —
  last writer wins, no ordering guarantee. v2 should make this one pipeline.
* **Chains through created records**: WF12/13/21 create draft Emails → WF17 gate; WF18 creates Lead → WF19 replies;
  WF19/20 create `auto_send` Emails → PROCESS_EMAIL; WF20 replies to lead e-mails that WF12/13/19 initiated.
* **Shared conventions**: `Milestone.name == 'support'` as the catch-all bucket (WF10/24/25/28); `sender_id = 1` as the
  system sender; `email_template = ai_lead_outreach_template`; `Context` rows as the AI's long-term memory
  (`contexts` relation fed back into prompts 10/13); `task_type_id` literals 2 (QA) and 4 (follow-up).
* **Legacy → v2 mapping table**
  | legacy | used by | v2 target (per brief) | what breaks |
  |---|---|---|---|
  | `Email` (status enum 13 values, `type` sent/received, `sender_type/id` morph, `body`, `template_data`, `email_template`, `is_private`, `conversation_id`, `deleted_at`, `categories`) | 10,12,13,14,17,18,19,20,21,23 | `Message` | every `trigger.email.*` path, `target_model: Email`, `referencable_type: App\Models\Email`, status vocabulary, `PROCESS_EMAIL` |
  | `Lead` (`status` LeadStatus, `campaign_id`, `next_follow_up_date`, `contacted_at`, `first_name`, `email`, `metadata`, `contexts`, `campaign`) and `Client` (`projects`) | 12,13,18,19,20,21,23 | `Contact` | morph literals `App\Models\Lead`/`client`/`lead` in `sender_type`, `conversable_type`, `linkable_type`; `lead.created` trigger name; conditions `sender_type == client|lead` |
  | `ProjectNote` (`type` meeting_minutes/comment, `content`, `noteable_*`, `chat_message_id`, `project`) | 24,28 | `Comment` / `Standup` | trigger name, `trigger.projectnote.*`, the `type` discriminator |
  | `Task` (`status` 'To Do'/'Done', `task_type_id`, `priority`, `requires_qa`, `needs_approval`, `source`/`source_id`, `additional_info`, `milestone_id`, `assigned_to_user_id`, `previous_status`, `project_deliverable_id`) | 8,10,22,24,25,26,28 | changed Task fields | literal FK ids, status strings, JSON blob path `additional_info.bugherd_info.project_id` |
  | `Project` (`integrations` JSON, `reporting_sites`, `contract_details`, `profit_margin_percentage`, `admin/client/manager/users`) | 24,25,28 | changed Project fields | `json_path` condition, loop-path relationships |
  | `Context` (referencable/linkable morphs) | 10,12,13,17,18,19,20 | ? (AI memory) | if renamed, every CREATE_RECORD Context step |
  | `Conversation` (`conversable` morph, `project_id`, `subject`) | 10,12,13,18,19,20,21,23 | ? | morph literals |

---

## 14. Consolidated defect register (beyond services.md §1 items 1–13)

Engine / jobs
* **D1** Save-time token remap corrupts ids via un-anchored sequential `str_replace` (§4.7). Live damage in WF17 (4 tokens) and WF9 (1). The approval gate's AI verdict is therefore ignored.
* **D2** Continue-on-error at every level; no run status; a CONDITION that throws = "no branch".
* **D3** Delay semantics: siblings after the container run before the delayed step and again after resume (§4.4/§4.6); resume unique key is per step not per execution; nested resume replays the container.
* **D4** AI resume: next top-level dispatch commented out → nothing after the AI step's enclosing top-level container ever runs; siblings are logged as children of the AI log; AI log `duration_ms` includes siblings; failure status spelled `error`; non-unique, non-idempotent retries.
* **D5** Inline `step_config.prompt` dead (job requires a prompt id) → orphan `started` log.
* **D6** `array_replace_recursive` list merge; `context['trigger']` never refreshed; loop context never exported.
* **D7** `schedule.run` guard only fires on manual empty-context runs; schedule fields on the TRIGGER step ignored; builder cannot create schedules.
* **D8** `__automation_suppressed` never set; `automation.run_synchronously` never read; `automation.defaults.task.task_type_id` never defined.
* **D9** Unique job key collapses distinct executions; manual `/run` bypasses the lock and runs synchronously in the request.
* **D10** `UpdateRecord::find()` respects soft deletes → WF18's own `deleted_at = NOW()` makes the rest of the run fail.
* **D11** `Http` calls without explicit timeout/retry; FETCH_API has no URL allow-list; credentials in `step_config` (and in the committed dump).
* **D12** `SendEmail` swallows all exceptions; single recipient; uses the default mailer not Gmail.
* **D13** `QueryData`: `contains`/`==`/anything unknown → `=`; `'true'/'false'` → `1/0` even for text columns; misses → `''`; nested `fields` ignored; `with('rel:cols')` drops FKs.
* **D14** `AiPrompt.gatherRelatedData` `select(fields)` without FK; `resolveModelFromContext` → `Schedule` for schedule runs; `relationships.base_model` from UI ignored in favour of context inference.
* **D15** Morph normalisation applied to any key ending in `_type` (Update also `type`), so an `Email.type = "sent"` update is passed through `normalizeMorphType('sent')` (harmless today because no `Sent` class exists — fragile).
* **D16** `CreateRecord` JSON-encodes arrays before `fill()` — double-encoding risk on `array`-cast columns (`template_data`, `additional_info`, `metadata`).
* **D17** `processFunctions` only NOW/TODAY/NULL; raw SQL literals (WF22) written as strings.
* **D18** `ValueDictionaryRegistry` vs `AutomationSchemaController` support different `source` kinds (`model` vs `model_const/config`); `Email.sender_type` hint is a `model: Client` (wrong concept).
* **D19** `WorkflowStep::children()` relation never used by ForEach (`isset` on unloaded relation) — every handler scans all steps of the workflow per execution (N queries per container, ×iterations).
* **D20** Schema endpoint uncached, executes every relationship method on every request; `events` list advertises 8 events that never fire; `*_type` dropdown options come from the 9-alias morphMap (no `client`/`lead`).

UI
* **U1** `TRANSFORM` node type has no handler (library/picker vs `TRANSFORM_CONTENT`).
* **U2** Token picker emits `{{trigger.<col>}}` (unresolvable) and `{{with.*}}` (unresolvable); offers `loop.key` (nonexistent); omits `is_first/is_last`, `variables.*`, `.count`, `user.*`, nested AI fields, API response fields (type check on `FETCH_API_DATA` instead of `ACTION`).
* **U3** Condition operators mismatch (`starts_with/ends_with` unimplemented; `empty/not_empty/in_past/in_future/today/truthy/not in` unlisted); empty rules ⇒ TRUE but UI says FALSE.
* **U4** FetchRecords `contains` ≡ `=`; no `in`, `older_than_*`, `json_path`, `limit`, `order`, `relationships`, `output_key`, `count_only` UI.
* **U5** Logs modal pagination dead (`meta` vs top-level paginator keys) → 30 newest rows only; grouping after pagination; `token_usage` renders `[object Object]`; no filters.
* **U6** Hub: 50 newest workflows only; dead status/sort selects; no confirm on delete; fake `v1.0`.
* **U7** Unlinked nodes execute; no edge drawn after containers; `position`/`is_unlinked` persisted into runtime config; container delete removes subtree silently; `temp_<ms>` id collisions.
* **U8** `CHECK_MILESTONE_COMPLETION` offered with no handler; `StepPickerModalV2` dead; `store.morphMap/campaigns/isTriggerConfigured/alert` unused.
* **U9** Prompts list capped at 50 by server pagination; archived prompts selectable; `responseStructure` snapshot goes stale when a prompt's schema changes; duplicate prompt ids with different names in snapshots.
* **U10** `allStepsBefore` includes sibling-branch and inside-loop steps; every keystroke rebuilds the tree and all canvas nodes.
* **U11** Value-dictionary endpoint needs `manage_placeholder_definitions` while the page needs `manage_projects`.
* **U12** `/prompt` route has no permission check beyond `auth`.

Data
* **W1** WF9/WF11 are test debris; WF8 unconditional task generator; WF14 unconditional status rewrite — all need an `is_active` check before migration.
* **W2** WF17 corrupted references (D1); WF22 SQL literal; WF18 NO-branch Context with null linkable and `trigger.context.*`; WF10/12/17 Context rows with wrong `referencable_id/linkable_id`; WF28 loop over a field its prompt does not return; WF24/28 duplicates; WF13/21 no state advance → repeat follow-ups; WF20 duplicate side effects on resume; WF25 tasks created without project/milestone; WF27 hand-built JSON.
* **W3** Secrets and personal addresses inside `step_config` (WF11/20/25/26).

---

## 15. Must-keep / must-fix / could-improve

### Must keep (capabilities the live automations depend on)
1. Step taxonomy: trigger (model event + schedule), condition (AND/OR rule list with var/literal sides), for-each, fetch-records (single/list, eager-load with field selection, relative-time operators, JSON-path filter), create/update record on any model with templated fields + `NOW()`/`NULL`, sync many-to-many, send internal e-mail, hand off an e-mail to the sending pipeline, HTTP call with auth modes and response-key extraction, AI prompt (library prompt + selected base fields + relationship payload + free text, JSON response), transform (truncate-at-marker, find/replace, strip HTML), define variables.
2. Token model: `{{ }}` with dotted paths into trigger payload, prior step outputs (including nested AI JSON), loop item/index, variables; native-typed single-token values; virtual `.count`; relative-time condition operators (`in_past`, `older_than_hours`); morph-alias normalisation for `*_type` columns.
3. Async AI with continuation of the remaining steps after the model returns; per-item AI inside loops.
4. Delayed steps (minutes) surviving process restarts.
5. Per-step execution log with input context snapshot, parsed/raw output, token usage, error, duration, run grouping, parent/child nesting — and a UI to browse them per workflow.
6. `CreatableViaWorkflow` defaults/required hooks and soft value-set validation.
7. Schedule-driven workflows (`schedule.run` → fetch → loop) and the `PROCESS_EMAIL` handoff contract with `EmailProcessingService`.
8. Prompt library with versions, response schema builder, generation config; `Context` as AI memory fed back through relationships.
9. UI affordances: node canvas with typed nodes and inline summaries; single properties panel; caret token insertion with grouped picker; relationship tree picker; grouped model picker; log timeline with JSON tree viewer.
10. The 15 real automations in §13 (8?,10,12,13,17,18,19,20,21,22,23,24/28,25,26,27) re-expressed against the v2 models.

### Must fix (in the rewrite's core design)
1. One resolver, one syntax, one miss semantics; trigger payload keyed consistently so `trigger.<field>` works; picker generates only resolvable tokens; explicit `null` vs `''`.
2. Step identity independent of DB auto-ids (stable keys/aliases per step) so saving cannot corrupt references; no string rewriting of configs.
3. Real columns for `parent_id`, `branch`, `position`; no UI state in runtime config; ordering per scope.
4. Run entity with status, trigger object, timeline; deterministic error policy (halt by default, opt-in continue per step); explicit `retry`/`idempotency` for AI and HTTP; no re-execution of siblings on retry.
5. Correct continuation after async AI at every nesting level; correct delay (pause exactly here, resume exactly here) with per-execution keys; no container replay.
6. Trigger contract: explicit domain events (with before/after for updates), event names that match what the UI offers, case-safe; suppression flag that actually exists; single pipeline per trigger for e-mail instead of six racing workflows.
7. Persistence path that does not disable model invariants (actor = automation flag) and does not fire loops.
8. Secrets out of step configs (credential store); URL allow-list; timeouts; log redaction; log retention and size caps (store diffs or references, not full context per step).
9. Operators and options consistent between UI and engine (condition, fetch); validation at save time (model/field/prompt existence, token references to earlier steps only).
10. Schedules authored from the trigger node and stored as first-class.

### Could improve
* Typed step outputs (schemas) so pickers can offer nested fields and the engine can validate at save time; `count`, `first`, `pluck` helpers instead of virtual props.
* Expression language: defaults, formatting, date arithmetic (replaces `NOW()`/SQL literals), string functions (replaces most TRANSFORM uses).
* Loop features: `loop.parent`, break/continue, collect outputs (`steps in loop → array`), batch size / concurrency limit for AI.
* Dry-run / test-with-sample-payload from the builder; replay a logged run; per-node last-result badge on the canvas.
* Versioned workflows (draft vs published), duplicate, import/export (replaces the JSON dump), diff of changes.
* Prompt ↔ step binding by version with drift warnings; prompt test bench; token/cost accounting per run.
* Log UI: filters (status/step/date), search in context, run-level view, pagination that works, redaction, retention policy.
* Accessibility and performance of the canvas (virtualise, debounce updates, keyboard shortcuts, undo/redo).
* Reuse: sub-workflows / callable workflows (WF24≈WF28, WF12≈WF13≈WF21 are copy-paste variants).

---

## Appendix A — Worked context evolution (WF19, `lead.created`, action = auto_send)

Values abbreviated; this is what each step's `input_context` snapshot contains and what tokens resolve to.

```jsonc
// 1. Emitted by GlobalModelEventSubscriber + WorkflowTriggerListener, then execute() adds _execution_id
{
  "lead": { "id": 30, "first_name": "Ana", "email": "ana@example.test", "status": "hot_incoming", "metadata": {...}, ... },
  "user": { "id": 1, "name": "…", "email": "…" },            // only if a web user caused the save (WF18's queued job → absent)
  "event": "lead.created",
  "trigger": { "lead": {...same...}, "user": {...}, "event": "lead.created" },
  "triggering_object_id": "30",
  "_execution_id": "8f2c…"
}
// 2. after TRIGGER #209   → + "step_209": {"trigger_event": "lead.created"}, "steps": {"209": {...}}
// 3. CONDITION #210 (trigger.lead.status == hot_incoming) → + "step_210": {"condition":"YES"}, "condition": {"210": true}
//    children run with the SAME context object (copied by value into executeSteps); their additions are NOT visible
//    to steps after #210 at top level (there are none).
// 4. FETCH #211 → + "step_211": {"count": 0, "records": []}
// 5. AI #212 → dispatch; log stays 'started'; executeSteps breaks. GenerateAiContentJob later writes:
//    "ai": {"last_output": {...}},
//    "step_212": { "raw": "{\"subject\":…}", "parsed": {"subject": "…", "ai_content": {...}, "action": "auto_send",
//                  "summary": {"context_summary": "…", "reason": "…"}}, "token_usage": {...}, "cost": null }
//    → {{step_212.action}} resolves via candidate "step_212.parsed.action"; {{step_212.summary.context_summary}} via "step_212.parsed.summary.context_summary"
// 6. siblings [213] run inside the job with parentLog = AI log
// 7. CONDITION #213 → YES → children: CREATE Conversation #215 → + "step_215": {"id": 501, "new_record_id": 501, "model": "App\\Models\\Conversation", "schema": {...}}, "conversation": {...toArray...}
// 8. CREATE Email #214 → "step_214": {"id": 621, "new_record_id": 621, ...}, "email": {...}   ← this Email's own email.created event fires here (queued)
// 9. PROCESS_EMAIL #216: email_id "{{step_214.new_record_id}}" → 621 → ProcessDraftEmailJob queued; "step_216": {"queued": true, "email_id": 621, "job": "…"}
// 10. UPDATE Lead #243 → "step_243": {"id": 30, "model": "App\\Models\\Lead"}, "lead": {...updated...}   (trigger.lead still has status hot_incoming)
// 11. CONDITION #220 → CREATE Context #221 referencable_id "{{step_214.new_record_id}}" → 621
```
ExecutionLog rows for this run (all `execution_id = 8f2c…`): #209 success (parent null) · #210 success (null) ·
#211 success (parent = log of #210) · #212 started→success (parent = #210's log; `duration_ms` covers steps 7–11) ·
#213 success (parent = **#212's log**) · #215, #214, #216, #243, #220 success (parent = #213's log) · #221 success (parent = #220's log).

## Appendix B — Save payload example (what `PUT /api/workflows/{id}` receives from V2)

```jsonc
{ "name": "Warm inbound lead", "trigger_event": "lead.created", "is_active": true,
  "steps": [
    { "id": 209, "step_order": 1, "name": "New Event Trigger", "step_type": "TRIGGER", "delay_minutes": 0,
      "step_config": { "model": "Lead", "event": "created", "trigger_event": "lead.created", "_parent_id": null, "_branch": null, "position": {"x":0,"y":0} } },
    { "id": 210, "step_order": 2, "step_type": "CONDITION", "step_config": { "logic": "AND", "rules": [...], "_parent_id": null, "_branch": null } },
    { "id": 211, "step_order": 1, "step_type": "FETCH_RECORDS", "step_config": { "model": "Email", "conditions": [...], "_parent_id": 210, "_branch": "yes" } },
    { "id": "temp_1725500000000", "step_order": 2, "step_type": "AI_PROMPT", "step_config": { "promptRef": {...}, "_parent_id": 210, "_branch": "yes", "is_unlinked": true } },
    ...
  ] }
```
Server: row 209/210/211 updated in place; `temp_…` inserted (new id, e.g. 320); any `step_config` string containing
`step_temp_1725500000000` rewritten to `step_320` (with the D1 prefix hazard if another temp id shares the prefix);
rows missing from the payload soft-deleted. Response: the workflow with flat `steps` (ids now numeric) — the store
replaces `activeWorkflow` but **does not reload `workflowSteps`**, so the canvas keeps `temp_…` ids until the user
navigates away and back; a second save in the same session re-sends the same `temp_…` ids → the first pass creates
**new rows again** and the delete pass soft-deletes the rows created by the first save (not in the payload). Net:
the workflow stays consistent (parents/tokens are remapped within each payload) but step ids churn on every save,
`execution_logs.step_id` of earlier runs point at soft-deleted rows (`with('step')` returns null → the log UI shows
"Step #N / Unknown Type"), and the soft-deleted `workflow_steps` table grows. Verified from `saveWorkflow()` (no
re-load) and `syncWorkflowSteps()`.

## Appendix C — Handler ↔ config key matrix (quick reference)

| key | TRIGGER | AI | COND | CREATE | UPDATE | SYNC | SEND | PROCESS | API | FETCH | FOREACH | TRANSFORM | DEFVAR |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| trigger_event / model / event | R / ui / ui | | | | | | | | | | | | |
| schedule_type / schedule_time / cron_expression | ui only | | | | | | | | | | | | |
| promptRef.id / prompt / aiInputs / freeText / relationships / responseStructure | | R / R(dead) / R / R / R / ui | | | | | | | ui | | | | |
| logic / rules[] / (condition_rules col) / sourceId | | | R / R / fallback / – | | | | | | | | | | |
| action_type | | | | R | R | R | R | R | R | | | | |
| target_model / fields[] | | | | R / R | R / R | R / – | | | | alias `target_model` | | | |
| record_id | | | | | R | R | | | | | | | |
| relationship / sync_mode / related_ids | | | | | | R | | | | | | | |
| to / subject / body | | | | | | | R | | | | | | |
| email_id / email_id_path / on_queue | | | | | | | | R | | | | | |
| api_url / api_method / api_auth_* / api_payload / api_response_key | | | | | | | | | R | | | | |
| model / conditions[] / with / relationships / order / limit / single / mode / count_only / output_key | | | | | | | | | | R | | | |
| sourceArray | | | | | | | | | | | R | | |
| type / source / marker|remove_after / find / replace | | | | | | | | | | | | R | |
| variables[] (variable_name/value legacy) | | | | | | | | | | | | | R (ui maps) |
| _parent_id / _branch | engine + container handlers (all) | | | | | | | | | | | | |
| position / is_unlinked | UI only, persisted | | | | | | | | | | | | |

R = read at runtime; ui = written/read by the V2 panel only.
