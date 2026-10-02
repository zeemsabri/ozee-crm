# Automation Module Plan — README

**Status:** Automation plan v1.0 — 2026-09-05. Companion to the v2 master plan (`../00-README.md`); same rules, same conventions, same executors. Read `../13-philosophy.md` and `../00-README.md` first, then this folder.
**Scope:** the `Modules\Automation` module of `ozee-crm-v2` — visual workflow builder + engine + logs + prompt library — and the migration of the 19 live legacy workflows.
**Ground truth:** `../survey/automation_deep.md` (1,500 lines: every step config key, resolver, engine semantic, UI component, live workflow and defect of the legacy system). `../survey/services.md §1–2` for the summary. Executors read those before touching a ticket.

## 0. The brief, in the owner's words
- "Our 99% of the automation we create works" — the *capabilities* are right; keep every one of them (A03 §1 is the keep-list).
- "We also have a v2 UI of automation which is great but obviously not created by Claude so could be better" — keep the V2 builder's shape (hub + canvas + properties panel + token picker + relationship picker + log timeline), rebuild it on the design system, fix the known UX defects (A04).
- "There are some gaps as how clear the log is and how long log stays etc." — logs get a first-class run model, a real timeline, retention and redaction (A05).
- "Whatever plan you make should be better, not a downgrade to what we have" — every legacy capability has a v2 home in A03 §1's parity table; no capability is dropped without a named replacement.
- "Our new database has a team structure etc so our automation might need to work differently when we migrate" — workflows are workspace-scoped, owned by a team, run as an explicit actor, respect visibility and permissions (A01 §4), and every live workflow is re-expressed against the v2 models (A06).

## 1. File map
| File | What |
|---|---|
| `A00-README.md` | this: brief, decisions, glossary, how this module relates to the master plan |
| `A01-architecture.md` | module boundaries, engine design (runs, steps, expressions, triggers, scheduling, actor/permissions/teams), integration with Comms approval, event catalogue |
| `A02-database-schema.md` | tables for workflows, versions, steps, runs, step runs, schedules, prompts, credentials, retention |
| `A03-step-catalogue-and-expressions.md` | every step type's spec (config schema, inputs, outputs, errors, UI form), the expression language, legacy parity table |
| `A04-builder-ui.md` | Hub, Builder canvas, properties panel, pickers, test/dry-run, logs UI, prompt library UI — on the v2 design system, desktop + phone |
| `A05-runs-logs-observability.md` | run model, log clarity rules, retention, redaction, alerts, cost accounting |
| `A06-migration-of-live-workflows.md` | the 19 legacy workflows → v2 (which become built-in pipelines, which migrate, which are dropped), config transformer, verification |
| `A07-phases-and-tickets.md` | ordered tickets with acceptance criteria |
| `A08-quality-gates.md` | tests specific to the engine (table-driven semantics, replay fixtures), performance budgets, security |

