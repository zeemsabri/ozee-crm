# Legacy data survey — `ozee-crm-admin-c9` dump + ER graphml

Sources analysed:

- `/home/claude/design/CRM Restructure/uploads/ozee-crm-admin-c9_30_tables_20260901_000000.sql` (1.47 MB, MariaDB 11.8.2 `mysqldump`, generated 2026-09-01, 30 `CREATE TABLE`, 29 `INSERT` blocks — `deliverable_comments` has no rows)
- `/home/claude/design/CRM Restructure/uploads/ozee-crm@localhost.graphml` and `ozee-crm@localhost-b06e5027.graphml` (yFiles/yEd-Live UML export of the live schema)

Method: a small Python parser (`scratchpad/parse.py`) tokenises every `INSERT ... VALUES (...),(...)` statement (handles escaped quotes, `NULL`, numerics) and asserts the value count matches the `CREATE TABLE` column count for every row; all 5,398 rows parsed cleanly. No personal data is reproduced here — everything is aggregated.

> **Headline caveat: the dump is PARTIAL twice over.** (1) It contains 30 of the ~126 tables in the live schema (the graphml). (2) Several of the largest tables are capped at exactly 100 rows and their id ranges show they are `ORDER BY id DESC LIMIT 100` samples (tasks ids 15763–15862, milestones 1486–1585, project_notes 13456–13555, user_activities 387333–387432, transactions 72–171). Counts and "orphan" findings for those tables describe the sample, not the database. **The migration must be developed and rehearsed against the live DB (or a full dump); this file grounds the plan, it cannot validate it.**

---

## 1. Tables in the dump, columns, and row counts

| # | Table | INSERT rows | Sampled? | Compact column list |
|---|---|---|---|---|
| 1 | approval_flows | 1 | full | id, name, approvable_type, project_id, is_active, is_default, created_at, updated_at |
| 2 | approval_flow_steps | 2 | full (ids 7–8, earlier ones deleted) | id, approval_flow_id, step_order, approver_type, approver_role_id, approver_user_id, label, created_at, updated_at |
| 3 | approval_instances | 89 | full (ids 1–89) | id, approval_flow_id, approvable_type, approvable_id, current_step_order, status, created_at, updated_at |
| 4 | approval_instance_steps | 178 | full (ids 3–182) | id, approval_instance_id, step_order, approver_type, approver_role_id, approver_user_id, label, status, acted_by_user_id, acted_at, comment, created_at, updated_at |
| 5 | bills | 89 | full (ids 1–89) | id, contractor_id, project_id, project_expendable_id, amount, status, xero_invoice_id, created_at, updated_at, deleted_at, transaction_type_id, xero_account_code, xero_tax_type, reference_number, due_date, currency |
| 6 | bill_payment_details | 79 | full (ids 1–79) | id, bill_id, contractor_id, payment_method, details, created_at, updated_at |
| 7 | clients | 94 | full (ids 1–169; 75 ids hard-deleted) | id, name, email, telegram_link_code, xero_contact_id, xero_contact_name, xero_contact_email, xero_sync_mode, xero_synced_at, xero_synced_by_user_id, phone, telegram_chat_id, address, notes, lead_id, pin, created_at, updated_at, timezone, active_telegram_project_id |
| 8 | deliverables | 285 | full (ids 1–285) | id, project_id, team_member_id, title, description, type, status, content_url, content_text, attachment_path, version, parent_deliverable_id, submitted_at, overall_approved_at, overall_approved_by_client_id, due_for_review_by, is_visible_to_client, created_at, updated_at, mime_type |
| 9 | deliverable_comments | 0 | empty | id, deliverable_id, client_id, comment_text, context, resolved_at, created_at, updated_at |
| 10 | invoices | 474 | full (ids 1–494) | id, client_id, project_id, total_amount, status, due_date, currency, line_amount_type, xero_branding_theme_id, xero_payment_service_ids, xero_invoice_id, invoice_number, created_at, updated_at, deleted_at |
| 11 | invoice_items | 575 | full (ids 1–576) | id, invoice_id, project_service_id, milestone_key, label, description, quantity, unit_price, tax_type, created_at, updated_at |
| 12 | projects | 105 | full (ids 2–106) | id, name, description, website, social_media_link, preferred_keywords, reporting_sites, google_chat_id, telegram_group_id, telegram_group_name, telegram_link_code, client_id, project_manager_id, project_admin_id, status, project_type, departments, services, service_details, source, total_amount, contract_details, google_drive_link, google_drive_folder_id, payment_type, logo, logo_google_drive_file_id, documents, last_email_sent, last_email_received, created_at, updated_at, timezone, deleted_at, project_tier_id, profit_margin_percentage, integrations, data, public_share_token, public_share_enabled |
| 13 | project_client | 127 | full (no PK) | project_id, client_id, role, role_id, created_at, updated_at |
| 14 | project_deliverables | 233 | full (ids 1–233) | id, project_id, milestone_id, name, description, details, status, due_date, completed_at, created_at, updated_at, deleted_at |
| 15 | project_expendables | 612 | full (ids 1–612) | id, name, description, payment_terms, project_id, user_id, currency, amount, balance, status, expendable_type, expendable_id, created_at, updated_at, deleted_at |
| 16 | project_services | 249 | full (ids 1–252) | id, project_id, enquiry_id, crm_service_id, description, amount, currency, frequency, start_date, payment_breakdown, status, service_tracking_type, show_on_leads_board, enquiry_status, enquiry_created_at, enquiry_updated_at, enquiry_meta, xero_account_code, created_at, updated_at |
| 17 | project_tiers | 3 | full | id, name, point_multiplier, min_profit_margin_percentage, max_profit_margin_percentage, min_client_amount_pkr, max_client_amount_pkr, created_at, updated_at, deleted_at |
| 18 | project_user | 441 | full (no PK) | project_id, user_id, role_id, created_at, updated_at |
| 19 | tasks | 100 | **SAMPLE** (ids 15763–15862) | id, parent_id, name, description, assigned_to_user_id, due_date, actual_completion_date, completed_at, status, task_type_id, milestone_id, project_deliverable_id, google_chat_space_id, google_chat_thread_id, chat_message_id, created_at, updated_at, creator_id, creator_type, deleted_at, deleted_by, priority, block_reason, previous_status, needs_approval, requires_qa, details, effort, manual_effort_override, source, source_id, additional_info |
| 20 | task_tag | 2 | full | id, task_id, tag_id, created_at, updated_at |
| 21 | task_types | 8 | full | id, name, description, created_by_user_id, created_at, updated_at |
| 22 | transactions | 100 | **SAMPLE** (ids 72–171) | id, project_id, description, currency, exchange_rate, amount, is_paid, payment_date, transaction_id, user_id, hours_spent, type, transaction_type_id, created_at, updated_at, deleted_at, client_id, bill_id, xero_payment_id, invoice_id, bank_transaction_id |
| 23 | transaction_types | 17 | full | id, name, slug, xero_account_code, created_by_user_id, created_at, updated_at |
| 24 | users | 87 | full (ids 1–88) | id, name, email, telegram_link_code, xero_contact_id, xero_contact_name, xero_contact_email, xero_synced_at, chat_name, telegram_chat_id, email_verified_at, password, remember_token, api_key, is_online, extension_mandatory, online_data, last_login_at, role_id, created_at, updated_at, timezone, checklist, notes, metadata, user_type, deleted_at |
| 25 | user_activities | 100 | **SAMPLE** (ids 387333–387432) | id, user_id, task_id, domain, url, title, is_incognito, is_audible, tab_count, duration, idle_state, category, is_category_override, hostname, browser, metadata, recorded_at, last_heartbeat_at, created_at, updated_at |
| 26 | user_otps | 179 | full (ids 2–208) | id, identifier, context, otp_hash, attempts, max_attempts, expires_at, verified_at, meta, created_at, updated_at |
| 27 | crm_services | 76 | full | id, name, xero_item_code, default_amount, default_currency, default_frequency, default_payment_breakdown, default_description, default_xero_account_code, created_at, updated_at |
| 28 | milestones | 100 | **SAMPLE** (ids 1486–1585) | id, project_id, name, description, completion_date, actual_completion_date, mark_completed_at, approved_at, completed_at, status, created_at, updated_at |
| 29 | project_notes | 100 | **SAMPLE** (ids 13456–13555) | id, project_id, noteable_id, noteable_type, content, type, chat_message_id, user_id, creator_id, creator_type, parent_id, created_at, updated_at, context |
| 30 | user_metadata_keys | 9 | full | id, key, label, type, created_at, updated_at |

