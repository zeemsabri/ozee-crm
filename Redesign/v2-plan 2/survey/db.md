**No seeder creates any `category_sets` or `categories`.** Yet `user_availabilities` has three FK columns pointing at `categories` (`did_not_show_up_reason_category_id`, `was_late_reason_category_id`, `left_early_reason_category_id`) and `user_availabilities`' accessors depend on those categories also being attached via `categorizables`. **The attendance-reason vocabulary must be created by hand in production; it has no reproducible definition anywhere in the repo.** This is the single largest gap in the seeded reference data.

### Domain 16 — smells / decisions needed

| # | Smell |
|---|---|
| 16.1 | **Three tagging systems**: `tags`+`taggables` (morph), `task_tag` (dead pivot), `leads.tags` (comma string). |
| 16.2 | **`task_tag` is a confirmed dead table** — zero references outside its own migration. |
| 16.3 | **Two taxonomy systems**: `tags`/`taggables` (flat, global, free-form) and `category_sets`/`categories`/`categorizables` (grouped, bindable, controlled). They overlap in purpose, and `Category` is itself `Taggable` with a `tag_name` accessor — the boundary is being blurred in the UI rather than resolved. |
| 16.4 | **`category_set_bindings.model_type` stores model FQCNs as data**, unconstrained and not in the morph map. Renaming a model class silently unbinds its category sets. |
| 16.5 | **`categories`/`category_sets` have no seeder**, but `user_availabilities` has three FKs into `categories`. Reference data exists only in production. |
| 16.6 | **`taggables` has no timestamps; `categorizables` does.** The two morph pivots for the two taxonomy systems disagree. |
| 16.7 | **`Tag::morphedByMany` list is out of sync with the `Taggable` trait users**: `ShareableResource` uses the trait but has no reverse relation; `Document` has a reverse relation but does not use the trait. |
| 16.8 | **`Taggable::syncTags()` expects pre-resolved integer IDs**, delegating name→ID creation to an HTTP middleware. Tag creation is therefore impossible outside the HTTP layer (jobs, console commands, workflows). |
| 16.9 | `tags` lost `created_by_user_id` but `User::createdTags()` still references it (see 1.3). |
| 16.10 | A category set with zero bindings is implicitly global — an absence of rows encoding a permission. |

---

## Domain 17 — Notifications, activity log, interactions, SEO reports

### `notifications`

**No model.** Migration: `2025_08_02_115520`
`id` **uuid PK**, `type` string, `notifiable_id`/`notifiable_type` morphs, `data` **text** (not json), `read_at` timestamp null, timestamps.

Standard Laravel notifications table. Accessed directly via `DB::table('notifications')` in three places. `EmailObserver` marks notifications read by querying `$user->unreadNotifications->where('type', EmailApprovalRequired::class)->where('data.email.id', $email->id)` — **a dot-path filter on a `text` column**, done in PHP after loading all unread notifications.

Notifiables: `User` (Notifiable trait), `Client` (Notifiable trait + `routeNotificationForMail()`).

### `activity_log`

**No model** (Spatie package model). Migrations: `2025_08_05_062934`, `2025_08_05_062935`, `2025_08_05_062936`, `2026_04_05_091228`
`id` bigIncrements, `log_name` string null (indexed), `description` text, `subject_id`/`subject_type` nullableMorphs (**`subject_id` changed to `varchar(191)`** by `2026_04_05_091228`), `event` string null, `causer_id`/`causer_type` nullableMorphs (**`causer_id` also `varchar(191)`**), `properties` json null, `batch_uuid` uuid null, timestamps.
Table name and connection come from `config/activitylog.php`.

**`LogsActivity` users:** `Bill`, `Icon`, `Component`, `ProjectExpendable`, `Task`, `User`, `Wireframe`, `WireframeVersion`.

`Task::calculateTotalTimeSpent()` **queries `activity_log` and walks status-change entries to derive elapsed time**, and this feeds `$appends['total_time_spent']`. `User::onlineActivityLogs()` filters `log_name = 'online_status'`. `ProjectExpendable::accept/reject/shortlist/complete()` write `withProperties(['reason'=>…,'status'=>…])`.

**The activity log is load-bearing application state, not just an audit trail.** Truncating it changes reported task durations.

### `user_interactions`

Model: `app/Models/UserInteraction.php` · Migrations: `2025_08_08_203300`, `2026_08_19_100000`
`id`, `user_id` FK cascade, `interactable_id`/`interactable_type` morphs, `interaction_type` string ("e.g. 'read','viewed','approved'"), timestamps.
Indexes: `(interactable_id, interactable_type)`, unique `user_interaction_unique (user_id, interactable_id, interactable_type, interaction_type)`, `user_interactions_lookup_idx (user_id, interaction_type, interactable_type, interactable_id)`.
**Relations:** `belongsTo` User; `morphTo` `interactable`. Scope `ofType`.
**Morph targets:** `Email`, `ChatMessage`, `NoticeBoard`.
Written implicitly by `ChatMessage::booted()`.

### `seo_reports`

Model: `app/Models/SeoReport.php` · Migration: `2025_07_31_112512`
`id`, `project_id` ubigint indexed + FK to projects (**no `onDelete`**), `report_date` date (cast date), `data` **json NOT NULL** (cast array), timestamps.
**Relations:** `belongsTo` Project.
**No unique on `(project_id, report_date)`.** The entire report is one opaque JSON blob; no metric is queryable.

### Domain 17 — smells / decisions needed

| # | Smell |
|---|---|
| 17.1 | **`notifications.data` is `text`, not `json`** — the `EmailObserver` filters it by dot-path in PHP after loading every unread row for every notified user. |
| 17.2 | **`notifications` has no model** despite being queried directly by `DB::table()`. |
| 17.3 | **`activity_log` is application state, not audit** — `Task::$appends['total_time_spent']` derives task duration by replaying status-change log rows on every serialisation. Purging the log silently changes business data. |
| 17.4 | **`activity_log.subject_id`/`causer_id` were widened to `varchar(191)`** to accommodate the UUID-keyed `client_vault_credentials` and string-keyed `stripe_payouts` — three PK types now share one polymorphic column, and the index is on a 191-char string. |
| 17.5 | **Three read/interaction-tracking mechanisms**: `user_interactions` (morph, users), `client_deliverable_interactions` (four timestamps, clients), `emails.read_at` (column). |
| 17.6 | **`user_interactions.interaction_type` is a free string** with the vocabulary documented only in a migration comment. |
| 17.7 | **`seo_reports.data` is a fully opaque JSON blob** with no unique on `(project_id, report_date)` — duplicate reports per day are allowed and nothing inside is queryable. |
| 17.8 | **`seo_reports.project_id` FK has no `onDelete`** while `projects` is soft-deletable — force-delete will fail. |
| 17.9 | Notifications are the only UUID-PK table besides `client_vault_credentials`; combined with `stripe_payouts` (string PK) the app has **four PK strategies**. |

---

## Domain 18 — Framework / infrastructure tables

None of these have models; all are vendor-standard.

