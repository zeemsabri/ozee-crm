# 05 — Phases and tickets

Ticket format: **ID · Title** — Module · Blocked by · Deliverables · Acceptance criteria (AC). Every ticket also: tests green, lint green, CHANGELOG line. Size: S ≤ half day, M ≤ 2 days, L ≤ 4 days for a competent agent. Phases are sequential; tickets inside a phase may run in parallel unless blocked.

## Phase 0 — Foundations (repo, tooling, kernel, design system, shell, auth) — ~2 weeks

- **F0-01 · Create repo `ozee-crm-v2`** — S — Laravel 12 skeleton, PHP 8.3, MySQL `ozee-crm-v2`, Pest, Pint, Larastan (level 6), `.env.example` with every var from `01-architecture`, `deploy/` supervisor+cron stubs, GitHub Actions CI (`composer test`, `npm run typecheck`, lint). AC: CI green on empty app; `/up` healthy.
- **F0-02 · Module kernel** — M — `Modules\Platform\Support\ModuleServiceProvider`, `config/modules.php`, composer autoload, `tests/Architecture/ModuleBoundariesTest.php` with every forbidden edge from 01 §3, `Schema::checkEnum` helper, `Money` VO, `HasPublicId`, `DomainException` + handler mapping, `Feature::enabled`, `Sequence`. AC: arch test passes; a sample enum CHECK constraint rejects a bad value in a test.
- **F0-03 · Framework + Platform migrations & models** — M — Blocked by F0-02 — all tables in 02 §00 + §01 with factories; morph map enforced; `HasComments/HasAttachments/HasTerms/HasApprovals` traits; `AttachmentStore` (gcs/local), `attachments:prune`; `AddComment` w/ mentions; `RequestApproval/DecideApproval`; `ExchangeRateService` + `rates:fetch`; `settings` service; `webhook_events`. AC: unit tests for each action; comment on a fake subject via trait; approval single-open invariant.
- **F0-04 · Frontend toolchain + design tokens** — M — Vite 6 + React 19 + TS strict + Inertia 2, `app.tsx`, CSS Modules, ESLint (adherence rules ported: hex ban, spacing scale), Prettier, `npm run types` (typescript-transformer), copy `resources/css/ozee-ds/**`, icons + logos into `public/ozee-ds/`, fonts (self-host Figtree + Poppins, no external @import in prod). AC: `/dev/ds` page renders tokens swatches; hex literal in a `.tsx` fails lint.
- **F0-05 · Port `ds/` primitives + hooks** — L — Blocked by F0-04 — copy the 28 primitives, `Popover`, `useAnchoredPopover`, `useTheme`, `useToasts`, `useIsMobile`, mobile `Sheet/PushScreen`; convert to TSX with prop types. AC: `/dev/ds` shows every variant; keyboard: Chips/Menu/Modal/Dropdown operable; dark mode toggles.
- **F0-06 · Port remaining primitives (batch 1)** — L — Blocked by F0-05 — `Table` (TanStack), `Tooltip`, `DatePicker`, `Combobox`, `BreadcrumbsBar`, `Accordion`, `AvatarGroup`, `Badge`, `Heading/Text/Link`. AC: kitchen sink; Table sorts/selects with tray; Combobox async with 300ms debounce.
- **F0-07 · Composite patterns** — M — Blocked by F0-06 — `PageHeader, StatTile/StatGrid, HeroCard, InsightWash, ListRow, EmptyState, SidePanel, FilterRail, SelectionTray, BoardTable` per 04 §3 (pixel values from Design Guide). AC: each matches the guide's recipe measured in the dev page.
- **F0-08 · App shell + navigation + shared props** — M — Blocked by F0-05 — `AppShell`, `ContextualSidebar`, mobile bottom bar, `navigation.ts`, `HandleInertiaRequests` per 01 §8, `usePermissions` from props, `useFlash`, `useEcho` (Reverb), `NotificationsPanel`, global search UI (stub endpoint). AC: shell renders with rail filtered by permissions; flash becomes toast; Echo receives a broadcast in a feature test (`Event::fake` + assertion) and a manual dev check.
- **F0-09 · Identity: migrations, models, seeders, permissions config** — M — Blocked by F0-03 — 02 §02 tables; `permissions.php` single source; roles+grants seeder; `PermissionResolver` cached; `permission:` middleware; policies base. AC: seeder idempotent; resolver tests (global, project override, super-admin).
- **F0-10 · Identity: login + OTP + remembered devices + password reset + profile** — L — Blocked by F0-08, F0-09 — pages Login/VerifyCode/Forgot/Reset/Profile, `one_time_codes` (Access table created here; Access module owns it later — put migration in Access with order 07 but ship in this ticket), device cookie, rate limits, `StaffLoginCodeMail` sent sync, API keys UI, Sanctum `POST /auth/token`. AC: Playwright journey login→code→home; device remembered skips code; lockout after 5 wrong codes.
- **F0-11 · Admin: users & roles pages** — M — Blocked by F0-10 — `/admin/users` (Table, create/edit/deactivate, role), `/admin/roles` (matrix editor, compare up to 3, duplicate), `/admin/settings` (settings table editor with typed fields). AC: non-admin gets 403; matrix save updates resolver cache.
- **F0-12 · Ai + Integrations plumbing** — M — Blocked by F0-03 — `GeminiClient` with prompt files, `ai_reviews`/`ai_usage`, `GoogleOAuth` (per-user + app mailbox), `GmailProvider` (MIME builder ported + tests with fixtures), `GoogleCalendarClient`, `GoogleDriveClient`, GCS signed URLs. AC: MIME output byte-compared to fixtures; OAuth callback stores encrypted tokens; token refresh unit test.