Total: 5,398 rows. Native MySQL ENUMs exist only on `projects.status`, `projects.payment_type`, `tasks.status`, `tasks.priority`, `transactions.type`, `users.user_type`, `milestones.status`, `project_notes.type`; every other "status/type" column is `varchar` with free-text values (see §2).

Declared foreign keys in the dump reference four tables that are **not** in the dump: `roles` (approval_flow_steps, approval_instance_steps), `leads` (clients.lead_id), `tags` (task_tag). `ON DELETE` behaviour is inconsistent: many FKs are plain (RESTRICT) — e.g. `bills.*`, `invoices.*`, `tasks.assigned_to_user_id/milestone_id/task_type_id` — while others are `SET NULL` / `CASCADE`. Dump starts with `SET FOREIGN_KEY_CHECKS=0`.

---

## 2. Data-quality facts the migration must handle

### 2.1 Status / type / enum-ish value distributions

| Column | Values (count) | Notes for migration |
|---|---|---|
| projects.status (ENUM) | active 62, completed 23, on_hold 20 | `archived` never used; 43/105 soft-deleted, incl. 16 `active`+deleted and 21 `completed`+deleted — status and deleted_at are independent flags |
| projects.payment_type (ENUM) | one_off 94, monthly 11 | |
| projects.project_type (varchar) | wix-website 36, wordpress-website 22, NULL 9, custom-web-application 3, mobile-app-development 2, social-media-management 2, shopify-website 2, and **23 one-off values**, mostly `new_<slug>_<epoch-ms>` auto-generated keys (e.g. `new_wix-studio_1769656334823`) plus `Support`, `crm-integration`, `seo-service`, `google-ads-campaign`, `branding-identity`; one typo `new_emial-setup_…` | Needs a lookup/normalisation map to a project-type reference table |
| projects.source (varchar) | Direct 65, Wix Marketplace 20, UpWork 9, Other 6, Advertising 2, Website 1, Reference 1, NULL 1 | mixed casing/spacing; map to enum |
| projects.project_tier_id | 1 (Silver) 91, NULL 14; tiers 2/3 unused | |
| projects.public_share_enabled | 0: 78, 1: 27 (27 tokens set, all 64 chars) | |
| tasks.status (ENUM) | Done 49, To Do 37, Paused 13, In Progress 1 (sample) | Enum also allows Blocked, Archived; note `subtasks.status` in live schema uses lower-case `'to do','in progress','done','blocked'` — case mismatch between tasks and subtasks |
| tasks.priority (ENUM) | medium 86, high 14 | |
| tasks.source | local 50, extension_quick_task 50; `source_id` always NULL | |
| tasks.creator_type | App\Models\User 61, NULL 39 (creator_id NULL for the same 39) | morph with nullable pair |
| tasks.task_type_id | 3 ("New") 54, 4 ("Support") 26, 2 ("Changes") 17, 6 ("New" dup) 2, 7 ("New" dup) 1 | `task_types` has **duplicate names**: "New" ×4 (ids 3,6,7,8) and "Bug"/"Bugs" (ids 5,1) — dedupe + remap |
| tasks flags | needs_approval all 0, requires_qa all 0, previous_status all NULL, block_reason/details/additional_info all NULL, effort NULL 92, completed_at NULL 100 (even for 49 Done — completion tracked by `actual_completion_date`?) | many dead columns |
| milestones.status (ENUM) | Completed 78, In Progress 14, Pending 8 (sample); `completed_at` NULL for all 100, incl. 78 Completed | `Not Started`, `Overdue`, `Approved` unused in sample; completion timestamp lives in `mark_completed_at`/`actual_completion_date` — verify on live |
| project_notes.type (ENUM) | note 79, meeting_minutes 15, standup 6 (sample of 100) | 9-value enum; kudos/private/comment/resolved_comment not seen in sample |
| project_notes.noteable_type | App\Models\Task 79, NULL 21 | |
| project_notes.creator_type | App\Models\User 100; creator_id never NULL; **user_id NULL 79/100** — `creator_*` morph superseded the `user_id` FK | |
| project_notes.content | 100% plain text, 0 HTML; 85/100 carry a `chat_message_id` (Telegram/Google-chat origin); `context` always NULL; `parent_id` always NULL | |
| project_expendables.status | Accepted 329, Pending Approval 182, Rejected 71, Completed 30 | Title-case with spaces; normalise to snake_case |
| project_expendables.expendable_type | App\Models\Milestone 463, App\Models\Project 149 | morph target |
| project_deliverables.status | pending 135, completed 50, in_progress 48 | |
| deliverables.type | blog_post 250, other 26, design_mockup 9 | |
| deliverables.status | pending_review 284, for_information 1 | approval columns (`overall_approved_at/by`, `due_for_review_by`, `parent_deliverable_id`, `attachment_path`, `content_text`) are NULL for all 285; `version` always 1; `content_url` set for all; only 22 distinct projects have deliverables |
| deliverables.mime_type | google_doc 274, other 5, image 2, video 2, pdf 2 | not real MIME types — a category |
| bills.status | paid 53, pending_approval 22, approved 14; 21/89 soft-deleted (all 21 are `pending_approval`) | |
| bills.xero_tax_type | BASEXCLUDED 67, INPUT 18, NULL 3, EXEMPTOUTPUT 1 | |
| bills.xero_account_code | 310: 82, 474: 3, NULL 3, '0331124' 1 (looks like a typo/other field) | |
| bill_payment_details.payment_method | bank_transfer 66, bank_local 10, other 3; `details` is one-line free text (bank details — sensitive) | |
| invoices.status | paid 415, voided 39, rejected 10, authorised 10 (Xero vocabulary; `rejected` rows are the only ones without `xero_invoice_id`) | |
| invoices.line_amount_type | Exclusive 440, NoTax 23, Inclusive 11 | |
| invoices.invoice_number | 463 match `INV-####`; 11 NULL (all 11 NULL are `rejected`/local) | unique index must allow NULL or backfill |
| invoice_items.tax_type | OUTPUT 541, BASEXCLUDED 31, EXEMPTOUTPUT 3 | |
| invoice_items.milestone_key | ~41 `N-N-Payment N-N-…` composite keys, 6 `xero_sync_<random>`, rest NULL/other; `quantity` 1.0 (492), 8 (26), 2, 3 | it is a loose string key, not an FK to milestones |
| transactions.type (ENUM) | expense 86, income 14 (sample); `bonus` unused in sample | |
| transactions.is_paid | 1: 82, 0: 18 | |
| transactions.transaction_type_id | expense→3 "full-amount" 53, 7, 1, 2, 10, …; income→9 "direct-transfer" 14; 1 NULL | transaction_types 1–8 all map to Xero account 310 and are near-synonyms (initial-agreed-amount, full-amount, final-amount, initial-payment-30, 50-advance-payment…) — consolidate |
| transactions.exchange_rate | NULL 34, 1.0 21, ~190–197 (PKR/AUD) 21, 273.86 (PKR/USD) 2 | rate direction is "foreign per base"; NULL must be back-filled or treated as 1 |
| users.user_type (ENUM) | contractor 62, employee 10, guest 7, supplier 3, NULL 5 | 40/87 soft-deleted (30 contractors, 4 with NULL type) |
| users.role_id (FK to roles, not in dump) | 4: 61, NULL 10, 3: 4, 13: 4, 14: 3, 2: 2, 12: 2, 1: 1 | role ids 1,2,3,4,12,13,14 exist; role slugs cannot be resolved from this dump |
| project_user.role_id | 9: 236, 16: 110, 8: 82, 11: 7, 2: 6 | project-level roles are different ids from user-level roles |
| project_client.role / role_id | role 'Primary' ×127 (string) and role_id 5 ×127 | redundant pair, keep one |
| approval_flows / steps | 1 flow (approvable_type App\Models\Bill, is_default, project_id NULL); 2 steps, both approver_type=role (role 2 "Manager Approval", role 1 "Final Approval") | |
| approval_instances.status | completed 66, in_progress 23; all 89 target bills, every bill has exactly one instance | |
| approval_instance_steps.status | approved 140, pending 38; approver_user_id NULL for all; comment NULL for all | |
| user_activities.category | social_media 58, neutral 42; idle_state active 60/idle 38/locked 2; browser chrome 100; task_id NULL 88/100 | high-volume telemetry (see §5) |
| user_otps.context | login 179; identifier is an e-mail address in all rows; 176/179 verified; `meta` is always an empty JSON array | transient — do not migrate |
| user_metadata_keys.type | text 4, date 3, number 2; keys include `dob`, `Location` (typed *date*, wrongly), `joining-date`, `personal-email-i.d`, `emergency-contact` (typed *number*), `Address`, `Role`, `CNIC` (national ID, typed number), `Contact Number` | Values live in `users.metadata` JSON (see 2.4). **CNIC / personal e-mail / emergency contact are sensitive PII inside a free JSON blob** — the migration needs an explicit, access-controlled target for them |
| crm_services | 76 rows; default_amount/currency/frequency/description NULL for all; xero_item_code set 41; default_xero_account_code 201 (50)/NULL | catalogue only partially populated |

