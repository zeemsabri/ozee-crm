I have a complete picture. Here is the survey.

---

# Legacy Laravel 12 App — Subsystem Inventory for Modular Rewrite

**App identity:** internal ops/CRM for *OZee Web & Digital* (`config/branding.php`, hardcoded `info@ozeeweb.com.au` in several places). Laravel 12 / PHP 8.2, Inertia + Vue 3 (plus a React "beta" inbox), Sanctum, Reverb, database queue, spatie/activitylog, Pulse.

**Scale:** 485 PHP files under `app/`. 96 models, ~130 controllers, 50 services, 10 jobs, 23 commands, 7 events, 6 listeners, 3 observers, 13 mailables, 9 notifications, 9 policies, 13 middleware, 13 enums.

**Notable global fact:** the codebase carries unusually rich, honest docblocks (the Inbox subsystem especially). Many of them document known bugs and deliberate trade-offs verbatim. Treat those docblocks as the primary spec source for v2 — I have quoted the load-bearing ones below.

---

## 0. Bootstrap, providers, cross-cutting

**Files**
- `/home/claude/src/bootstrap/app.php` — routing (web/api/console), `health: /up`, `statefulApi()`.
- `/home/claude/src/bootstrap/providers.php` — registers `AppServiceProvider`, `EventServiceProvider`. **`AuthServiceProvider` is NOT registered** (dead).
- `/home/claude/src/app/Providers/AppServiceProvider.php`
- `/home/claude/src/app/Providers/EventServiceProvider.php`
- `/home/claude/src/app/Providers/AuthServiceProvider.php` ← **dead code**
- `/home/claude/src/app/Exceptions/Handler.php` (legacy L10-style handler; `withExceptions()` in bootstrap is empty — likely also dead)

**Middleware aliases** (`bootstrap/app.php`): `permission`, `permissionInAnyProject`, `auth.magiclink`, `auth.magiclink.external`, `auth.apikey`, `process.tags`, `process.basic`, `google.chat.auth`, `client.throttle`, `not.guest`, `portal.user`.
Web stack appends: `HandleInertiaRequests`, `AddLinkHeadersForPreloadedAssets`, `RestoreRememberedDevice`, `EnsureNotGuest` (order is deliberate and documented inline).

**Boot behaviour in `AppServiceProvider`:**
- `Relation::morphMap` registers 9 aliases for only 3 classes (`Task`, `Workflow`, `Email` × FQCN/lowercase/StudlyCase). This is a workaround for the workflow engine's `normalizeMorphType()` accepting any of those spellings.
- Observers registered here (not via attributes): `EmailObserver`, `TransactionObserver`, `KudoObserver`.
- `Gate::before` → super-admin bypasses everything.
- `$policies` property on `AppServiceProvider` is **inert** (plain `ServiceProvider`, not `AuthServiceProvider`) — policies are only picked up by Laravel's convention-based discovery. Two dead policy maps exist (`AppServiceProvider::$policies` and the unregistered `AuthServiceProvider::$policies`), and they **disagree** (`ClientPolicy` and `KudosPolicy` only in the dead one).

**What v2 must preserve:** super-admin gate bypass; project-scoped permission middleware.
**Can drop:** morphMap alias sprawl (pick one canonical alias per model); `AuthServiceProvider`; the legacy `Exceptions/Handler`.

---

## 1. Workflow / automation engine

### Components
| File | Role |
|---|---|
| `app/Services/WorkflowEngineService.php` (759 L) | orchestrator: `execute`, `executeFromStepId`, `executeSteps`, `getTemplatedValue`, `getFromContextPath`, `findNextTopLevelStepId*`, `findTopLevelAncestorId` |
| `app/Services/StepHandlers/*.php` (13 handlers + contract) | one per step type |
| `app/Services/ForEachStepHandler.php` | **dead duplicate** — declares `namespace App\Services\StepHandlers` but lives in `app/Services/`, so PSR-4 never autoloads it; its `execute()` doesn't even match `StepHandlerContract` |
| `app/Jobs/RunWorkflowJob.php` | queued runner, `ShouldBeUniqueUntilProcessing`, `uniqueFor=60`, `tries=3`, `timeout=120`, backoff 60/300/900 |
| `app/Jobs/GenerateAiContentJob.php` | async AI step; `timeout=180` |
| `app/Events/WorkflowTriggerEvent.php`, `app/Listeners/WorkflowTriggerListener.php` |
| `app/Listeners/GlobalModelEventSubscriber.php` | eloquent wildcard → trigger events |
| `app/Models/Workflow.php`, `WorkflowStep.php`, `ExecutionLog.php`, `Prompt.php` |
| `app/Contracts/CreatableViaWorkflow.php`, `SchedulableAction.php` |
| `app/Services/ValueSetValidator.php`, `ValueDictionaryRegistry.php`, `config/value_sets.php`, `config/values.php` |
| Controllers: `Api/WorkflowController`, `Api/WorkflowStepController`, `Api/WorkflowLogController`, `Api/AutomationTriggerController`, `Api/AutomationSchemaController`, `Api/ModelDataController`, `Api/PromptController` |
| `config/automation.php` |
| `workflow_steps.json` (repo root, 173 KB) — a **DB dump of the `workflow_steps` table**, 289 rows |

### Flow
1. Any allow-listed model save fires `eloquent.created:*` / `eloquent.updated:*`.
2. `GlobalModelEventSubscriber::handleModelEvent()` builds `event = strtolower(class_basename).'.'.verb`, context `{ modelname: $model->toArray(), user: {...} }`, and dispatches `WorkflowTriggerEvent`.
   - Skips if `$model->__automation_suppressed`; skips deny-listed (`ExecutionLog`); allow-list is `Task, Project, Email, Campaign, Lead, User, ProjectNote, UserProductivity`.
3. `WorkflowTriggerListener` finds `Workflow::where(is_active)->where(trigger_event = $eventName)` and dispatches `RunWorkflowJob` with `uniqueKey = workflow:{id}|event:{name}|object:{id}`.
4. `WorkflowEngineService::execute()` walks top-level steps (`step_order ASC, id ASC`; nested steps identified by `step_config._parent_id`), writing an `ExecutionLog` row per step (`started` → `success` / `failed` / `scheduled`).
5. Step output merges into context under `step_{id}` **and** `steps[{id}]`; `getFromContextPath` supports `.parsed` fallbacks, `:`/`.` separators, and virtual `.count`/`.length`.
6. `delay_minutes > 0` → log `scheduled`, dispatch delayed `RunWorkflowJob(startStepId)`, **break**.
7. `AI_PROMPT` returns `status = AI_JOB_DISPATCHED` → engine **breaks**; `GenerateAiContentJob` continues siblings via `executeSteps`.
8. Schedule-run guard: when `trigger_event === 'schedule.run'` and context is empty, the first non-TRIGGER step **must** be `FETCH_RECORDS`, else the run aborts.

### Step types registered
`TRIGGER` (no-op), `ACTION` (aliases to `ACTION_<action_type>`), `AI_PROMPT`, `CONDITION`, `ACTION_CREATE_RECORD`, `ACTION_UPDATE_RECORD`, `ACTION_SYNC_RELATIONSHIP`, `ACTION_SEND_EMAIL`, `ACTION_PROCESS_EMAIL`, `ACTION_FETCH_API_DATA`, `QUERY_DATA`, `FETCH_RECORDS` (same handler), `FOR_EACH`, `TRANSFORM_CONTENT`, `DEFINE_VARIABLE`.

### Data touched
`workflows`, `workflow_steps` (`step_config` JSON, `condition_rules` JSON, no timestamps), `execution_logs` (`input_context`, `raw_output`, `parsed_output`, `token_usage`, `cost`, `duration_ms`, parent/child self-ref), `prompts`. `CREATE_RECORD`/`UPDATE_RECORD`/`QUERY_DATA`/`SYNC_RELATIONSHIP` can touch **any** Eloquent model by name.

