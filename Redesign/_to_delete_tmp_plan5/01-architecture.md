# 01 — Architecture, repo layout, conventions

## 1. Stack (pinned)

| Layer | Choice | Notes |
|---|---|---|
| PHP | 8.3 | `declare(strict_types=1)` in every file |
| Framework | Laravel 12 (slim skeleton, `bootstrap/app.php`) | no `app/Http/Kernel.php` |
| DB | MySQL 8.0+ (`ozee-crm-v2`), `strict => true`, `utf8mb4_0900_ai_ci` | InnoDB; CHECK constraints are enforced on 8.0.16+ |
| Queue | `database` driver in dev, Redis in prod; supervised `queue:work` (Supervisor config in `deploy/`) | queues: `default`, `mail`, `ai`, `sync` |
| Cache/session | Redis in prod, file in dev | |
| Realtime | Laravel Reverb + Echo (React hook) | private user channel + project channels |
| Auth | Laravel session guard (`web`) for staff; Sanctum tokens for API consumers; custom `portal` and `client` guards (see Access module) | |
| Frontend | Inertia 2 + React 19 + TypeScript 5 + Vite 6 | `@inertiajs/react` |
| Data fetching | Inertia props for page loads; `@tanstack/react-query` for in-page API lists (inbox, pickers) | |
| Tables | `@tanstack/react-table` (headless) wrapped by `ds/Table` | |
| Forms | Inertia `useForm` for page forms; `react-hook-form` + `zod` for complex modals | |
| Styling | CSS custom properties (tokens) + CSS Modules; `ds/` primitives use inline styles | **no Tailwind** |
| Icons | Vibe glyph SVGs in `public/ozee-ds/icons/` via `ds/Icon` | no Heroicons/Lucide |
| Rich text | Markdown editor from legacy `inbox/LetterEditor` (ported) | no CKEditor/Quill/TipTap |
| Charts | decided in `09-design-guide-alignment.md` (default: `recharts` wrapped in `ds/charts/*`) | |
| Files | Google Cloud Storage (`spatie/laravel-google-cloud-storage`), disk `gcs`, private; local disk in dev | |
| AI | Gemini via one `Modules\Ai\GeminiClient` | |
| Accounting | Xero (hand-rolled `Http::` client kept — drop `dcblogdev/laravel-xero`) | |
| Payments | Stripe `stripe-php`, Airwallex `Http::` | |
| Audit | `spatie/laravel-activitylog` as pure audit (never application state) | |
| API docs | `knuckleswtf/scribe` for `/api/v1` | |
| Testing | Pest 3 (feature + unit), Playwright for 6 smoke journeys | |
| Lint | Pint (PSR-12 + Laravel preset), Larastan level 6, ESLint + `_adherence.oxlintrc.json` rules ported, Prettier | |

## 2. Module list and release scope