Columns/tables requested but **not in the dump** (cannot be profiled here): `emails.status/type/body/to/template_data`, `conversations.conversable_type/project_id`, `leads.status`, `files.fileable_type`, `roles`/`permissions` slugs. Their column shapes are known from the graphml (§4) and profiling queries for them are listed at the end of this section.

### 2.2 Null rates of key FKs

| FK | NULL / total | Comment |
|---|---|---|
| projects.client_id | **105/105** | Column is dead; client linkage lives entirely in `project_client` (127 rows, 103 projects, 90 clients, 23 projects have >1 client). Drop the column or backfill from the pivot's Primary row |
| projects.project_manager_id | 13/105 | |
| projects.project_admin_id | 7/105 | |
| projects.telegram_group_id | 61/105 | `integrations` JSON also carries telegram_general_topic_id (68) and bugherd_project_id (30) |
| clients.lead_id | 85/94 (**9 clients have a lead_id**) | `leads` not in dump — orphan check impossible |
| clients.xero_contact_id | 24/94 (70 synced, xero_sync_mode 'manual' 70 / NULL 24) | |
| clients.phone | 67/94 NULL; email never NULL; pin set on 16; telegram_chat_id / active_telegram_project_id NULL for all | |
| tasks.milestone_id | 0/100 (NOT NULL FK; tasks have no project_id — project is derived via milestone) | |
| tasks.assigned_to_user_id | 3/100 | |
| tasks.creator_id | 39/100 | |
| tasks.project_deliverable_id, parent_id, deleted_by | 100/100 | |
| milestones.project_id | **6/100 NULL** | FK is `ON DELETE CASCADE`, so these are milestones created without a project — the new schema's NOT NULL will reject them; decide drop vs. placeholder project |
| project_deliverables.milestone_id | 200/233 | |
| project_expendables.user_id | **222/612** (see 2.6) | |
| project_notes.user_id | 79/100 (creator_id never NULL) | |
| project_notes.noteable_id | 21/100 | |
| transactions.user_id | 14/100; client_id 86/100; bill_id 46/100; invoice_id 87/100; transaction_type_id 1/100; payment_date 18/100; transaction_id (self-ref) 100/100 | |
| transactions.xero_payment_id | 61/100; bank_transaction_id 30/100 | |
| bills.project_expendable_id | 10/89; contractor_id 1/89; transaction_type_id 1/89; xero_invoice_id 23/89; due_date 14/89; reference_number 12/89 | |
| invoices.xero_invoice_id | 10/474 (the `rejected` ones); xero_branding_theme_id 433/474 | |
| users.role_id | 10/87 | |
| user_activities.task_id | 88/100 | |
| approval_instance_steps.acted_by_user_id | 38/178 (= the 38 pending steps) | |