### External APIs / env
- Gemini via `AIGenerationService` → `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key=…` — `GOOGLE_GEMINI_API`, `GOOGLE_GEMINI_MODEL`.
- `ACTION_FETCH_API_DATA` → arbitrary HTTP with `NONE|BEARER|BASIC|CUSTOM_HEADER` auth, all templated from context.
- `AUTOMATION` (enable global model events), `AUTOMATION_RUN_SYNC`, `VALUES_ENFORCE_VALIDATION`, `VALUES_LOG_CHANNEL`.

### `workflow_steps.json` analysis
289 rows, 28 workflows; **196 rows alive** across workflows 8–14, 17–28. Distribution:
- Step types: `ACTION` 112, `CONDITION` 55, `FETCH_RECORDS` 36, `TRIGGER` 31, `AI_PROMPT` 27, `FOR_EACH` 19, `TRANSFORM_CONTENT` 5, `DEFINE_VARIABLE` 3, `ACTION_AI_PROMPT` 1 (**unregistered type — will throw "No handler"**).
- Action types: `CREATE_RECORD` 53, `UPDATE_RECORD` 32, `SEND_EMAIL` 9, `PROCESS_EMAIL` 9, `FETCH_API_DATA` 7, `SYNC_RELATIONSHIP` 2.
- Trigger events: `schedule.run` ×8, `email.created` ×6, `task.updated` ×3, `Lead.created` ×2, `Task.updated` ×2, `user.updated` ×2, `projectnote.created` ×2, `new_lead_created` ×1, `send_to_ai` ×1, `User.updated` ×1, `Email.created` ×1, `lead.created` ×1, `userproductivity.created` ×1.