## 2. Where this sits in the master plan
- Master plan D8: **email approval is explicit code in Comms** (submit → AI check → approve/hold → send). This module does **not** own that pipeline. Legacy WF17/WF10/WF14/WF23 (the six racing `email.created` workflows) are replaced by Comms code (A06 §2). Automation *can* react to Comms events (`message.received`, `message.sent`) and *can* call Comms actions (`comms.submit_message`) — through published actions, never by writing `messages.status` directly.
- Master plan 01 §3 dependency graph: Automation sits **to the right of Finance/Access** (it may call any lower module's published actions and models, read-only or via actions) and to the left of Portal. It depends on Ai (prompts, Gemini client) and Platform (comments, attachments, approvals, settings).
- Master plan 07: Automation was "R2". This plan makes it a scheduled programme of its own (**Phase A0–A6**, ~7 weeks) that can start once Phase 1 (Work) and Phase 2 (Comms) of the master plan exist, because live workflows need Contacts, Projects, Tasks, Messages. Recommended slot: in parallel with master Phase 3–4, cutover together (the live workflows must be running on day one after migration — they create tasks from emails, run lead follow-ups, import BugHerd).

## 3. Decisions (do not re-litigate)
| # | Decision | Why |
|---|---|---|
| AD1 | Keep the **step-tree model** (steps with branches and loops, executed in order) and the **visual canvas** — no switch to a code/DSL-only or a pure DAG. | It is what the team knows and it works; 99% of authored automations run. |
| AD2 | **Triggers are explicit domain events** published by modules (`task.completed`, `message.received`, `contact.created`…), with a typed payload and `before/after` on updates. No wildcard Eloquent listener. | Legacy case-mismatch bug, no payload contract, six racing workflows. |
| AD3 | **Steps have stable keys** (`key` = short slug unique within the workflow, e.g. `fetch_leads`, `ai_reply`) and tokens reference keys, never DB ids. | Legacy id-remap on save corrupted WF17 (the approval gate has been ignoring the AI verdict). |
| AD4 | **One expression language, one resolver** (`{{ path | filter }}` with defaults, filters, comparison functions), used by every step and the UI picker; the picker only offers tokens that resolve. | Eight incompatible resolvers in legacy. |
| AD5 | **Runs are first-class** (`workflow_runs` with status, trigger subject, actor, timeline); step runs store *outputs + diff of context*, not a full context copy per step; retention policy per workflow; secrets and PII redacted. | The log gaps the owner named. |
| AD6 | **Halt on error by default**, per-step `on_error: halt|continue|branch`; retries declared per step; idempotency keys for AI/HTTP/create steps. | Legacy continued blindly; CONDITION errors became "no branch". |
| AD7 | **Async steps (AI, HTTP, delay, wait-for-approval) suspend and resume the run at exactly that step** via a persisted cursor; no container replay, no sibling re-execution. | Legacy resume bugs D3/D4. |
| AD8 | Writes to domain models go through **published module actions** (e.g. `work.create_task`, `crm.update_contact`) executed with `actor = automation(workflow)`. Generic `create_record/update_record` remain for admin-level power users but run the model's normal events/invariants — never `withoutEvents`. | Keep invariants; stop trampling `guardStatusRegression`-style rules. |
| AD9 | **Workspace + team scoped**: a workflow belongs to a workspace and a team; it can only touch records its owning team can reach; it runs as a system actor whose permission set = the team's; managers (level ≥2) author, owners (level ≥3) publish workflows that write finance/DNS/people. | New team structure and three-ceiling permissions. |
| AD10 | **Draft/published versions**: editing never changes a running workflow; runs pin the version; publish validates (models, fields, tokens, prompts) — invalid workflows cannot be published. | No runtime failures from typos; safe editing. |
| AD11 | **Credentials live in a credential store**, referenced by name from HTTP steps; URL allow-list per credential. | API keys were in `step_config` and in a committed dump. |
| AD12 | **Schedules are authored on the trigger node** and stored as `workflow_schedules` (cron or simple), executed by one scheduler with a lock that covers execution. | Legacy schedule fields on the node were ignored. |
| AD13 | Prompt library is **Ai module's**; Automation binds a step to a prompt *version* and warns on drift; prompt test bench in the UI. | Stale `promptRef` snapshots with duplicate ids. |
| AD14 | Builder rebuilt in React on the v2 design system (**React Flow** replaces vue-flow), desktop-first with a phone **read/monitor** mode (runs, toggles, approvals) — authoring on phone is not a goal. | Master plan D3/D22. |

## 4. Glossary
**Workflow** (an automation) · **Version** (immutable snapshot of steps; `draft` or `published`) · **Trigger** (event, schedule or manual) · **Step** (node; has `key`, `type`, `config`, `on_error`, `retry`) · **Branch** (`then`/`else` on a condition; `body` on a loop) · **Run** (one execution of a version for one trigger occurrence) · **Step run** (one step attempt inside a run) · **Cursor** (where a suspended run resumes) · **Context** (the run's data: `trigger`, `steps.<key>`, `vars`, `loop`, `actor`, `workspace`) · **Expression** (`{{ … }}`) · **Action** (a published module operation a step can call) · **Credential** (named secret) · **Actor** (who a run acts as: `automation:<workflow>`).