### 2.3 Body / text classification

`emails` is not in the dump, so the HTML-vs-plain-vs-JSON classification of `emails.body`, `template_data` double-encoding and `emails.to` shape **could not be measured**. From the graphml: `emails.body` is `longtext`, `template_data` is `longtext` (not `json`), `draft_meta` is native `json`, `to` is **`varchar(255)`** (so a JSON array of recipients will truncate at 255 — check `LENGTH(to)=255` on live), `subject` varchar(255), `rfc_message_id` varchar(512), `gmail_thread_id` varchar(128), and `status` is a 12-value ENUM: draft, pending_approval, approved, rejected, sent, received, pending_approval_received, auto_send, unknown, pending, delayed, saved.

Every text/longtext column that *is* in the dump was classified (NULL / empty / JSON object / JSON array / JSON string / HTML / plain):

- **No column contains HTML** and **no column is double-encoded JSON** (0 rows where a JSON string wraps JSON).
- JSON-object columns: `projects.integrations` (68; keys telegram_general_topic_id, bugherd_project_id), `projects.data` (8; key action_points), `project_deliverables.details` (233/233; keys deliverable_type_key, checklist), `project_expendables.payment_terms` (48; keys type, installments, hourly_rate, estimated_hours, months, monthly_amount, notes, currency), `users.metadata` (38), `users.online_data` (30), `users.notes` (5), `user_activities.metadata` (100/100; extension telemetry), `project_services.enquiry_meta` (7 objects / 242 empty arrays — mixed shape `[]` vs `{}`).
- JSON-array columns: `projects.services` (75; arrays of 1–5 service *strings*), `projects.service_details` (75; arrays of objects that **duplicate `project_services` rows** — same enquiry_id/service_id/amount/currency/frequency/payment_breakdown/enquiry_* keys, 249 elements, 231 of them carrying a `project_service_id`; the JSON is the pre-normalisation copy), `projects.documents` (2), `invoices.xero_payment_service_ids` (44), `project_services.payment_breakdown` (249/249; arrays of {label, percentage, due_date}, 1–5 elements), `users.checklist` (2), `user_otps.meta` (179, all empty).
- Plain text only: `project_notes.content`, `invoice_items.label/description`, `bill_payment_details.details`, `tasks.description` (50 set), `milestones.description` (93 set), `deliverables.description` (15), `clients.address/notes`, `project_services.description` (49 are **empty string**, 160 NULL, 40 text — normalise '' → NULL).
- Always NULL (dead): `projects.departments`, `projects.contract_details`, `deliverables.content_text`, `tasks.details/additional_info/block_reason`, `project_notes.context`, `approval_instance_steps.comment`, `crm_services.default_payment_breakdown/default_description`.

