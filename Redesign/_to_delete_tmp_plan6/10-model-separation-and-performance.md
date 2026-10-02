# 10 — Model separation register & performance rules

The user's rule: *wherever a legacy model was made to do several jobs, v2 separates it, following industry standards, and stays performance-focused.* This file is the checklist executors verify against. Each row: legacy overload → v2 shape → why → where specified.

## A. Overloaded legacy models → separated v2 models

| # | Legacy (one table, many jobs) | v2 (one job per table) | Standard / rationale | Spec |
|---|---|---|---|---|
| 1 | `project_notes` = project note, standup, kudos, task comment, milestone comment, deliverable comment, document comment, user note, wireframe annotation | `comments` (polymorphic subject + polymorphic author, threaded, anchored, kind, visibility) · `standups` (per user/project/day) · kudos → Recognition (R2) | GitHub/Jira/Linear "comment" model; standups are a distinct periodic record with uniqueness rules | 02 §01, §04 |
| 2 | `project_expendables` = budget line (`user_id NULL`) **and** contractor proposal/contract (`user_id` set) | `budgets` (planned spend per project/milestone, one per milestone) · `proposals` (supplier quote; `accepted` = contract; `billed_amount`, terms) | Budget is planning data; proposal is a negotiated document with a lifecycle and a counter-party — different owners, permissions, and states | 02 §06, 03 §3.6 |
| 3 | `transactions` = project income, project expense, bonus payout, invoice payment, bill payment | `payments` (money against an invoice or bill, with FX snapshot and bank ref) · `ledger_entries` (P&L lines not tied to a document) · `bank_transactions` (imported feed, matched to payments) · bonus → Recognition | Accounting separation of *documents* (invoice/bill), *settlements* (payments), *journal lines* (ledger) and *bank feed* (reconciliation) | 02 §06 |
| 4 | `project_services.payment_breakdown` JSON keyed by `invoice_items.milestone_key` | `service_milestones` (payment terms rows with `key`, amount/percent, due rule, state) as a real FK target with uniqueness | Relational children, never JSON paths as foreign keys | 02 §06, 09 §2 |
| 5 | `deliverables` (client approval artefact) vs `project_deliverables` (scope checklist) — confusingly named twins | `deliverables` (+ `deliverable_versions`, `deliverable_reviews`) · `scope_items` | Different lifecycles: one is a submission for approval, the other a promise checklist | 02 §04 |
| 6 | `tasks` + `subtasks` (two hierarchies) | `tasks.parent_id` only | One tree | 02 §04 |
| 7 | `files` + `documents` + `projects.documents` JSON + `deliverables.attachment_path` + `resources.file_id` | `attachments` (single polymorphic store with disk/path/kind/purpose/expiry) | Single media library; ownership by morph; explicit storage driver | 02 §01 |
| 8 | `tags/taggables` + `task_tag` + `leads.tags` string + `category_sets/categories/categorizables` | `taxonomies` / `terms` / `term_assignments` | WordPress/Drupal-style taxonomy: one mechanism, many vocabularies | 02 §01 |
| 9 | `leads` + `clients` (same person at two lifecycle stages; `clients.lead_id`) | `contacts` with `stage` + `pipeline_status`; `businesses` for the organisation; `business_contacts` with relationship | CRM standard: Contact (person) / Account-Business (organisation) / lifecycle stage; conversion keeps identity and history | 02 §03, 09 §2 |
| 10 | `emails.body` holding text / markdown / HTML / blocks / NULL-for-template / AI JSON; `template_data` triple payload double-encoded; `to` JSON-in-varchar(255) | `messages.body_format` + exactly one of `body` / `body_blocks` / `template_values`; derived `body_text`; `rendered_html` at send; `message_recipients` rows; `headers` JSON | One representation per row; derived columns for search/AI; recipients relational | 02 §05 |
| 11 | `emails` status/approval columns + `approval_flows` (bills only) + 7 other hand-rolled approval mechanisms | `approval_requests` (generic subject, kind, status, decider, reason, step, flow) + `approval_flows` for multi-step | One approval engine; subject modules react to `ApprovalDecided` | 02 §01 |
| 12 | `magic_links` (client link + external API token + share token) + `projects.public_share_token` | `access_links` (kind, hashed token, scope, whitelist, uses) | Single link model; hashed at rest | 02 §07 |
| 13 | `user_otps` + `otp_verifications` + `magic_links.temporary_pin` + `clients.pin` | `one_time_codes` (purpose, identifier, scope, hash) + `contacts.portal_pin_hash` | One OTP store; PINs hashed | 02 §07 |
| 14 | `users.metadata/notes/checklist` JSON + `user_metadata_keys` EAV + `bill_payment_details` | `user_profiles` · `payout_methods` (encrypted, per user, default flag) · `personal_checklist_items` · comments | Typed tables instead of EAV blobs; payout details stored once per supplier, snapshotted on bills | 02 §02, §06 |
| 15 | `activity_log` used as application state (task time via replay) | `task_time_entries` (+ `activity_log` pure audit) | Audit logs are append-only evidence, never the source of business values | 02 §04 |
| 16 | `project_user` + `projects.project_manager_id/admin_id` + `project_client` + `projects.client_id` | `project_members` (role) · `project_contacts` (reach[]) · `projects.client_id`/`business_id` as pointers kept in sync by actions | One membership table per party type; pointers are denormalised conveniences with a single writer | 02 §04 |
| 17 | `schedules` polymorphic cron table used for emails, tasks, workflows | `messages.scheduled_for` (R1); generic scheduler R2 | Don't generalise before there are three real users of the abstraction | 07 |
| 18 | `email_apps` sparse SMTP/API + `external_email_logs` | `mailboxes` (provider enum + typed config) · external send API R2 | Provider strategy behind one contract | 02 §05 |
| 19 | `xero_connections` + `xero_tenants` + loose `xero_*` columns on 6 tables + JSON FK arrays | `xero_connections` (one per tenant) · `xero_accounts` · `xero_links` (generic local↔remote map with sync state) · pivots | Integration mapping table pattern; no provider ids scattered as columns | 02 §06 |
| 20 | `shareable_resources` shared by `NoticeBoard` via a boolean discriminator | `announcements` (Platform) now; resource library R2 | STI by boolean with mismatched vocabularies is not a pattern | 02 §01, 07 |
| 21 | `roles` with `type` string and no hierarchy; permissions seeded in 9 places | `roles.level` + `scope` · `permissions` = actions catalogue (module, guard) seeded from **one** config · overrides in `role_permission.granted/denied` | Three-ceiling model from the Admin Console; single source of truth | 09 §2 |
| 22 | `workflows` JSON-tree engine driving email approval | explicit `Comms` pipeline (jobs + state machine); workflow engine R2 with real `parent_id` | Critical paths are code, not interpreted config | 03 §3.5 |