| Table | Migration | Notes |
|---|---|---|
| `cache`, `cache_locks` | `0001_01_01_000001` | `key` PK, `value` mediumText, `expiration` int |
| `jobs` | `0001_01_01_000002` | `queue` indexed, `payload` longText, `attempts` tinyint |
| `job_batches` | `0001_01_01_000002` | string PK |
| `failed_jobs` | `0001_01_01_000002` | `uuid` unique |
| `sessions` | `0001_01_01_000000` | string PK, `user_id` indexed (no FK), `payload` longText |
| `password_reset_tokens` | `0001_01_01_000000` | `email` PK |
| `personal_access_tokens` | `2023_07_14_203602` | Sanctum; `morphs('tokenable')`, `token(64)` unique |
| `notifications` | `2025_08_02_115520` | see §17 |
| `activity_log` | `2025_08_05_062934` + 3 | Spatie; see §17 |
| `pulse_values`, `pulse_entries`, `pulse_aggregates` | `2026_05_01_213531` | Laravel Pulse; driver-conditional `key_hash` (`char(16) binary virtualAs unhex(md5(key))` on MySQL, `uuid storedAs` on pgsql, plain string on sqlite). Guarded by a `shouldRun()` check. |

**Note:** the Pulse migration is the only place the schema branches on driver at *creation* time; `2026_04_28_120000_make_vault_credential_expiry_nullable.php` branches at *alter* time. Everything else assumes MySQL (four raw `ALTER TABLE … MODIFY COLUMN` statements on `emails.status` and `users.user_type`, plus `jsonb` columns on `projects.integrations` and `tasks.additional_info` which are a **Postgres type used in a MySQL-targeted schema** — Laravel maps `jsonb` to `json` on MySQL, so this is a portability smell rather than a break).

---

## Appendix A — Complete polymorphic relation inventory

| Morph name | Columns | Table(s) holding it | Declared targets | Notes |
|---|---|---|---|---|
| `taggable` | `taggable_id`, `taggable_type` | `taggables` | Task, Project, Document, Email, Milestone, ProjectNote, Resource, Client (+ ShareableResource via trait, no reverse) | no timestamps; composite PK |
| `categorizable` | `categorizable_id`, `categorizable_type` | `categorizables` | Client, Email, User, UserAvailability | has timestamps; unique + index |
| `commentable` | `commentable_id`, `commentable_type` | `comments` | Conversation, Resource | |
| `noteable` | `noteable_id`, `noteable_type` | `project_notes` | Task, Milestone, Deliverable, Document, User | nullable |
| `creator` | `creator_id`, `creator_type` | `project_notes`, `tasks` | User, Client | coexists with `project_notes.user_id` |
| `fileable` | `fileable_id`, `fileable_type` | `files` | Bill, Email, ChatMessage, Task, Project, ProjectExpendable, Transaction, Invoice | + denormalised `project_id` |
| `resourceable` | `resourceable_id`, `resourceable_type` | `resources` | Project | single target |
| `expendable` | `expendable_id`, `expendable_type` | `project_expendables` | Project, Milestone, Task | two-valued in practice |
| `pointable` | `pointable_id`, `pointable_type` | `points_ledgers` | Kudo, ProjectNote, **WeeklyStreak (no table)** | |
| `interactable` | `interactable_id`, `interactable_type` | `user_interactions` | Email, ChatMessage, NoticeBoard | |
| `conversable` | `conversable_id`, `conversable_type` | `conversations` | Client, Lead | backfilled from dropped `client_id` |
| `sender` | `sender_id`, `sender_type` | `emails` | User, Client, Lead | **FK dropped**; default `'App\Models\User'` |
| `presentable` | `presentable_id`, `presentable_type` | `presentations` | Lead, Client | |
| `referencable` | `referencable_id`, `referencable_type` | `contexts` | Email, ProjectNote, Task | |
| `linkable` | `linkable_id`, `linkable_type` | `contexts` | Client, Lead, User | **second morph on the same row** |
| `scheduledItem` | `scheduled_item_id`, `scheduled_item_type` | `schedules` | Task, Workflow, Email | **camelCase relation name vs snake_case columns** |
| `topicable` | `topicable_id`, `topicable_type` | `telegram_topics` | undocumented | `nullableMorphs` |
| `telegramable` | `telegramable_id`, `telegramable_type` | `telegram_accounts` | User, Client | duplicates `*.telegram_chat_id` |
| `approvable` | `approvable_id`, `approvable_type` | `approval_instances` | **Bill only** | `approval_flows.approvable_type` is a bare type |
| `notifiable` | `notifiable_id`, `notifiable_type` | `notifications` | User, Client | |
| `subject` / `causer` | `subject_id`/`_type`, `causer_id`/`_type` | `activity_log` | 8 LogsActivity models / User | ids widened to `varchar(191)` |
| `tokenable` | `tokenable_id`, `tokenable_type` | `personal_access_tokens` | User | Sanctum |

**21 distinct morph names.** Only 3 model classes are aliased in `Relation::morphMap`, and it is not enforced.

**Hand-rolled pseudo-morphs (not real morphs):**
- `bonus_transactions.source_type` (enum) + `source_id` (string)
- `tasks.source` (string) + `source_id` (string)
- `execution_logs.triggering_object_id` (string, **no type column**)
- `user_otps.identifier` (string, no type)
- `otp_verifications.project_token` (string ref to `projects.public_share_token`)
- `placeholder_definitions.source_model` + `source_attribute` (FQCN + attribute name)
- `category_set_bindings.model_type` (FQCN)
- `approval_flows.approvable_type` (FQCN, no id)
- `invoice_items.milestone_key` (string key into a JSON document)

---

## Appendix B — Complete enum inventory

### PHP backed enums (`app/Enums/`)

| Enum | File | Cases | Cast onto |
|---|---|---|---|
| `BillStatus` | `BillStatus.php` | `pending_approval`, `approved`, `paid`, `partial_paid`, `void` | `bills.status` (string col) |
| `BonusTransactionStatus` | `BonusTransactionStatus.php` | `pending`, `approved`, `rejected`, `processed` | `bonus_transactions.status` (DB enum ✓ matches) |
| `EmailAiStatus` | `EmailAiStatus.php` | `queued`, `checking`, `approved`, `held`, `failed` (+ `isPending()`, `needsHuman()`) | `emails.ai_status` (varchar 24) |
| `EmailDraftStatus` | `EmailDraftStatus.php` | `queued`, `writing`, `ready`, `failed` (+ `isWorking()`) | `emails.ai_draft_status`, **`conversations.ai_summary_status`** |
| `EmailStatus` | `EmailStatus.php` | `pending_approval_received`, `pending_approval`, **`rejected_received`**, `rejected`, `received`, `sent`, `draft`, `unknown`, `pending`, `approved`, `auto_send`, `delayed`, `saved` | `emails.status` — **`rejected_received` is NOT in the DB enum** |
| `EmailType` | `EmailType.php` | `received`, `sent` | `emails.type` (string col) |
| `LeadStatus` | `LeadStatus.php` | `new`, `hot_incoming`, `hot_outgoing`, `processing`, `contacted`, `generation_failed`, `sequence_completed`, `converted`, `lost`, `qualified`, `outreach_sent` | `leads.status` (string col). **Case names mix StudlyCase and SCREAMING_CASE.** |
| `MilestoneStatus` | `MilestoneStatus.php` | `pending`, `approved`, `rejected`, `completed`, `in progress`, `overdue`, `canceled`, `expired`, `pending approval`, `pending review` | `milestones.status` via `MilestoneStatusCast` — **DB enum is `('Not Started','In Progress','Completed','Overdue')`, zero overlap** |
| `ProjectExpendableStatus` | `ProjectExpendableStatus.php` | `Pending Approval`, `Shortlisted`, `Accepted`, `Rejected`, `Completed` | `project_expendables.status`. **Only Title-Case-with-spaces enum in the app.** |
| `ProjectStatus` | `ProjectStatus.php` | `active`, `planned`, `in_progress`, `on_hold`, `completed`, `canceled` | `projects.status` — **DB enum is `('active','completed','on_hold','archived')`; `planned`/`in_progress`/`canceled` unpersistable, `archived` unrepresentable** |
| `SubtaskStatus` | `SubtaskStatus.php` | `To Do`, `In Progress`, `Done`, `Blocked` | `subtasks.status` via `MilestoneStatusCast` (matches DB enum ✓) |
| `TaskStatus` | `TaskStatus.php` | `To Do`, `In Progress`, **`Paused`**, `Done`, `Blocked`, `Archived` | `tasks.status` via `MilestoneStatusCast` — **`Paused` is not in the DB enum** |
| `TelegramTopicType` | `TelegramTopicType.php` | `general`, `client`, `custom`, `proxy` (+ `label()`) | `telegram_topics.type` (string col) |

