# A03 — Step catalogue and the expression language

## 1. Legacy → v2 parity table (nothing lost)
| Legacy step / capability | v2 | Notes |
|---|---|---|
| `TRIGGER` model event (`email.created`…) | `trigger` (kind event) with typed events from the catalogue + optional filter | event names fixed and case-safe; `changes` available on updates |
| `SCHEDULE_TRIGGER` (fields ignored) | `trigger` (kind schedule) → `workflow_schedules` | actually stored and executed |
| manual `/triggers/{event}` | `trigger` (kind manual) + `Run now` with input | |
| — | `trigger` (kind webhook) | new: inbound webhook with secret |
| `CONDITION` (AND/OR rules, var/literal, morph normalisation) | `condition` (`all`/`any`, rule groups nestable one level, operators §3) | `then`/`else` branches; decision recorded |
| `FOR_EACH` (`sourceArray`) | `loop` (`over`, `as`, `max_items`, `break_if`, `concurrency`, `collect`) | `loop.parent`, outputs collectable |
| `FETCH_RECORDS`/`QUERY_DATA` (conditions, json_path, relative-time ops, `with`/relationships with field selection, single, count_only, limit, order, output_key) | `fetch_records` (all of these in the UI, permission-scoped) | operators consistent with condition |
| `ACTION_CREATE_RECORD` (templated fields, `NOW()`/`NULL`, morph normalisation, `CreatableViaWorkflow` defaults, value-set validation) | `create_record` (generic) **and** typed actions (`work.create_task`…) | functions replaced by expression filters (`{{ now }}`, `{{ null }}`); defaults from the typed action's rules; validation at publish |
| `ACTION_UPDATE_RECORD` | `update_record` (generic, runs model events) + typed `update_*`/`transition_*` actions | `withoutEvents` removed; invariants hold |
| `ACTION_SYNC_RELATIONSHIP` (sync/attach/detach, terms/categories) | `sync_relation` + `platform.assign_terms` | |
| `ACTION_SEND_EMAIL` (raw internal mail) | `platform.send_internal_email` (to users/teams/roles or addresses; template or markdown) | via mailer with error surfaced |
| `ACTION_PROCESS_EMAIL` (hand-off to sending pipeline) | `comms.submit_message` (enters approval pipeline) + `comms.compose_message` to create the draft | no direct send |
| `ACTION_FETCH_API_DATA` (methods, auth modes, payload, response key, response structure) | `http_request` (credential by name, allow-list, timeout, retry, response schema for the picker) | secrets out of config |
| `AI_PROMPT` (library prompt + base fields + relationship payload + free text, JSON response; async) | `ai.run_prompt` (prompt version, inputs mapping with the relationship picker, response schema from the prompt, async with suspend/resume) + `ai.classify`, `ai.summarise`, `ai.extract_fields` presets | drift warnings; cost per run |
| `TRANSFORM_CONTENT` (remove_after_marker, find_replace, remove_html) | expression filters `\| cut_after`, `\| replace`, `\| strip_html`, plus `transform` step for multi-output text ops | most uses become inline filters |
| `DEFINE_VARIABLE` | `set_variables` | `vars.<name>` |
| `delay_minutes` on any step | `delay` step (`for: 3 minutes` / `until: expression`) or `delay_seconds` on a step | suspend/resume exactly here |
| — | `wait_for_approval` (creates an `approval_requests` row; branches approved/rejected; timeout) | new: human-in-the-loop without hacks |
| — | `wait_for_event` (e.g. wait until `message.received` on this thread, timeout 7d) | new: replaces "schedule + fetch + compare" follow-up patterns |
| — | `run_workflow` (sub-workflow with inputs/outputs) | new: WF24≈WF28, WF12≈13≈21 become one |
| — | `send_notification` (in-app/broadcast/email to users/teams/roles) | new |
| `Context` rows as AI memory (CREATE_RECORD on Context) | `crm.add_contact_memory` action writing `comments(kind=note, visibility=internal, source=automation)` on the contact + `ai.summarise` for compaction | Context table dropped; memory = notes on the contact, fed to prompts via the relationship picker |
| execution logs with input/output/config/error per step, run grouping | runs + step runs + effects + events (A05) | |
| Prompt library (versions, response builder, generation config) | Ai module prompts (kept) + drift warnings + test bench | |
| `CreatableViaWorkflow` / `ValueSetValidator` | replaced by typed actions' input schemas + `ModelCatalogue` field metadata (enums, required, defaults) validated at publish and at run | |