## Phase 1 — Crm + Work core — ~3 weeks

- **W1-01 · Crm migrations/models/factories/seeders** (taxonomies `lead_sources`, `project_sources` with legacy option lists) — M — Blocked by F0-09.
- **W1-02 · Contact actions + policies + API** — M — Blocked by W1-01 — Create/Update/Convert/Merge/Lost/Enquiries/public intake. AC per 03 §3.3 tests.
- **W1-03 · Client Board page + Contact detail + Leads pipeline + Campaigns** — L — Blocked by W1-02, F0-07 — pages per 04 §5; detail tabs use contracts with placeholder empties for Mail/Invoices/Vault until those modules land. AC: board groups by stage/owner as in mock; kanban drag changes pipeline_status.
- **W1-04 · Work migrations/models/factories/seeders** (task_types) — M — Blocked by W1-01.
- **W1-05 · Project actions + policies + API** — L — Blocked by W1-04 — 03 §3.4 project/member/contact actions; `ProjectTabProvider` contract. AC: create adds support milestone + manager member; policy matrix tests.
- **W1-06 · Milestone + Task actions + time entries + plan + checklist** — L — Blocked by W1-05 — full task lifecycle, milestone lifecycle w/ approvals, `TaskCompleted` etc. AC: 03 §3.4 tests; start pauses siblings; time sums.
- **W1-07 · Scope items, Deliverables + reviews, Standups, Meetings** — L — Blocked by W1-06, F0-12 — incl. `DeliverableReadyMail` (uses Comms template later → in R1 Phase 1 send plain mailable, switch in C2-08). AC: deliverable approval requires all primary contacts; meeting creates Calendar event (mocked client).
- **W1-08 · Projects index + create/settings pages** — M — Blocked by W1-05, F0-07 — BoardTable grouped by status, filters, page header actions.
- **W1-09 · Project detail page + tabs (Overview, Tasks, Milestones, Scope, Deliverables, Team, Documents, Standups, Meetings, Settings)** — L — Blocked by W1-07, W1-08 — stat tiles, task list/kanban, milestone phases (portal-visible toggle), task SidePanel route. AC: every tab renders with empty states; permissions hide actions.
- **W1-10 · Home (Today + At a glance) + My tasks + board** — L — Blocked by W1-06, F0-07 — `WorkspaceQuery`, `/home`, `/tasks`, `/tasks/board`, daily plan (reorder, carry-over), checklist. AC: "needs you" ordering test; plan reorder persists.
- **W1-11 · Global search + notifications wiring** — M — Blocked by W1-09 — `GlobalSearch` across Project/Contact/Task (+Thread/Invoice later), notification centre backed by database notifications, `TaskAssignedNotification`. AC: permission-aware results test.
- **W1-12 · External API v1 for extension/tracker (Work subset)** — M — Blocked by W1-06 — `tasks/active`, quick create, status, notes, time; api-key middleware. AC: Scribe docs generated; contract tests match legacy response shapes (copy from legacy `ExternalApiController` responses).

