# 02 — v2 Database Schema (`ozee-crm-v2`)

Conventions (apply to every table unless stated):
- `id BIGINT UNSIGNED AUTO_INCREMENT PK`, `created_at`/`updated_at` `TIMESTAMP NULL`.
- FK columns are `BIGINT UNSIGNED` with a real constraint; `onDelete` stated per column.
- Enum-like columns are `VARCHAR(32)` + CHECK constraint generated from the PHP enum (D12).
- Money: `amount DECIMAL(19,4)` + `currency CHAR(3)` (ISO 4217, uppercase) (D10).
- JSON columns are `JSON` with a documented shape and a model cast; no JSON column may be used as a foreign key.
- Soft deletes only where the UI has a "restore" or where children must survive (marked `SD`).
- `public_id CHAR(26)` = ULID, unique, for anything exposed to non-staff.
- "Legacy" column = where the data comes from in `06-data-migration.md`. `—` = new in v2.
- Index notation: `IDX(a,b)`, `UNQ(a,b)`, `FT(a)` fulltext.

Total tables in R1: **74** application tables + 8 framework + `legacy_*` archive tables.

---

## 00 Framework (database/migrations)

`sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens`, `personal_access_tokens` (Sanctum), `notifications` (**`data JSON`**, not text; index `(notifiable_type, notifiable_id, read_at)`), `activity_log` (Spatie default; `subject_id`/`causer_id` BIGINT — all v2 PKs are bigint, D11).

---

## 01 Platform

### `settings`
| column | type | notes |
|---|---|---|
| key | VARCHAR(100) PK | e.g. `inbox.sla_minutes`, `inbox.reply_clock_since`, `mail.sign_off_name` |
| value | JSON | typed value |
| updated_by_id | FK users NULL SET NULL | |
Legacy: `config/inbox.php`, `config/branding.php` values that admins should edit.

### `sequences`
`name VARCHAR(50) PK`, `next BIGINT UNSIGNED`. Used for `invoices.number`, `bills.number`. Locked with `lockForUpdate`.

### `webhook_events`
`id`, `provider VARCHAR(32)` (xero|stripe|gmail), `event_id VARCHAR(191)`, `payload JSON`, `received_at`, `processed_at NULL`, `error TEXT NULL`. `UNQ(provider,event_id)`.

### `exchange_rates`
| column | type | notes |
|---|---|---|
| base | CHAR(3) | always `USD` in R1 (provider base) |
| quote | CHAR(3) | |
| rate | DECIMAL(19,8) | |
| as_of | DATE | |
`UNQ(base,quote,as_of)`. Legacy: `currency_rates` (single row per currency, no history → one row dated at migration time).

### `taxonomies`
`id`, `slug VARCHAR(50) UNQ` (`tags`, `email_categories`, `lead_sources`, `project_sources`, `attendance_reasons`(archive only)), `name`, `applies_to JSON` (list of morph aliases; empty = any), `is_system BOOL`.
Legacy: `category_sets` (+ `category_set_bindings.model_type` → `applies_to` aliases).