### DB `enum()` columns with **no** PHP enum

| Table.column | Values |
|---|---|
| `projects.payment_type` | `one_off`, `monthly` |
| `tasks.priority` | `low`, `medium`, `high` |
| `transactions.type` | `income`, `expense`, `bonus` |
| `users.user_type` | `employee`, `contractor`, `guest`, `supplier` |
| `resources.type` | `link`, `file` |
| `bonus_configurations.type` | `bonus`, `penalty` |
| `bonus_configurations.amountType` | `percentage`, `fixed`, `all_related_bonus` |
| `bonus_configurations.appliesTo` | `task`, `milestone`, `standup`, `late_task`, `late_milestone`, `standup_missed` |
| `bonus_transactions.type` | `bonus`, `penalty` |
| `bonus_transactions.source_type` | `standup`, `task`, `milestone`, `manual`, `other` |
| `points_ledgers.status` | `pending`, `refunded`, `cancelled`, `paid`, `consumed`, `rejected` |
| `project_notes.type` | `standup`, `kudos`, `general` |
| `wireframe_versions.status` | `draft`, `published` |
| `login_attempts.attempt_type` | `pin`, `magic_link` |

### Free-string status columns with **no** enum and **no** DB constraint

`invoices.status`, `deliverables.status`, `deliverables.type`, `project_deliverables.status`, `project_services.status`, `project_services.frequency`, `project_services.service_tracking_type`, `project_services.enquiry_status`, `daily_tasks.status`, `execution_logs.status`, `approval_instances.status`, `approval_instance_steps.status`, `approval_flow_steps.approver_type`, `approval_instance_steps.approver_type`, `user_productivities.status`, `xero_connections.status`, `xero_payment_services.status`, `stripe_subscriptions.status`, `stripe_subscription_payments.status`, `stripe_payouts.status`, `external_email_logs.status`, `prompts.status`, `client_vault_credentials.source`, `invoice_comments.action`, `magic_links.type`, `chat_messages.type`, `chat_messages.source`, `email_apps.delivery_mode`, `roles.type`, `presentation_user.role`, `project_client.role`, `shareable_resources.type`, `user_activities.category`, `user_activities.idle_state`, `user_interactions.interaction_type`, `tasks.source`, `bills.currency`/`transactions.currency`/etc., `clients.xero_sync_mode`, `workflow_steps.step_type`, `workflows.trigger_event`, `invoices.line_amount_type`, `user_otps.context`.

**~44 free-string state columns vs 13 enums.**

---

## Appendix C — Complete JSON column inventory

| Table.column | Type | Cast | Contents |
|---|---|---|---|
| `projects.services` | json | array | list of service names — **duplicates `project_services`** |
| `projects.service_details` | json | array | untyped |
| `projects.documents` | json | array | file descriptors — **duplicates `documents` table, shadows the relation** |
| `projects.departments` | json | **none** | orphan column |
| `projects.integrations` | jsonb | array | integration key→config; `setIntegration()`/`getIntegration()` |
| `projects.data` | json | array | fully untyped catch-all |
| `users.checklist` | json | array | per-user checklist |
| `users.notes` | json | array | **duplicates `user_notes` + morph notes** |
| `users.metadata` | json | array | keys defined by `user_metadata_keys` |
| `users.online_data` | json | array | `{last_status_change, source, status, reason}` |
| `clients` | — | — | *(no JSON)* |
| `leads.metadata` | json | array | untyped |
| `leads.email_thread_history` | json | array | **duplicates `conversations`/`emails`** |
| `campaigns.services_offered` | json | array | |
| `emails.template_data` | json | array | **double-encoded**; holds placeholders ∪ `blocks` ∪ raw inbound headers |
| `emails.draft_meta` | json | array | |
| `conversations.ai_task_suggestion` | json | array | |
| `tasks.details` | json | array | |
| `tasks.additional_info` | jsonb | **none** | returns a raw string |
| `project_notes.context` | **string** | **array** | **type mismatch** |
| `project_deliverables.details` | json | array | checklist items |
| `magic_links.whitelist` | json | array | allowed emails/domains |
| `user_otps.meta` | json | array | |
| `chat_messages.meta_data` | json | array | email refs / extras |
| `contexts.meta_data` | json | array | |
| `content_blocks.content_data` | json NOT NULL | array | **schema varies by `block_type` (13 shapes)** |
| `wireframe_versions.data` | json NOT NULL | array | whole wireframe document |
| `components.definition` | json NOT NULL | array | component default props |
| `seo_reports.data` | json NOT NULL | array | **entire report, nothing queryable** |
| `prompts.generation_config` | json | array | |
| `prompts.template_variables` | json | array | |
| `prompts.response_variables` | json | array | |
| `prompts.response_json_template` | json | array | |
| `workflow_steps.step_config` | json | array | **contains `_parent_id`, used as an FK** |
| `workflow_steps.condition_rules` | json | array | |
| `execution_logs.input_context` | json | array | |
| `execution_logs.raw_output` | json | array | |
| `execution_logs.parsed_output` | json | array | |
| `execution_logs.token_usage` | json | **none** | |
| `bonus_transactions.metadata` | json | array | |
| `points_ledgers.meta` | json | array | |
| `crm_services.default_payment_breakdown` | json | array | |
| `project_services.payment_breakdown` | json | array | **payment milestones — keyed into by `invoice_items.milestone_key`** |
| `project_services.enquiry_meta` | json | array | foreign system payload |
| `invoices.xero_payment_service_ids` | json | array | **array of FKs — should be a pivot** |
| `invoice_comments.meta` | json | array | |
| `bill_payment_details.details` | text | **encrypted:array** | bank details |
| `stripe_configurations.settings` | json | array | |
| `stripe_subscriptions.metadata` | json | array | |
| `stripe_payouts.breakdown` | json | array | per-charge detail — **the only link to underlying transactions** |
| `xero_connections.metadata` | json | array | |
| `xero_payment_services.raw_payload` | json | array | |
| `external_email_logs.request_payload` | json | array | |
| `external_email_logs.response_payload` | json | array | |
| `user_activities.metadata` | json | array | |
| `user_availabilities.time_slots` | json | array | planned windows |
| `user_productivities.stats_json` | json | array | |
| `user_productivities.tasks_json` | json | array | |
| `user_productivities.timeline_json` | json | array | |
| `user_productivities.ai_report_json` | json | array | **not reliably valid JSON — hand-parsed** |
| `user_productivities.accuracy_json` | json | array | |
| `user_productivities.feedback_json` | json | array | |
| `activity_log.properties` | json | — | Spatie |
| `notifications.data` | **text** | — | filtered by dot-path in PHP |