| Module | Namespace | R1? | Owns |
|---|---|---|---|
| **Platform** | `Modules\Platform` | R1 | shared kernel: base classes, enums helpers, money, `Attachment`, `Comment`, `Taxonomy/Term`, `ApprovalRequest`, `ActivityLog` config, notifications base, `ExchangeRate`, settings, search index contract |
| **Identity** | `Modules\Identity` | R1 | `User`, `Role`, `Permission`, staff login + OTP + remembered devices, API keys, Sanctum tokens, Google account link (OAuth for Gmail/Calendar), profile |
| **Crm** | `Modules\Crm` | R1 | `Contact` (lead/client), pipeline, campaigns (minimal), enquiries, contact notes via Comment, Xero contact link |
| **Work** | `Modules\Work` | R1 | `Project`, `ProjectMember`, `ProjectContact`, `Milestone`, `Task`, `TaskType`, `TaskTimeEntry`, `DailyPlanItem`, `ScopeItem`, `Deliverable` (+ client review), `Standup`, `Meeting` (Google Calendar), workspace queries |
| **Comms** | `Modules\Comms` | R1 | `Mailbox`, `Thread`, `Message`, `MessageRecipient`, `EmailTemplate`, placeholder registry, blocks, Gmail gateway, ingestion, send pipeline, approval pipeline, AI reviews, reply clock, inbox queries |
| **Finance** | `Modules\Finance` | R1 | `ServiceCatalogueItem`, `ProjectService`, `ServiceMilestone`, `Invoice`, `InvoiceItem`, `Bill`, `Payment`, `LedgerEntry`, `Budget`, `Proposal`/`Contract`, `PayoutMethod`, Xero, Stripe, Airwallex, P&L |
| **Access** | `Modules\Access` | R1 | `AccessLink`, `OneTimeCode`, portal sessions, client sessions, `VaultCredential`, throttling, external API tokens |
| **Portal** | `Modules\Portal` | R1 | supplier portal (projects, proposals, bills, profile, payout methods) and client portal (dashboard, deliverable review, documents, invoices, vault) — HTTP + pages only; entities live in owning modules |
| **Ai** | `Modules\Ai` | R1 | one Gemini client, prompt files, token/cost accounting, kill switches |
| **Integrations** | `Modules\Integrations` | R1 | Google (Gmail, Calendar, Drive) client + OAuth plumbing, GCS helpers — infrastructure only |
| Automation | `Modules\Automation` | R2 | workflows, steps, execution logs, prompt library UI |
| Recognition | `Modules\Recognition` | R2 | points, kudos, bonus configs, leaderboard, budgets |
| Presence | `Modules\Presence` | R2 | availability, attendance, activity telemetry, productivity, live status |
| Studio | `Modules\Studio` | R2 | presentations, wireframes, resource library, notice board |
| Chat | `Modules\Chat` | R2 | project chat, Telegram, Google Chat |

## 3. Module dependency graph (HARD RULE)

```
Platform  ← Identity ← Crm ← Work ← Comms ← Finance ← Access ← Portal
   ↑           ↑                           ↑
   Ai      Integrations ───────────────────┘   (Ai and Integrations depend only on Platform/Identity)
```

- A module may `use` models, contracts, enums and events of modules **to its left** (lower). Never to its right.
- Upward communication is by **domain events** (`Modules\Work\Events\TaskCompleted`) that higher modules listen to, or by **contracts** bound in the container (`Modules\Platform\Contracts\CommentableSubject`).
- Eloquent relations across modules are allowed only **downward** (e.g. `Work\Task::project()` → `Work\Project`, `Comms\Thread::project()` → `Work\Project`, `Work\Project::client()` → `Crm\Contact`). A lower module never declares a relation to a higher module's model (no `Project::threads()` in Work — Comms provides a `ThreadRepository::forProject()` instead; Portal composes everything).
- Larastan rule + a Pest architecture test (`tests/Architecture/ModuleBoundariesTest.php`, using `pestphp/pest-plugin-arch`) enforce this: `arch('Work does not use Comms')->expect('Modules\Work')->not->toUse('Modules\Comms')`, one expectation per forbidden edge.

## 4. Repository layout