### Known bugs / smells / dead code
1. **Case-mismatched triggers never fire.** `GlobalModelEventSubscriber` emits `strtolower(class_basename).'.'.strtolower(verb)`, and `WorkflowTriggerListener` does an exact `where('trigger_event', …)`. Steps configured with `Lead.created`, `Task.updated`, `User.updated`, `Email.created` (6 trigger rows) are **unreachable**. So are the ad-hoc names `new_lead_created` and `send_to_ai` unless dispatched manually.
2. `ACTION_AI_PROMPT` step type has no handler.
3. **Failures do not stop a workflow.** The engine catches per step and continues ("Decision: continue on error"). A `CONDITION` that throws behaves like "no branch taken" and the following steps run anyway.
4. **Context merge is `array_replace_recursive`.** Deep merges silently combine unrelated arrays; list-typed values merge index-wise rather than being replaced.
5. `executeSteps()` has a **type bug**: on the AI-dispatch path it writes `$results['steps'][] = …` into a list-shaped `$results` (elsewhere it's `$results[] = …`), producing a mixed-shape return.
6. **Resume semantics are fragile.** Nested resume uses a `_resume_from_nested_step_id` marker threaded through recursion with `isAncestorOf()` doing an N-query walk per step. `executeFromStepId` for a nested step recurses into the parent — depth guard 50/100.
7. `UpdateRecordStepHandler` persists inside `Model::withoutEvents()` to avoid a feedback loop, which **silences every model invariant**. The fix was to hardcode `Email::guardStatusRegression($model)` there — the docblock explicitly says "Add a line for every model whose events carry a rule a workflow could otherwise trample." This is an open, growing hazard.
8. Four **near-duplicate `applyTemplate`/`getFromContextPath` implementations** (engine, Create, Update, DefineVariable, Condition, QueryData, AIGenerationService) with subtly different fallback rules.
9. `QueryDataStepHandler::applyTemplate` uses a naive path walk that returns `''` on miss, whereas the engine returns `null` — different comparison semantics between `CONDITION` and `QUERY_DATA` on the same token.
10. Uniqueness key falls back to `''` when event/object are missing → distinct runs can collide on the unique lock.
11. `WorkflowStep` relations query JSON columns (`step_config->_parent_id`) — every handler has a manual "fallback: scan all steps" path because "JSON querying behaves unexpectedly on some DB drivers".
12. `automation.run_synchronously` config exists but is **never read** anywhere.
13. `workflow_steps.json` at repo root is a raw table dump checked into VCS — it is a de-facto seed/migration artefact with no loader.

### v2: preserve vs drop
**Preserve:** the step-type taxonomy; per-step `ExecutionLog` with input context + token usage (this is the debuggability story); delayed steps; the `schedule.run` + `FETCH_RECORDS` contract; `CreatableViaWorkflow` defaults hook; `ValueSetValidator` soft/hard validation.
**Drop / rebuild:** the wildcard `eloquent.*` subscriber (replace with explicit domain events — the case mismatch bug is a direct consequence); JSON-column parent pointers (use a real `parent_id` + `branch` column); the 6 copies of template resolution (one resolver); `withoutEvents()` persistence (use an explicit "actor = automation" flag instead); continue-on-error default (make it configurable per workflow, default halt); the root JSON dump.

---

## 2. Scheduling module

**Components:** `app/Models/Schedule.php`, `app/Jobs/RunScheduledItem.php`, `app/Console/Commands/RunScheduler.php`, `app/Contracts/SchedulableAction.php`, `app/Http/Controllers/ScheduleController.php`, `app/Http/Controllers/Concerns/HandlesSchedules.php`, `Api/ScheduleApiController.php`. Documented in `README.md`.

**Flow:** `app:run-scheduler` (every minute, from `routes/console.php`) → `Schedule::active()->withinWindow($now)` chunked by 200 → skip if `last_run_at >= startOfMinute` → `isDueAt()` (dragonmantank cron, or `is_onetime` + `start_at` passed + never run) → `Cache::lock('schedule:{id}:{YmdHi}', 55)` → dispatch `RunScheduledItem`.

`RunScheduledItem` dispatch ladder: `SchedulableAction::runScheduled()` → `method_exists('runScheduled')` → `Workflow` → `RunWorkflowJob` → `Task` → `spawnChildFromTemplate()` → `run()` → `execute()` → warn. Then sets `last_run_at`, deactivates if `is_onetime`.

**Polymorphic targets today:** `Task` (spawns child task from template), `Workflow` (dispatches `RunWorkflowJob` with `schedule.run` context), `Email` (`runScheduled` flips `delayed` → `draft`; see §3 "Send Later").

**Smells:**
- The lock is released in a `finally` **immediately after dispatch**, before the job runs — the only real double-run protection is the `last_run_at >= startOfMinute` check, which the *job* writes asynchronously. Two schedulers a few seconds apart can double-dispatch.
- `RunScheduledItem` swallows every `Throwable` and still marks `last_run_at` — a permanently failing schedule looks healthy.
- `RunScheduledItem` imports `Task`/`Workflow` and `instanceof`-checks them: the "future-proof contract" is only half-adopted.
- `Schedule::getRecurrenceSummaryAttribute()` is a 50-line hand-rolled cron-to-English translator in the model.

**v2:** keep the polymorphic schedule + contract; move the dispatch ladder to a resolver registry; make the lock cover execution, not dispatch; put `recurrence_summary` in a presenter.

---

## 3. Email pipeline

This is the largest and most interesting subsystem. There are **two inboxes running side by side**: the legacy Vue `/inbox` and the React `/inbox/beta` (gated on `config('inbox.beta')`).

### 3.1 Receiving (Gmail poll)

**Components:** `app/Console/Commands/FetchEmails.php` (`emails:fetch`, **every minute**) → `app/Http/Controllers/EmailReceiveController.php::receiveEmails()` → `app/Services/GmailService.php`.

**Flow:**
1. Watermark = newest `type='received'` email's `sent_at` minus 5 min → Gmail query `is:inbox after:{unix}`; first run `is:inbox newer_than:30d`. `listMessages(50, $query)`.
2. Per message: `getMessage()` (format=full) → dedupe on `emails.message_id` (Gmail **API** id).
3. Sender routing by lowercased `from` address:
   - `Client` found + a project has that client → `handleClientEmailWithProject` (status `Draft`, `is_private` unset)
   - `Client` found, no project → `handleEmailWithoutProjectOrLead` (status `Received`, `is_private = true`)
   - `Lead` found → same handler (status `Received`, `is_private = true`)
   - neither → `handleUnknownEmail` (status `Unknown`, conversation with `project_id = null`)
4. Conversation match: **exact `subject` + `project_id` + `conversable`**. Unknown senders use `Conversation::createOrFirst(['subject' => …])` — matching **on subject alone across the whole table**.
5. Body: `cleanEmailBody()` → `<br>`→`\n`, block closers→`\n\n`, `strip_tags`, `html_entity_decode`. **Stored as plain text.**
6. Stores `message_id` (API id), `rfc_message_id` (RFC 5322 header), `gmail_thread_id`, `sent_at`.
7. Attachments → temp file → `uploadFilesToGcsWithThumbnails()` → `$email->files()->create(...)`.

**Also:** `inbox:fetch-sent` every 5 min (`withoutOverlapping`) → `app/Services/Inbox/IngestSentMail.php` — ingests mail sent from the Gmail *web UI*. Three outcomes: ALREADY SEEN (message_id exists) / OURS (`rfc_message_id` matches → enrich, don't duplicate) / THEIRS (create outbound `sent` row, but **only** if it can attach to a known thread by gmail thread id, `In-Reply-To`, or recipient — otherwise skipped, deliberately).

### 3.2 Sending

Two send paths, both through `GmailService::sendMessage()`:

**A. `EmailProcessingService::processDraftEmail()`** (via `ProcessDraftEmailJob`, dispatched by workflow step `ACTION_PROCESS_EMAIL`).
**B. `Api\EmailController::editAndApprove()`** (manual approval, classic inbox).

`sendApprovedEmail()` ordering (documented as load-bearing):
1. Block emails: `BlockComposition::renderForSend()` + `inlinePartsFor()` — must happen *before* the branded layout wrap, and *before* threading (threading appends the quote).
2. `EmailAttachmentStore::attachmentPartsFor($email, $blockImageIds)` — fetches bytes from GCS.
3. `getSenderDetails` → `getData` → `renderHtmlTemplate($data, 'email_template' | 'ai_lead_outreach_template')` (Blade wrapper with branding).
4. Mint `rfc_message_id` for **every** send via `ReplyThreading::newMessageId()` (needed so `IngestSentMail` recognises our own mail).
5. If reply: `headersFor()` (In-Reply-To / References) + `withQuotedThread()`.
6. `sendMessage(to, subject, html, headers, messageId, inlineImages, attachments)` — **once per recipient**.
7. **One atomic `forceFill(...)->save()`** writing `rfc_message_id`, `gmail_thread_id`, `message_id`, `status=Sent`, `approved_by`, `sent_at`.

MIME construction in `GmailService`: `multipart/mixed` (attachments) wrapping `multipart/related` (CID inline images) wrapping `text/html; base64`. Header values pass through `sanitiseHeader()` (CRLF strip, explicit header-injection defence).

**C. External SMTP path (separate system):** `Api/External/ExternalEmailController::send()` → `ExternalEmailLog` row → `SendExternalEmailJob` → dynamically registers a mailer `external_email_app_{id}` from the `EmailApp` row's SMTP creds → `Mail::mailer(...)->send(new ExternalApiEmail(...))`. Auth via `auth.apikey` / `auth.magiclink.external`. Nothing to do with Gmail or conversations.

### 3.3 Approval gate

Two shapes:

**Classic:** email created at `status=draft` → `Email::created` → automation → **workflow 17** (documented in `Api/InboxReplyController`'s docblock):
```
Email::created
 → GlobalModelEventSubscriber (Email allow-listed)
   → WorkflowTriggerEvent('email.created')
     → WorkflowTriggerListener → RunWorkflowJob → WorkflowEngineService
       → workflow 17, gated on status=='draft' AND type=='sent'
         → AI_PROMPT "Email Approval Analysis"
            → approved → ACTION PROCESS_EMAIL → ProcessDraftEmailJob → Gmail
            → refused  → UPDATE_RECORD status='pending_approval' (a human decides)
```
Human path: `POST emails/{email}/edit-and-approve`, plus `approveReceived` / `editAndApproveReceived` / `rejectReceived` / `reject` (`rejection_reason` min:10). `Email::getCanApproveAttribute()` computes button visibility from `approve_all_emails` / project `approve_emails` / global `approve_received_emails` / `contact_lead`.

**Beta inbox:** `config('inbox.manual_approval_after_minutes', 30)` — while an email is still `draft` the automation owns it and "Approve & send" is **disabled**; it unlocks when the automation hands it back as `pending_approval`, or after the timeout (meaning the automation never ran).

**Statuses** (`app/Enums/EmailStatus.php`, 13 cases): `pending_approval_received, pending_approval, rejected_received, rejected, received, sent, draft, unknown, pending, approved, auto_send, delayed, saved`.
`approved` is explicitly documented as legacy — no current code writes it, but real rows carry it, so `config('inbox.reply_clock.delivered_statuses')` includes it.

**Critical invariant:** `Email::guardStatusRegression()` — a `sent` email may never move to `draft`/`pending_approval`/`auto_send`. Enforced on the `updating` model event **and** called explicitly by `UpdateRecordStepHandler` (which saves inside `withoutEvents()`). The docblock records the incident: a workflow rewrote a delivered email back to `pending_approval`, the UI offered "Approve & send", and the client got a second copy.

Matching fix in `EmailProcessingService::processDraftEmail()`'s catch: `refresh()` and check `status === Sent || sent_at !== null` before demoting to `pending_approval`.

### 3.4 The body-shape problem

`emails.body` holds **five** different things (verbatim from `app/Services/Inbox/EmailHtml.php`):

| # | Origin | Shape |
|---|---|---|
| 1 | Received mail (`EmailReceiveController::cleanEmailBody`) | **plain text** |
| 2 | Beta composer replies (`ReplyBox` textarea) | **plain text** (markdown subset) |
| 3 | Legacy custom composer (`CustomComposeEmailContent.vue`) | **HTML**, prefixed `greeting.'<br/>'` |
| 4 | Block builder | **HTML** with `cid:blk-…` references |
| 5 | Templated emails | `body` is **NULL**; text lives in `email_templates.body_html` + `emails.template_data` |

Plus AI lead-outreach emails, where `body` is a **JSON object** (`{greeting, paragraphs, call_to_action}`) — see `HandlesAiTemplatedEmails::renderAiEmailContent` and `EmailProcessingService::processDraftEmail`'s sniff `json_decode($email->body)->greeting && isset(->paragraphs)`.

**Resolvers:**
- `EmailHtml::looksLikeHtml()` — regex for a real element (deliberately not `str_contains('<')`, which false-positives on `profit < cost` and `<a@b.com>`).
- `EmailHtml::display()` → `{body, quote}`; `fromPlainText()` (paragraph-per-blank-line + `linkify` on already-escaped text); `sanitise()` (DOM-based; strips `script/style/link/meta/base/title/iframe/object/embed/applet/form/input/button/select/textarea` entirely; scheme allowlist `http/https/mailto/tel`); quote-chain detection via 6 marker regexes + `>`-prefix runs.
- `EmailBodyRenderer` — **always** renders in preview mode (`isFinalSend = false`) so opening a thread never mints a magic link; and turns `nl2br` **off** for templates (running it over template HTML double-spaces everything).
- `MarkdownBody` — the beta composer stores a markdown subset (`**bold**`, `*italic*`, `~~strike~~`, `[text](url)`, `- `/`1. ` lists, `> ` quotes) chosen so the AI approval read stays cheap; rendered to HTML at send/preview. Selection is by `emails.draft_meta.body_format = 'markdown'`, **never by sniffing** (a legacy custom email is rich-editor HTML and escaping it would destroy it).
- `app/Support/TemplateData.php` — `emails.template_data` is **double-encoded JSON** and `encode()` deliberately reproduces that shape rather than fixing it.
- `app/Console/Commands/InboxBodySamples.php` (`inbox:body-samples`) — a diagnostic that classifies real stored bodies against the taxonomy above.

**This is the single biggest thing v2 must fix.** Store one canonical representation (suggest: a `body_format` enum column that is *always* set + a normalized content model), and keep the AI's cheap read as a derived plain-text column rather than as a constraint on the storage format.

### 3.5 Templates and placeholders

`app/Models/EmailTemplate.php` (`name, slug, subject, body_html, is_default, is_private`) ⇄ `PlaceholderDefinition` (`name, source_model, source_attribute, is_dynamic, is_repeatable, is_link, is_selectable`) via `email_template_placeholder`. `EmailTemplatePlaceholder.php` is an **empty stub model** (dead). Templates also link to `EmailApp` via `email_app_template`.

`Api/Concerns/HandlesTemplatedEmails::populateAllPlaceholders()` replaces `{{ Name }}` tags. Handles dynamic values, repeatables (both "array of strings with mixed text+links" and "array of source-model IDs"), static values from `source_model::source_attribute`, and a **magic-link special case** — `App\Models\MagicLink` + name `Magic Link` renders a styled `<a>` to `client.magic-link-login`. In preview mode it returns `#preview_magic_link_url` (no DB write); on final send it reuses a valid link or mints one via `MagicLinkService`.

### 3.6 Threading & conversations

`app/Services/Inbox/ReplyThreading.php`. Two jobs, both at send time:
1. **Headers.** `In-Reply-To`/`References` must carry the parent's **RFC Message-ID header** (`emails.rfc_message_id`) — *not* `emails.message_id`, which is Gmail's API id and matches nothing. That's why the column exists.
2. **Quoted chain.** Appended to outgoing HTML and **deliberately NOT stored** in `emails.body`, because the AI checker reads that column and a stored quote would re-send the whole thread to the model on every reply. Stated trade-off: "what we store is no longer byte-identical to what was sent."

`app/Services/Inbox/GmailCopy.php` — finding and trashing the Gmail copy. Two lookup routes: `rfc822msgid:` search (reliable inbound, "a backfill run over 50 outbound rows resolved 0 of them" because Gmail replaces our minted Message-ID on send) and `gmail_thread_id` thread-walk (reliable, since it's recorded at send). Never trashes by thread.

`app/Services/Inbox/ReplyClock.php` — the *single* definition of "answered". Aggregates: `last_inbound` (newest `received`), `last_outbound` (newest `sent` with a delivered status), needs-reply = inbound newer. Duplicated deliberately in SQL (`ThreadQuery`) and PHP (`ThreadPresenter`), which is why this class exists. `config('inbox.reply_clock.since')` is a **cutover** — threads older than it are excluded, because "an old thread showing as unanswered means *this system holds no record of a reply*, which is emphatically not *nobody replied*."

`ThreadQuery` (519 L) paginates *conversations* with correlated subqueries (explicitly chosen over denormalised columns; migration `2026_08_19_100000` adds the composite indexes). `ThreadPresenter` (979 L) resolves permissions server-side and **strips bodies from the payload** rather than hiding them in CSS — two distinct redaction rules: *screening* (`pending_approval_received` withheld without `approve_received_emails`) and *privacy* (`is_private` withheld without `view_private_emails`, forever).

`Correspondent` (457 L) — names, never addresses ("Priya Nair to the OZee Team"); knows `Lead` has `first_name`/`last_name` not `name`.

### 3.7 AI in the email pipeline

**Five separate hand-rolled Gemini callers** (the `InboxAiService` docblock names them all and says consolidating is a separate job):
1. `app/Services/AIGenerationService.php` — workflow `AI_PROMPT`; renders `Prompt::system_prompt_text` + appends `response_json_template`; forces `responseMimeType: application/json`; returns `{raw, parsed, token_usage, cost: null}`.
2. `app/Services/EmailAiAnalysisService.php` — the compliance/approval prompt (hardcoded in `buildSystemPrompt()`); returns `{approval_required, reason, context_summary}`; flags personal contact details, rude language, unintelligible CTA, and (incoming only) financial terms.
3. `app/Services/Inbox/InboxAiService.php` — the beta inbox's three calls: `checkOutbound`, `summariseThread`, `summariseMessage`, `draftReply`. **Every method returns null rather than throwing.**
4. `app/Jobs/GenerateLeadOutreachJob.php`
5. `app/Jobs/GenerateLeadFollowUpJob.php`

**Beta AI jobs** (`app/Jobs/Inbox/`, all on the `emails` queue by default):
- `CheckEmailWithAi` — state machine `submitted → queued → checking → approved|held|failed`. **Approval does not send**; it only clears the flag. Deliberate: "auto-send should arrive deliberately with the queue proven."
- `DraftReplyForEmail` — pre-writes a reply + one-line summary. Skips private emails outright. Every guard clause lands on a terminal `EmailDraftStatus` (was previously a silent `return`).
- `SummariseConversation` — writes only `conversations.ai_*`; safe to enable.

`Conversation::hasCurrentAiSummary()` compares `ai_summary_email_count` **exactly** against the current count — a thread that lost a message is a different thread.

`config('inbox.ai')`: all off by default; `check_outbound` flagged as the dangerous one ("CHANGES WHAT HAPPENS TO OUTGOING MAIL"). Trimming knobs `max_chars_per_message=4000`, `max_messages_per_thread=12`; `stall_minutes=5`.

### 3.8 Tracking

`app/Http/Controllers/EmailTrackingController.php` — 1×1 GIF pixel. `track(id)` sets `emails.read_at`. `notice(id, email)` and `project(id, email)` write `UserInteraction` rows (`interaction_type = 'email_open'`). Routes `/email/track/{id}`, `/notice/track/{id}/{email?}`, `/project/track/{id}/{email?}` — **all unauthenticated and unsigned**, so any crawler prefetching the pixel marks mail as read, and `notice`/`project` take an arbitrary email address in the URL.

### 3.9 Send Later (uncommitted work, per `work.md`)

`EmailStatus::Delayed` + a polymorphic `Schedule` on the `Email`. `Email::runScheduled()` flips `delayed` → `draft`, which re-enters the automation. `work.md` notes this is **uncommitted** and lists an unrun migration.

### 3.10 Email pipeline: bugs / smells / dead code

- `EmailObserver` is **almost entirely commented out**. `notifyAdminsForApproval`, `notifyUsersOfSentEmail`, `markApprovalNotificationsAsRead` are all defined but every call site is `//`-ed. Only `updateProjectAndLeadTimestamps` runs. So `EmailApprovalRequired` / `EmailApproved` notifications are **dead** from this path.
- `EmailObserver::updated` mixes string constants (`Email::STATUS_PENDING_APPROVAL`) with enum comparisons (`$originalStatus === EmailStatus::PendingApproval`) on the same variable — `getOriginal('status')` returns a raw string, so **that enum branch can never be true**.
- Hardcoded approver: `approved_by => User::where('email','info@ozeeweb.com.au')->first()?->id` in `EmailProcessingService`.
- Multi-recipient sends produce **N Gmail messages against 1 row**, and only the **first** recipient's `threadId`/`id` are recorded (documented).
- `Email::getCanOpenAttribute()` returns `false` on both branches — dead accessor, still in `$appends` (so it runs on every serialization).
- `Email::approve()` in the controller throws "This method is deprecated" — dead endpoint still routed.
- `Conversation::createOrFirst(['subject' => …])` for unknown senders matches on subject globally; two unrelated senders using "Invoice" land in one conversation.
- `inbox:fix-email-types` exists because a `2025-07-22` migration added `emails.type NOT NULL DEFAULT 'sent'` **with no backfill**, stamping every historical inbound email as outbound. Those rows are invisible to *both* halves of the reply clock.
- `config/public_api.php` contains **three plaintext API keys committed to source**.
- `google/cloud-pubsub` is in `composer.json` but **there is no PubSub code anywhere** — the Gmail integration is polling-only. Dead dependency (and a hint that push was planned).
- Google tokens for the app account live in `storage/app/private/google_tokens.json` (a file), not the DB; the DB `google_accounts` table is per-user only.
- `GoogleApiAuthTrait::__construct` calls `initializeGoogleClient()` **five times** (once directly, once per `set*Scope`), each of which reads tokens and may refresh — 5× the work per instantiation.
- `GoogleApiAuthTrait` catch block does `$user->googleAccount()?->delete()` — an auth hiccup silently deletes the user's Google connection.
- `GoogleApiTrait::createGoogleClient()` has `config(env('USER_REDIRECT_URL', 'services.google.redirect_url'))` — nested `config(env(...))`, and `services.google.redirect_url` doesn't exist (the key is `services.google.redirect`). Broken.

### v2: preserve vs drop
**Preserve:** the `rfc_message_id` vs `message_id` distinction; threading + quote-at-send-time; the reply clock as a derived value with an explicit cutover; server-side redaction (`ThreadPresenter`); `guardStatusRegression`; CID-embedded images with expiring local copies; the block builder's "AI reads the cheap version" architecture; `EmailHtml::sanitise()`.
**Drop:** the body-shape ambiguity (biggest win); `EmailObserver`'s dead notification code; the tracking-pixel endpoints as currently unsigned; polling in favour of Gmail push (the pubsub dep is already there); five separate Gemini clients → one; `template_data` double encoding; classic-vs-beta inbox duality (pick one).

---

## 4. Finance

### Xero (`dcblogdev/laravel-xero` in composer, but **all calls are hand-rolled `Http::`**)
| Service | Endpoint(s) |
|---|---|
| `XeroAuthService` | `login.xero.com/identity/connect/authorize`, `identity.xero.com/connect/token`, `/connect/revocation`, `api.xero.com/connections` |
| `XeroTokenService` | active connection + `getRuntimeCredentials()`; `Organisation` probe |
| `XeroBillService` | `Invoices` (ACCPAY create/void/status), `Accounts`, `Payments` |
| `XeroInvoiceService` (555 L) | `Invoices` (ACCREC), email send, `Items`, `History`, `BrandingThemes`, `Payments`; status/tax/lineAmountType normalizers; `syncLocalInvoiceFromXero` |
| `XeroWebhookService` | HMAC verify (`config('xero.webhookKey')`), intent-to-receive, `Contacts`/`Invoices`/`CreditNotes` fetch |
| `XeroContactSyncService` / `XeroUserContactSyncService` | `Contacts` match + create for `Client` / `User` |
| `XeroPaymentServiceCatalog` | `PaymentServices`, cached in `xero_payment_services` |
| `XeroAttachmentService` | `{Endpoint}/{id}/Attachments/{name}`; reads bytes from GCS |

Models: `XeroConnection`, `XeroTenant`, `XeroPaymentService`, `AirwallexXeroBankMapping`.
Controllers: `Admin/XeroConnectionController` (+ `/ozee-xero/callback` web route), `Api/XeroWebhookController`, `Api/XeroAccountController`, `Api/XeroPaymentServiceController`, `Api/XeroReverseSyncController`.
Scheduled: `XeroPaymentSyncJob` daily; `xero:refresh-payment-services` every 6h; `xero:sync-invoices` hourly.
Env: `XERO_CLIENT_ID/SECRET/REDIRECT_URL/LANDING_URL/ACCESS_TOKEN/WEBHOOK_KEY/SCOPES/ENCRYPT`.

**Smell:** `XeroInvoiceService::syncPaymentToXero` posts to `self::INVOICES_URL . '/../Payments'` — a literal `..` in a URL path.
**Smell:** `xero.encrypt` defaults false — tokens at rest in plaintext.

### Airwallex
`app/Services/AirwallexService.php` — `authenticate()` (client_id + api_key), `getTransactions`, `getTransaction`, `getPayment`. Env `AIR_WALLEX_CLIENT_ID`, `AIR_WALLEX_KEY`, `AIR_WALLED_END_POINT` (**typo in the env name**, defaults `https://api.airwallex.com`). Feeds `XeroBillService::syncPaymentToXero($bill, $transaction, $airwallexData)`.

### Stripe
`stripe/stripe-php ^19.4`. `StripeService` (checkout sessions, prices), `StripePayoutService` (371 L — payout details, trace-id extraction from descriptions, reconciliation to `Invoice`). Models `StripeConfiguration`, `StripePayout`, `StripeSubscription`, `StripeSubscriptionPayment`. Controllers `Admin/StripeConfigurationController`, `Api/External/StripeWebhookController`, `Api/External/ExternalPaymentController`.
Env: `services.stripe.secret` = `STRIPE_MMS_READ` ?? `STRIPE_RESTRICTED_KEY` ?? `STRIPE_SECRET_KEY` (three-level fallback — a smell).

### Bills & approval
`Bill` (activity-logged) → `BillPaymentDetail` (`details` is `encrypted:array`) → `ApprovalFlow`/`ApprovalFlowStep`/`ApprovalInstance`/`ApprovalInstanceStep`. `BillApprovalFlowService::initialize()` was extracted so the **guest** bill-upload endpoint starts the same flow as an internal one. `BillExceedsContractException`. `Bill::recalculateStatus()`, `getPaidAmountAttribute`, `getRemainingAmountAttribute`. `BillStatus`: `pending_approval, approved, paid, partial_paid, void`.

### Currency & ledger
- `FetchCurrencyRatesJob` (daily) → `https://api.exchangeratesapi.io/v1/latest` (`EXCHANGE_RATES_API_KEY`) → `currency_rates`. Also `app:fetch-conversion-rate` command (duplicate, unscheduled).
- `CurrencyConversionService::convert()`; base = `services.default_currency` (`DEFAULT_CURRENCY`, default `AUD`).
- `LedgerService::record()` → `points_ledger` + `monthly_points`.
- `ProfitLossService` (301 L) — dashboard + cash-management timeline over `Bill`, `Invoice`, `Transaction`, `ProjectExpendable`.
- `TransactionObserver` (created/updated/deleted) → `HasFinancialCalculations`.

### Bonus / points system
Two parallel mechanisms:

**(a) Points ledger** — `PointsService::awardPointsFor($model)` maps model→action:
| Model | Action | Points |
|---|---|---|
| `ProjectNote` (standup) | `AwardStandupPointsAction` | 25 on-time (before 11:00 user-local) / 10 late; dedupe per user+project+local-day |
| `Task` | `AwardTaskPointsAction` | 50 base on-time (+penalties) |
| `Milestone` | `AwardMilestonePointsAction` | 500 on-time / 100 late, awarded to **every** user assigned to the milestone's tasks |
| `Kudo` | `AwardKudosPointsAction` | 25 |
| `Email` | `AwardEmailPointsAction` | 50, only if sent within **4 hours** of the preceding received email |

Triggers: `Email::booted()->updated` (direct call to `PointsService`), and events `StandupSubmittedEvent`, `KudoApprovedEvent` (from `KudoObserver`), `TaskCompletedEvent`, `MilestoneApprovedEvent` → queued listeners.
Denials are recorded as ledger rows with 0 points and a reason string (auditable — good design).
Backfill/repair commands: `points:recalculate`, `points:fix-standups`, `points:calculate-streak` (weekly, day 7) → `WeeklyStreak`.

**(b) Bonus configuration** — `BonusConfiguration`, `BonusConfigurationGroup`, `BonusTransaction` (`pending/approved/rejected/processed`), `MonthlyBudget`, `MonthlyPoint`. `BonusCalculationService` (503 L, `PROJECT_PERFORMANCE_BONUS_PERCENTAGE = 0.05`), `BonusService` (leaderboard + `distributeMonthlyBonuses`), `TransactionBonusService::createBonusTransactions(year, month)`. Controllers `Admin/BonusCalculatorController`, `Api/BonusConfigurationController`, `Api/BonusConfigurationGroupController`, `Api/LeaderboardController`, `Api/PointsLedgerController`.

**Smells:** `AwardEmailPointsAction` compares `$email->type !== 'sent'` against an **enum-cast** attribute — this comparison is `EmailType::Sent !== 'sent'` → **always true** → the guard always returns null. Email points are effectively dead unless the cast is bypassed. Points constants are hardcoded class constants while a whole `BonusConfiguration` table exists — two sources of truth.

### v2
**Preserve:** the deny-with-reason ledger; the approval-flow abstraction; encrypted payment details; the Xero token/tenant separation; currency normalisation at the boundary.
**Drop:** the unused `dcblogdev/laravel-xero` package (or use it); hardcoded points constants; the duplicate currency-fetch command; the three-level Stripe key fallback.

---

## 5. Messaging & real-time

### Telegram
`app/Services/TelegramService.php` (752 L) — direct `Http::post("https://api.telegram.org/bot{token}/…")`: `createForumTopic`, `sendMessage`, `deleteMessage`. Concepts: **forum topics** per project, typed by `TelegramTopicType` (`general`, `client`, `custom`, `proxy`). `createDefaultTopics`, `ensureClientTopicExists`, `ensureGeneralTopicExists`, `sendMessageToTopic`, `sendDirectMessageToClient`, `updateClientPersistentMenu`, `sendProjectSelectionMessage`, `isCreateTaskCommand`/`handleCreateTaskCommand` (creates a `Task` from a Telegram message).
Models: `TelegramTopic` (polymorphic `topicable`), `TelegramAccount` (polymorphic `telegramable` — links a `User` **or** a `Client` to a Telegram id).
Webhooks: **two controllers** — `app/Http/Controllers/TelegramWebhookController.php` and `app/Http/Controllers/Api/TelegramWebhookController.php` (duplication). Handles account verification/linking, lazy profile sync, project selection, proxy-topic routing.
Env: `TELEGRAM_TOKEN`, `TELEGRAM_BOT_NAME`.
**Smell:** `TelegramService` reads both `$this->token` and `$this->botToken` in different methods.

### Google Chat
`GoogleChatService` (252 L) — `createSpace`, `addMembersToSpace`, `removeMembersFromSpace`, `sendAs(User)`, `sendMessage(+cards)`, `sendWelcomeMessage`, `pinProjectDocument`, `sendThreadedMessage`. `GoogleChatServiceV2` (159 L) is a **partial reimplementation** with only `sendThreadedMessage` — clear dead/abandoned refactor.
`google/apps-chat ^0.11.2`. Scopes `chat.spaces`, `chat.messages`, `chat.memberships`.
Middleware `google.chat.auth` (`AuthenticateGoogleChat`); controllers `GoogleChatUserController`, `api/user/google-chat/*`.
Env: `PUSH_TO_CHAT`.

### Reverb / broadcast
`laravel/reverb ^1.0`, `BROADCAST_CONNECTION` (**defaults to `null`**). `ChatMessageSent` (`ShouldBroadcast, ShouldQueue`) broadcasts on `PrivateChannel("project.{id}")` and optionally `PrivateChannel("topic.{id}")`. `routes/channels.php` authorises `App.Models.User.{id}`, `project.{projectId}`, `topic.{topicId}` (each with a `view_all_projects` override). `Broadcast::routes(['middleware' => ['web','auth:web,sanctum']])`.
`app/Events/TestNotification.php` — a debug event broadcasting on a **public** `test-channel`. Dead/leftover.

### Notifications
9 notifications, most `ShouldBroadcast, ShouldQueue` with `via` → `['database','broadcast']` (+ `mail` for some): `EmailApprovalRequired`, `EmailApproved`, `KudoApprovalRequired`, `MeetingInvitation`, `NoticeCreated`, `TaskApprovalCompleted`, `TaskAssigned`, `UserMentioned`, `GroupableTaskNotification`.
`app/Notifications/Traits/GroupableNotification.php` — grouping helper.
`MentionService::parseAndNotify()` — parses `@mentions` out of content, fires `UserMentioned`.
`NoticeBoard` + `NoticeMail` + `NoticeCreated` + `NoticeBoardController` + tracking pixel.

**Dead:** `EmailApprovalRequired` / `EmailApproved` have no live dispatcher (see §3.10). `GroupableTaskNotification` is not `ShouldQueue` and has a no-arg constructor with hardcoded content — looks like a scaffold.

---

## 6. Storage & documents

- **GCS** via `spatie/laravel-google-cloud-storage`, disk `gcs`, `visibility: private`. Env: `GOOGLE_CLOUD_KEY_FILE_PATH`, `GOOGLE_CLOUD_PROJECT_ID`, `GOOGLE_CLOUD_STORAGE_BUCKET` (default `ozee-docs`), `GOOGLE_CLOUD_STORAGE_PATH_PREFIX`, `GOOGLE_CLOUD_STORAGE_API_URI`.
- `FileAttachment` — polymorphic `fileable` + `project_id`, `path`, `thumbnail`, `google_drive_file_id`, and `expires_at` (**null = keep forever**, which is every pre-existing row).
- Prefixes: `task/` (general), `email-attachments/` (`EmailAttachmentStore::PREFIX`), `email-blocks/` (`EmailImageStore`). The prune command keys off prefix + `expires_at`, so a task attachment can never be swept.
- `PruneExpiredFiles` (`files:prune-expired`, daily 03:15, `withoutOverlapping`) → `DeleteFileAttachmentAction` (single owner of "delete a file and its object").
- `Api/Concerns/HandlesImageUploads` — `uploadFilesToGcsWithThumbnails()`; GD-based, **throws on webp/svg** (hence `config('inbox.blocks.image_mimes')` = jpeg/png/gif only).
- `GoogleDriveService` (540 L) — folders, upload, `createDocument`, `copyFile`, permissions add/remove, thumbnails, `findOrCreateSubfolder`, `getFileContent`. Used by `ChatAttachmentService` (chat → Drive), `ShareableResourceCopyController`, project doc pinning.
- `Document` (project-scoped), `Deliverable` + `DeliverableComment` + `ClientDeliverableInteraction`, `ProjectDeliverable` (+ `config/project_deliverable_types.php`).
- **Presentations:** `Presentation` (polymorphic `presentable`, auto `share_token` 64 chars on create) → `Slide` → `ContentBlock` (`block_type`, `content_data` JSON), `PresentationMetadata`. `PresentationService` translates Gemini's minified output via `KEY_MAP` / `BLOCK_TYPE_MAP`. `config/presentation_templates.php` (349 L: `generator_pool`, `slide_blueprints`). Controllers: `Api/PresentationController`, `Api/PresentationAIController`, `Api/PresentationGeneratorController`.
- **Wireframes:** `Wireframe`, `WireframeVersion`, `Api/WireframeController`, `Component`/`Icon` + `config/components.php` (760 L of inline SVG icon definitions and component schemas — config used as a content store).

---

## 7. Scheduling, availability, attendance, standups

- `UserAvailability` — per-date `is_available`, `did_not_show_up` / `was_late` / `left_early` (+ a `Category` FK for each reason), `actual_start_time`/`actual_end_time`, `time_slots` JSON, `admin_comments`. `Api/AvailabilityController`, `Api/UserAttendanceController`. Env `CHECK_AVAILABILITY`.
- `DailyTask` — per-user per-date ordered work log (`user_id, task_id, date, order, status, note`). `Api/DailyTaskController`.
- Standups = `ProjectNote` rows (aliased `as Standup` in the points action). `Api/StandupAnalyticsController`, `test-user-standups`, `points:fix-standups`.
- `Meeting` + `MeetingAttendee` + `MeetingInvitation` notification + `GoogleCalendarService::createEvent/deleteEvent`. Heavy timezone work: `Models/Traits/HasTimezoneCalculations`, `HasUserTimezone`; `readmes/timezone-display-analysis.md`.
- `UserProductivity`, `ProductivityReportService::generateDailySnapshot`, `config/activity_categories.php` (categories + `productivity_weights`), `UserActivity`, `Api/ActivityController`/`ActivityDataController`/`ActivityReportController`, `Admin/ProductivityReportController`, `Admin/CtoReportController`, `Admin/LiveStatusController`, `Admin/ProjectTimeCostReportController`.

---

## 8. Auth, access, portal, vault

- **Permissions:** `Role`, `Permission`, `PermissionHelper`, `CheckPermission` / `CheckPermissionInAnyProject` middleware, `PermissionDeniedException` (renders itself), 9 policies. Project-scoped roles (`hasProjectPermission`) layered over global ones. ~40 readmes in `readmes/` are permission bug fixes — this area churned heavily.
- **Magic links:** `MagicLink` model, `MagicLinkService` (generate/reuse valid), `MagicLinkMail`, `VerifyMagicLinkToken` + `VerifyExternalMagicLink` middleware, `Api/MagicLinkController`, `/client/dashboard/{token}`.
- **OTP:** `OtpService` (project-token scoped guest OTP), `GenericOtpService` (identifier + context + attempts), `UserOtp`, `OtpVerification`, `OtpVerificationMail`, `GenericOtpMail`. Env `OTP_ENABLED`.
- **Remember device:** `RememberDeviceService` (cookie `remember_device`, 64-char token, **SHA-256 hashed at rest**, HttpOnly/Secure/Lax, not rotated on use — documented rationale), `UserRememberedDevice`, `RestoreRememberedDevice` middleware, `devices:expire [--all]` command. Env `REMEMBER_DEVICE_DAYS/MAX/SLIDING`. The docblock explicitly flags this as raising the security bar vs Laravel's native remember-me.
- **Supplier portal:** `PortalSessionService` (cookie `portal_session`, 30 days, **deliberately does not log into the `web` guard**), `PortalAccessService`, `PortalProfileService` (verification details in `users.metadata`), `PortalProjectPresenter`, `GuestPaymentMethodService` (payout methods in `users.metadata['payment_methods']` as an **encrypted string**, because `users.metadata` is a plain JSON column). `EnsurePortalUser` / `EnsureNotGuest` middleware. `config/portal.php` (`PORTAL_CLASSIC`).
- **Vault:** `VaultService` (PIN + salt → derived key, encrypt/decrypt), `ClientVaultCredential`, `Client/VaultController`, `Admin/VaultController`, `Api/ExtensionVaultController` (Chrome extension), `vault:prune`. Env `CHROME_EXTENSION`.
- `auth:cleanup-client-data` hourly; `ThrottleClientAuth` middleware; `LoginAttempt` model.

---

## 9. Leads / CRM / campaigns

`Lead` (`LeadStatus`: new, processing, contacted, generation_failed, sequence_completed, converted, lost, qualified), `Campaign`, `Context` (polymorphic `referencable` + `linkable`), `LeadReplyHandlerService`, `GenerateLeadOutreachJob`, `GenerateLeadFollowUpJob`, `ProcessNewLeadsCommand` (`leads:process-new`) and `ProcessLeadFollowUpsCommand` (`leads:process-follow-ups`) — **both commented out of the schedule**. `Api/LeadController`, `Api/PublicLeadIntakeController`, `Api/PublicLeadApiController`, `LeadIntakeSubmitted` mailable. `ExistingClientEnquiryService` (626 L) — the client-enquiry → quote → convert-to-task/milestone/project pipeline.

Public API key auth: `config/public_api.php` + `AuthenticateWithApiKey` middleware, per-key domain allowlists.

---

## 10. Cross-cutting utilities

- `Models/Traits/HasCategories` + `Category`/`CategorySet`/`CategorySetBinding` + `categorizables` pivot — polymorphic categorisation (documented in `Category.md`).
- `Models/Traits/Taggable` + `Tag` + `ProcessTags` middleware — universal tagging (`readmes/universal-tagging-system-documentation.md`).
- `Casts/MilestoneStatusCast` — a **generic** normalizing enum cast (name is misleading), parameterizable with any enum class + a synonyms JSON; tolerates case/underscore/hyphen/camelCase variants. Exists because `MilestoneStatus` has 10 cases including `'in progress'` and `'pending approval'` with spaces.
- `ValueDictionaryRegistry` / `ValueSetValidator` / `config/value_sets.php` — a field→allowed-values registry (sources: `php_enum`, `model_const`, `config`, `db`) used to soft-validate workflow writes.
- `GlobalSearchController` + `docs/global-search-module.md`.
- `laravel/pulse` at `/pulse`; `spatie/laravel-activitylog` (used on `Bill`, likely more).
- `knuckleswtf/scribe` for API docs.

---

## 11. Scheduled tasks — complete cadence table

From `routes/console.php` (there is **no `app/Console/Kernel.php`** — Laravel 12 style):

| Schedule | Command / Job | Notes |
|---|---|---|
| everyMinute | `FetchEmails` (`emails:fetch`) | Gmail `is:inbox` poll |
| everyFiveMinutes, withoutOverlapping | `inbox:fetch-sent` | only if `config('inbox.sent_ingest.enabled')` |
| dailyAt 03:15, withoutOverlapping | `files:prune-expired` | |
| daily | `FetchCurrencyRatesJob` | |
| daily | `XeroPaymentSyncJob` | |
| everySixHours | `xero:refresh-payment-services` | |
| hourly | `xero:sync-invoices` | |
| everyMinute | `queue:work --stop-when-empty` | **default queue** |
| everyMinute | `queue:work --queue=emails --stop-when-empty` | |
| weeklyOn(7) | `points:calculate-streak` | |
| hourly | `auth:cleanup-client-data` | |
| everyMinute | `app:run-scheduler` | the polymorphic scheduler |
| *commented out* | `leads:process-new` (every 4h), `leads:process-follow-ups` (daily) | |

**Major smell:** the queue is drained by `queue:work --stop-when-empty` fired from cron every minute rather than by a supervised long-running worker. Consequences: a job taking >60s overlaps with the next invocation; there is no `--tries`/`--timeout`/`--max-time`; nothing supervises failures; jobs with `backoff` up to 900s wait for a worker that may already have exited. Anything queued right after a worker exits waits up to a minute.

**Unscheduled commands** (manual only): `app:fetch-conversion-rate`, `devices:expire`, `vault:prune`, `inbox:audit-reply-clock`, `inbox:backfill-gmail-ids`, `inbox:body-samples`, `inbox:fix-email-types`, `points:recalculate`, `points:fix-standups`, `projects:migrate-service-details`, and three `test:*` / `app:test-*` commands (`test:email-observer`, `test:permission-helper-fix`, `app:test-project-deliverable`) that are **debug scaffolding shipped in production**.

---

## 12. Documentation inventory

### Root
| File | Summary |
|---|---|
| `README.md` | Stock Laravel README + a **Scheduler Module** section documenting the polymorphic `schedules` table, `SchedulableAction`, cron entry, and Task parent/child spawning. Says "the legacy `Subtask` model can be ignored in the UI." |
| `WARP.md` | Agent-facing architecture guide. Confirms the workflow engine and scheduler as the two "key subsystems"; documents the schedule-run `FETCH_RECORDS` guard; **references `app/Http/Kernel.php` and `app/Console/Kernel.php`, neither of which exists** (stale). |
| `work.md` | Review of **uncommitted** work: email `delayed` status + Send Later + client/project timezone display. Includes an unrun migration and a verification checklist. |
| `compact.md` | Two fixes: invoice status filter (`ConvertEmptyStringsToNull` turning `?status=` into null) and the new `xero:sync-invoices` command. Then a **roles/permissions migration checklist** (`isManager` → permissions) with ~20 named file+line TODOs across Phases 2–4 (Email, Project/Workspace, Tasks/Permissions/API/Availability). |
| `client-authorization-changes.md` | Why `authorizeResource` was removed from `ClientController` (contractors need project-scoped client access without `view_clients`). |
| `Category.md` | The polymorphic categorisation engine spec (`category_sets`, `categories`, `category_set_bindings`, `categorizables`, `HasCategories`). |
| `workflow_steps.json` | 289-row dump of the `workflow_steps` table (see §1). |
| `Redesign/` | Design source directory; `BlockRenderer` cites `Redesign/.../EmailBlocks.dc.html` as its spec. |

### `docs/` (7 files)
- `NESTED_FIELDS_FIX.md` — DataTokenInserter only surfaced top-level AI response fields, not nested ones.
- `PROCESS_EMAIL_FIX.md` — the `PROCESS_EMAIL` action had no UI email-id config and fell back to `triggering_object_id`, which could be a **Lead** id. (Matches the handler's `resolveEmailId` fallback list, which still ends with `triggering_object_id` marked "can be wrong!".)
- `SYNC_RELATIONSHIP_FEATURE.md` — spec for the `SYNC_RELATIONSHIP` action type + `CategorySet` in the automation schema.
- `automations_v2_architecture.md.resolved` — the `/automations/v2` drag-and-drop builder: Vue Flow canvas, Pinia state, Hub/Builder split-view. **The `.resolved` suffix indicates an unresolved merge artefact.**
- `global-search-module.md` — permission-aware global search across Tasks, Emails, Projects, Proposals, Bills, Invoices, Nav; supports hash-prefix shorthand.
- `milestone-due-date-update.md` — `update_milestone_due_date` permission; changes logged to ProjectNotes with old/new dates + reason.
- `table_design_style_guide.md` — UI/table design patterns (`AuthenticatedLayout`, `<Head>`, Credentials/Proposals page patterns).

### `readmes/` (~250 files) — **not documentation, a dumping ground**
Roughly 130 `test-*.php` / `test-*.js` **executable scripts** (tinker snippets, API probes) checked into the repo alongside ~110 `*-documentation.md` post-mortems. Also `fix-note-encryption.php`, `update-role-references.php`, `verify-timezone-columns.php` — one-off migration scripts.

Thematic clusters (each represents an area that churned hard):
- **Permissions/roles** (~35 files): `permissions-system-documentation.md`, `role-system-changes.md`, `project-specific-roles-*.md`, `router-level-permission-checks-*.md`, `permission-loading-at-login-*.md`, `frontend-role-system-changes.md`.
- **Project form / project show** (~25 files): `project-form-*`, `project-show-*`, `project-policy-*`.
- **Availability** (~12 files): `availability-blocking-*`, `availability-calendar-*`, `availability-modal-*`, `weekly-availability-feature-*`.
- **Email** (~12): `all-emails-filters-*`, `email-conversation-polymorphic-sender-*`, `email-fetch-schedule-*`, `email-subject-capture-fix.md`, `email-to-field-conversion-*`, `rejected-email-*`, `pending-approval-simplified-*`.
- **Google** (~5): `google-account-connection-fix-*`, `google-auth-flow-fix-*`, `google-chat-service-fix-*`.
- **Timezone/meetings** (~10 JS tests + `timezone-display-analysis.md`).
- Singles worth reading: `task_management_system_documentation.md`, `universal-tagging-system-documentation.md`, `tagging-system-documentation.md`, `transaction-observer-implementation.md`, `wireframe-editor-implementation.md`, `note-encryption-fix-documentation.md`, `api-decryption-fix-documentation.md`, `api-token-authentication-documentation.md`, `session-improvements-documentation.md`, `notification-system-fix-documentation.md`, `native-app-chat-drive-attachments-apis.md`, `permission-api-optimization.md`, `migration-cleanup-documentation.md`.

**Stated architectural intent extractable from these:** (1) migrate all `isManager`/role checks to named permissions (`compact.md` checklist, `permission-based-checks-documentation.md`); (2) rebuild the automation builder as `/automations/v2` on Vue Flow; (3) replace the Vue inbox with the React beta; (4) retire the classic portal (`PORTAL_CLASSIC=false`); (5) unify the five Gemini clients (`InboxAiService` docblock).

---

## 13. Consolidated bug / smell / dead-code register

**Correctness**
1. Workflow triggers with capitalised event names (`Lead.created`, `Task.updated`, `User.updated`, `Email.created`) can never fire.
2. `ACTION_AI_PROMPT` step type has no handler.
3. `AwardEmailPointsAction` guard compares an enum-cast attribute to a string — email points never award.
4. `EmailObserver::updated` compares `getOriginal('status')` (raw string) to an enum case — that branch is unreachable.
5. `Conversation::createOrFirst(['subject'=>…])` merges unrelated unknown-sender threads.
6. `GoogleApiTrait::createGoogleClient()` — `config(env(...))` with a non-existent config key.
7. `XeroInvoiceService::syncPaymentToXero` posts to a URL containing `..`.
8. `executeSteps()` writes `$results['steps'][]` into a list-shaped array on one path.
9. Multi-recipient sends record only the first recipient's Gmail ids.
10. Historical rows stamped `type='sent'` by an un-backfilled 2025-07-22 migration are invisible to the reply clock (`inbox:fix-email-types` exists to repair).

**Security**
11. Three plaintext API keys committed in `config/public_api.php`.
12. Tracking-pixel routes are unauthenticated and unsigned; `notice`/`project` accept an arbitrary email in the path.
13. `xero.encrypt` defaults false — OAuth tokens plaintext at rest.
14. App-level Google tokens in a local file (`storage/app/private/google_tokens.json`).
15. `GoogleApiAuthTrait` deletes a user's Google account row on any client-init exception.
16. `TestNotification` broadcasts on a public channel.

**Operational**
17. Queue driven by cron `queue:work --stop-when-empty` rather than a supervised worker.
18. Scheduler lock released before execution.
19. Workflow engine continues after step failures by default.
20. `UpdateRecordStepHandler` saves inside `withoutEvents()`, requiring per-model invariant re-assertion.

**Dead / duplicate code**
21. `app/Services/ForEachStepHandler.php` (namespace/path mismatch, unloadable).
22. `app/Providers/AuthServiceProvider.php` (unregistered) + `AppServiceProvider::$policies` (inert) — two conflicting dead policy maps.
23. `app/Exceptions/Handler.php` (bootstrap `withExceptions` is empty).
24. `GoogleChatServiceV2` — abandoned partial rewrite.
25. Duplicate `TelegramWebhookController` (web + Api).
26. `EmailTemplatePlaceholder` — empty stub model.
27. `Email::getCanOpenAttribute()` — returns false on both branches, still in `$appends`.
28. `Email::approve()` — throws "deprecated", still routed.
29. `EmailObserver`'s three notification methods — all call sites commented out.
30. `google/cloud-pubsub` dependency with zero usage.
31. `dcblogdev/laravel-xero` dependency — all Xero calls are hand-rolled `Http::`.
32. `automation.run_synchronously` config — never read.
33. `app:fetch-conversion-rate` — duplicates `FetchCurrencyRatesJob`.
34. Three `test:*` artisan commands shipped as production code.
35. ~130 executable `test-*.php`/`test-*.js` scripts in `readmes/`.
36. `docs/automations_v2_architecture.md.resolved` — merge artefact.
37. `WARP.md` references `app/Http/Kernel.php` / `app/Console/Kernel.php`, which don't exist.

---

## 14. v2 modular boundaries — recommended

Based on the coupling actually observed:

| Module | Owns | Key preserved concepts |
|---|---|---|
| **Mail Gateway** | Gmail auth, poll/push, send, MIME, threading, Gmail-copy management | `rfc_message_id` vs API `message_id`; CID inline images; header sanitisation; sent-mail ingestion |
| **Conversations** | threads, messages, body normalisation, reply clock, redaction, notes | one canonical body format; server-side redaction; the SLA/cutover distinction |
| **Approval** | the review gate, statuses, status invariants, audit | `guardStatusRegression`; "draft means submitted, not parked"; deny-with-reason |
| **Automation** | workflows, steps, execution logs, triggers, scheduling | per-step logs with token usage; delayed steps; `SchedulableAction` |
| **AI** | one Gemini client, prompt registry, cost/token accounting, per-feature kill switches | `config('inbox.ai')`-style granular flags; never-throw semantics |
| **Finance** | Xero, Airwallex, Stripe, bills, invoices, approval flows, currency | approval-flow abstraction; encrypted payment details; currency normalisation at the boundary |
| **Recognition** | points ledger, bonuses, budgets, leaderboards | ledger with denial reasons; make point values data, not constants |
| **Messaging** | Telegram, Google Chat, in-app chat, broadcast, notifications | topic taxonomy; polymorphic external-account linking |
| **Files** | GCS, Drive, attachments, expiry | `DeleteFileAttachmentAction` as sole deleter; prefix-scoped expiry |
| **Access** | roles, permissions, project scoping, magic links, OTP, devices, portal | project-scoped permissions; hashed device tokens; portal-outside-`web`-guard |

The two hardest migrations are (a) collapsing `emails.body`'s five shapes, and (b) replacing the wildcard Eloquent-event automation trigger with explicit domain events — everything else is comparatively mechanical.