**~62 JSON columns.** Three have no cast (`tasks.additional_info`, `execution_logs.token_usage`, `projects.departments`); one is cast but is not a JSON column (`project_notes.context`); one is double-encoded (`emails.template_data`).

**JSON that should be relational, ranked by risk:**
1. `project_services.payment_breakdown` ← `invoice_items.milestone_key` (double-billing risk, unique constraint dropped)
2. `emails.template_data` (three payloads, double-encoded)
3. `projects.documents` (shadows the `documents` relation)
4. `projects.services` / `service_details` (duplicates `project_services`)
5. `invoices.xero_payment_service_ids` (array of FKs)
6. `workflow_steps.step_config._parent_id` (tree structure as a JSON path)
7. `leads.email_thread_history` (duplicates `conversations`/`emails`)
8. `stripe_payouts.breakdown` (only reconciliation path)
9. `users.notes` / `users.checklist` / `users.metadata`
10. `seo_reports.data`, `user_productivities.*_json` (report blobs — arguably legitimately JSON)

---

## Appendix D — Seeded reference data

Run order per `database/seeders/DatabaseSeeder.php`:
`RolePermissionSeeder` → `FinancialPermissionSeeder` → `UserSeeder` → `TransactionTypeSeeder` → `PresentationSeeder`.

**Not called by `DatabaseSeeder`** (must be run manually): `PointsSystemPermissionsSeeder`, `CeoDashboardPermissionSeeder`, `ExtensionBypassPermissionSeeder`, `AddEditCredentialPermissionSeeder`, `EmailTemplateSeeder`, `IconSeeder`, `ComponentSeeder`, `DeliverableSeeder`.

### Roles (`database/seeders/RolePermissionSeeder.php`) — 10 rows

| name | slug | type |
|---|---|---|
| Super Admin | `super-admin` | application |
| Manager | `manager` | application |
| Employee | `employee` | application |
| Contractor | `contractor` | application |
| Client Admin | `client-admin` | client |
| Client User | `client-user` | client |
| Client Viewer | `client-viewer` | client |
| Project Manager | `project-manager` | project |
| Project Member | `project-member` | project |
| Project Viewer | `project-viewer` | project |

**Note:** the six `client`/`project` roles are created but **assigned no permissions by any seeder**. Only the four `application` roles get permission grants.

### Permissions — `RolePermissionSeeder`, by category

**Client Management (7):** `view_clients`, `create_clients`, `edit_clients`, `delete_clients`, `view_client_financial`, `view_client_contacts`, `view_all_credentials`
**User Management (5):** `view_users`, `create_users`, `edit_users`, `delete_users`, `assign_roles`
**Project Management (18):** `view_projects`, `create_projects`, `edit_projects`, `delete_projects`, `view_project_financial`, `view_project_transactions`, `manage_projects`, `view_project_documents`, `upload_project_documents`, `manage_project_expenses`, `manage_project_income`, `edit_invoice`, `manage_project_services_and_payments`, `view_project_services_and_payments`, `add_project_notes`, `view_project_notes`, `manage_project_users`, `view_project_users`, `manage_project_clients`, `view_project_clients`
**Email Management (5):** `compose_emails`, `view_emails`, `approve_emails`, `view_rejected_emails`, `resubmit_emails`
**Role & Permission Management (6):** `manage_roles`, `view_roles`, `create_roles`, `edit_roles`, `delete_roles`, `assign_permissions`
**Dashboard (2):** `view_dashboard`, `view_statistics`

Grants: Super Admin → all. Manager → 33 listed slugs. Employee → 14. Contractor → 11.
Finally: `User::where('email','info@ozeeweb.com.au')->first()` is assigned Super Admin.

### Permissions — `FinancialPermissionSeeder` (21, category "Financial Management")

`add_expendables`, `view_project_expendable`, `manage_project_expendable`, `view_project_expendables_proposals`, `view_project_expendables_planning`, `view_project_expendables_execution`, `view_project_expendables_financials`, `view_project_bills`, `create_project_bills`, `edit_project_bills`, `approve_project_bills`, `void_project_bills`, `delete_project_bills`, `restore_project_bills`, `link_xero_contractors`, `view_project_invoices`, `create_project_invoices`, `edit_project_invoices`, `approve_project_invoices`, `void_project_invoices`, `configure_xero_settings`

### Permissions — `PointsSystemPermissionsSeeder` (16, category "Points System")

`view_project_tiers`, `create_project_tiers`, `edit_project_tiers`, `delete_project_tiers`, `assign_project_tiers`, `view_kudos`, `create_kudos`, `approve_kudos`, `view_own_kudos`, `view_all_kudos`, `view_points_ledger`, `manage_points`, `view_monthly_budgets`, `manage_monthly_budgets`, `view_monthly_points`, `view_own_points`

### Permissions — one-off seeders and data migrations

| Slug | Name | Category | Source | Granted to |
|---|---|---|---|---|
| `edit_credential` | Unlock and edit vault credentials | Client Management | `AddEditCredentialPermissionSeeder.php` | super-admin, manager |
| `view-ceo-dashboard` | View CEO Dashboard | **`dashboard`** (lowercase) | `CeoDashboardPermissionSeeder.php` | super-admin |
| `by_pass_extension` | By Pass Extension | User Management | `ExtensionBypassPermissionSeeder.php` | super-admin |
| `view_all_credentials` | View All Credentials | Client Management | migration `2026_04_28_100200` | super-admin, manager |
| `email_custom_recipients` | Email Custom Recipients | Email Management | migration `2026_08_20_120000` | super-admin |

**Permissions referenced in code but seeded nowhere:** `approve_received_emails` (`Email::APPROVE_RECEIVED_EMAILS_PERMISSION`), `view_all_projects` (used in `Project::getUsersWithProjectAccess()`).

### Users (`database/seeders/UserSeeder.php`) — 4 rows, all password `password`

| email | name | role |
|---|---|---|
| `info@ozeeweb.com.au` | Zeeshan Sabri | super-admin |
| `usama@ezysoft.solutions` | Usama Saeed | manager |
| `dev1@ezysoft.solutions` | Employee User | employee |
| `dev2@ezysoft.solutions` | Contractor User | contractor |

### Transaction types (`database/seeders/TransactionTypeSeeder.php`) — 2 rows

`Initial Agreed Amount` (slug `initial-agreed-amount`), `Out of Scope` (slug `out-of-scope`).
Migration `2025_08_12_051700` **also** creates `Initial Agreed Amount` and backfills every transaction with `transaction_id IS NULL`.

### Email templates (`database/seeders/EmailTemplateSeeder.php`) — 5 rows, all `is_default = true`

