# A07 — Phases and tickets (Automation programme, ~7 weeks; starts once master Phase 1 (Work) and Phase 2 (Comms) exist)

Same ticket format and definition of done as `../05-phases-and-tickets.md` and `../08-quality-gates.md`. IDs `AU-…`.

## Phase A0 — Contracts the rest of the app must publish (can start with master Phase 1) — 1 week
- **AU-00-01 · DomainEventBus + EventCatalogue** — Platform — M — `Modules\Platform\Events\DomainEventBus` (publish typed events with payload DTO, actor, occurrence id, `withoutAutomation`), `EventCatalogue` registry (name, module, payload schema from DTO, sample payload), `AutomationExposed` model contract. AC: registering an event without a DTO fails a test; catalogue lists events with JSON schemas.
- **AU-00-02 · Publish R1 events from modules** — Crm/Work/Comms/Finance/Platform/Identity — L — every event in A01 §3.1 dispatched from the owning Action with `changes` on updates. AC: feature test per event asserting payload shape; arch test forbids Eloquent wildcard listeners.
- **AU-00-03 · ActionCatalogue + first actions** — M — `ActionDefinition` registry; register Work/Crm/Comms/Platform/Ai actions in A01 §6 (thin wrappers over existing module Actions, actor-aware, idempotency-key aware). AC: each action has input/output JSON schema and a test invoking it as an automation actor (permission denied case included).
- **AU-00-04 · ModelCatalogue** — M — `AutomationExposed` on the R1 models with field metadata, sensitive flags, relations, reach scopes. AC: catalogue snapshot test; sensitive fields absent.

## Phase A1 — Engine core — 2 weeks
- **AU-01-01 · Automation migrations/models/factories/policies/permissions** (A02) — M.
- **AU-01-02 · Expression language** — L — parser, evaluator, filters, path resolver, `SchemaBuilder`; fixture set (≥300 cases) shared with the TS mirror. AC: 95% branch coverage; TS mirror produces identical results in CI.
- **AU-01-03 · Runner + cursor + context + step runs + effects** — L — `ExecuteRunJob`, per-step protocol, `on_error`, retries, idempotency keys, guards, halt/continue/branch, condition/loop semantics, run counters, `RunFinished`. AC: table-driven semantics tests (A08 §1) all green.
- **AU-01-04 · Suspension/resume** — L — delay (`resume_at` sweeper), AI async (`AiStepJob` → resume), HTTP async, `wait_for_approval` (ApprovalDecided listener), `wait_for_event` (subscription table + matcher), timeouts. AC: resume at exact cursor inside nested loop; no sibling re-execution; process kill mid-run resumes cleanly.
- **AU-01-05 · Step handlers batch 1** — L — trigger, condition, loop, fetch_records (SQL compiler with reach scoping), set_variables, transform, delay, stop, log, action (catalogue dispatch), send_notification. AC: per-handler validate/execute/outputSchema tests.
- **AU-01-06 · Step handlers batch 2** — L — create_record/update_record/sync_relation (generic, with catalogue validation and events), ai_prompt (Ai module client, cost caps, drift), http_request (credentials, allow-list, retry), wait_for_approval, wait_for_event, run_workflow. AC: HTTP fakes; AI fakes; credential never appears in any persisted row (grep test).
- **AU-01-07 · Triggers** — M — EventListener (subscriptions + filters + `ignore_own_events` + depth), ScheduleDispatcher (`SKIP LOCKED`, timezone), ManualTrigger, WebhookTrigger (HMAC, IP allow-list), occurrence uniqueness, `per_subject` concurrency. AC: flood test (1,000 task.updated events → filtered count correct, no duplicate runs).
- **AU-01-08 · Versioning + publish validation** — M — draft/published versions, `PublishVersion` with full validation (A03 §6), capability computation + level check, `trigger_subscriptions` rebuild, revert. AC: each validation rule has a failing fixture.
- **AU-01-09 · Retention, redaction, stats, alerts, commands** (A05) — M.