## 2. Expression language (one resolver: `Modules\Automation\Expressions`)
- Syntax: `{{ path }}` and `{{ path | filter(arg) | filter }}`. Whole-string single expression returns the native value; mixed text interpolates (arrays/objects → JSON).
- Paths: dotted, with `[n]` index and `[*]` map (`steps.fetch_leads.records[*].email`); roots: `trigger`, `steps.<key>`, `vars`, `loop` (`item, index, first, last, parent`), `actor`, `workspace`, `now`, `input` (manual/sub-workflow inputs). Legacy `:` separator accepted by the **importer only** and rewritten.
- Missing path → `null` (never `''`); `| default(x)` supplies a fallback; publish-time validation flags paths that cannot exist at that step (using each step's `outputSchema`).
- Filters (all pure): `default(v)`, `upper`, `lower`, `title`, `trim`, `truncate(n)`, `cut_after(marker)`, `cut_before(marker)`, `replace(a,b)`, `strip_html`, `markdown_to_text`, `json`, `join(sep)`, `split(sep)`, `first`, `last`, `count`, `pluck(field)`, `where(field, op, value)`, `sum(field)`, `unique`, `sort(field)`, `date(format)`, `add(n, unit)`, `sub(n, unit)`, `diff(other, unit)`, `tz(zone)`, `startOf(unit)`, `number(decimals)`, `money(currency)`, `bool`, `coalesce(...)`, `slug`, `initials`.
- Literals: `now`, `today`, `null`, `true/false`, numbers, quoted strings; `{{ now | add(1, 'day') | date('Y-m-d') }}` replaces `DATE_ADD(CURDATE(), INTERVAL 1 DAY)`; `{{ null }}` clears a field.
- Escaping: `{{ '{{' }}` emits literal braces.
- Evaluation is sandboxed (no code execution, no model access); the evaluator is a unit-tested pure PHP class with a ≥95% branch-coverage requirement and a shared fixture set also used by the TypeScript `expressions.ts` mirror the builder uses for live previews (both must produce identical results on the fixture set — CI diff).

## 3. Comparison operators (shared by `condition`, trigger `filter`, `fetch_records`, `loop.break_if`, `| where`)
`==`, `!=`, `>`, `>=`, `<`, `<=` (numeric when both numeric, dates when both parse as dates, else string), `contains`, `not_contains`, `starts_with`, `ends_with`, `matches` (regex), `in`, `not_in`, `is_empty`, `is_not_empty` (null, '', [], {} — note `"0"` is NOT empty), `is_null`, `is_not_null`, `is_true`, `is_false`, `before`, `after`, `today`, `in_past`, `in_future`, `within_last(n, unit)`, `older_than(n, unit)`, `changed` (only on `trigger.changes.<field>`), `changed_to(v)`, `changed_from(v)`. Morph-type comparisons use aliases (`author_type == 'contact'`) — the UI offers alias dropdowns for `*_type` fields. `fetch_records` compiles the same operators to SQL (`contains` → `LIKE %x%`, JSON paths → `->`, relative time → `>=/<` now±n); unsupported combinations are rejected at publish, never silently mapped to `=`.

## 4. Step specifications
Common to every step: `key`, `name`, `type`, `guard` (`if` expression; false → `skipped`), `on_error` (`halt|continue|branch`), `retry {max ≤5, backoff_seconds}`, `timeout_seconds`, `delay_seconds` (short inline delay ≤ 300; longer → `delay` step). Each handler declares `configSchema` (JSON Schema, drives the properties panel via a form generator + custom widgets), `outputSchema(config, catalogue)` (drives the token picker), `capability(config)`, `validate(config, tokensAvailable)`.

| type | config | outputs (`steps.<key>.*`) | notes |
|---|---|---|---|
| `trigger` | `kind event|schedule|manual|webhook`, `event`, `filter` (rules), `schedule {…}`, `webhook {slug, secret}`, `inputs` schema (manual/sub-workflow) | `trigger.*` root only | exactly one, always first; filter evaluated pre-run |
| `condition` | `match all|any`, `rules:[{left, op, right}]`, optional `groups` | `result: bool`, `evaluated:[{rule, left_value, right_value, passed}]` | branches `then`/`else`; continues after |
| `loop` | `over` (list expr), `as` (default item), `max_items ≤1000`, `concurrency 1–5`, `break_if`, `collect: step_key` | `count`, `items[]` (collected), `iterations_run` | body branch; `loop.parent` for nested |
| `fetch_records` | `model` (catalogue), `where:[{field, op, value}]`, `with:[{relation, fields[]}]`, `order:[{field, dir}]`, `limit ≤1000`, `mode list|first|count|exists`, `json_paths` | `records[]`, `record`, `count`, `exists` | scoped by actor reach; fields per `with` include FKs automatically |
| `create_record` | `model`, `fields:{col: expr}`, `idempotent bool` | `record`, `id` | generic; validated against catalogue (required, enums, types); runs events |
| `update_record` | `model`, `record_id` expr, `fields`, `if_changed_only bool` | `record`, `changed[]` | fires model invariants |
| `sync_relation` | `model`, `record_id`, `relation`, `mode sync|attach|detach`, `ids` expr | `attached[]`, `detached[]`, `count` | |
| `action` | `action` (slug from catalogue), `inputs:{name: expr}` | per action's outputSchema | preferred over generic; UI shows action forms grouped by module |
| `ai_prompt` | `prompt_id`, `prompt_version`, `inputs:{var: expr}` (with relationship picker producing `{{ trigger.contact | with('notes','projects.milestones') }}`), `response_schema` (from prompt, read-only), `model_override?`, `temperature?` | `output` (typed by response schema), `raw`, `tokens_in/out`, `cost`, `review_id` | async: suspend → `AiStepJob` → resume; per-run cost caps (`settings.automation.ai_cost_cap`) |
| `http_request` | `credential`, `method`, `url` (host must match credential allow-list), `headers{}`, `query{}`, `body` (json expr), `response_path`, `response_schema`, `timeout ≤60`, `expect_status[]` | `status`, `body` (typed), `headers`, `duration_ms` | retry with backoff on 5xx/429; bodies never logged (only sizes) |
| `transform` | `operations:[{op, args}]` on `source` | `result` | for multi-step text ops; single ops → filters |
| `set_variables` | `variables:{name: expr}` | `vars.<name>` | |
| `delay` | `for {n, unit}` or `until expr` (≤30 days) | `resumed_at` | suspend/resume at this step |
| `wait_for_approval` | `kind` (ApprovalKind), `subject` expr, `approvers {users|roles|team_lead}`, `message`, `timeout {n, unit}`, `on_timeout approve|reject|halt` | `decision`, `decided_by`, `reason` | branches approved/rejected; creates `approval_requests` |
| `wait_for_event` | `event`, `match:[rules on the incoming payload vs context]`, `timeout` | `event_payload` | e.g. wait for `message.received` on `steps.compose.thread_id` |
| `send_notification` | `to {users[], roles[], teams[], contact?}`, `channel in_app|email|both`, `title`, `body`, `link` | `notified[]` | |
| `run_workflow` | `workflow` (published), `inputs{}`, `wait bool` | `run_id`, `outputs` | depth-limited |
| `stop` | `status succeeded|failed`, `reason` | — | explicit end of a branch |
| `log` | `level`, `message` | — | writes `run_events` |

## 5. Model catalogue (what fetch/create/update can see)
`ModelCatalogue` lists v2 models opted-in via a `AutomationExposed` contract: alias, label, fields (name, type, enum values, required, writable, sensitive → redacted), relations (name, target alias, type, default fields), scopes for reach. R1 exposure: contact, business, enquiry, deal, project, milestone, task, scope_item, deliverable, standup, meeting, thread (read), message (read + limited fields), comment, attachment (read), invoice/bill/proposal/payment (read; write via actions), term. Sensitive fields (payout details, vault, tokens) are never exposed. Cached per publish of any module; the picker reads it.

## 6. Validation at publish (all must pass)
Trigger present and first; every expression parses; every path resolvable from the tokens available at that step (trigger schema, earlier sibling/ancestor steps only — never later steps, never sibling branches, never inside-loop steps from outside); every `fetch_records`/`create_record`/`update_record` field exists and is writable; enum values valid; required action inputs present; credentials exist and host matches; prompts exist at the pinned version (drift = warning); loop `over` is list-typed; `wait_for_approval` approvers resolvable; capabilities computed and permitted for the publisher's level; no unreachable steps (detached nodes are an **error**, not silently executed).