| slug | subject | placeholders extracted |
|---|---|---|
| `deliverables-for-approval` | New blog posts are ready for your review: `{{project_name}}` | `project_name`, `client_name`, `magic_link` |
| `deliverable-approval-reminder` | Reminder: Action required for `{{project_name}}` deliverables | `client_name`, `project_name`, `due_date`, `magic_link` |
| `monthly-seo-report` | Your Monthly SEO Report for `{{report_month}}` is Ready! | `client_name`, `report_month`, `magic_link` |
| `invoice-notification` | New Invoice for `{{project_name}}` is ready: #`{{invoice_number}}` | `client_name`, `invoice_number`, `project_name`, `total_amount`, `due_date`, `invoice_link` |
| `review-request` | How did we do? Leave us a review! | `client_name`, `project_name`, `review_link`, `sender_name` |

`placeholder_definitions` rows are **auto-created** by `extractPlaceholders()` (regex `/\{\{(\s*[\w\.]+\s*)\}\}/`) and `syncPlaceholders()`. So the placeholder catalogue is a **side effect of template text**, not curated: the seeder produces ~12 distinct placeholder names, all with `source_model`/`source_attribute` NULL and all four booleans false. **Nothing sets `is_dynamic` for `magic_link`** despite the column's comment naming that exact case.

### Presentations (`database/seeders/PresentationSeeder.php`)

Creates a placeholder Lead (`jane@example.com`, "John Doe", "Future Co") if none exists, then builds template `Presentation` rows (`is_template = true`, `type = proposal`) from `config/presentation_templates.php`, deleting and re-creating slides each run.
Slide `template_name` values: `IntroCover`, `TwoColumnWithImageLeft`, `TwoColumnWithImageRight`, `TwoColumnWithChart`, `ThreeColumn`, `FourColumn`, `FourStepProcess`, `ProjectDetails`, `CallToAction`.
Block types: `heading`, `paragraph`, `slogan`, `button`, `image`, `image_block`, `feature_card`, `feature_list`, `details_list`, `list_with_icons`, `step_card`, `pricing_table`, `timeline_table`.
Slide titles observed: `Full Digital Transformation Proposal`, `Introduction`, `Challenge One/Two/Three`, `Inconsistent Posting`, `Lack of Authority`, `Inefficient Operations`, `Dated Visual Design`, `Core Service Detail`, `Content Creation`, `Link Building`, `Commitment to Quality`, `Innovative & Scalable Solutions`, `Expected Timeline`, `Estimated Timeline`, `Investment`, `Call to Action`.

### Icons / Components (`IconSeeder.php`, `ComponentSeeder.php`)

Both read from `config/components.php` (481 config lines). `icons` keyed on `name`; `components` **upserted on `type`** while the table's unique is on `name`.
`components.category` values: `CallToAction`, `Content`, `Footer`, `Form`, **`Forms`**, `Layout`, `Navigation`, `Typography`.

### Reference data that exists ONLY in config, not in any table

| Concept | File | Consumed as |
|---|---|---|
| Activity categories (`productive`, `development`, …) with `label`, `color`, `patterns[]` | `config/activity_categories.php` | written as a free string into `user_activities.category` |
| Project deliverable types (`website_development`, `social_media_management`, `seo_services`, `branding_design`, `email_marketing`, `content_creation`, …) with `key`, `name`, `description`, `checklistItemPlaceholder` | `config/project_deliverable_types.php` | **`project_deliverables` has no column to store the key** |
| Presentation templates & slide blueprints | `config/presentation_templates.php` | seeded into `presentations`/`slides`/`content_blocks` |
| Icons & components | `config/components.php` | seeded into `icons`/`components` |
| Value sets (Task.status → `TaskStatus` enum, Task.task_type_id → `TaskType` model, Milestone.status → `MilestoneStatus` enum) | `config/value_sets.php` | `ValueDictionaryRegistry` for the workflow builder |
| Inbox, portal, branding, forms, options, public API, Xero config | `config/inbox.php`, `portal.php`, `branding.php`, `forms.php`, `options.php`, `public_api.php`, `xero.php` | various |

### Reference data with NO source at all

| Concept | Needed by | Status |
|---|---|---|
| `category_sets` / `categories` | `user_availabilities.*_reason_category_id` (3 FKs), `HasCategories` on Client/Email/User/UserAvailability | **No seeder, no config, no migration. Production-only.** |
| `task_types` | `tasks.task_type_id` is **NOT NULL** | No seeder. Only `TaskType::firstOrCreate(['name'=>'New'])` in `ProjectNote::createTaskFromComment()`. **A fresh install cannot create a task.** |
| `crm_services` | `project_services.crm_service_id` NOT NULL | No seeder. |
| `project_tiers` | `projects.project_tier_id` | No seeder. |
| `bonus_configurations` / groups | the whole bonus engine | No seeder. |
| `email_apps` | `magic_links.email_app_id`, external mail | No seeder. |
| `approval_flows` / steps | Bill approval | No seeder. |
| `prompts` / `workflows` / `workflow_steps` | automation | No seeder (partial data in `config/automation.php` and repo-root `workflow_steps.json`). |
| `stripe_configurations` / `xero_connections` | integrations | No seeder (expected — runtime OAuth). |
| `currency_rates` | all money conversion | No seeder; fetched at runtime. |

**`DeliverableSeeder.php` (157 lines) is not called by `DatabaseSeeder`** and creates demo deliverables only.

---

## Appendix E — Tables with no model / models with no table / dead tables

### Tables with no model — framework (11)

`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `password_reset_tokens`, `personal_access_tokens`, `notifications`, `activity_log`, `pulse_values`/`pulse_entries`/`pulse_aggregates`

### Pivot tables with no model (15)

| Table | Payload columns beyond the two FKs | Model warranted? |
|---|---|---|
| `role_permission` | — (no timestamps) | no |
| `project_user` | `role_id`, timestamps | **yes** — role is meaningful |
| `project_client` | `role` (string), `role_id`, timestamps | **yes** |
| `task_tag` | timestamps | **dead — delete** |
| `taggables` | — (no timestamps) | no |
| `categorizables` | timestamps | no |
| `email_template_placeholder` | — | no (a broken stub model exists) |
| `email_app_template` | timestamps | no |
| `campaign_shareable_resource` | timestamps | no |
| `bonus_configuration_group_items` | **`sort_order`**, timestamps | **yes** — payload unreachable |
| `project_bonus_configuration_group` | timestamps | no |
| `presentation_user` | **`role`**, timestamps | **yes** — payload unreachable |
| `project_google_chat_members` | timestamps | no |
| `client_vault_credential_user` | **`granted_by`**, timestamps | **yes** — audit fact unreachable |
| `meeting_attendees` | `notification_sent`, `notification_sent_at` | *has* `MeetingAttendee` ✓ |

### Models with no table (2)

| Model | File | Expected table | Reality |
|---|---|---|---|
| `WeeklyStreak` | `app/Models/WeeklyStreak.php` | `weekly_streaks` | **Does not exist.** Instantiated unsaved in `app/Console/Commands/CalculateWeeklyStreakBonusCommand.php:90` purely to produce a `pointable_type`/`pointable_id` pair. `points_ledgers` rows point at it. `resources/js/Pages/Ledger/Index.vue:141` exposes it as a filter. **Eager-loading `pointable` on those rows will fail.** |
| `EmailTemplatePlaceholder` | `app/Models/EmailTemplatePlaceholder.php` | `email_template_placeholders` | **Does not exist** (the real table is the singular pivot `email_template_placeholder`). The class body is `// ` — completely empty. Referenced in exactly 1 file (itself). **Delete.** |