### 2.4 Users

- 87 users (ids 1–88); 40 soft-deleted. Password hashes are all bcrypt (`$2y$`), so they can be carried over if the new auth stack uses bcrypt. `email_verified_at` is NULL for **all 87** (legacy relies on OTP login instead). `api_key` set on 34 (64-char), `remember_token` present, `telegram_link_code` on 3, `extension_mandatory` 1 on 32, `is_online` 1 on 17 (stale state).
- No duplicate e-mails among users or among clients. Users and clients are separate identity pools (user ↔ client overlap not checkable without matching e-mails — do it on live).
- `users.metadata` (38 rows) holds per-user key/values keyed by `user_metadata_keys.key` (dob 29, Location 28, joining-date 27, personal-email-i.d 27, emergency-contact 27, Address 27, Role 27, CNIC 27, Contact Number 24) plus keys **not** declared in `user_metadata_keys`: `phone` 23, `payment_methods` 2, `business_name` 2, `verification_details` 2. Migration needs a defined EAV/JSON target and PII handling.
- Timezones: users Asia/Karachi 60, NULL 18, America/Los_Angeles 4, Australia/Perth 2, others 2; projects Asia/Karachi 44, Australia/Perth 21, NULL 11, Brisbane 7, Melbourne 5; clients NULL 78. Default TZ policy needed.

### 2.5 Clients vs leads

- 94 clients; **9 have `lead_id`** (leads table absent — verify those 9 resolve on live). `leads` in the live schema has its own first_name/last_name/email/phone/company/status/source/pipeline_stage/estimated_value/currency (varchar(3)) and `email_thread_history longtext`, `metadata longtext`, `tags varchar(255)` (denormalised tags string).
- `conversations.conversable_type/id` (Client vs Lead) not in dump; conversations also carry `project_id` and `contractor_id`.

### 2.6 project_expendables → bills → transactions chain

- 612 expendables: user_id NULL 222 / set 390. By status: Accepted with user 248 / **Accepted without user 81**, Pending Approval without user 141 / with user 41, Rejected 71 (all with user), Completed 30 (all with user). `balance == amount` for 556, balance 0 for 43 (partial-payment tracking barely used).
- 65 expendables have a bill; **293 `Accepted` expendables have no bill**. 10 bills have no expendable.
- Bills: every bill has an approval_instance; 79 bills have payment details.
- transactions (sample) by (type, has bill, has invoice, is_paid): expense+bill+paid 54; expense, no bill, unpaid 17; expense, no bill, paid 15; income+invoice+paid 13; income, no invoice, unpaid 1. All 14 income rows have a client_id. `hours_spent` used once. Self-referencing `transaction_id` never used.

