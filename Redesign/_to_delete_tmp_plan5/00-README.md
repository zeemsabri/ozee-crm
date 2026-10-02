# OZee CRM v2 — Master Migration Plan

**Status:** Master plan v1.1 — 2026-09-02 (v1.1 adds the design-export findings: 09, 10, 02 addendum A, 05 addendum)
**Author role:** master planner (Claude). Executors: other Claude sessions following these files literally.
**Legacy repo:** `email-approval-app` (Laravel 12 + Inertia, Vue 3 + partial React 19). Legacy DB: current MySQL.
**Target:** a NEW repository `ozee-crm-v2` — Laravel 12 modular monolith + Inertia 2 + React 19 (TypeScript), monday.com-style design (Vibe tokens, OZee brand), pointing at the empty MySQL database `ozee-crm-v2`.

---

## 0. How to use this plan (read first, every session)

0. **Read `13-philosophy.md` before anything else.** It explains what the owner is optimising for; every judgement call you make must be consistent with it.

1. **This plan is the spec.** Where the plan and the legacy code disagree, the plan wins. Where the plan is silent, copy the legacy *behaviour* (not the legacy code) as described in `12-legacy-system-reference.md` — do not open the legacy repo unless 12 and `survey/` cannot answer and record the gap in `docs/decisions/` of the new repo as an ADR (template in `08-quality-gates.md`).
2. **Work ticket by ticket.** Tickets live in `05-phases-and-tickets.md`. Each ticket names: the module, the files to create, the acceptance criteria, and the tests that must pass. Do not start a ticket whose `Blocked by` list is not complete.
3. **One module at a time, never reach upward.** The module dependency graph in `01-architecture.md §3` is a hard rule. If a ticket needs data from a module higher in the graph, it is a design error — stop and raise it.
4. **Every ticket ends with:** `composer test` green, `npm run typecheck` green, `npm run lint` green, and a one-paragraph entry appended to `CHANGELOG.md` of the new repo.
5. **Never edit the legacy repo** except for the read-only `_legacy-export/` scripts described in `06-data-migration.md`.
6. **Copy, don't invent, the design system.** The React primitive kit and tokens already ported in the legacy repo (`resources/js/ReactComponents/ds/`, `resources/css/ozee-ds/`) are the starting point. Port missing primitives from `Redesign/inbox-mobile/_ds/vibe-monday-*/_ds_bundle.js`. Rules in `04-frontend.md`.
7. **Feature scope is fixed.** In scope for release 1: the modules marked **R1** in `01-architecture.md §2`. Everything else is **Deferred** (`07-deferred-and-dropped.md`). Do not build deferred features "because they were in the old app". The user explicitly wants to start with what is necessary.

## 1. File map