### Models sharing a table (1)

`NoticeBoard extends ShareableResource`, both `$table = 'shareable_resources'`, discriminated by the `notice` boolean via two identically-named global scopes that both admit `NULL`.

### Dead / near-dead tables and columns

| Item | Evidence |
|---|---|
| **`task_tag` table** | zero references in `app/`, `routes/`, `resources/` |
| **`projects.departments` column** | not in `$fillable`, not in `$casts`, no query anywhere |
| **`transactions.transaction_id` column** | self-FK, not fillable, no relation method; only used in a backfill `WHERE … IS NULL` |
| **`deliverable_comments` table** | superseded by `project_notes` per `ClientDashboard/ProjectClientAction.php:18` (`// use App\Models\DeliverableComment; // Replaced with ProjectNote`); `Deliverable::comments()` returns ProjectNote morphs. Model + controller + `Client::deliverableComments()` remain. |
| **`magic_links.used` column** | superseded by `uses_count`/`max_uses` |
| **`user_availabilities.reason` column** | superseded by three `*_reason_category_id` FKs; the "refactor" migration never dropped it |
| **`project_client.role` column** | superseded by `role_id`; the parallel `project_user.role` *was* dropped |
| **`project_notes.user_id` column** | superseded by `creator_id`/`creator_type`; both still fillable |
| **`xero_connections.selected_tenant_id`/`_name`** | duplicated by `xero_tenants.is_selected`/`tenant_name` |
| **`users.telegram_chat_id`, `clients.telegram_chat_id`** | duplicated by `telegram_accounts` |
| **`users.google_id`, `google_access_token`, `google_refresh_token`, `google_expires_in`** | **in `$fillable`/`$hidden` but no such columns exist** — superseded by `google_accounts` |
| **`User::createdTags()`** | targets `tags.created_by_user_id`, dropped in `2025_07_29_083617` |

### Lowest-referenced models (candidates for review)

Counted as files in `app/`, `routes/`, `resources/`, `tests/` containing the class name:

`EmailTemplatePlaceholder` (1) · `ApprovalFlowStep`, `ApprovalInstanceStep`, `PresentationMetadata`, `StripePayout`, `UserMetadataKey`, `UserOtp` (2) · `BillPaymentDetail`, `ContentBlock`, `DeliverableComment`, `InvoiceComment`, `LoginAttempt`, `MeetingAttendee`, `SeoReport`, `StripeSubscriptionPayment`, `UserNote`, `UserRememberedDevice`, `WeeklyStreak`, `WireframeVersion`, `XeroPaymentService` (3) · `AirwallexXeroBankMapping`, `CategorySetBinding`, `ClientDeliverableInteraction`, `GoogleAccounts`, `OtpVerification`, `StripeSubscription`, `XeroTenant` (4).

---

## Appendix F — Master smell list, ranked

### Tier 1 — Correctness bugs (fix or the rewrite inherits broken data)

| # | Smell | Location |
|---|---|---|
| F1 | **`emails.to` is `varchar(255)` cast to `array`** — recipient lists silently truncate | `2023_07_15_010838_create_emails_table.php`; `app/Models/Email.php` casts |
| F2 | **`project_notes.context` is `string` cast to `array`** — same class of bug | `2025_07_31_021217`; `app/Models/ProjectNote.php` |
| F3 | **`emails.template_data` is double-encoded** and holds three unrelated payloads | `app/Support/TemplateData.php` (documents it in full) |
| F4 | **`Project::documents` — JSON cast shadows the `documents()` hasMany relation.** Two live file storages; the unifying migration is an empty stub | `app/Models/Project.php`; `2025_07_28_113335` |
| F5 | **`Invoice::getInvoiceNumberAttribute()` shadows the `invoice_number` column** — the persisted Xero number is unreachable | `app/Models/Invoice.php` |
| F6 | **`BonusConfigurationGroup::$appends['configurations']` shadows `bonusConfigurations()`** | `app/Models/BonusConfigurationGroup.php` |
| F7 | **`MonthlyBudget::monthlyPoints()` uses composite-key `hasMany`** — unsupported by Eloquent | `app/Models/MonthlyBudget.php` |
| F8 | **`WeeklyStreak` model has no table**, yet `points_ledgers.pointable_type` points at it | `app/Models/WeeklyStreak.php`; `app/Console/Commands/CalculateWeeklyStreakBonusCommand.php:90` |
| F9 | **`User::createdTags()` targets a dropped column** | `app/Models/User.php`; `2025_07_29_083617` |
| F10 | **`users.$fillable` lists 4 non-existent Google columns** | `app/Models/User.php` |
| F11 | **`emails.$fillable` lists `last_communication_at`/`contacted_at`** (columns on `leads`) | `app/Models/Email.php` |
| F12 | **`projects.status` DB enum ≠ `ProjectStatus` PHP enum** (3 unpersistable cases) | `2023_07_15_010742`; `app/Enums/ProjectStatus.php` |
| F13 | **`tasks.status` cannot store `TaskStatus::Paused`** | `2025_07_22_233500`; `app/Enums/TaskStatus.php` |
| F14 | **`milestones.status` DB enum and `MilestoneStatus` share zero values** | `2025_07_22_233200`; `app/Enums/MilestoneStatus.php`; `app/Casts/MilestoneStatusCast.php` |
| F15 | **`EmailStatus::RejectedReceived = 'rejected_received'` is not in the DB enum** | `app/Enums/EmailStatus.php` vs `2026_08_26_100000` |
| F16 | **`invoice_items` unique on `(project_service_id, milestone_key)` was deliberately dropped** — the same payment milestone can be invoiced repeatedly | `2026_05_18_120000` |
| F17 | **`ShareableResource`/`NoticeBoard` global scopes both admit `notice IS NULL`** — such rows appear in both models | `app/Models/ShareableResource.php`, `app/Models/NoticeBoard.php` |
| F18 | **`schedules` morph named `scheduledItem` against `scheduled_item_*` columns** | `app/Models/Schedule.php`, `Task::schedules()`, `Workflow::schedules()` |
| F19 | **`user_productivities.ai_report_json` is not reliably valid JSON** — parsed with manual string offsets | `app/Models/UserProductivity.php` |
| F20 | **`Presentation::booted()` writes pipeline constants into `presentation_user.role`** (documented `editor|viewer`) | `app/Models/Presentation.php` |
| F21 | **`ComponentSeeder` upserts on `type` while the unique is on `name`** | `database/seeders/ComponentSeeder.php` |
| F22 | **`task_types` has no unique on `name`** but is used with `firstOrCreate` | `2025_07_22_233300`; `app/Models/ProjectNote.php` |
| F23 | **`tasks` has no `project_id`**; a task with `milestone_id = NULL` has no project, yet `Task::addNote()` writes `project_id` | `app/Models/Task.php` |
| F24 | **A fresh install cannot create a Task** — `tasks.task_type_id` is NOT NULL and nothing seeds `task_types` | schema + seeders |
| F25 | **`categories`/`category_sets` have no seeder** but `user_availabilities` has three FKs into `categories` | `2026_05_11_125000` + missing seeder |