Rule for anything not in this table: if a table needs a discriminator column *and* the two branches have different nullable-column sets, different permissions, or different lifecycles → two tables (or a parent table + typed child tables). If they only differ by a label → one table with an enum.

## B. Performance rules (enforced by tests/lint, see 08)
1. **No computed relations in serialisation**: `$appends` that query are banned (lint grep). Values like `total_time_spent`, `paid_amount`, `logo_url`, `can_approve` become Query DTO fields computed in one SQL pass or cached counters.
2. **Denormalised counters/timestamps with a single writer**: `threads.needs_reply_since/last_*`, `invoices.amount_paid`, `proposals.billed_amount`, `projects` stat tiles (cached 60 s) — updated by the Action that changes the source, verified by a nightly `audit:*` command.
3. **Indexes designed with the queries** (02 lists them); every list Query has an `EXPLAIN` check in H5-02 and a query-count assertion (≤ 12 per page, ≤ 4 per API list).
4. **Eager loading declared per Query**, `preventLazyLoading` in tests; no global `$with` on models (legacy `User::$with` + global `ORDER BY` scope are exactly what not to do).
5. **Pagination everywhere** (cursor pagination for inbox/feeds), never `->get()` on unbounded tables; bulk actions chunked.
6. **JSON only for genuinely document-shaped data** (block documents, report payloads, settings); never for lists of FKs or anything filtered in WHERE.
7. **Enums as VARCHAR + CHECK** (cheap ALTERs, indexable); money as DECIMAL(19,4) (no float, no int-minor-units mixing).
8. **Permission resolution cached** per user (invalidated by role/permission events) and shipped in shared props — zero per-request permission queries on pages.
9. **Queue design**: separate `mail`, `ai`, `sync` queues with supervised workers; long jobs are idempotent and resumable; polling jobs `WithoutOverlapping`.
10. **Signed URLs / thumbnails generated on demand**, never in list serialisation; attachments expire by purpose and are pruned.
11. **Search** via FULLTEXT `search_text` columns rebuilt by `Searchable` on save (no `LIKE %x%` chains across six columns as legacy did).
12. **Frontend**: Inertia partial reloads (`only`), TanStack Query caching for live lists, code-splitting per page, bundle budget 350 kB gz, no inline-style hover hacks in feature code (CSS Modules).