### 2.7 Currencies

Distinct values (all upper-case ISO, no casing problems): `PKR`, `AUD`, `USD`, plus NULL.

| Table | PKR | AUD | USD | NULL |
|---|---|---|---|---|
| bills | 71 | 12 | 6 | 0 |
| project_expendables | 583 | 16 | 13 | 0 |
| transactions (sample) | 73 | 21 | 6 | 0 |
| invoices (varchar(3)) | 0 | 442 | 31 | 1 (a `voided` row) |
| project_services | 2 | 210 | 28 | 9 |
| crm_services.default_currency | – | – | – | 76 |

Column widths vary (varchar(255) / varchar(10) / varchar(3)); standardise to CHAR(3) NOT NULL with a default. Paid invoice totals: AUD ≈ 168.6k, USD ≈ 8.3k across 2024–2026. Cost side is PKR-dominant, revenue side AUD-dominant; `transactions.exchange_rate` is the only stored rate (34% NULL) and `currency_rates` (rate_to_usd) is not in the dump.

### 2.8 Date ranges (created_at min → max)

| Table | min | max | Notes |
|---|---|---|---|
| users | 2025-07-22 | 2026-08-20 | deleted_at 2025-08-14 → 2026-08-31 |
| clients | 2025-07-22 | 2026-08-25 | |
| projects | 2025-07-22 | 2026-08-28 | deleted_at 2025-10-22 → 2026-06-01 |
| invoices | **2024-02-12** | 2026-08-31 | 37 rows dated 2024, 183 in 2025 — historical Xero import predates the app (updated_at starts 2026-06-13, the import date) |
| invoice_items | 2026-06-09 | 2026-08-31 | items only exist from the Xero-sync era; 2 invoices have no items |
| deliverables | 2025-08-03 | 2026-07-14 | |
| project_deliverables | 2025-08-09 | 2026-05-11 | |
| project_expendables | 2025-08-16 | 2026-08-28 | |
| bills | 2026-06-08 | 2026-08-31 | |
| bill_payment_details | 2026-06-08 | 2026-08-29 | |
| approval_* | 2026-07-23 | 2026-08-31 | approvals feature is ~6 weeks old |
| project_services / crm_services | 2026-06-01 | 2026-08-27 | services model introduced 2026-06 |
| transactions (sample) | 2025-08-25 | 2026-08-31 | |
| tasks (sample) | 2026-08-31 | 2026-08-31 | latest-100 only |
| milestones (sample) | 2026-07-29 | 2026-08-31 | |
| project_notes (sample) | 2026-08-29 | 2026-08-31 | ~100 notes per 2.5 days |
| user_activities (sample) | 2026-08-31 17:05 | 2026-08-31 20:40 | **100 rows per ~3.5 h** |
| user_otps | 2026-08-02 | 2026-08-31 | |

All timestamps are dumped with `TIME_ZONE='+00:00'`.

### 2.9 Orphans (FK values with no parent, both tables in dump)

Genuine (parent table fully present):

- **None** among fully-present pairs: bills→users/projects/expendables/transaction_types, bill_payment_details→bills/users, invoices→clients/projects, invoice_items→invoices/project_services, project_client, project_user, project_expendables→projects/users, project_services→projects/crm_services, deliverables→projects/users, approval_*→flows/instances/users, transactions→projects/users/clients/bills/invoices/transaction_types, task_types/transaction_types→users, projects→users/project_tiers all resolve (0 orphans). Referential integrity within the dumped set is good.
- **Duplicate key check**: 0 duplicate (project_id, client_id) in project_client and 0 duplicate (project_id, user_id) in project_user (both lack a PK — add composite PKs); 0 duplicate emails; 0 duplicate non-NULL invoice numbers.

Apparent orphans that are **sampling artefacts** (parent is a 100-row sample) — re-run on live:

- tasks.milestone_id → milestones: 92/100 unresolved
- project_deliverables.milestone_id → milestones: 33/33 unresolved
- project_expendables (expendable_type=Milestone).expendable_id → milestones: 455/463 unresolved
- project_notes (noteable_type=Task).noteable_id → tasks: 37/79 unresolved
- user_activities.task_id → tasks: 12/12 unresolved (one task id)
- task_tag.task_id → tasks: 2/2 (ids 5, 6 — old tasks)

Unresolvable here (parent not in dump): users.role_id (77 non-NULL), project_user.role_id (441), project_client.role_id (127), approval_*.approver_role_id (180), clients.lead_id (9), task_tag.tag_id (2), tasks/project_notes.chat_message_id (79 + 85 non-NULL, varchar — the graph draws it as an FK to chat_messages, the dump does not declare one).

### 2.10 Live-DB profiling queries still needed (tables absent from dump)