### Tier 2 — Duplicated concepts (pick one per pair in the rewrite)

| # | Concept | Duplicates |
|---|---|---|
| F26 | **Comments/notes** | `project_notes` · `comments` · `deliverable_comments` · `invoice_comments` · `user_notes` · `users.notes` JSON |
| F27 | **Files** | `files` · `documents` · `projects.documents` JSON · `deliverables.attachment_path` · `resources.file_id` |
| F28 | **Tagging** | `tags`+`taggables` · `task_tag` (dead) · `leads.tags` string |
| F29 | **Taxonomy** | `tags`/`taggables` · `category_sets`/`categories`/`categorizables` (and `Category` is itself `Taggable`) |
| F30 | **Read/interaction tracking** | `user_interactions` · `client_deliverable_interactions` · `emails.read_at` |
| F31 | **Email records** | `emails`+`conversations` · `external_email_logs` · `leads.email_thread_history` JSON |
| F32 | **Email templates** | `email_templates`+`placeholder_definitions` · `campaigns.email_template` longText · `emails.email_template` slug string |
| F33 | **Task hierarchy** | `subtasks` · `tasks.parent_id` |
| F34 | **Deliverables** | `deliverables` (client approval artefact) · `project_deliverables` (scope checklist) — unrelated tables, near-identical names |
| F35 | **Kudos** | `kudos` table · `project_notes.type='kudos'` |
| F36 | **Telegram identity** | `users.telegram_chat_id`/`clients.telegram_chat_id` · `telegram_accounts` morph |
| F37 | **Telegram link codes** | `users.telegram_link_code` · `clients.telegram_link_code` · `projects.telegram_link_code` |
| F38 | **Portal auth** | `magic_links`(+`temporary_pin`) · `otp_verifications` · `user_otps` · `clients.pin` |
| F39 | **Project services** | `project_services`+`crm_services` · `projects.services`/`service_details` JSON |
| F40 | **Project client** | `projects.client_id` · `project_client` pivot |
| F41 | **Project roles** | `projects.project_manager_id`/`project_admin_id` · `project_user.role_id` |
| F42 | **Xero selected tenant** | `xero_connections.selected_tenant_id`/`_name` · `xero_tenants.is_selected`/`tenant_name` |
| F43 | **Approvals** | `approval_flows`/`instances` (used by `Bill` only) · 8 hand-rolled approval columns elsewhere (`emails.status`+`approved_by`, `deliverables.overall_approved_*`, `project_expendables.status`, `kudos.is_approved`, `bonus_transactions.status`, `tasks.needs_approval`, `resources.requires_approval`, `milestones.approved_at`) |
| F44 | **Chat** | `chat_messages` (internal+Telegram) · `emails`/`conversations` · `project_notes`+Google Chat push · Google Chat string columns on 4 tables |
| F45 | **Author of a message** | `chat_messages.user_id`/`client_id` (two FKs) · `project_notes.creator_*` (morph) — same User/Client choice, two modellings |
| F46 | **Note author** | `project_notes.user_id` · `project_notes.creator_id`/`creator_type` |
| F47 | **Client role on project** | `project_client.role` string · `project_client.role_id` |
| F48 | **Workflow definition** | `workflows`/`workflow_steps` · `config/automation.php` · repo-root `workflow_steps.json` |
| F49 | **Attendance reason** | `user_availabilities.reason` text · three `*_reason_category_id` FKs · the `categorizables` morph (which the accessors actually read) |
| F50 | **Reference data** | `icons`/`components` tables · `config/components.php` (config is authoritative) |

### Tier 3 — Structural / modelling issues