## Phase 2 — Comms (mail) — ~4 weeks

- **C2-01 · Comms migrations/models/factories** — M — Blocked by W1-04.
- **C2-02 · Message state machine + ComposeMessage/SaveDraft/SubmitMessage + recipients** — L — Blocked by C2-01 — table-driven transition tests; regression guard.
- **C2-03 · Ingestion: `PollMailboxJob`, `IngestInboundMessage`, threading strategies, sender→contact, screening, attachments** — L — Blocked by C2-02, F0-12 — fixtures from legacy `inbox:body-samples` taxonomy. AC: 4 threading strategies tested; dedupe; unknown sender → screening; body normalised to `body_format=text`.
- **C2-04 · Rendering + sending: `MessageRenderer` (markdown/html/blocks/template → branded layout), quote-at-send, CID images, `SendMessageJob`, per-recipient ids, scheduled send, sent-from-Gmail-UI ingest** — L — Blocked by C2-03 — AC: rendered HTML snapshot tests; body unchanged after send; scheduled message sends at time (time-travel test).
- **C2-05 · Approval pipeline: AI outbound check, approve/return/edit-approve, auto-send flag, 30-min manual override rule, approvals notifications** — L — Blocked by C2-04, F0-12 — AC: flag off → pending_approval; held → pending; approve sends; cannot approve own without approve_all; return requires reason ≥10.
- **C2-06 · Reply clock + counters + `ThreadListQuery` + `ThreadDetailQuery` with redaction + read markers** — L — Blocked by C2-03 — AC: cutover setting; private/screening redaction per permission; pagination by thread.
- **C2-07 · Inbox API v1 (all endpoints 03 §3.5) + bulk + bin/restore + Gmail copy trash + tracking pixel (signed)** — L — Blocked by C2-05, C2-06.
- **C2-08 · Templates + placeholder registry + preview + admin pages; switch Work mailables to templates (`deliverables-for-approval`, `deliverable-approval-reminder`, `invoice-notification`, `review-request`, `monthly-seo-report`)** — M — Blocked by C2-04 — AC: magic link minted only at final send; preview never writes.
- **C2-09 · Inbox desktop page (port `features/inbox` desktop to new API; newest mock `OZee CRM Inbox.dc.html` deltas applied per 09)** — L — Blocked by C2-07, F0-08.
- **C2-10 · Inbox mobile layout (port)** — M — Blocked by C2-09.
- **C2-11 · Compose: template mode, custom (LetterEditor markdown), blocks builder (EmailBlocks mock), attachments via `useUpload`, greeting picker, schedule options, recipient picker (candidates/keys), private toggle, saved drafts** — L — Blocked by C2-09.
- **C2-12 · AI extras: summarise thread, draft reply, task suggestion, inbound screening; AI settings page** — M — Blocked by C2-05 — AC: all default off; null-safe.
- **C2-13 · Project Mail tab + Contact Mail tab (via contracts) + inbox rail badge + SLA breach notification** — M — Blocked by C2-09.

## Phase 3 — Finance — ~4 weeks

- **M3-01 · Finance migrations/models/factories/seeders (ledger categories from legacy transaction_types, catalogue)** — M — Blocked by W1-04.
- **M3-02 · Services + service milestones + catalogue admin** — M — Blocked by M3-01.
- **M3-03 · Invoices: actions, items uniqueness, approvals, payments, PDF/HTML view, send via template** — L — Blocked by M3-02, C2-08.
- **M3-04 · Bills: submit (staff), approval flows (multi-step), payout snapshot, payments, contract-limit rule** — L — Blocked by M3-01, F0-03.
- **M3-05 · Budgets + Proposals/Contracts (staff side: shortlist/accept/reject/complete)** — M — Blocked by M3-04.
- **M3-06 · Payments, ledger entries, FX snapshots, project profitability query** — M — Blocked by M3-03, M3-04.
- **M3-07 · Xero: OAuth, tenants, accounts/payment-services sync, contact push, invoice push/pull, bill push, payments, webhook (HMAC, idempotent), `xero_links`** — L — Blocked by M3-06 — AC: HTTP fakes; idempotency.
- **M3-08 · Stripe apps + webhooks + subscriptions/payments; Airwallex import + reconciliation** — M — Blocked by M3-06.
- **M3-09 · Finance pages: dashboard (stat tiles + OzeeChart), invoices list/detail, bills list/detail (approval UI), proposals, ledger, reconciliation, catalogue/xero/stripe admin, project Money tab** — L — Blocked by M3-07, F0-07 — AC: every route permission-gated; numbers match fixture.
- **M3-10 · Charts port `OzeeChart`** — M — Blocked by F0-05 — can run in parallel earlier.