## Phase A2 — Builder UI — 2 weeks (parallel with A1 from AU-01-02 onward)
- **AU-02-01 · React Flow canvas foundation** — L — `features/automation/canvas`: StepNode (L3), ports, sequential edges incl. after containers, insert on edge/node, dagre auto-layout + manual, layout persistence, multi-select/copy/paste/undo, virtualisation, keyboard. AC: `/dev/ds` story with 200 nodes at 60 fps; Playwright: add condition, attach nodes to both branches, publish.
- **AU-02-02 · Properties panel + form generator + widgets** — L — schema-driven forms; ExpressionInput with chips + live preview; TokenPicker (per-step available tokens from `catalogue/tokens`); RuleBuilder; Model/Field/Relationship pickers; ActionForm; AI step form with test; HTTP step form with test request; Schedule/Event trigger forms with sample payloads. AC: every step type's form renders from its schema; picker never offers an unresolvable token (test against SchemaBuilder).
- **AU-02-03 · Hub + Templates + Credentials pages** — M — DataTable with real filters, actions, confirmations, templates drawer, credentials CRUD. AC: phone card mode; permissions hide actions.
- **AU-02-04 · Autosave, validate, publish dialog, version history/diff, import/export** — M.
- **AU-02-05 · Test run / dry run panel** — M — sample picker (recent occurrence, record search, JSON), dry-run flag, live timeline on canvas. AC: dry run writes nothing (DB assertion), stored as test run.
- **AU-02-06 · Runs list + run detail timeline + effects component on entity pages** — L — English summaries, loop grouping, decision rows, inputs/outputs/effects/error/context tabs, retry/cancel/replay, Echo live updates, phone-first timeline. AC: pagination works (server-side); a 500-step run renders in <1 s.
- **AU-02-07 · Insights dashboard + failure notifications deep links** — S.

## Phase A3 — Ai prompt library UI (Ai module) — 1 week
- **AU-03-01 · Prompt pages** — L — list/versions/diff, editor (system prompt, variables with types, response schema builder nested), generation config, test bench with sample inputs and cost, "used by" from step references, archive. AC: schema round-trips to `response_schema` used by `ai_prompt` outputs.

## Phase A4 — Migration of live automations — 1.5 weeks (runs with master Phase 6)
- **AU-MIG-01 · Legacy import transformer** — M — `automation:import-legacy` (drafts + issues report), prompt import with renames and drift report, schedules mapping, secrets → credentials list. AC: all 19 legacy workflows import as drafts with issues enumerated; zero secrets in output.
- **AU-MIG-02 · Author the 10 templates** (A06 §1) as seeded JSON with sample payloads and expected-effects fixtures — L.
- **AU-MIG-03 · Comms built-ins parity check** — M — confirm the six replaced workflows' behaviours exist in Comms (quote stripping, screening, project assignment, approval gate honouring AI verdict) with tests referencing the legacy WF numbers. AC: fixture emails from legacy produce the same decisions.
- **AU-MIG-04 · Shadow week + verification report** — M — dry-run templates against the rehearsal DB copy; effects diff vs legacy `execution_logs`; sign-off per template; publish at cutover with `Pause after publish` off. AC: report attached; owner sign-off.
- **AU-MIG-05 · Archive legacy automation tables + backfill daily stats** — S.

## Phase A5 — Hardening — 0.5 week
- **AU-05-01 · Load test**: 10k events/hour, 200 concurrent waiting runs, scheduler under 1-minute jitter; queue sizing; indexes verified. **AU-05-02 · Security review**: credential handling, webhook HMAC, SSRF allow-list, policy matrix, redaction grep. **AU-05-03 · Accessibility of canvas & panel** (keyboard-only authoring of a 5-step workflow).

## Later (backlog, not scheduled)
Sub-workflow library/marketplace; branch-level A/B; approval delegation rules; expression functions for money/FX; Zapier-style outbound webhook action with signing; workflow analytics per contact journey; natural-language "describe an automation → draft" using Ai (draft only, never auto-publish).