### `terms`
`id`, `taxonomy_id FK CASCADE`, `name VARCHAR(100)`, `slug VARCHAR(100)`, `color VARCHAR(32) NULL` (board palette token name), `sort INT DEFAULT 0`, `is_active BOOL DEFAULT 1`. `UNQ(taxonomy_id, slug)`.
Legacy: `tags` (→ taxonomy `tags`), `categories` (→ their set's taxonomy), distinct `leads.source`/`projects.source` values (→ `lead_sources`/`project_sources`; the hard-coded `sourceOptions` list in legacy `routes/web.php`).

### `term_assignments`
`term_id FK CASCADE`, `subject_type VARCHAR(32)`, `subject_id BIGINT UNSIGNED`, `created_at`. PK `(term_id, subject_type, subject_id)`, `IDX(subject_type, subject_id)`.
Legacy: `taggables`, `categorizables`, `leads.tags` (split on comma).

### `comments`  (D5)
| column | type | notes |
|---|---|---|
| id | | |
| subject_type / subject_id | VARCHAR(32) / BIGINT | morph: project, task, milestone, deliverable, thread, invoice, bill, proposal, contact, user, attachment |
| project_id | FK projects NULL CASCADE | denormalised for "all comments on project" queries; set by `HasComments` from `subject->projectIdForComments()` |
| author_type / author_id | VARCHAR(32) / BIGINT | morph: user \| contact |
| parent_id | FK comments NULL CASCADE | replies |
| kind | enum `CommentKind`: comment, note, revision_request, meeting_minutes, system | `system` = generated (e.g. "status changed"), never editable |
| visibility | enum `Visibility`: internal, client | client-visible comments show in the client portal |
| body | TEXT | markdown subset (same as message composer) — plaintext at rest (D18) |
| body_text | TEXT | derived plain text for search; `FT(body_text)` |
| anchor | JSON NULL | `{page?, x?, y?, selector?, label?}` for deliverable/wireframe annotations |
| edited_at | TIMESTAMP NULL | |
| deleted_at | SD | |
`IDX(subject_type,subject_id,created_at)`, `IDX(project_id,created_at)`, `IDX(author_type,author_id)`.
Legacy: `project_notes` (type general/comment/note/meeting_minutes → kind; `noteable` → subject; `creator_*`/`user_id` → author; `context` → anchor; `parent_id`), `comments` (Conversation → thread, Resource → archive), `invoice_comments` (action=comment → comment; other actions → activity_log), `user_notes` (subject=user), `deliverable_comments` (dead but migrate), `clients.notes`/`leads.notes` (→ one `note` comment on the contact if non-empty).

### `comment_mentions`
`comment_id FK CASCADE`, `user_id FK CASCADE`, `notified_at NULL`. PK `(comment_id,user_id)`. Legacy: parsed from `@mentions` by `MentionService` (not stored).

### `attachments`  (D6)
| column | type | notes |
|---|---|---|
| id | | |
| public_id | CHAR(26) UNQ | used in download URLs |
| subject_type / subject_id | morph | project, task, message, comment, deliverable, bill, invoice, proposal, payment, contact, scope_item; NULL allowed while "pending" (composer uploads) |
| project_id | FK NULL CASCADE | denormalised via subject |
| uploaded_by_type / uploaded_by_id | morph NULL | user \| contact |
| disk | VARCHAR(20) | gcs \| local \| gdrive \| url |
| path | VARCHAR(500) | object key, Drive file id, or absolute URL depending on `disk` |
| filename | VARCHAR(255) | original |
| mime_type | VARCHAR(100) | |
| size_bytes | BIGINT UNSIGNED | |
| kind | enum `AttachmentKind`: file, image, inline_image, logo, document, thumbnail_source | `inline_image` = email block/CID images |
| thumbnail_path | VARCHAR(500) NULL | |
| checksum | CHAR(64) NULL | sha256 for dedupe |
| purpose | VARCHAR(32) NULL | `email_attachment`, `email_block`, `chat`, `deliverable`, `bill`, … drives expiry policy |
| expires_at | TIMESTAMP NULL | swept by `attachments:prune` |
| deleted_at | SD | |
`IDX(subject_type,subject_id)`, `IDX(project_id)`, `IDX(expires_at)`.
Legacy: `files` (all), `documents` (+ `projects.documents` JSON items), `deliverables.attachment_path`, `projects.logo`/`logo_google_drive_file_id` (kind=logo), `resources` of type file (archive), chat attachments (archive).

### `approval_requests`  (D6)
| column | type | notes |
|---|---|---|
| id | | |
| subject_type / subject_id | morph | message, bill, proposal, deliverable, invoice, milestone, task |
| kind | enum `ApprovalKind`: send_email, screen_inbound, pay_bill, accept_proposal, approve_invoice, client_review, complete_milestone, task_review | |
| status | enum `ApprovalStatus`: pending, approved, rejected, cancelled, expired | |
| requested_by_type/_id | morph NULL | user \| contact \| system (NULL) |
| decided_by_type/_id | morph NULL | user \| contact |
| decided_at | TIMESTAMP NULL | |
| reason | TEXT NULL | rejection/return reason (min 10 chars enforced in action for send_email) |
| step | SMALLINT DEFAULT 1 | for multi-step bill flows |
| flow_id | FK approval_flows NULL SET NULL | |
| meta | JSON NULL | e.g. AI verdict snapshot |
`IDX(subject_type,subject_id,status)`, `IDX(status,kind,created_at)`.
Legacy: `approval_instances`/`approval_instance_steps` (bills); email `status` transitions (`pending_approval`, `rejected`, `approved_by`, `rejection_reason`); `deliverables.overall_approved_*` + `client_deliverable_interactions`; `project_expendables.status` + activity rows; `milestones.approved_at`; `tasks.needs_approval`.

### `approval_flows` / `approval_flow_steps`
`approval_flows`: `id`, `name`, `kind` (ApprovalKind), `project_id FK NULL CASCADE`, `is_default BOOL`, `is_active BOOL`. `UNQ(kind, project_id, is_default)` where is_default=1 (enforced in action).
`approval_flow_steps`: `id`, `flow_id FK CASCADE`, `step SMALLINT`, `approver_role_id FK roles NULL SET NULL`, `approver_user_id FK users NULL SET NULL`, `label`. `UNQ(flow_id, step)`. CHECK exactly one approver set.
Legacy: `approval_flows`, `approval_flow_steps`.

### `search_documents` *(optional, R1 uses FULLTEXT on entity tables; table reserved)*

---

## 02 Identity

### `users`
| column | type | notes | legacy |
|---|---|---|---|
| id | | | users.id |
| public_id | CHAR(26) UNQ | | — |
| name | VARCHAR(120) | | name |
| email | VARCHAR(191) UNQ | | email |
| password | VARCHAR(255) NULL | NULL for portal-only accounts | password |
| type | enum `UserType`: staff, contractor, supplier | supplier = portal-only guest | user_type (employee→staff, contractor, guest/supplier→supplier) |
| role_id | FK roles NULL SET NULL | global role | role_id |
| timezone | VARCHAR(64) | default `Australia/Perth` | timezone |
| avatar_path | VARCHAR(500) NULL | | — (ui-avatars URL was computed) |
| chat_display_name | VARCHAR(120) NULL | | chat_name |
| email_verified_at | | | |
| last_login_at | | | |
| is_active | BOOL DEFAULT 1 | replaces soft delete for staff | deleted_at (→ is_active=0) |
| requires_extension | BOOL DEFAULT 0 | Chrome extension enforcement | extension_mandatory |
| preferences | JSON NULL | `{theme, density, inbox_sort, …}` | — |
| deleted_at | SD | | |
`FT(name,email)`. Dropped: `notes`, `checklist`, `metadata` (→ `user_profiles`, `payout_methods`, `daily_plan_items`/`personal_checklist_items`), `api_key` (→ `api_keys`), `telegram_*` (archive), `xero_contact_*` (→ `xero_links`), `is_online/online_data` (archive), `remember_token` (→ remembered_devices).

### `user_profiles` (1:1, supplier/contractor verification details)
`user_id PK FK CASCADE`, `legal_name`, `date_of_birth DATE NULL`, `id_type VARCHAR(32) NULL`, `id_number_encrypted TEXT NULL` (encrypted cast), `address_line1`, `address_line2`, `city`, `state`, `postcode`, `country CHAR(2)`, `phone`, `preferred_currency CHAR(3)`, `verified_at NULL`. Legacy: `users.metadata` (PortalProfileService keys).

### `roles`
`id`, `name`, `slug VARCHAR(50) UNQ`, `scope` enum `RoleScope`: global, project, `description`, `is_system BOOL`. Legacy: `roles` (type application→global, project→project; `client` roles dropped — clients are contacts, not users).

### `permissions`
`id`, `slug VARCHAR(80) UNQ`, `name`, `group VARCHAR(50)`, `description`. Seeded from `modules/Identity/Config/permissions.php` — **single source of truth** (legacy had 7 seeders + 2 migrations). Full slug list in `03-modules/identity.md §5`.

### `role_permission`
`role_id FK CASCADE`, `permission_id FK CASCADE`, PK both.

### `api_keys`
`id`, `user_id FK CASCADE`, `name`, `key_hash CHAR(64) UNQ`, `key_prefix CHAR(8)`, `scopes JSON`, `last_used_at NULL`, `expires_at NULL`, `revoked_at NULL`. Legacy: `users.api_key` (re-issued at cutover; old keys hashed if you want continuity — see 06).

### `remembered_devices`
`id`, `user_id FK CASCADE`, `token_hash CHAR(64)`, `user_agent TEXT NULL`, `ip VARCHAR(45)`, `last_used_at`, `expires_at`. `UNQ(user_id, token_hash)`, `IDX(expires_at)`. Legacy: `user_remembered_devices`.

### `google_accounts`
`id`, `user_id FK CASCADE UNQ`, `google_email VARCHAR(191)`, `access_token TEXT` (encrypted), `refresh_token TEXT NULL` (encrypted), `expires_at TIMESTAMP`, `scopes JSON`, `is_app_mailbox BOOL DEFAULT 0` (the org's sending account). Legacy: `google_accounts` + `storage/app/private/google_tokens.json` (→ one row flagged `is_app_mailbox`, attached to the super-admin).

---

## 03 Crm

### `contacts`  (D7)
| column | type | notes | legacy |
|---|---|---|---|
| id | | | new ids; map table in 06 |
| public_id | CHAR(26) UNQ | | — |
| stage | enum `ContactStage`: lead, client, archived | | clients → client; leads → lead (converted → client if `clients.lead_id` matches: **merge into one row**) |
| first_name / last_name | VARCHAR(80) | clients.name split on last space | clients.name, leads.first/last_name |
| display_name | VARCHAR(160) | generated | |
| email | VARCHAR(191) NULL, UNQ | | email |
| phone | VARCHAR(40) NULL | | phone |
| company_name | VARCHAR(160) NULL | | leads.company |
| job_title | VARCHAR(120) NULL | | leads.title |
| website | VARCHAR(255) NULL | | leads.website |
| address JSON NULL | `{line1,city,state,postcode,country}` | clients.address, leads.address/city/state/zip/country |
| timezone | VARCHAR(64) NULL | | timezone |
| source_term_id | FK terms NULL SET NULL | taxonomy lead_sources | leads.source |
| pipeline_status | enum `LeadStatus`: new, contacted, qualified, proposal_sent, won, lost, dormant | NULL for clients | leads.status (map in 06) |
| estimated_value / estimated_currency | DECIMAL(19,4)/CHAR(3) NULL | | leads.estimated_value/currency |
| owner_id | FK users NULL SET NULL | assigned staff | leads.assigned_to_id |
| campaign_id | FK campaigns NULL SET NULL | | leads.campaign_id |
| lost_reason | VARCHAR(255) NULL | | leads.lost_reason |
| last_inbound_at / last_outbound_at | TIMESTAMP NULL | maintained by Comms listener | clients/projects last_email_*, leads.contacted_at |
| next_follow_up_at | TIMESTAMP NULL | | leads.next_follow_up_date |
| converted_at | TIMESTAMP NULL | | leads.converted_at |
| portal_pin_hash | VARCHAR(255) NULL | client portal PIN (hashed) | clients.pin (plaintext → users must re-set; see 06) |
| search_text | TEXT | FT | |
| deleted_at | SD | | leads.deleted_at |
`IDX(stage, pipeline_status)`, `IDX(owner_id)`, `FT(search_text)`.
Dropped: `leads.tags` (→ terms), `leads.pipeline_stage` (dup of status), `leads.email_thread_history` (dup of threads), `leads.metadata.additional_campaign_ids` (→ `campaign_contact`), `telegram_*` (archive), `xero_*` (→ `xero_links`), `active_telegram_project_id` (archive).

### `campaigns`
`id`, `name`, `goal`, `target_audience TEXT`, `services_offered JSON`, `is_active BOOL`. Legacy: `campaigns` minus `email_template`/`ai_persona` (→ archive; outreach AI deferred).

### `campaign_contact`
`campaign_id FK CASCADE`, `contact_id FK CASCADE`, `added_at`. PK both.

### `enquiries` (existing-client enquiry intake)
`id`, `contact_id FK CASCADE`, `project_id FK NULL SET NULL`, `external_ref VARCHAR(100) NULL`, `status` enum `EnquiryStatus`: new, quoted, accepted, declined, converted, `summary TEXT`, `payload JSON NULL`, `converted_to_type/_id` morph NULL, `created_by_id FK users NULL`. Legacy: `project_services` rows with `enquiry_id` (+ `enquiry_meta`, `enquiry_status`).

---

## 04 Work

### `projects`
| column | type | notes | legacy |
|---|---|---|---|
| id, public_id | | | |
| name | VARCHAR(160) | | name |
| description | TEXT NULL | | description |
| client_id | FK contacts RESTRICT | primary client | client_id |
| status | enum `ProjectStatus`: planned, active, on_hold, completed, cancelled, archived | | status (DB enum values) |
| type | VARCHAR(60) NULL | | project_type |
| source_term_id | FK terms NULL | taxonomy project_sources | source |
| payment_type | enum `PaymentType`: one_off, retainer | | payment_type (monthly→retainer) |
| currency | CHAR(3) | | derived from services / default |
| total_value | DECIMAL(19,4) NULL | contracted value | total_amount |
| contract_terms | TEXT NULL | | contract_details |
| website | VARCHAR(255) NULL | | website |
| timezone | VARCHAR(64) NULL | | timezone |
| manager_id | FK users NULL SET NULL | convenience pointer; the member row with role `project-manager` is authoritative — kept in sync by action | project_manager_id |
| starts_at / due_at | DATE NULL | | — |
| share_enabled | BOOL DEFAULT 0 | supplier share link | public_share_enabled |
| settings | JSON NULL | `{seo:{keywords,reporting_sites}, drive_folder_id, integrations:{}}` | preferred_keywords, reporting_sites, google_drive_folder_id, integrations |
| search_text | TEXT FT | | |
| archived_at | TIMESTAMP NULL | | status=archived |
| deleted_at | SD | | deleted_at |
Dropped: `services`/`service_details`/`documents`/`data`/`departments` JSON, `logo` (→ attachments kind=logo), `profit_margin_percentage` (→ computed by `Finance\Queries\ProjectProfitability`, cached), `google_chat_id`, `telegram_*` (archive), `last_email_*` (→ `threads` query), `project_admin_id` (→ member role), `public_share_token` (→ `access_links`), `project_tier_id` (archive).

### `project_members`
`id`, `project_id FK CASCADE`, `user_id FK CASCADE`, `role_id FK roles (scope=project) RESTRICT`, `added_by_id FK users NULL`, `created_at`. `UNQ(project_id,user_id)`. Legacy: `project_user` (+ manager/admin columns → rows with `project-manager` role).

### `project_contacts`
`project_id FK CASCADE`, `contact_id FK CASCADE`, `role` enum `ProjectContactRole`: primary, billing, stakeholder, viewer, `created_at`. PK both. Legacy: `project_client` (`role_id` → primary/viewer mapping; `projects.client_id` → primary row).

### `milestones`
| column | type | notes | legacy |
|---|---|---|---|
| id | | | |
| project_id | FK CASCADE NOT NULL | | project_id |
| name | VARCHAR(160) | | name |
| description | TEXT NULL | | |
| status | enum `MilestoneStatus`: planned, in_progress, submitted, approved, completed, cancelled | `submitted` = marked complete awaiting approval | status + mark_completed_at/approved_at |
| sort | INT | | — |
| due_at | DATE NULL | | completion_date |
| submitted_at / approved_at / completed_at | TIMESTAMP NULL | | mark_completed_at / approved_at / completed_at |
| is_support | BOOL DEFAULT 0 | the auto "support" milestone | name='support' |
| portal_visible | BOOL DEFAULT 1 | shown as a phase to suppliers | — |
| deleted_at | SD | | |
`IDX(project_id, sort)`.

### `task_types`
`id`, `name VARCHAR(60) UNQ`, `color VARCHAR(32) NULL`, `is_default BOOL`, `sort INT`. Seeded: General, Development, Design, Content, SEO, Support, Meeting. Legacy: `task_types` (name-mapped, deduped).

### `tasks`
| column | type | notes | legacy |
|---|---|---|---|
| id | | | |
| project_id | FK CASCADE NOT NULL | **real column** (legacy derived via milestone) | milestone.project_id |
| milestone_id | FK NULL SET NULL | | milestone_id |
| parent_id | FK tasks NULL CASCADE | subtasks | parent_id; `subtasks` rows → child tasks |
| scope_item_id | FK NULL SET NULL | | project_deliverable_id |
| task_type_id | FK RESTRICT | | task_type_id |
| title | VARCHAR(255) | | name |
| description | TEXT NULL | markdown | description |
| status | enum `TaskStatus`: todo, in_progress, paused, blocked, in_review, done, archived | | status (+ Paused finally storable) |
| previous_status | VARCHAR(32) NULL | for unblock | previous_status |
| block_reason | TEXT NULL | | block_reason |
| priority | enum `Priority`: low, medium, high, urgent | | priority |
| assignee_id | FK users NULL SET NULL | | assigned_to_user_id |
| creator_type/_id | morph | user \| contact (client-raised tickets) | creator_* |
| due_at | DATE NULL | | due_date |
| started_at / completed_at | TIMESTAMP NULL | | derived / completed_at |
| estimate_minutes | INT NULL | | effort (hours→minutes) |
| manual_time_minutes | INT NULL | override | manual_effort_override (seconds→minutes) |
| needs_review | BOOL DEFAULT 0 | | needs_approval |
| origin | enum `TaskOrigin`: manual, email, client_portal, wireframe, telegram, automation | | source |
| origin_ref | VARCHAR(191) NULL | e.g. message id | source_id |
| sort | INT DEFAULT 0 | kanban order | — |
| search_text | TEXT FT | | |
| deleted_at | SD | | deleted_at |
`IDX(project_id,status)`, `IDX(assignee_id,status,due_at)`, `IDX(milestone_id)`, `IDX(parent_id)`.
Dropped: `details`/`additional_info` JSON (→ description or archive), `google_chat_*`/`chat_message_id` (archive), `requires_qa` (→ needs_review), `deleted_by` (→ activity_log).

### `task_time_entries`
`id`, `task_id FK CASCADE`, `user_id FK CASCADE`, `started_at`, `ended_at NULL`, `minutes INT NULL` (computed on end), `source` enum: timer, manual, tracker, `note VARCHAR(255) NULL`. `IDX(task_id)`, `IDX(user_id, started_at)`. Legacy: derived from `activity_log` status replay (`Task::calculateTotalTimeSpent`) → one synthetic entry per In-Progress interval (06).

### `daily_plan_items`
`id`, `user_id FK CASCADE`, `task_id FK CASCADE`, `date DATE`, `sort INT`, `status` enum `PlanItemStatus`: planned, done, carried_over, `note VARCHAR(255) NULL`. `UNQ(user_id, task_id, date)`. Legacy: `daily_tasks`.

### `personal_checklist_items`
`id`, `user_id FK CASCADE`, `text VARCHAR(255)`, `is_done BOOL`, `sort INT`. Legacy: `users.checklist` JSON.

### `scope_items`
`id`, `project_id FK CASCADE`, `milestone_id FK NULL SET NULL`, `name`, `description TEXT NULL`, `type VARCHAR(50) NULL` (from `config/scope_item_types.php`), `status` enum `ScopeStatus`: pending, in_progress, delivered, cancelled, `checklist JSON NULL` (`[{id,text,done}]`), `due_at DATE NULL`, `completed_at TIMESTAMP NULL`, `sort INT`, `deleted_at SD`. Legacy: `project_deliverables` (+ type from config vocabulary where inferable).

### `deliverables`
| column | type | notes | legacy |
|---|---|---|---|
| id, public_id | | | |
| project_id | FK CASCADE | | project_id |
| milestone_id | FK NULL SET NULL | | — |
| submitted_by_id | FK users NULL | | team_member_id |
| title | VARCHAR(255) | | title |
| description | TEXT NULL | | description |
| type | enum `DeliverableType`: blog_post, design, video, document, link, other | | type/mime_type |
| content_url | VARCHAR(500) NULL | | content_url |
| content_text | LONGTEXT NULL | | content_text |
| version | INT DEFAULT 1 | | version |
| previous_version_id | FK deliverables NULL SET NULL | | parent_deliverable_id |
| status | enum `DeliverableStatus`: draft, in_review, changes_requested, approved, published | | status |
| review_due_at | TIMESTAMP NULL | | due_for_review_by |
| submitted_at / approved_at | TIMESTAMP NULL | | submitted_at / overall_approved_at |
| approved_by_contact_id | FK contacts NULL | | overall_approved_by_client_id |
| client_visible | BOOL DEFAULT 1 | | is_visible_to_client |
| deleted_at | SD | | |
Attachment via `attachments`; comments via `comments`. Per-contact verdicts:

### `deliverable_reviews`
`id`, `deliverable_id FK CASCADE`, `contact_id FK CASCADE`, `viewed_at NULL`, `decision` enum: pending, approved, changes_requested, `decided_at NULL`, `feedback TEXT NULL`. `UNQ(deliverable_id, contact_id)`. Legacy: `client_deliverable_interactions` (4 timestamps → decision + decided_at).

### `standups`
`id`, `user_id FK CASCADE`, `project_id FK CASCADE`, `date DATE`, `body TEXT`, `submitted_at TIMESTAMP`, `is_late BOOL` (after 11:00 user-local). `UNQ(user_id, project_id, date)`. Legacy: `project_notes.type='standup'`.

### `meetings`
`id`, `project_id FK CASCADE`, `created_by_id FK users`, `title`, `description TEXT NULL`, `starts_at`, `ends_at`, `timezone`, `location NULL`, `google_event_id VARCHAR(191) NULL UNQ`, `google_event_url`, `meet_url NULL`. Legacy: `meetings`.
### `meeting_attendees`
`meeting_id FK CASCADE`, `user_id FK CASCADE`, `notified_at NULL`. PK both. Legacy: `meeting_attendees`.

---

## 05 Comms

### `mailboxes`
`id`, `name`, `address VARCHAR(191) UNQ`, `provider` enum: gmail, smtp, `google_account_id FK NULL`, `smtp_config JSON NULL` (encrypted cast), `is_default BOOL`, `is_active BOOL`, `signature_html TEXT NULL`, `sign_off_name`, `sign_off_role`, `last_polled_at NULL`, `poll_cursor VARCHAR(191) NULL` (Gmail historyId / last sent_at). Legacy: app Google token (→ the default gmail mailbox), `email_apps` (SMTP rows → provider=smtp; API rows → archive).

### `threads`
| column | type | notes | legacy |
|---|---|---|---|
| id, public_id | | | conversations.id |
| project_id | FK NULL SET NULL | NULL = no project yet (unknown sender / lead) | project_id |
| contact_id | FK contacts NULL SET NULL | | conversable (client or lead → contact) |
| owner_id | FK users NULL SET NULL | responsible staff | contractor_id |
| subject | VARCHAR(500) | | subject |
| normalised_subject | VARCHAR(255) | Re:/Fwd: stripped, lowercased; `IDX` | — |
| provider_thread_id | VARCHAR(128) NULL | Gmail threadId; `IDX` | emails.gmail_thread_id (first non-null) |
| status | enum `ThreadStatus`: open, waiting_us, waiting_them, closed | maintained by ReplyClock listener | derived |
| last_inbound_at / last_outbound_at / last_activity_at | TIMESTAMP NULL | | derived |
| needs_reply_since | TIMESTAMP NULL | inbound newer than outbound → SLA clock start | derived |
| is_private | BOOL DEFAULT 0 | thread-level privacy (any private message → private thread) | emails.is_private |
| message_count | INT DEFAULT 0 | | |
| ai_summary | TEXT NULL, ai_summary_message_count INT NULL, ai_summary_at NULL, ai_summary_status enum `AiJobStatus` NULL | | conversations.ai_* |
| ai_task_suggestion | JSON NULL | | |
| deleted_at | SD | | |
`IDX(project_id,last_activity_at)`, `IDX(status,needs_reply_since)`, `IDX(contact_id)`.

### `messages`  (D9)
| column | type | notes | legacy |
|---|---|---|---|
| id, public_id | | | emails.id |
| thread_id | FK threads CASCADE | | conversation_id |
| mailbox_id | FK mailboxes RESTRICT | | — (default) |
| direction | enum `Direction`: inbound, outbound | | type |
| status | enum `MessageStatus`: draft, saved, submitted, ai_checking, pending_approval, approved, scheduled, sending, sent, failed, received, screening, screened_out, returned, deleted | full table in 03-modules/comms.md §4 | status (map in 06) |
| author_type/_id | morph NULL | user (outbound) / contact (inbound) | sender_* |
| subject | VARCHAR(500) | | subject |
| body_format | enum `BodyFormat`: text, markdown, html, blocks, template | exactly one representation | sniffed (06 §5.3) |
| body | LONGTEXT NULL | markdown/html/text source; NULL for blocks/template | body |
| body_blocks | JSON NULL | block builder document | template_data.blocks |
| template_id | FK email_templates NULL SET NULL | | template_id / email_template slug |
| template_values | JSON NULL | resolved placeholder values `{name: value}` | template_data (placeholders part) |
| body_text | LONGTEXT | derived plain text (for AI, search, preview); `FT` | cleaned body |
| rendered_html | LONGTEXT NULL | exact HTML as sent (with quote), written at send time | — |
| greeting | JSON NULL | `{mode: first|full|custom|none, custom_name?}` | draft_meta / custom_greeting_name |
| in_reply_to_id | FK messages NULL SET NULL | | in_reply_to_email_id |
| rfc_message_id | VARCHAR(512) NULL | RFC 5322 Message-ID; `IDX(191)` | rfc_message_id |
| provider_message_id | VARCHAR(128) NULL UNQ | Gmail API id | message_id |
| provider_thread_id | VARCHAR(128) NULL | | gmail_thread_id |
| headers | JSON NULL | selected inbound headers | template_data (headers part) |
| is_private | BOOL DEFAULT 0 | | is_private |
| scheduled_for | TIMESTAMP NULL | Send later | Schedule on Email (delayed) |
| submitted_at / approved_at / sent_at / received_at / read_at | TIMESTAMP NULL | | sent_at, read_at |
| approved_by_id | FK users NULL SET NULL | | approved_by |
| last_error | TEXT NULL | | |
| ai_check_status | enum `AiJobStatus` NULL: queued, running, done, failed | | ai_status |
| ai_verdict | enum `AiVerdict` NULL: approved, held | | ai_status approved/held |
| ai_reason | TEXT NULL | ai_checked_at NULL | ai_reason/ai_checked_at |
| ai_summary | TEXT NULL | | ai_summary |
| deleted_at | SD (the "bin") | | deleted_at |
`IDX(thread_id,direction,created_at)`, `IDX(status,created_at)`, `IDX(direction,status,received_at)`, `IDX(scheduled_for)`.
Dropped: `to` varchar (→ `message_recipients`), `email_template` slug, `rejection_reason` (→ approval_requests.reason), `ai_draft*` (→ `message_drafts`).

### `message_recipients`
`id`, `message_id FK CASCADE`, `kind` enum: to, cc, bcc, from, reply_to, `address VARCHAR(191)`, `name VARCHAR(191) NULL`, `contact_id FK NULL SET NULL`, `user_id FK NULL SET NULL`, `delivery_status VARCHAR(32) NULL`, `provider_message_id VARCHAR(128) NULL` (per-recipient Gmail id — fixes legacy "only first recipient recorded"). `IDX(message_id,kind)`, `IDX(address)`. Legacy: `emails.to` (JSON-in-varchar) + sender.

### `message_drafts` (AI-suggested replies)
`id`, `thread_id FK CASCADE`, `for_message_id FK messages NULL CASCADE`, `status AiJobStatus`, `body TEXT NULL`, `summary VARCHAR(500) NULL`, `requested_at`, `generated_at NULL`, `used_at NULL`, `tokens_in INT`, `tokens_out INT`. Legacy: `emails.ai_draft*`.

### `ai_reviews` (audit of every AI call on a message/thread)
`id`, `subject_type/_id` morph (message|thread), `kind` enum: outbound_check, inbound_screen, summarise_thread, summarise_message, draft_reply, `status AiJobStatus`, `model VARCHAR(80)`, `prompt_version VARCHAR(20)`, `verdict VARCHAR(32) NULL`, `result JSON NULL`, `tokens_in`, `tokens_out`, `cost DECIMAL(12,6) NULL`, `duration_ms INT NULL`, `error TEXT NULL`. `IDX(subject_type,subject_id,kind)`. Legacy: `execution_logs` rows for workflow 17 (AI step) — migrated as `outbound_check` reviews where mappable.

### `email_templates`
`id`, `slug VARCHAR(80) UNQ`, `name`, `subject VARCHAR(500)`, `body_html LONGTEXT`, `body_markdown LONGTEXT NULL`, `description NULL`, `category VARCHAR(40) NULL`, `placeholders JSON` (`[{name, source: 'project.name'|'contact.first_name'|'magic_link'|'custom', required, repeatable, type}]` — **the placeholder registry lives here + `config/comms.placeholders.php`**, no `placeholder_definitions` table), `is_private BOOL`, `is_active BOOL`, `mailbox_id FK NULL`, `created_by_id`. Legacy: `email_templates` + `placeholder_definitions` + pivot (folded into JSON).

### `thread_reads` (per-user read marker; replaces `emails.read_at` + `user_interactions` for staff)
`thread_id FK CASCADE`, `user_id FK CASCADE`, `last_read_message_id FK messages`, `read_at`. PK `(thread_id,user_id)`.

### `message_events` (delivery/open tracking)
`id`, `message_id FK CASCADE`, `kind` enum: opened, link_clicked, bounced, delivered, `occurred_at`, `ip VARCHAR(45) NULL`, `user_agent VARCHAR(255) NULL`, `meta JSON NULL`. Signed pixel writes `opened`. Legacy: `emails.read_at` (inbound only), `user_interactions` email_open.

---

## 06 Finance

### `service_catalogue_items`
`id`, `name UNQ`, `description TEXT NULL`, `default_amount DECIMAL(19,4) NULL`, `default_currency CHAR(3) NULL`, `default_frequency` enum `Frequency`: one_off, monthly, quarterly, yearly, `default_schedule JSON NULL` (`[{label, percent|amount, due_rule}]`), `xero_item_code VARCHAR(50) NULL`, `xero_account_code VARCHAR(20) NULL`, `is_active BOOL`. Legacy: `crm_services`.

### `project_services`
`id`, `project_id FK CASCADE`, `catalogue_item_id FK RESTRICT` (**RESTRICT**, legacy cascaded and could delete invoices), `name VARCHAR(160)` (copied at creation), `description TEXT NULL`, `amount DECIMAL(19,4)`, `currency CHAR(3)`, `frequency Frequency`, `starts_at DATE NULL`, `status` enum `ServiceStatus`: active, paused, completed, cancelled, `tracking` enum: operational, lead_generation, `show_on_leads_board BOOL`, `xero_account_code NULL`, `sort INT`. Legacy: `project_services` (minus enquiry_* → `enquiries`).

### `service_milestones` (payment schedule — replaces `payment_breakdown` JSON, D6)
`id`, `project_service_id FK CASCADE`, `label VARCHAR(120)`, `key VARCHAR(60)` (legacy milestone_key for migration), `amount DECIMAL(19,4)`, `due_rule VARCHAR(60) NULL` (e.g. `on_start`, `on_completion`, `net_30`), `due_at DATE NULL`, `sort INT`, `invoiced_at TIMESTAMP NULL`. `UNQ(project_service_id, key)`. Legacy: `project_services.payment_breakdown[]`.

### `invoices`
| column | type | notes | legacy |
|---|---|---|---|
| id, public_id | | | |
| number | VARCHAR(20) UNQ | `OZI-000123` from `sequences` | id (`OZI`+id) |
| project_id | FK RESTRICT | | project_id |
| contact_id | FK contacts RESTRICT | bill-to | client_id |
| status | enum `InvoiceStatus`: draft, pending_approval, approved, sent, partially_paid, paid, overdue, void | | status |
| currency | CHAR(3) | | currency |
| subtotal / tax_total / total | DECIMAL(19,4) | derived from items on save | total_amount |
| amount_paid | DECIMAL(19,4) DEFAULT 0 | maintained by Payment actions | derived |
| tax_mode | enum: exclusive, inclusive, none | | line_amount_type |
| issued_at / due_at | DATE NULL | | due_date |
| sent_at / paid_at / voided_at | TIMESTAMP NULL | | |
| xero_invoice_id | VARCHAR(64) NULL | also in `xero_links` | xero_invoice_id |
| xero_number | VARCHAR(50) NULL | the Xero-side number (legacy's shadowed `invoice_number`) | invoice_number |
| xero_branding_theme_id | VARCHAR(64) NULL | | |
| notes | TEXT NULL | | |
| deleted_at | SD | | |
`IDX(project_id,status)`, `IDX(contact_id)`, `IDX(status,due_at)`.

### `invoice_items`
`id`, `invoice_id FK CASCADE`, `service_milestone_id FK NULL SET NULL`, `project_service_id FK NULL SET NULL`, `description VARCHAR(500)`, `quantity DECIMAL(12,4) DEFAULT 1`, `unit_price DECIMAL(19,4)`, `tax_type VARCHAR(50) NULL`, `tax_rate DECIMAL(6,4) NULL`, `line_total DECIMAL(19,4)`, `sort INT`. `UNQ(service_milestone_id)` where invoice not void (**restores the dropped uniqueness** — enforced in `AddInvoiceItem` action + partial unique via generated column `active_milestone_id` = milestone id when invoice status != void). Legacy: `invoice_items` (milestone_key → service_milestones.key).

### `invoice_payment_services`
`invoice_id FK CASCADE`, `xero_payment_service_id FK CASCADE`. PK both. Legacy: `invoices.xero_payment_service_ids` JSON.

### `bills`
| column | type | notes | legacy |
|---|---|---|---|
| id, public_id | | | |
| number | VARCHAR(20) UNQ | `OZB-000045` | id |
| project_id | FK RESTRICT | | project_id |
| supplier_id | FK users RESTRICT | contractor/supplier user | contractor_id |
| contract_id | FK proposals NULL SET NULL | the accepted proposal this bills against | project_expendable_id |
| status | enum `BillStatus`: submitted, pending_approval, approved, scheduled, partially_paid, paid, rejected, void | | status |
| currency / amount | | | |
| amount_paid | DECIMAL(19,4) DEFAULT 0 | | |
| reference | VARCHAR(100) NULL | supplier's invoice number | reference_number |
| due_at | DATE NULL | 7-day terms default | due_date |
| payout_method_id | FK payout_methods NULL SET NULL | snapshot copied to `payout_snapshot` on approval | bill_payment_details |
| payout_snapshot | JSON NULL (encrypted) | | bill_payment_details.details |
| xero_bill_id | VARCHAR(64) NULL, xero_account_code, xero_tax_type | | |
| submitted_by_type/_id | morph | user (staff) or the supplier user | |
| approved_at / paid_at / voided_at | | | |
| deleted_at | SD | | |

### `payout_methods`
`id`, `user_id FK CASCADE`, `type` enum `PayoutType`: bank_au, bank_international, paypal, wise, payoneer, other, `label`, `country CHAR(2) NULL`, `details JSON` (**encrypted cast**; shape per type in 03-modules/finance.md §6), `is_default BOOL`, `verified_at NULL`, `deleted_at SD`. Legacy: `users.metadata.payment_methods` (encrypted string) + `bill_payment_details`.

### `budgets`
`id`, `project_id FK CASCADE`, `milestone_id FK NULL CASCADE`, `name`, `description NULL`, `currency`, `amount`, `status` enum: planned, approved, closed, `created_by_id`. `UNQ(milestone_id)` where milestone not null (one budget per milestone). Legacy: `project_expendables` with `user_id IS NULL`.

### `proposals` (supplier quotes; accepted = contract)
| column | type | notes | legacy |
|---|---|---|---|
| id, public_id, number `OZX-…` | | | id |
| project_id | FK CASCADE | | project_id |
| milestone_id | FK NULL SET NULL | the phase quoted | expendable morph (Milestone) |
| supplier_id | FK users RESTRICT | | user_id |
| title | VARCHAR(160) | | name |
| description | TEXT NULL | | description |
| currency / amount | | | |
| payment_terms | JSON NULL | `[{label, percent, trigger}]` | payment_terms text |
| status | enum `ProposalStatus`: submitted, shortlisted, accepted, rejected, withdrawn, completed | accepted = live contract | status |
| decided_by_id / decided_at / decision_reason | | | activity rows |
| billed_amount | DECIMAL(19,4) DEFAULT 0 | Σ approved bills; contract limit check `BillExceedsContract` | computed |
| completed_at | | | |
| deleted_at | SD | | |
`UNQ(milestone_id, supplier_id)` where status in (submitted, shortlisted, accepted) — one live proposal per supplier per phase (portal rule).

### `payments`
`id`, `public_id`, `payable_type/_id` morph (invoice|bill), `project_id FK`, `direction` enum: in, out, `amount`, `currency`, `fx_rate_to_base DECIMAL(19,8) NULL`, `base_amount DECIMAL(19,4) NULL` (in `ozee.base_currency`), `paid_at DATE`, `method` enum: bank, stripe, airwallex, paypal, cash, other, `provider_ref VARCHAR(191) NULL` (Airwallex/Stripe/Xero payment id), `recorded_by_id FK users NULL`, `notes NULL`, `deleted_at SD`. `IDX(payable_type,payable_id)`, `IDX(project_id,paid_at)`, `IDX(provider_ref)`. Legacy: `transactions` with `invoice_id` or `bill_id` (+ `is_paid`, `payment_date`, `exchange_rate`, `bank_transaction_id`, `xero_payment_id`).

### `ledger_entries` (project P&L lines not tied to an invoice/bill)
`id`, `project_id FK CASCADE`, `kind` enum: income, expense, `category_id FK ledger_categories NULL`, `description VARCHAR(255)`, `amount`, `currency`, `fx_rate_to_base`, `base_amount`, `occurred_at DATE`, `user_id FK NULL` (who it relates to), `contact_id FK NULL`, `hours DECIMAL(8,2) NULL`, `provider_ref NULL`, `recorded_by_id`, `deleted_at SD`. Legacy: remaining `transactions` (income/expense without invoice/bill; `bonus` type → Recognition archive).
### `ledger_categories`
`id`, `name UNQ`, `slug UNQ`, `kind` enum: income, expense, both, `xero_account_code NULL`. Legacy: `transaction_types`.

### `xero_connections`
`id`, `tenant_id VARCHAR(64) UNQ`, `tenant_name`, `connected_by_id FK users`, `status` enum: connected, disconnected, error, `access_token/refresh_token/id_token TEXT` (encrypted), `scopes TEXT`, `access_expires_at`, `refresh_expires_at`, `last_refreshed_at`, `last_error TEXT NULL`, `is_active BOOL` (exactly one active), `default_branding_theme_id NULL`, `webhook_key TEXT NULL` (encrypted). Legacy: `xero_connections` + `xero_tenants` (one row per tenant; selected → is_active).
### `xero_accounts`
`id`, `connection_id FK CASCADE`, `xero_account_id CHAR(36)`, `code VARCHAR(20)`, `name`, `type VARCHAR(40)`, `currency CHAR(3) NULL`, `is_bank BOOL`, `raw JSON`, `synced_at`. `UNQ(connection_id, xero_account_id)`. Legacy: — (fetched live; cached now).
### `xero_payment_services`
as legacy: `connection_id FK`, `service_id VARCHAR(64)`, `name`, `provider`, `status`, `raw JSON`, `synced_at`. `UNQ(connection_id, service_id)`.
### `xero_links` (generic local↔Xero mapping + sync state)
`id`, `subject_type/_id` morph (contact|user|invoice|bill|payment|service_catalogue_item), `xero_type` enum: contact, invoice, bill, payment, item, `xero_id VARCHAR(64)`, `xero_number VARCHAR(50) NULL`, `sync_mode` enum: push, pull, both, none, `last_pushed_at`, `last_pulled_at`, `last_error NULL`, `raw JSON NULL`. `UNQ(subject_type,subject_id,xero_type)`, `IDX(xero_id)`. Legacy: `clients.xero_*`, `users.xero_*`, `invoices.xero_invoice_id`, `bills.xero_invoice_id`, `transactions.xero_payment_id`.
### `airwallex_bank_mappings`
`id`, `connection_id FK xero_connections CASCADE`, `airwallex_currency CHAR(3)`, `xero_account_id FK xero_accounts CASCADE`. `UNQ(connection_id, airwallex_currency)`. Legacy: `airwallex_xero_bank_mappings`.
### `bank_transactions` (imported Airwallex/Stripe feed, for reconciliation)
`id`, `provider` enum: airwallex, stripe, `provider_id VARCHAR(191) UNQ`, `occurred_at`, `amount`, `currency`, `fee DECIMAL(19,4) NULL`, `net DECIMAL(19,4) NULL`, `descriptor VARCHAR(255) NULL`, `raw JSON`, `matched_payment_id FK payments NULL SET NULL`, `matched_at NULL`. Legacy: `stripe_payouts` (+ breakdown), `transactions.bank_transaction_id`.
### `stripe_apps`
`id`, `app_key VARCHAR(64) UNQ`, `name`, `secret_key TEXT` (encrypted), `publishable_key`, `webhook_secret TEXT` (encrypted), `settings JSON NULL`, `is_active BOOL`. Legacy: `stripe_configurations`.
### `stripe_subscriptions`
`id`, `stripe_app_id FK CASCADE`, `contact_id FK NULL`, `project_id FK NULL`, `stripe_subscription_id UNQ`, `stripe_customer_id`, `status VARCHAR(32)`, `amount DECIMAL(19,4)`, `currency`, `cancel_at/canceled_at/ended_at NULL`, `metadata JSON`. Legacy: `stripe_subscriptions` (app_id → FK; minor units → decimal).
### `stripe_subscription_payments`
`id`, `stripe_subscription_id FK CASCADE`, `stripe_invoice_id UNQ`, `amount`, `currency`, `status`, `paid_at`, `payment_id FK payments NULL`. Legacy: same (int → decimal).

---

## 07 Access

### `access_links`  (D6)
| column | type | notes | legacy |
|---|---|---|---|
| id | | | magic_links.id |
| token_hash | CHAR(64) UNQ | sha256 of the token; token itself only in the URL | token (plaintext → hashed at migration; old URLs keep working because we hash the legacy token) |
| kind | enum `AccessLinkKind`: client_dashboard, project_share, external_api, deliverable_review | | type client/external + projects.public_share_token |
| project_id | FK NULL CASCADE | | project_id |
| contact_id | FK NULL CASCADE | for client_dashboard | email → contact |
| email | VARCHAR(191) NULL | for external | email |
| label | VARCHAR(120) NULL | | label |
| whitelist | JSON NULL | ips/domains | whitelist |
| max_uses / uses_count | INT NULL / INT | | |
| expires_at / last_used_at / revoked_at | | | |
| created_by_id | FK users NULL | | |
`IDX(kind, project_id)`, `IDX(contact_id)`.

### `one_time_codes`  (D6)
`id`, `purpose` enum `CodePurpose`: staff_login, portal_login, client_pin_setup, client_verify, `identifier VARCHAR(191)` (email), `scope VARCHAR(191) NULL` (e.g. project public_id), `code_hash VARCHAR(255)`, `attempts SMALLINT`, `max_attempts SMALLINT DEFAULT 5`, `expires_at`, `consumed_at NULL`, `meta JSON NULL`. `IDX(purpose, identifier, scope)`. Legacy: `user_otps`, `otp_verifications`, `magic_links.temporary_pin` (all expired at migration — not migrated).

### `portal_sessions`
`id`, `token_hash CHAR(64) UNQ`, `user_id FK users CASCADE` (supplier account), `resolved_via` enum: login, code, `project_id FK NULL`, `ip`, `user_agent`, `expires_at`, `last_seen_at`, `revoked_at NULL`. Legacy: `otp_verifications.session_token`.

### `client_sessions`
same shape for contacts: `contact_id FK CASCADE`, `access_link_id FK`, `pin_verified_at NULL`.

### `auth_attempts`
`id`, `channel` enum: staff, client, portal, api, `identifier`, `ip`, `succeeded BOOL`, `attempted_at`. `IDX(channel, identifier, attempted_at)`. Pruned daily (30 days). Legacy: `login_attempts`.

### `vault_credentials`
`id`, `public_id`, `contact_id FK CASCADE`, `project_id FK NULL SET NULL`, `created_by_type/_id` morph (user|contact), `source` enum: client, team, `label`, `username_encrypted TEXT`, `password_encrypted TEXT`, `pin_encrypted TEXT NULL`, `salt VARCHAR(64)`, `notes_encrypted TEXT NULL`, `url VARCHAR(500) NULL`, `client_visible BOOL`, `expires_at NULL`, `last_viewed_at NULL`, `deleted_at SD`. Legacy: `client_vault_credentials` (uuid → new bigint; `public_id` = old uuid).
### `vault_credential_shares`
`vault_credential_id FK CASCADE`, `user_id FK CASCADE`, `granted_by_id FK users NULL`, `created_at`. PK both. Legacy: `client_vault_credential_user`.
### `vault_access_logs` → use `activity_log` (log name `vault`).

---

## 08 Ai / Integrations

### `ai_usage` (daily roll-up, for cost dashboards)
`date DATE`, `kind VARCHAR(40)`, `model VARCHAR(80)`, `calls INT`, `tokens_in BIGINT`, `tokens_out BIGINT`, `cost DECIMAL(12,6)`. PK `(date,kind,model)`.

### `google_drive_links` *(only if the project "Documents" tab keeps Drive)*
`id`, `subject_type/_id` morph (project), `drive_file_id VARCHAR(191)`, `kind` enum: folder, doc, `name`, `url`. Legacy: `projects.google_drive_folder_id`, `documents.google_drive_file_id`.

---

## 09 Legacy archive tables (`legacy_*`) — D17

Raw copies (same columns, plus `legacy_id` = original id) of every legacy table whose module is deferred, created by `06-data-migration.md` step 3: `legacy_workflows`, `legacy_workflow_steps`, `legacy_execution_logs`, `legacy_prompts`, `legacy_points_ledgers`, `legacy_kudos`, `legacy_bonus_*`, `legacy_monthly_*`, `legacy_project_tiers`, `legacy_user_availabilities`, `legacy_user_activities`, `legacy_user_productivities`, `legacy_chat_messages`, `legacy_telegram_*`, `legacy_presentations`, `legacy_slides`, `legacy_content_blocks`, `legacy_wireframes`, `legacy_wireframe_versions`, `legacy_shareable_resources`, `legacy_resources`, `legacy_seo_reports`, `legacy_schedules`, `legacy_external_email_logs`, `legacy_email_apps` (API rows), `legacy_campaign_ai` (campaign template/persona), `legacy_user_interactions`, `legacy_activity_log` (full copy — task durations are derived from it before archiving). These tables have **no models** in v2 and are never read by application code.

---

## Cascade / soft-delete decision table

| Parent (SD?) | Child | onDelete | Rationale |
|---|---|---|---|
| contacts (SD) | projects | RESTRICT | never lose projects by deleting a contact; archive instead |
| projects (SD) | milestones, tasks, scope_items, deliverables, standups, meetings, project_services, budgets, proposals, comments, attachments | CASCADE | project force-delete is an explicit admin action that removes the tree |
| projects (SD) | threads, invoices, bills, payments, ledger_entries | RESTRICT / SET NULL (threads) | money and mail are never cascade-deleted |
| milestones (SD) | tasks, scope_items, deliverables | SET NULL | tasks outlive a removed phase |
| tasks (SD) | tasks (children), time entries, plan items | CASCADE | |
| threads (SD) | messages | CASCADE | bin behaviour: thread soft-delete hides all messages |
| messages (SD) | recipients, events, drafts | CASCADE | |
| users (SD via is_active) | anything | SET NULL / RESTRICT | users are never hard-deleted |
| invoices (SD) | items, payment links | CASCADE | void instead of delete in UI |
| proposals (SD) | bills.contract_id | SET NULL | |

## Enum vocabulary (all backed enums live in the owning module's `Enums/`)

`UserType, RoleScope, ContactStage, LeadStatus, EnquiryStatus, ProjectStatus, PaymentType, ProjectContactRole, MilestoneStatus, TaskStatus, Priority, TaskOrigin, PlanItemStatus, ScopeStatus, DeliverableType, DeliverableStatus, ReviewDecision, CommentKind, Visibility, AttachmentKind, ApprovalKind, ApprovalStatus, ThreadStatus, Direction, MessageStatus, BodyFormat, RecipientKind, AiJobStatus, AiVerdict, AiReviewKind, Frequency, ServiceStatus, InvoiceStatus, TaxMode, BillStatus, PayoutType, BudgetStatus, ProposalStatus, PaymentDirection, PaymentMethod, LedgerKind, XeroLinkType, SyncMode, AccessLinkKind, CodePurpose, AuthChannel`.

---

## Addendum A (v1.1) — additions from the Admin Console / Client OS mocks (see 09 §2)

| Table / column | Definition | Legacy |
|---|---|---|
| `workspaces` | `id, slug UNQ, name, status enum host|active|trial|suspended, plan_id NULL, settings JSON, support_access_until NULL` — R1 has one row (OZee). **Every business table gets `workspace_id FK NOT NULL` + a global scope** (D19). | — |
| `businesses` | `id, workspace_id, public_id, name, status enum live|onboarding|paused, industry, website, primary_domain_id FK NULL, address JSON, notes, archived_at` | — (one per legacy client at migration) |
| `business_contacts` | `business_id, contact_id, relationship enum account_owner|joint_owner|billing_contact|franchise_holder, since DATE` PK both | `project_client` roles |
| `projects.business_id` | FK businesses RESTRICT | — |
| `projects.tier` | enum care|growth|partner default care | `project_tiers` (different meaning; archived) |
| `roles.level` | SMALLINT 0–4; `roles.based_on_id` FK NULL; `roles.is_system` | — |
| `permissions` (actions catalogue) | add `module VARCHAR(40)`, `guard enum none|approval|reauth|logged|reason|ceiling`, `min_level SMALLINT` | 9 seeders → one config |
| `role_permission.mode` | enum granted|denied (overrides over the matrix defaults) | — |
| `teams` / `team_members` / `team_clients` | `teams(id, workspace_id, name, color, lead_user_id, lead_manages BOOL, modules JSON)`, `team_members(team_id, user_id, role enum lead|senior|member|contributor)`, `team_clients(team_id, contact_id|business_id)` | — |
| `access_grants` | `user_id, business_id, reach JSON, ends_at NULL, reason, source enum team|direct|proposal, granted_by_id` | contractors' project access |
| `project_contacts.reach` | JSON list of approve_deliverables|comment|weekly_report|billing|on_contract (replaces `role`) | — |
| `service_packs` | `id, workspace_id, name, color, plan JSON [{milestone, scope_items[], tasks[]}]` | — |
| `deliverable_versions` | `deliverable_id, version, submitted_by_id, submitted_at, state enum waiting|approved|revisions_requested, approver_contact_id, note, attachment_id` | `deliverables.version/parent_deliverable_id` |
| `bills.status` | add `needs_coding`, `ready_to_sync`, `synced` before `paid` | derived in mock |
| `bills.tax_treatment` | enum gst_10|gst_free|no_gst_overseas; `gst_amount` DECIMAL | `xero_tax_type` |
| `bank_transactions.paid_from_account` | VARCHAR(60) (ANZ business, Wise AUD, Wise PKR, Company card…) | — |
| `domain_providers` | `id, workspace_id, name, kind enum registrar|dns|both, api_connected BOOL, account_ref, credentials JSON encrypted NULL, last_synced_at, notes` | — |
| `domains` | `id, workspace_id, name UNQ, business_id NULL, registrar_provider_id NULL, dns_provider_id NULL, registered_at, expires_at, auto_renew BOOL, ssl_expires_at, ssl_ok BOOL, use VARCHAR(40), notes` | — |
| `dns_records` | `domain_id, type enum A|AAAA|CNAME|MX|TXT|NS, host, value TEXT, ttl INT, priority NULL, locked BOOL, previous_value TEXT NULL, previous_value_kept_until DATE NULL, note` | — |
| `domain_team_reach` | `domain_id, team_id, reach enum full|read|email_only|verification_txt` | — |
| `access_requests` | `id, workspace_id, requester_user_id, action_slug, business_id NULL, contact_id NULL, reason, status enum pending|approved|returned|expired, decided_by_id, decided_at, note` | — |
| `ghost_sessions` (+ `ghost_session_events`) | `actor_user_id, target_type/id (contact|user), reason, elevated_until NULL, started_at, ended_at`; events `kind opened|read|elevated|write, subject, at` | — |
| `users.engagement` / `users.access_ends_at` / `users.two_factor_required` | enum employee|contractor|supplier; date; bool | `user_type`, `users.metadata` |
| `announcements` | `id, workspace_id, kind enum feature|promotion|approval, title, body, cta_label, cta_url, media_attachment_id, audience enum all|business|contact, business_id NULL, contact_id NULL, publish_at, expires_at` + `announcement_reads(announcement_id, contact_id, read_at)` | `shareable_resources(notice=1)` |
| `seo_reports` | `id, business_id, project_id NULL, month DATE, data JSON (schema in survey/design_screens_clientos.md §5.11), published_at, created_by_id`; `UNQ(business_id, month)` | `seo_reports` |
| `tickets` → use `tasks` with `kind enum task|ticket` and `origin client_portal`; add `tasks.kind` | — | client-raised tasks |
| `client_feed_items` | **no table** — a union Query over approvals, deliverable_versions, invoices, announcements, tickets, comments | — |