```
ozee-crm-v2/
├── app/                      # framework glue only: Providers/AppServiceProvider, Http/Middleware (HandleInertiaRequests), Console kernel bits
├── bootstrap/app.php         # registers module providers from config/modules.php
├── config/
│   ├── modules.php           # ordered list of module ServiceProviders
│   ├── ozee.php              # brand, default currency, feature flags (see §9)
│   └── ...
├── database/
│   ├── migrations/           # ONLY framework tables (sessions, cache, jobs, notifications, activity_log, personal_access_tokens)
│   └── seeders/DatabaseSeeder.php  # calls each module's seeder in dependency order
├── modules/
│   └── <Name>/
│       ├── Providers/<Name>ServiceProvider.php
│       ├── Config/<name>.php            # optional, merged as config('<name>')
│       ├── Database/Migrations/         # module tables, prefixed with module order (see §6)
│       ├── Database/Seeders/<Name>Seeder.php
│       ├── Database/Factories/
│       ├── Models/
│       ├── Enums/
│       ├── Actions/                     # one class per use-case: CreateTask, SubmitMessage… (invokable, `handle()`)
│       ├── Queries/                     # read-side query classes for lists/pages (return DTOs/arrays for Inertia)
│       ├── Services/                    # stateful collaborators (GmailGateway, XeroClient)
│       ├── Events/  Listeners/  Jobs/  Notifications/  Mail/
│       ├── Policies/
│       ├── Http/Controllers/  Http/Requests/  Http/Resources/  Http/Middleware/
│       ├── Routes/web.php  Routes/api.php   # loaded by the provider with module prefix + middleware
│       ├── Resources/views/             # blade for emails only
│       └── Tests/Feature/  Tests/Unit/  # Pest, autoloaded via composer `autoload-dev`
├── resources/
│   ├── css/ozee-ds/          # tokens (copied), theme-dark, animations, index.css
│   └── js/
│       ├── app.tsx           # Inertia entry
│       ├── ds/               # design system primitives (ported)
│       ├── shell/            # AppShell, PortalShell, navigation data
│       ├── hooks/  lib/      # usePermissions, useEcho, useToasts, format, api
│       ├── features/<module>/  # feature components, per module
│       ├── pages/<Module>/<Page>.tsx  # Inertia page components (thin)
│       └── types/            # generated TS types for Inertia props (see 04-frontend §7)
├── public/ozee-ds/icons/*.svg, public/ozee-ds/ozee-logo-sm.png
├── docs/decisions/ADR-0001-*.md
├── deploy/ (supervisor, nginx, cron)
├── _legacy-export/           # read-only extraction scripts run against the legacy DB (Phase 6)
└── tests/Architecture/
```

Composer autoload: `"Modules\\": "modules/"`. Each module provider:

```php
final class WorkServiceProvider extends ModuleServiceProvider   // base in Modules\Platform\Support
{
    protected string $module = 'Work';       // base class: loads Routes/web.php (middleware web,auth,verified), Routes/api.php (prefix api/v1, middleware auth:sanctum), migrations, views, config, policies map, event subscribers
    protected array $policies = [Project::class => ProjectPolicy::class, Task::class => TaskPolicy::class, ...];
    protected array $listen = [ ... ];       // event => [listeners]
}
```

`config/modules.php` lists providers in dependency order; `bootstrap/app.php` registers them with `->withProviders(config('modules.providers'))`.

## 5. Coding conventions (backend)