```sql
SELECT status, type, COUNT(*) FROM emails GROUP BY 1,2;
SELECT SUM(conversation_id IS NULL), SUM(body IS NULL), SUM(body LIKE '<%' OR body LIKE '%<html%' OR body LIKE '%<div%' OR body LIKE '%<p>%'),
       SUM(body LIKE '{%'), SUM(LENGTH(`to`)=255), SUM(`to` LIKE '[%'), SUM(template_data LIKE '"%'), COUNT(*) FROM emails;
SELECT conversable_type, SUM(project_id IS NULL), COUNT(*) FROM conversations GROUP BY 1;
SELECT status, pipeline_stage, COUNT(*) FROM leads GROUP BY 1,2;
SELECT fileable_type, COUNT(*), SUM(file_size) FROM files GROUP BY 1;
SELECT id, slug, type FROM roles; SELECT category, COUNT(*) FROM permissions GROUP BY 1;
SELECT type, source, COUNT(*) FROM chat_messages GROUP BY 1,2;
SELECT MIN(id), MAX(id), COUNT(*) FROM tasks; -- repeat for milestones, project_notes, transactions, user_activities, emails, chat_messages
```

---

## 3. Legacy tables NOT in the dump

Compared against the supplied list of ~80 expected tables, **49 are missing from the dump** (present in the live schema per graphml):

`users`-adjacent: roles, permissions, role_permission, user_remembered_devices, google_accounts, otp_verifications, magic_links, user_availabilities, user_productivities, points_ledgers, kudos, bonus_configurations, bonus_transactions, monthly_budgets.
CRM/comms: leads, campaigns, conversations, **emails**, email_templates, placeholder_definitions, chat_messages, telegram_topics, telegram_accounts, notifications, activity_log, comments, subtasks.
Content/files: files, documents, tags, taggables, categories, category_sets, categorizables, presentations, slides, content_blocks, wireframes, shareable_resources, resources, seo_reports.
Automation/ops: workflows, workflow_steps, execution_logs, prompts, schedules, meetings, meeting_attendees, daily_tasks, client_vault_credentials, currency_rates, xero_connections, xero_tenants, stripe_* (stripe_configurations, stripe_payouts, stripe_subscriptions, stripe_subscription_payments).

Present in the dump but not in the supplied list (additions the plan should include): `approval_flow_steps`, `approval_instance_steps`, `deliverable_comments`, `project_tiers`, `task_tag`, `user_metadata_keys`.

The graphml further reveals **43 live tables that are in neither the supplied list nor the dump**: airwallex_xero_bank_mappings, bonus_configuration_groups, bonus_configuration_group_items, project_bonus_configuration_group, campaign_shareable_resource, category_set_bindings, client_deliverable_interactions, client_vault_credential_user, components, icons, contexts, database_sync_runs, database_sync_table_runs, email_apps, email_app_template, email_template_placeholder, external_email_logs, invoice_comments, monthly_points, presentation_metadata, presentation_user, project_google_chat_members, user_interactions, user_notes, wireframe_versions, xero_payment_services, plus Laravel framework tables (cache, cache_locks, jobs, job_batches, failed_jobs, migrations, sessions, password_reset_tokens, personal_access_tokens, login_attempts, pulse_aggregates, pulse_entries, pulse_values).

**Conclusion: this dump covers 30 / 126 live tables and samples 5 of those 30. It is sufficient to design mappings for the finance/project core (projects, clients, users, invoices, bills, expendables, services, approvals, deliverables) but the communications core (emails, conversations, leads, chat_messages, files) — which the CRM restructure is largely about — is entirely absent. The full migration must be built and rehearsed against the live database or a complete dump, with the profiling queries in §2.10 run first.**

---

## 4. The graphml ER diagrams

- **The two files are byte-identical** (`cmp` returns no difference; md5 `65287aa9302039c1bc2b3a72c7f0422e`, both 536,222 bytes). `-b06e5027` is just a second export/download of the same yEd-Live document — there is nothing to reconcile.
- Format: yFiles for HTML GraphML with `uml:UMLClassModel` nodes; each node's `className` is the table, attributes/operations arrays list `column: type` (non-varchar columns in "attributes", varchar columns in "operations" — an artefact of the exporter, not a semantic split). A few column strings carry SQL column comments (e.g. `deliverables.status /* e.g., pending_review, approved, revisions_requested, completed */`, `tasks.manual_effort_override /* Manual override for total duration in seconds */`, `placeholder_definitions.is_dynamic /* e.g. magic_link */`).
- **126 table nodes, 178 edges**; every edge is labelled `<fk_column>:id`. Most-referenced: users (53 in-edges), projects (31), clients (9), roles (6), tasks (5), categories (4), xero_connections (3), chat_messages (3). 23 nodes are isolated (no edges): activity_log, cache, cache_locks, currency_rates, failed_jobs, job_batches, jobs, login_attempts, migrations, monthly_budgets, notifications, otp_verifications, password_reset_tokens, personal_access_tokens, pulse_*, schedules, stripe_configurations, stripe_payouts, telegram_accounts, user_metadata_keys, user_otps.
- All 30 dump tables appear in the graph, and **every declared FK in the dump appears as an edge** (0 missing). Column sets match exactly for all 30 (only difference: the graph appends SQL comments to 8 column names).
- **Relationships the graph adds that the dump's DDL does not declare** (i.e. logical/Eloquent relations without DB constraints — the migration must enforce them itself):
  - `users.role_id → roles`, `project_user.role_id → roles`, `project_client.role_id → roles`
  - `tasks.chat_message_id → chat_messages`, `project_notes.chat_message_id → chat_messages` (varchar columns holding ids)
  - `transactions.transaction_id → transactions` (self-ref, unused)
  - and for absent tables: `emails → conversations/email_templates/users(approved_by)/emails(in_reply_to)`, `conversations → projects, users(contractor_id)`, `leads → campaigns, users(assigned_to_id, created_by_id)`, `files → projects`, `resources → files`, `subtasks → tasks(parent_task_id)`, `daily_tasks → tasks`, `chat_messages → projects/users/clients/telegram_topics/self(parent_id)`, `client_vault_credentials → clients/projects/users`, `magic_links → projects/email_apps`, `external_email_logs → magic_links/email_apps/projects`, `xero_tenants/xero_payment_services/airwallex_xero_bank_mappings → xero_connections`, `user_availabilities → categories (×3 reason FKs)`, `bonus_transactions → bonus_configurations/projects/users`, `points_ledgers/kudos → projects/users`, `invoice_comments → invoices/projects/users`, `stripe_subscription_payments → stripe_subscriptions`.
  - Polymorphic relations (`*_type/*_id` pairs: taggables, categorizables, comments.commentable, conversations.conversable, files.fileable, project_notes.noteable, project_expendables.expendable, approval_instances.approvable, tasks.creator) are **not** drawn as edges — the graph under-represents morph links.