| # | Issue |
|---|---|
| F51 | **`project_expendables` conflates budgets and contractor contracts via `user_id IS NULL`** — two business objects, one table, discriminated by a nullable FK |
| F52 | **`transactions` is five things** (project income, project expense, bonus, bill payment, invoice payment) with a 3-value `type` enum that doesn't capture the split |
| F53 | **`project_notes` is nine things** (note, standup, kudos, task comment, milestone comment, deliverable comment, document comment, user note, wireframe annotation) |
| F54 | **`email_apps` is a sparse two-subtype table** (9 SMTP columns / 4 API columns) discriminated by `delivery_mode` |
| F55 | **`milestones` has five lifecycle date columns**, three renamed by accessors — a state machine as timestamps |
| F56 | **`client_deliverable_interactions` has four mutually-exclusive nullable timestamps** encoding one state |
| F57 | **`workflow_steps` tree lives at `step_config->_parent_id`** — a JSON path used as an FK, with yes/no branching also in JSON |
| F58 | **`invoice_items.milestone_key` keys into `project_services.payment_breakdown` JSON** — a relational child pointing into a JSON document |
| F59 | **`invoices.xero_payment_service_ids` is a JSON array of FKs** — should be a pivot |
| F60 | **`content_blocks.content_data` is a 13-shape polymorphic JSON blob** documented only in config |
| F61 | **`presentation_metadata` is a soft-deletable EAV table with no unique on `(presentation_id, meta_key)`** |
| F62 | **`users.metadata` JSON + `user_metadata_keys` registry** — EAV split across a blob and a schema table |
| F63 | **`placeholder_definitions.source_model`/`source_attribute` store FQCN+attribute as data** — schema-as-data, unprotected by the morph map |
| F64 | **`category_set_bindings.model_type` stores FQCNs as data** |
| F65 | **`approval_flows.approvable_type` is a bare morph type with no id** |
| F66 | **21 morph names, only 3 aliased in `Relation::morphMap`, and it is not enforced** — Task/Workflow/Email have three spellings each in production data |
| F67 | **9 hand-rolled pseudo-morphs** (`bonus_transactions.source_*`, `tasks.source`/`source_id`, `execution_logs.triggering_object_id` with no type, `user_otps.identifier`, `otp_verifications.project_token`, …) |
| F68 | **`contexts` carries two morph pairs on one row** with no valid-combination constraint |
| F69 | **Four PK strategies**: bigint auto-inc (most), uuid (`client_vault_credentials`, `notifications`), string business key (`stripe_payouts`), plus `bonus_configurations.uuid` as a second identity alongside `id` |
| F70 | **`activity_log.subject_id`/`causer_id` widened to `varchar(191)`** to accommodate three PK types in one polymorphic column |
| F71 | **`activity_log` is application state** — `Task::$appends['total_time_spent']` replays status-change rows to derive duration |
| F72 | **`notifications.data` is `text`, filtered by dot-path in PHP** after loading all unread rows |
| F73 | **`user_activities` is an unpartitioned high-volume event log** with `updated_at` and two indexes |
| F74 | **`monthly_points`/`monthly_budgets` are materialised aggregates with no invalidation**; `monthly_budgets` has **no unique on `(year, month)`** |
| F75 | **`projects.profit_margin_percentage` is recomputed by loading all project transactions on every Transaction write** (`app/Observers/TransactionObserver.php`) |
| F76 | **`Bill::$appends['paid_amount']` calls the currency conversion service per transaction on every serialisation** |
| F77 | **`emails.$appends['can_approve','can_open']` run permission checks per serialised row** |
| F78 | **`User::$with = ['role','categories']`** — global eager load on every User query |
| F79 | **`Wireframe::latestVersion()`/`latestDraftVersion()`/`latestPublishedVersion()` return models, not relations** — guaranteed N+1 |
| F80 | **`bill_payment_details` duplicates encrypted bank details per bill** (unique on `bill_id`) rather than per contractor |
| F81 | **`stripe_payouts` has no FK to anything**; `breakdown` JSON is the only reconciliation path |
| F82 | **`stripe_subscriptions.app_id` is not an FK** to `stripe_configurations.app_id`; the subscription↔payment join is on a string business key |
| F83 | **`xero_connections.provider` is UNIQUE** — single-tenancy hard-coded into the schema |
| F84 | **No `xero_accounts` table** despite Xero account codes appearing in four tables as loose strings |
| F85 | **`currency_rates` keeps no history**; `transactions.exchange_rate` was bolted on later for transactions only |
| F86 | **Money units inconsistent**: `transactions.amount decimal(10,2)` vs `bills`/`invoices`/`invoice_items`/`project_expendables` `decimal(15,2)`; Stripe uses `int` minor units on two tables and `decimal(15,2)` on a third |
| F87 | **Currency baked into 11 column names** (`*_pkr` on `monthly_budgets`, `project_tiers`) |
| F88 | **`bonus_transactions` has no currency column** but is compared against PKR budgets |
| F89 | **Three reward currencies** (money / points / PKR) with conversion via `monthly_budgets.points_value_pkr` and no FK tying a ledger row to its pricing budget |
| F90 | **Standups have no table** — they are `project_notes.type='standup'` yet drive the entire bonus engine |
| F91 | **Google Chat has no table** — 6 string columns across 4 tables + a pivot, while Telegram got proper models |
| F92 | **Seven boolean visibility flags across seven tables** with no unified model or defined precedence |
| F93 | **~44 free-string state columns vs 13 enums** |
| F94 | **`bonus_configurations` uses five camelCase columns** in a snake_case schema |
| F95 | **Pivot naming inconsistency**: `bonus_configuration_group_items` (plural+items) vs `project_bonus_configuration_group` (singular) |
| F96 | **`taggables` has no timestamps; `categorizables` does** |
| F97 | **`FileAttachment` model ↔ `files` table name mismatch**; `files.project_id` denormalised alongside the morph |
| F98 | **Migration filename `create_notice_boards_table` creates no table**; `create_project_expandables_table` is misspelled; `create_template_placeholders_table` creates `placeholder_definitions` |
| F99 | **Six models have no `$casts` at all** (`Client`, `Document`, `Resource`, `EmailTemplate`, `PlaceholderDefinition`, `Icon`) — booleans return as ints |
| F100 | **`workflow_steps` and `execution_logs` have `$timestamps = false`**; `workflow_steps` nonetheless has `deleted_at` |
| F101 | **Soft deletes are inconsistent across parent/child pairs**: `conversations` (none) → `emails` (soft); `clients` (none) → `projects` (soft); `milestones` (none) → `tasks` (soft); `wireframes` (none) vs `presentations` (soft) |
| F102 | **Several NOT NULL FKs have no `onDelete`** on soft-deletable parents: `invoices.client_id`, `invoices.project_id`, `execution_logs.workflow_id`, `seo_reports.project_id` |
| F103 | **`jsonb` (a Postgres type) used on `projects.integrations` and `tasks.additional_info`** in an otherwise MySQL-targeted schema (4 raw `ALTER TABLE … MODIFY COLUMN` statements assume MySQL) |
| F104 | **`prompts.model_name` hard-codes `'gemini-2.5-flash-preview-05-20'` as a DB column default** |
| F105 | **`schedules.recurrence_pattern` (a cron string) is indexed** — a meaningless index costing writes |
| F106 | **Permissions are seeded by 7 seeders + 2 data migrations**, with no single source of truth; `permissions.category` has a `Dashboard`/`dashboard` casing split; `approve_received_emails` and `view_all_projects` are used in code but seeded nowhere |
| F107 | **The 6 `client`/`project` roles are created with zero permissions** by any seeder |
| F108 | **`placeholder_definitions` rows are a side effect of regex-scanning template text**, never curated; `magic_link` is not flagged `is_dynamic` despite the column comment naming that case |
| F109 | **`config/project_deliverable_types.php` defines a type vocabulary that `project_deliverables` has no column to store** |
| F110 | **`Taggable::syncTags()` requires pre-resolved IDs**, delegating name→ID creation to HTTP middleware — tag creation is impossible from jobs, console commands, or workflows |

---

## Recommended shape for the rewrite (one-paragraph summary per cluster)

1. **Identity & access.** One `users` table (staff), one `contacts` table (clients + leads unified — they already share `conversable`, `presentable`, `telegramable` morphs and `clients.lead_id` links them). One `roles` table per scope, or one polymorphic `role_assignments`. One `access_grants` table replacing `magic_links`, and one hashed `one_time_codes` table replacing `otp_verifications` + `user_otps` + `magic_links.temporary_pin` + `clients.pin`.

2. **Work.** `projects` → `milestones` → `tasks` with a real `tasks.project_id`. Delete `subtasks` (use `tasks.parent_id`). Rename `deliverables` → `client_artifacts` and `project_deliverables` → `scope_items`. One `task_statuses` vocabulary, enum-backed, matching the DB constraint.

3. **Communication.** One `messages` table with a `channel` discriminator (email / telegram / google_chat / internal), one `threads` table, one `participants` morph. Delete `external_email_logs` (fold into `messages` + a `delivery_attempts` child). Split `emails.template_data` into `rendered_placeholders` (json), `composer_document` (json), and `inbound_headers` (json) — three columns, single-encoded.

4. **Annotations.** One `comments` table: morph `commentable`, morph `author` (User|Contact), `parent_id`, `body`, optional `anchor` (json, replacing `project_notes.context`), `kind` enum. Retire `project_notes`, `deliverable_comments`, `invoice_comments`, `user_notes`, `users.notes`.

5. **Files.** One `attachments` table: morph `attachable`, no denormalised `project_id`, `expires_at`, storage-driver columns. Retire `documents`, `projects.documents`, `deliverables.attachment_path`.

6. **Taxonomy.** One system. Given `category_sets`/`categories` is the newer, controlled, bindable one, keep it and migrate `tags` into a global set. Delete `task_tag` and `leads.tags`.

7. **Money.** `invoices` / `invoice_items` / `bills` / `payments` (replacing the five-purpose `transactions`), all `decimal(19,4)` minor-unit-agnostic with an explicit `currency` char(3) uppercase and a historical `exchange_rates` table. Promote `project_services.payment_breakdown` to a real `service_milestones` table so `invoice_items.service_milestone_id` can be a genuine FK with a restored uniqueness guarantee.

8. **Approvals.** Adopt `approval_flows`/`approval_instances` for all eight approval workflows, or delete it and standardise on a `status` + `approved_by` + `approved_at` triple.

9. **Gamification.** `points_ledger` with a real `pointable` morph (delete `WeeklyStreak`; make streaks a first-class table or an `origin` enum). One `standups` table. Drop the `_pkr` column-name suffixes in favour of a `currency` column.

10. **Telemetry.** Partition or externalise `user_activities`. Make `activity_log` a pure audit trail and store task duration in a real `task_time_entries` table rather than deriving it from log replay.