1. **Controllers are thin**: validate (FormRequest) → authorize (policy) → call an Action or Query → return `Inertia::render` / `JsonResource` / redirect with flash. Max ~30 lines per method. No queries in controllers, **no logic in route files** (legacy had ~250 lines of it).
2. **Actions** (`Modules\X\Actions\DoThing`): single public `handle(...)` method, typed arguments, return the entity or a DTO, wrap multi-table writes in `DB::transaction`, dispatch domain events after commit (`DB::afterCommit`).
3. **Queries** (`Modules\X\Queries\ProjectListQuery`): build Eloquent/`DB` queries for pages; return paginators or arrays shaped exactly like the page's props type. Every list query accepts a `Filters` DTO and is covered by a unit test with a seeded dataset.
4. **Models**: `$fillable` explicit (never `$guarded = []`), every JSON column cast, every enum column cast to a backed enum, `$casts` for every date/bool. **No `$appends` that run queries.** No business logic beyond scopes, relations, simple accessors. No model events that do network calls — use listeners on domain events with `ShouldQueue`.
5. **Enums**: `modules/<X>/Enums/*.php`, backed by string, with `label()`; DB column `VARCHAR(32)` + CHECK constraint generated from the enum in the migration (`Schema::table` + `DB::statement("ALTER TABLE t ADD CONSTRAINT chk_t_status CHECK (status IN (...))")`). Helper: `Modules\Platform\Support\Schema::checkEnum($table, $column, EnumClass)`.
6. **Morphs**: register every morph class in `Relation::enforceMorphMap([...])` in `PlatformServiceProvider` with **short snake aliases** (`'project' => Project::class`). Enforced = unknown class throws.
7. **Policies** everywhere; `Gate::before` super-admin bypass; project-scoped permission via `Modules\Identity\Support\Permissions::forProject($user, $project)`. Never `$user->role->slug === '...'` checks in code (use permission slugs; role slugs only in seeders).
8. **Money**: `Modules\Platform\Support\Money` value object (`amount` as string decimal, `currency`); DB `DECIMAL(19,4)`. Convert only via `ExchangeRateService::convert(Money, to, on: Carbon)`.
9. **Dates**: store UTC; `users.timezone`, `contacts.timezone`, `projects.timezone` for display only; API returns ISO-8601 with offset.
10. **IDs in URLs**: numeric ids for internal pages; `public_id` (ULID) for anything reachable by non-staff (access links, share links, portal projects).
11. **Errors**: throw `Modules\Platform\Exceptions\DomainException` subclasses with a stable `code`; the handler maps to 422 (JSON) or flash (Inertia).
12. **Logging**: structured context (`['module' => 'comms', 'thread_id' => …]`); never log message bodies or credentials.
13. **Jobs**: idempotent, `ShouldBeUnique` where a duplicate is harmful, `tries`/`backoff`/`timeout` set explicitly, `failed()` records the failure on the entity (e.g. `messages.last_error`).
14. **Tests per ticket**: Feature test for every HTTP endpoint (happy + forbidden + validation), Unit test for every Action with business rules, Query tests with factories. Use `RefreshDatabase`.

## 6. Migration naming and order

Module migrations are timestamped `2026_09_<module-order><nn>_<table>.php` so the whole set applies in dependency order on a fresh DB:

| Order | Module | Range |
|---|---|---|
| 00 | framework (`database/migrations`) | `2026_09_00xx` |
| 01 | Platform | `2026_09_01xx` |
| 02 | Identity | `2026_09_02xx` |
| 03 | Crm | `2026_09_03xx` |
| 04 | Work | `2026_09_04xx` |
| 05 | Comms | `2026_09_05xx` |
| 06 | Finance | `2026_09_06xx` |
| 07 | Access | `2026_09_07xx` |
| 08 | Ai / Integrations | `2026_09_08xx` |
| 09 | legacy archive tables (`legacy_*`) | `2026_09_09xx` |

Rules: one table per migration; FKs declared in the table's own migration (targets are always earlier); every FK states `onDelete`; every `deleted_at` parent has children with `cascadeOnDelete` or `nullOnDelete` decided deliberately (table in `02-database-schema.md`). Never `ALTER` in R1 — edit the create migration (the DB is empty until cutover). After cutover, alters only.

## 7. HTTP surface

- **Web (Inertia)**: `modules/<X>/Routes/web.php`, middleware `['web', 'auth', 'verified', 'staff']` for internal; Portal/Access modules define `client` and `portal` guards.
- **API v1** (`/api/v1/...`): JSON only, `auth:sanctum` or `api-key` middleware; used by (a) the React pages for in-page fetches, (b) the Chrome extension, (c) the desktop tracker, (d) third-party apps. Response envelope `{ data, meta }`; errors `{ message, errors?, code }`. Scribe-documented. **Never** Inertia and API sharing a controller method.
- **Webhooks** (`/webhooks/{provider}`): signature-verified, no session, idempotent by provider event id (`webhook_events` table in Platform).
- Route names: `<module>.<entity>.<action>` (`work.projects.show`, `comms.threads.index`, `portal.proposals.store`).