## Phase 4 — Access + Portals — ~3 weeks

- **A4-01 · Access module: access links, one-time codes, sessions, throttles, auth attempts, vault (+ shares, unlock, extension resolve), admin pages (`/admin/access-links`, `/admin/vault`)** — L — Blocked by F0-10, W1-05.
- **P4-02 · Supplier portal: entry `/p/{id}/{code}`, OTP sign-in, projects, project (phases, proposals, bills), profile + payout methods, sign-out** — L — Blocked by A4-01, M3-05 — port `features/portal` to new API. AC: Playwright journey.
- **P4-03 · Client OS: login (link + PIN), home, product plan, approvals (deliverable review), documents, invoices (+ Stripe pay link when enabled), vault, announcements, SEO reports (with `seo_reports` table + upload API + OzeeChart)** — L — Blocked by A4-01, W1-07, M3-03 — AC: Playwright journey; redaction: client sees only client-visible comments/attachments.
- **P4-04 · Staff-side link issuing UI (project Share, Client link modal), Contact Vault tab, project Vault tab** — M — Blocked by A4-01.

## Phase 5 — Hardening — ~2 weeks
- **H5-01 · Security review checklist (08 §5) + rate limits + signed URLs audit** — M.
- **H5-02 · Performance: N+1 sweep (Laravel Debugbar/`preventLazyLoading` in tests), indexes verified with `EXPLAIN` on the 12 hot queries (thread list, project list, home, board, invoices), counters cache, Reverb load test** — M.
- **H5-03 · Accessibility pass (WCAG AA) on shell, inbox, portal** — M.
- **H5-04 · Playwright smoke suite (6 journeys: login, create project→task→complete, inbox reply→approve→sent (Gmail faked), invoice→payment, supplier proposal→bill, client review)** — M.
- **H5-05 · Ops: supervisor queue workers, scheduler, backups, Pulse/health, error tracking** — S.

## Phase 6 — Data migration — ~2 weeks (see 06)
- **D6-01 · `_legacy-export/` profiling queries against live DB** (row counts, value distributions for tables absent from the dump). 
- **D6-02 · Migration framework**: `php artisan legacy:migrate {step} --since= --dry-run` reading legacy DB via a second connection `legacy`, id map tables `migration_map(entity, legacy_id, v2_id)`, chunked, resumable, logs per step.
- **D6-03..D6-14 · One ticket per mapping step in 06 §4** (identity, contacts, projects, work, comments, attachments, terms, comms, finance, access, archive tables, verification).
- **D6-15 · Rehearsal 1 on a copy** → fix → **Rehearsal 2** → sign-off report.

## Phase 7 — Cutover & legacy freeze — 1 week
- **X7-01 · Freeze plan**: legacy read-only mode (middleware returning 503 for writes except inbox polling stop), final delta migration, DNS/URL switch, Gmail mailbox poll cursor set to cutover time, external consumers repointed to `/api/v1` (extension/tracker builds), old URLs redirect map (`/inbox/beta`→`/inbox`, `/projects/public/{slug}/{code}`→`/p/…`, `/client/dashboard/{token}`→`/c/{token}`).
- **X7-02 · Post-cutover monitoring 72h**, rollback = repoint DNS to legacy (legacy DB untouched).

## Release 2 backlog (not scheduled): 07-deferred-and-dropped.md