| File | What it is | Who reads it |
|---|---|---|
| `automation/A00..A08` | the Automation module programme (engine, builder UI, logs, migration of the 19 live workflows) — a self-contained plan with the same structure | automation tickets |
| `13-philosophy.md` | the owner's reasoning and priorities — how to decide when the plan is silent | everyone, every session, **read first** |
| `00-README.md` | this file: rules, glossary, decisions register | everyone, every session |
| `01-architecture.md` | tech stack, repo layout, module structure, conventions, cross-cutting rules | everyone before first ticket |
| `02-database-schema.md` | the complete v2 schema, table by table, with legacy source per column | DB/backend tickets, migration tickets |
| `03-modules/*.md` | one spec per module: entities, actions, HTTP endpoints, pages, policies, events, tests | the ticket's implementer |
| `04-frontend.md` | design system, shell, routing, page catalogue, component port list, styling and state rules | frontend tickets |
| `05-phases-and-tickets.md` | ordered work breakdown with dependencies and acceptance criteria | project lead + implementers |
| `06-data-migration.md` | legacy→v2 mapping, ETL runbook, rehearsals, cutover, rollback | migration tickets (Phase 6/7) |
| `07-deferred-and-dropped.md` | what is NOT built now, what is deleted forever, and how deferred data is preserved | everyone (so nobody rebuilds it by accident) |
| `08-quality-gates.md` | testing strategy, CI, lint (incl. the design adherence lint), performance budgets, security checklist, ADR template | everyone |
| `09-design-guide-alignment.md` | the two products in the design export, the adopted scope split, the Admin Console permission/finance/domain rules, Client OS rail, ticket deltas | everyone before Phase 1 |
| `10-model-separation-and-performance.md` | register of every legacy overloaded model → v2 split, and the performance rules | backend tickets, reviewers |
| `12-legacy-system-reference.md` | the whole legacy app in one document (architecture, auth paths, every table's purpose, feature→route map, key flows, integrations, bugs, volumes) — read this instead of the legacy code | everyone; migration and behaviour-parity tickets |
| `11-design-system-components-and-mobile.md` | how to build components (five layers, API rules, pattern library) and the mobile strategy + checklists every UI ticket must pass | every frontend ticket |

Survey reports that ground this plan (produced 2026-09-02 from the legacy repo; keep with the plan): `survey/db.md`, `survey/db_domains.md`, `survey/routes.md`, `survey/services.md`, `survey/frontend.md`. When a ticket says "see legacy X", the survey files tell you where X lives.

## 2. Glossary (v2 vocabulary — use these words everywhere: code, DB, UI copy)

| v2 term | Meaning | Legacy term(s) it replaces |
|---|---|---|
| **Contact** | a person outside OZee: lead or client, one row for their whole lifecycle (`contacts.stage`) | `leads`, `clients` |
| **Account** | *(not modelled in R1)* | — |
| **Project** | a piece of client work; owns milestones, tasks, threads, money | `projects` |
| **Milestone** | a phase of a project that is priced/approved/invoiced on its own | `milestones` ("phase" in the portal UI) |
| **Task** | unit of work, may nest via `parent_id` | `tasks`, `subtasks` |
| **Scope item** | a promised deliverable checklist item on a project/milestone | `project_deliverables` |
| **Deliverable** | an artefact submitted to the client for review/approval | `deliverables` |
| **Budget** | a planned spend line on a project or milestone | `project_expendables` with `user_id IS NULL` |
| **Proposal** | a supplier's quote for a milestone; accepted proposal = **Contract** | `project_expendables` with `user_id` set (+ portal proposals) |
| **Thread** | an email conversation with a contact about a project | `conversations` |
| **Message** | one email in a thread (inbound or outbound) | `emails` |
| **Mailbox** | a Gmail account v2 sends/receives through | `google_tokens.json` + `email_apps` |
| **Approval request** | a generic "someone must approve X" record | 8 hand-rolled approval mechanisms |
| **Comment** | a note/comment on any entity, by a user or a contact, threaded | `project_notes`, `comments`, `invoice_comments`, `deliverable_comments`, `user_notes` |
| **Standup** | a per-user, per-day project update | `project_notes.type='standup'` |
| **Attachment** | any stored file on any entity | `files`, `documents`, `projects.documents` JSON, `deliverables.attachment_path` |
| **Term / Taxonomy** | a controlled label (tag, email category…) | `tags`, `categories`, `category_sets`, `leads.tags` |
| **Access link** | a signed URL that grants a contact or external app access | `magic_links` |
| **One-time code** | an OTP for login/portal/PIN | `user_otps`, `otp_verifications`, `magic_links.temporary_pin`, `clients.pin` |
| **Payout method** | a supplier's bank/PayPal details for being paid | `users.metadata.payment_methods`, `bill_payment_details` |
| **Payment** | money received against an invoice or paid against a bill | `transactions` with `invoice_id`/`bill_id` |
| **Ledger entry** | a project income/expense line not tied to an invoice/bill | other `transactions` |
| **Service** | a sold service line on a project, with **service milestones** (payment schedule) | `project_services` + `payment_breakdown` JSON |
| **Service catalogue item** | the reusable definition of a service | `crm_services` |

Number prefixes (kept from legacy, users know them): project `OZP-{id}`, task `OZ-{id}`, invoice `OZI-{id}`, bill `OZB-{id}`, proposal `OZX-{id}`, contact `OZC-{id}` (new; legacy leads used `OZ` — collision removed).

## 3. Decisions register (the "why" — do not re-litigate in tickets)

| # | Decision | Why |
|---|---|---|
| D1 | Fresh repo, fresh DB, run in parallel, migrate data, cut over. No page-by-page inside the legacy repo. | A new schema cannot be kept in sync with the legacy one page by page; the user chose this option. |
| D2 | Modular monolith (`modules/<Name>`), not microservices, not `nwidart/laravel-modules`. Plain PSR-4 + one ServiceProvider per module. | Isolation without operational cost; fewest moving parts for executor agents. |
| D3 | React 19 + Inertia 2 + TypeScript only. No Vue. | One runtime, one shell, one permission client. The Vue↔React dispatcher problem disappears. |
| D4 | Design: Vibe tokens + OZee brand, already ported. Feature styling with **CSS Modules** reading tokens; `ds/` primitives keep inline styles. **No Tailwind.** | Matches the mocks exactly, supports hover/media queries without hand-rolled JS, and the adherence lint can enforce it. |
| D5 | One `comments` table (polymorphic subject + polymorphic author) + one `standups` table. | User-stated mistake (#1) — `project_notes` was nine things. |
| D6 | One `attachments` table, one `taxonomies/terms` system, one `approval_requests` table, one `access_links` table, one `one_time_codes` table. | Ten note stores, four file stores, three tagging systems, eight approval mechanisms, four OTP mechanisms in legacy. |
| D7 | Leads + clients = `contacts` with `stage`. | They already share morphs; conversion should keep identity. |
| D8 | Email approval is explicit code (jobs + state machine), not the workflow engine. Workflow engine deferred. | Approval must not depend on a JSON-tree interpreter with a known trigger-case bug; user deferred automations. |
| D9 | Message body has ONE storage format per row, declared by `body_format`; `body_text` is always derived. | The five-shape `emails.body` problem is the biggest legacy defect. |
| D10 | Money: `DECIMAL(19,4)` + `currency CHAR(3)` uppercase everywhere; historical `exchange_rates`; FX frozen on each payment. | Legacy mixes 10,2 / 15,2 / int minor units and lowercase currency codes. |
| D11 | PKs: `BIGINT UNSIGNED` auto-increment everywhere; ULIDs/UUIDs only as *public* ids where a URL must not leak counts (`public_id` column). | Legacy has four PK strategies. |
| D12 | All state columns are PHP backed enums mirrored as `VARCHAR(32)` + CHECK constraint (not MySQL `ENUM`). | MySQL ENUM changes need `ALTER TABLE` and broke five times in legacy. |
| D13 | Permissions shipped in Inertia shared props; no `/api/user/permissions` round trip. | Removes the flash of unauthorised UI and the duplicated permission clients. |
| D14 | Gmail ingestion stays **polling** in R1 (every minute) but behind a `MailProvider` contract so push can be added. | Push was never built; keep the risk out of R1. |
| D15 | Queue: a supervised `queue:work` (Horizon-style or Supervisor) — never cron `--stop-when-empty`. | Legacy operational smell #17. |
| D16 | External API contracts consumed by the Chrome extension, desktop tracker and third-party apps are **versioned under `/api/v1`** and reproduced byte-for-byte where in scope; deferred ones return `410 Gone` with a message. | Those consumers cannot be changed with the app. |
| D17 | Deferred modules' data is migrated into `legacy_*` archive tables in the new DB (raw copies), not into v2 entities. | Nothing is lost; nothing half-built. |
| D19 | Tenant-ready from day one: `workspaces` + `workspace_id` on every business table with a global scope; only one workspace in R1. | The design export plans a multi-agency SaaS ("Client OS"); adding the column later means migrating every table twice. |
| D20 | Client OS product modules (signatures, enquiries/SMS, scheduling, domain provider sync, social, platform/plans) are R2; the client portal ("Agency hub"), Client Board, Results/SEO reports, businesses layer, tiers/levels permission model, teams, domains registry, access requests, ghost sessions and audit viewer are R1. | 09 §1 — they map onto OZee's own operation and legacy data; the product modules have no backend/data yet and four unresolved product decisions. |
| D22 | Mobile-first: every page ships with a phone layout from the recipes in 11 §4 even before dedicated mobile designs exist; mobile is part of each page's acceptance criteria. | User rule 2026-09-02. |
| D21 | Every overloaded legacy model is split per `10-model-separation-and-performance.md`; that file is the checklist. | User rule: separate models, industry standards, performance. |
| D18 | Comments are stored in plaintext in the DB; encryption is at the disk/DB layer, not application-level. Vault credentials remain application-encrypted (per-row salt, PIN-derived key). | App-level encrypted notes made search impossible and failed silently. Vault has a genuine threat model. |

## 4. Assumptions (stated because the user did not answer the "secondary areas" question)

- A1: Deferred to release 2+: Automations builder & AI prompt library, Availability/Attendance/Live status/Productivity/CTO reports, Presentations/Wireframes/Shareable & Team resources/Notice board, Project chat/Telegram/Google Chat/Schedules, Points/Kudos/Bonus/Leaderboard/Monthly budgets.
- A2: "Whatever is available in design" is applied as decided in `09 §1`: CRM/agency-hub screens are R1; the separate "Client OS" product modules and multi-agency platform layer are R2 (user to confirm).
- A3: Google Calendar meetings and standups stay in R1 in minimal form (create/list on a project) because the project detail screen in the design bundle shows them; Google Chat push does not.
- A4: The AI provider stays Gemini (one client), with per-feature kill switches, defaults **off** in production until the queue is proven (legacy rule).

## 5. Where things are

- Legacy repo (read-only for us): connected folder `email-approval-app/`.
- Design bundle: `email-approval-app/Redesign/inbox-mobile/` (canonical; the other folder is a byte-identical subset).
- Already-ported React kit to seed v2 from: `email-approval-app/resources/js/ReactComponents/{ds,app,inbox,portal}/`, tokens `email-approval-app/resources/css/ozee-ds/`.
- New repo: `ozee-crm-v2/` (sibling folder). Create it in ticket F0-01.
- New DB: MySQL `ozee-crm-v2` (already created, empty).