## 8. Shared props (Inertia `HandleInertiaRequests`)

```ts
{
  auth: { user: { id, name, email, avatar_url, timezone, role: {slug,name} } | null,
          permissions: string[],                 // global permission slugs (all for super-admin)
          project_permissions?: Record<number, string[]> }  // only on project pages
  flash: { success?: string, error?: string, info?: string }
  app:   { name, default_currency, brand: {...}, chrome_extension_url, features: Record<string, boolean> }
  counters: { inbox_needs_reply: number, approvals_pending: number, notifications_unread: number }  // cached 30s
  ziggy: {...}   // route() helper
}
```

Permissions come from `Identity\Support\PermissionResolver` (cached per user, invalidated on role/permission change events). This fixes legacy's commented-out `global_permissions`.

## 9. Feature flags (`config/ozee.php` → `features`)

`ai.check_outbound`, `ai.summarise`, `ai.draft_replies`, `mail.sent_ingest`, `mail.auto_send_when_approved`, `xero.enabled`, `stripe.enabled`, `airwallex.enabled`, `portal.supplier`, `portal.client`, `search.global`. All read via `Feature::enabled('ai.check_outbound')` (thin wrapper, env-driven). Defaults: AI flags **false** in production.

## 10. Cross-cutting behaviours (implemented once, in Platform)

| Concern | Implementation |
|---|---|
| Comments | `Platform\Models\Comment`, trait `HasComments` (morph `subject`), `CommentPolicy` delegates to subject policy `view`/`comment` |
| Attachments | `Platform\Models\Attachment`, trait `HasAttachments`, `AttachmentStore` (upload to disk with prefix `<module>/<subject>/<ulid>.<ext>`, thumbnails for images, `expires_at` sweep command `attachments:prune`), signed temporary URLs generated on demand (never in serialisation) |
| Taxonomy | `Taxonomy` + `Term` + `term_assignments` morph pivot; trait `HasTerms`; seeded taxonomies `tags`, `email_categories`, `lead_sources`, `project_sources` |
| Approvals | `ApprovalRequest` (morph subject, `kind`, `status`, requester, decider, reason), `RequestApproval`/`DecideApproval` actions, events `ApprovalDecided`; subject modules react to the event |
| Audit | activitylog on: User, Role, Contact, Project, Milestone, Task, Message(status only), Invoice, Bill, Payment, Proposal, AccessLink, VaultCredential |
| Notifications | Laravel database + broadcast channels; `NotificationCenter` API (`/api/v1/notifications`), React `useNotifications`; mail channel for the 6 mailables in scope |
| Search | `SearchIndexable` contract + `GlobalSearch` query across Project, Contact, Task, Thread, Invoice, Bill, Proposal (MySQL FULLTEXT on `search_text` columns) |
| Settings | `settings` key/value table (typed) for org-wide config editable in Admin (sign-off name/role, SLA minutes, inbox cutover date…) |
| Webhook idempotency | `webhook_events` (provider, event_id unique, payload, processed_at) |
| Sequence numbers | `Platform\Support\Sequence::next('invoice')` backed by `sequences` table for gap-free document numbers |

## 11. Security baseline (must hold at every phase gate)

- No route without middleware; admin pages require a named permission (legacy gap #778–784 in survey/routes.md).
- Tracking pixels are **signed URLs** (`URL::signedRoute`) — never accept an email address from the path.
- Every secret column uses the `encrypted` cast (`google_accounts` tokens, Xero tokens, Stripe keys, SMTP passwords, payout method details).
- No API keys in config files; `.env.example` lists every variable with a comment.
- CSRF for web, Sanctum for API, rate limits: login 5/min, OTP 6/min, portal 120/min, public share 60/min.
- Debug/test routes do not exist (the legacy `/api/playground`, `/receive-test-emails` etc. are dropped).