## Addendum (v1.1) — tickets added from the design export (09 §5)
Phase 0: **F0-03b** tenancy (`workspaces`, `workspace_id` on all tables via `BelongsToWorkspace` trait + global scope, arch test that every business model uses it) — M, blocked by F0-03. **F0-09b** actions catalogue + three-ceiling `Permissions::can(user, action, business|project)` + guards (approval/re-auth `sudo` middleware/logged/reason/ceiling) + tier blocks — L, blocked by F0-09. **F0-11b** Teams (CRUD, members with team roles, clients served, modules, lead-manages) — M. **F0-11c** Audit log viewer + CSV export — S.
Phase 1: **W1-01b** Businesses + business_contacts + business switcher; default business per migrated client — M. **W1-03b** Client Board (staff view: feed, board with Requests/Waiting-on-client, deliverables, emails, tickets, compose modal ×5) — L, blocked by W1-09, C2-13 (emails tab lands with C2). **W1-06b** Service packs scaffolding, deliverable versions, contact reach — M. **W1-13** Domains registry: providers, domains, DNS records with guarded MX/NS/SPF/DMARC (approval by level ≥3, previous value kept 90 days), team reach, expiry flags, admin pages — L.
Phase 3: **M3-03b** invoices raised from payment terms (presets, labels, `Invoiced` lock, repricing rules) — M. **M3-04b** bill coding (Xero account/tax), approval chain by AUD size, ready-to-sync/synced states, bank feed matching with part payments and "Not reconciled" — M.
Phase 4: **A4-05** access requests (approve/send back with ceiling + tier check), ghost sessions (read-only default, 15-min elevation with reason, owner email, event log, banner), re-auth middleware — L. **P4-03a** Client OS login (passwordless steps, Google), Home (role heroes, feed query), Announcements — L. **P4-03b** Agency hub = Client Board client view + tickets + documents + invoices + vault — L. **P4-03c** Results / SEO reports (overview + monthly + question modal, OzeeChart) — M.
Admin console screens (28) map to: F0-11 (users/people, roles matrix & compare & role editor), F0-11b (teams), F0-11c (audit), W1-01b (clients access, businesses), W1-08/W1-09 (projects list + 12-tab project), M3-09 (finance hub + bill detail + catalogue), W1-13 (domains + providers), A4-05 (access requests, ghost). Platform screens (workspaces, plans, platform team) → R2 S-07.
R2 "Client OS" programme (not scheduled): S-01 Signatures (templates, brand kit, employees, studio, Gmail push, HTML export), S-02 Enquiries + SMS auto-reply, S-03 Scheduling/booking, S-04 Domain & DNS provider APIs (Cloudflare/Namecheap), S-05 client-side Jobs & invoicing, S-06 Social planner, S-07 Platform layer (workspaces, plans, white-label, support access), S-08 Automation & AI.


## Addendum (v1.2) — design-system build tickets (see 11 §9)
**F0-04b** layer lint + breakpoints + `useViewport` + `/dev/ds` harness (viewport/theme switcher, `ds:snap` baselines) — M. **F0-07** split into **F0-07a** shell/layout patterns (AppShell, ContextualSidebar, PageHeader, MasterDetail, SidePanel, Sheet/PushScreen, BottomTabBar, FormLayout, FilterBar→Sheet) and **F0-07b** data patterns (StatGrid, HeroCard, InsightWash, ListRow, BoardTable, DataTable with card mode, Kanban with phone mode, SelectionTray, Timeline, ApprovalBar, EmptyState) — both L. **C2-09 + C2-10** become one ticket: single inbox page tree using the recipes. Every UI ticket in this file inherits checklists 11 §7/§8 (phone/tablet/desktop screenshots required in the PR).


## Addendum (v1.5) — Automation programme
The Automation module has its own ticket plan in `automation/A07-phases-and-tickets.md` (phases A0–A5, ~7 weeks). Dependencies on this plan: A0 (event bus, event publishing, action catalogue, model catalogue) starts with Phase 1 and must be done by end of Phase 2; A1–A3 run alongside Phases 3–4; A4 (migration of the 19 live workflows) runs with Phase 6; cutover (Phase 7) requires AU-MIG-04 sign-off. Add to Phase 1/2 tickets: every Action dispatches its domain event through `DomainEventBus` (AU-00-02).