- The graph carries no cardinality, index, nullability or ON DELETE information; use the dump DDL for those where available.

---

## 5. Volume estimate for ETL sizing

"Live estimate" uses the max id seen (auto-increment high-water mark) as a proxy where the dump is a sample; hard-deleted gaps mean actual live counts are somewhat lower.

| Table | Rows in dump | Live estimate | Avg row size (dump) | Suggested chunk | Notes |
|---|---|---|---|---|---|
| user_activities | 100 (sample) | **≥ 387k, growing ~700/day** | ~600 B (url/title/JSON) | 5,000 | Telemetry; consider migrating only last N days or aggregating; biggest table |
| tasks | 100 (sample) | ≥ 15.9k | ~400 B | 2,000 | 50% created by browser extension |
| project_notes | 100 (sample) | ≥ 13.6k | ~500 B (content up to 1k+) | 2,000 | |
| milestones | 100 (sample) | ≥ 1.6k | ~300 B | 2,000 | 6% have NULL project_id |
| transactions | 100 (sample) | ~170 | ~250 B | 1,000 | |
| project_expendables | 612 | ~612 | ~250 B | 1,000 | |
| invoice_items | 575 | ~576 | ~300 B | 1,000 | |
| invoices | 474 | ~494 | ~300 B | 1,000 | |
| project_user | 441 | 441 | small | 1,000 | no PK |
| deliverables | 285 | 285 | ~300 B | 1,000 | |
| project_services | 249 | ~252 | ~600 B (JSON) | 500 | |
| project_deliverables | 233 | 233 | ~500 B (JSON checklist) | 500 | |
| user_otps | 179 | ~208 | small | skip | transient |
| approval_instance_steps | 178 | 182 | small | 1,000 | |
| project_client | 127 | 127 | small | 1,000 | no PK |
| projects | 105 | 106 | ~2 KB (service_details JSON) | 200 | |
| clients | 94 | ~169 ids | ~300 B | 500 | |
| bills | 89 | 89 | ~200 B | 500 | |
| approval_instances | 89 | 89 | small | 500 | |
| users | 87 | 88 | ~1 KB (metadata JSON) | 200 | |
| bill_payment_details | 79 | 79 | small | 500 | sensitive |
| crm_services | 76 | 76 | small | 500 | |
| transaction_types | 17 | 17 | small | all | consolidate |
| user_metadata_keys | 9 | 9 | small | all | |
| task_types | 8 | 8 | small | all | dedupe |
| project_tiers | 3 | 3 | small | all | |
| approval_flow_steps | 2 | 2 | small | all | |
| task_tag | 2 | 2 | small | all | |
| approval_flows | 1 | 1 | small | all | |
| deliverable_comments | 0 | 0 | – | – | |

Not in dump — sizes must be taken from live (`SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH FROM information_schema.TABLES WHERE TABLE_SCHEMA='ozee-crm-admin-c9'`): emails (longtext bodies + AI columns — expect the largest by bytes), chat_messages, conversations, leads, files, activity_log, notifications, execution_logs, pulse_*.

Practical sizing: the whole dumped core is < 6k rows / 1.5 MB; even at live volumes everything except `user_activities` (and probably `emails`/`chat_messages`/`activity_log`) fits in a single-pass, transaction-per-table ETL. Chunk by primary-key range (not OFFSET) for the ≥10k tables; order the load users → roles → clients → project_tiers → projects → project_client/project_user → crm_services → project_services → milestones → project_deliverables → tasks → project_expendables → transaction_types → bills → approval_* → invoices → invoice_items → transactions → deliverables → project_notes → user_activities, with FK checks disabled only for the morph/varchar links listed in §4.
