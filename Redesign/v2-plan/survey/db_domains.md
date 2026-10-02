# Database survey — Domains 1–15

Companion to `db.md` (which holds Domains 16–18 and Appendices A–F). Same conventions: one section per table, final consolidated column list after all migrations, then a numbered "smells / decisions needed" table per domain. Column types are as declared by the migrations (MySQL-targeted). "FK" means a real `constrained()` / `foreign()` constraint; a bare `unsignedBigInteger`/`foreignId()` without `constrained()` is noted as **no FK**.

Model paths are relative to `/home/claude/src/`. Migration paths are relative to `/home/claude/src/database/migrations/` and abbreviated to their timestamp prefix.

**Runtime assumptions that matter for the smells below:** `config/database.php` sets `'strict' => true` for MySQL, so every "value not in the DB enum" or "NOT NULL column never set" finding is a hard insert/update failure, not a silent `''`/`0`. `Model::preventSilentlyDiscardingAttributes()` is **not** enabled, so mass-assigning a non-fillable or non-existent column (several cases below) is silently dropped.

---

## Domain 1 — Users, roles, permissions, auth

### `users`

Model: `app/Models/User.php` · Migrations: `0001_01_01_000000`, `2023_07_17_201922` (**empty — adds nothing**), `2025_07_22_014221`, `2025_07_29_122437`, `2025_08_09_034448`, `2025_08_10_225600`, `2025_08_14_182500`, `2025_08_26_231500`, `2026_02_16_205528`, `2026_03_03_223729`, `2026_03_04_072935`, `2026_03_14_044145`, `2026_04_05_172524`, `2026_05_11_112312_add_metadata`, `2026_05_12_054425`, `2026_08_04_155648`

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | bigint auto | — | |
| `name` | string | NOT NULL | global scope orders by it |
| `email` | string | NOT NULL, **unique** | |
| `chat_name` | string | null | Google Chat display name |
| `telegram_chat_id` | bigint | null, **unique** | duplicated by `telegram_accounts` (see §11) |
| `telegram_link_code` | string | null, **unique** | one-time link code |
| `xero_contact_id` | string | null, indexed | contractor as Xero contact |
| `xero_contact_name` | string | null | |
| `xero_contact_email` | string | null | |
| `xero_synced_at` | timestamp | null | |
| `email_verified_at` | timestamp | null | cast datetime |
| `password` | string | NOT NULL | cast `hashed` |
| `remember_token` | varchar(100) | null | |
| `api_key` | varchar(64) | null, **unique** | hidden; `generateApiKey()` = 32 random bytes hex |
| `is_online` | boolean | default false | |
| `online_data` | json | null | cast array `{last_status_change, source, status, reason}` |
| `extension_mandatory` | boolean | default false | browser-extension enforcement |
| `last_login_at` | datetime | null | |
| `role_id` | ubigint | null, **no FK** | `foreignId()` without `constrained()` |
| `timezone` | string | null | |
| `checklist` | json | null | cast array |
| `notes` | json | null | cast array — **duplicates `user_notes` and the `noteable` morph** |
| `metadata` | json | null | cast array; keys registered in `user_metadata_keys` |
| `user_type` | enum(`employee`,`contractor`,`guest`,`supplier`) | null | widened twice by raw `ALTER TABLE` (MySQL-only) |
| `deleted_at` | timestamp | null | SoftDeletes |
| timestamps | | | |

**Indexes/FKs:** unique `email`, `telegram_chat_id`, `telegram_link_code`, `api_key`; index `xero_contact_id`. **`role_id` has no FK constraint.**

**Traits:** `HasApiTokens` (Sanctum), `HasFactory`, `Notifiable`, `SoftDeletes`, `HasCategories`, `LogsActivity` (logs `metadata`, `name`, `email`, `role_id`, dirty only).
**Global scope:** `orderByName` — every `User` query is `ORDER BY users.name`, including subqueries and `whereHas` closures.
**`$with = ['role', 'categories']`** — global eager load on every User query.
**`$appends = ['role_data', 'avatar', 'app_role']`** — `avatar` is a ui-avatars.com URL, `app_role` defaults to `'employee'` when no role.
**`$hidden`:** `password`, `remember_token`, `google_access_token`, `google_refresh_token`, `api_key`.
**`$fillable` includes `google_id`, `google_access_token`, `google_refresh_token`, `google_expires_in`** — none of these columns exist (see F10 in db.md). The three `getGoogle*Attribute()` accessors proxy to `googleAccount()` with a fresh query each call.

**Relations:** `role()` belongsTo Role · `projects()` belongsToMany Project via `project_user` withPivot `role_id` · `notes()` morphMany ProjectNote `noteable` · `userNotes()` hasMany UserNote · `projectExpendable()` hasMany ProjectExpendable · `conversations()` hasMany Conversation `contractor_id` · `sentEmails()` hasMany Email `sender_id` (**ignores `sender_type` morph**) · `approvedEmails()` hasMany Email `approved_by` · `assignedTasks()` hasMany Task `assigned_to_user_id` · `assignedSubtasks()` hasMany Subtask · `createdTaskTypes()` hasMany TaskType `created_by_user_id` · `createdTags()` hasMany Tag `created_by_user_id` (**dropped column**) · `availabilities()` hasMany UserAvailability · `meetings()` belongsToMany Meeting via `meeting_attendees` withPivot · `bonusTransactions()` hasMany · `googleAccount()` hasOne GoogleAccounts · `points()` hasMany PointsLedger · `monthlyPoints()` hasMany MonthlyPoint · `kudosSent()`/`kudosReceived()` hasMany Kudo · `contexts()` hasMany Context · `productivities()` hasMany UserProductivity · `activeTask()` hasOne Task where status In Progress · `telegramAccount()` morphOne TelegramAccount `telegramable`.
**Accessor-as-relation:** `getClientsAttribute()` loads all projects then queries `Client::whereIn`.
**Scopes:** `withProjectRole($projectId)`.
**Role helpers:** `isSuperAdmin/isManager/isEmployee/isContractor` compare `app_role` slug strings; `isManager()` also accepts `'assistant-manager'` — **a slug no seeder creates**. `hasPermission()` goes through `role->hasPermission()` (an `exists()` query per call). `hasProjectPermission()` does `Role::find()` + a permissions query per call.
**`onlineActivityLogs()` references `Activity::forSubject()` with no `use` import** — resolves to `App\Models\Activity`, which does not exist. **Fatal on first call.**

### `roles`

Model: `app/Models/Role.php` · Migrations: `2023_07_17_040118`, `2025_07_18_025029`
`id`, `name` string, `slug` string **unique**, `description` text null, `type` varchar(20) default `'application'` (values seeded: `application`, `client`, `project` — free string), timestamps.
**Relations:** `permissions()` belongsToMany via `role_permission`; `users()` hasMany User.
`$hidden = ['created_at','updated_at']`. No casts. `hasPermission()` is an `exists()` query per call (never cached, called in loops by `User::hasAnyPermission`).

### `permissions`

Model: `app/Models/Permission.php` · Migration: `2023_07_17_040053`
`id`, `name` string, `slug` string **unique**, `description` text null, `category` string NOT NULL, timestamps.
**Relations:** `roles()` belongsToMany via `role_permission`. No casts. Seeding is spread over 7 seeders + 2 data migrations (Appendix D in db.md).

### `role_permission`

**No model.** Migration: `2023_07_17_040141`
`role_id` FK roles cascade, `permission_id` FK permissions cascade, composite PK `(role_id, permission_id)`. No timestamps.

### `user_remembered_devices`

Model: `app/Models/UserRememberedDevice.php` · Migration: `2026_08_02_143948_create_user_remembered_devices_table`
`id`, `user_id` FK users cascade, `device_token_hash` varchar(64) indexed, `user_agent` text null, `ip_address` string null, `last_used_at` timestamp NOT NULL, `expires_at` timestamp NOT NULL indexed, timestamps.
Casts: `last_used_at`, `expires_at` datetime. **Relations:** `user()` belongsTo. Written by `app/Services/RememberDeviceService.php`. No unique on `(user_id, device_token_hash)`.

### `login_attempts`

Model: `app/Models/LoginAttempt.php` · Migration: `2026_01_30_011433`
`id`, `email` string indexed, `ip_address` string indexed, `attempt_type` enum(`pin`,`magic_link`) default `pin`, `successful` boolean default false, `attempted_at` timestamp NOT NULL indexed, timestamps.
Casts: `successful` boolean, `attempted_at` datetime. **Scopes:** `failed`, `ofType`, `forEmail`, `forIp`, `recent($minutes=15)`. No relations (email is a free string, not a user FK — it is the *client portal* login, not staff). Unbounded growth: nothing prunes it except `CleanupClientAuthData` command.

### `user_otps`

Model: `app/Models/UserOtp.php` · Migration: `2026_08_02_143948_create_user_otps_table`
`id`, `identifier` string indexed, `context` string indexed, `otp_hash` string, `attempts` usmallint default 0, `max_attempts` usmallint default 5, `expires_at` timestamp NOT NULL, `verified_at` timestamp null, `meta` json null, timestamps. Index `(identifier, context)`.
Casts: `expires_at`, `verified_at` datetime; `meta` array. Helpers `isExpired()`, `hasExceededMaxAttempts()`. No relations — `identifier` is an email string (`GenericOtpService`), `context` is a free string (only `'login'` observed). This is the **staff** 2FA OTP.

### `otp_verifications`

Model: `app/Models/OtpVerification.php` · Migration: `2026_05_30_000002`
`id`, `email` string, `otp_hash` string, `project_token` varchar(64) (string reference to `projects.public_share_token`, **no FK**), `expires_at` timestamp NOT NULL, `verified_at` timestamp null, `session_token` varchar(64) null **unique**, `guest_user_id` ubigint null **no FK**, timestamps. Index `(email, project_token)`.
Casts: `expires_at`, `verified_at` datetime. **Relations:** `guestUser()` belongsTo User `guest_user_id`. Helpers `isExpired()`, `isVerified()`. This is the **public-share / guest** OTP — a third OTP mechanism alongside `user_otps` and `magic_links.temporary_pin`.

### `magic_links`

Model: `app/Models/MagicLink.php` · Migrations: `2025_07_24_124649`, `2026_01_30_011443`, `2026_03_15_061554`, `2026_03_15_061734`, `2026_03_15_062045`, `2026_03_15_062842`, `2026_04_26_000001` (adds `email_app_id`)

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | bigint auto | | |
| `email` | string | **null** (was NOT NULL) | |
| `token` | varchar(64) | unique | |
| `type` | string | default `'client'` | observed values `client`, `external` |
| `label` | string | null | for external tokens |
| `whitelist` | json | null | cast array; allowed emails/domains for `external` |
| `max_uses` | int | null | |
| `uses_count` | int | default 0 | |
| `last_used_at` | timestamp | null | |
| `temporary_pin` | string | null | plaintext PIN |
| `temp_pin_expires_at` | timestamp | null | |
| `project_id` | ubigint | **null** (was NOT NULL), FK projects cascade | |
| `email_app_id` | ubigint | null, FK email_apps nullOnDelete, indexed | |
| `expires_at` | timestamp | **null** (was NOT NULL) | `hasExpired()` treats null as never |
| `used` | boolean | default false | **superseded by `uses_count`/`max_uses`**, still fillable, `markAsUsed()` still exists |
| timestamps | | | |

Casts: `expires_at`, `temp_pin_expires_at`, `last_used_at` datetime; `used` boolean; `whitelist` array; `max_uses`, `uses_count`, `email_app_id` integer.
**Relations:** `project()` belongsTo; `emailApp()` belongsTo; `externalEmailLogs()` hasMany.
Two subtypes in one table: **client portal links** (`type=client`, `email`+`project_id`+`temporary_pin`) and **external API tokens** (`type=external`, `label`+`whitelist`+`max_uses`+`email_app_id`, project nullable). The nullable-ification migration exists purely to admit the second subtype. Consumers: `VerifyMagicLinkToken` and `VerifyExternalMagicLink` middlewares, `MagicLinkService`, `ExternalTokenController`.

### `google_accounts`

Model: `app/Models/GoogleAccounts.php` (**plural class name**) · Migration: `2025_08_07_102202`
`id`, `user_id` FK users cascade **unique**, `access_token` text NOT NULL, `refresh_token` text null, `expires_in` ubigint NOT NULL, `created` ubigint NOT NULL (Unix epoch), `email` string, timestamps.
Casts: `created`, `expires_in` integer. `$hidden`: `access_token`, `refresh_token` — **not encrypted** (compare `xero_connections`, `email_apps`, which use the `encrypted` cast). **Relations:** `user()` belongsTo. `isExpired()` = `time() > created + expires_in`. `getTokensAttribute()` returns a JSON string. Effectively one-to-one via the unique.

### `telegram_accounts`

Model: `app/Models/TelegramAccount.php` · Migration: `2026_04_05_172523`
`id`, `telegram_id` string **unique** + index (redundant), `telegramable_id`/`telegramable_type` morphs, `username`, `first_name`, `last_name` string null, timestamps.
**Relations:** `telegramable()` morphTo (User, Client). No casts. `telegram_id` is a string here but `users.telegram_chat_id`/`clients.telegram_chat_id` are `bigint` — **same identity, two types, three columns**. No unique on the morph pair, so one user can have several Telegram accounts while the `bigint` column admits one.

### `user_metadata_keys`

Model: `app/Models/UserMetadataKey.php` · Migration: `2026_05_11_112312_create_user_metadata_keys_table`
`id`, `key` string **unique**, `label` string, `type` string default `'text'` (comment: text, date, number…), timestamps. No relations, no casts. The registry half of an EAV whose value half is `users.metadata` JSON — nothing enforces that JSON keys match rows here.

### `user_notes`

Model: `app/Models/UserNote.php` · Migration: `2026_05_11_112312_create_user_notes_table`
`id`, `user_id` FK users cascade (subject), `author_id` FK users (**no onDelete** → restrict; deleting an author user fails while notes exist — and `users` is soft-deletable so force-delete will error), `content` text (encrypted via accessor/mutator with `Crypt::encryptString`, **decrypt failure silently returns ciphertext**), timestamps.
**Relations:** `subject()` belongsTo User `user_id`; `author()` belongsTo User `author_id`. Not `encrypted` cast — hand-rolled, so `where('content', …)` is impossible and the fallback masks key rotation.

### Domain 1 — smells / decisions needed

| # | Smell |
|---|---|
| 1.1 | **`User::onlineActivityLogs()` calls `Activity::forSubject()` without importing Spatie's `Activity`** — resolves to non-existent `App\Models\Activity`; fatal when invoked. |
| 1.2 | **`users.role_id` has no FK constraint** (`foreignId()` without `constrained()`); `2023_07_17_201922_add_role_id_to_users_table` is an empty migration. |
| 1.3 | `User::createdTags()` targets `tags.created_by_user_id`, dropped in `2025_07_29_083617` (F9). |
| 1.4 | `User::$fillable` lists four `google_*` columns that do not exist (F10); the accessors query `google_accounts` on each access. |
| 1.5 | **Global scope `orderByName` on `User`** — every user query including subqueries carries an `ORDER BY`, and `$with = ['role','categories']` eager-loads two relations on every query (F78). |
| 1.6 | **Four OTP/PIN mechanisms**: `user_otps` (staff login), `otp_verifications` (public share guests), `magic_links.temporary_pin` (client portal), `clients.pin` (F38). Three of them store hashes; `magic_links.temporary_pin` and `clients.pin` are plaintext. |
| 1.7 | **`magic_links` is two subtypes** (client portal link vs external API token) discriminated by `type`, with three columns made nullable to admit the second. |
| 1.8 | `magic_links.used` is dead (superseded by `uses_count`/`max_uses`) but still fillable with `markAsUsed()`. |
| 1.9 | **Telegram identity in three places with two types**: `users.telegram_chat_id` bigint, `clients.telegram_chat_id` bigint, `telegram_accounts.telegram_id` string. |
| 1.10 | `google_accounts` stores OAuth tokens **unencrypted** while `xero_connections`/`email_apps` use the `encrypted` cast. Model class is plural (`GoogleAccounts`). |
| 1.11 | `user_notes.author_id` FK has no `onDelete`; `users` is soft-deletable. Content encryption is hand-rolled with a silent-fallback decrypt. |
| 1.12 | `users.notes` JSON, `user_notes` table, and `ProjectNote` morph `noteable=User` are **three note stores for one user**. |
| 1.13 | `users.metadata` JSON + `user_metadata_keys` registry is an unenforced EAV (F62). |
| 1.14 | `User::isManager()` accepts slug `assistant-manager`, which no seeder creates; `Email::getCanApproveAttribute()` checks `approve_all_emails`, `view_private_emails`, `contact_lead`, `contact_leads` — **none seeded anywhere**. |
| 1.15 | `roles.type` is a free string (`application`/`client`/`project`) with no enum; six of ten seeded roles get no permissions. |
| 1.16 | `login_attempts` and `user_remembered_devices` have no uniqueness or pruning beyond a console command; `login_attempts.email` is not a user FK (client portal). |
| 1.17 | `users.user_type` widened twice via raw MySQL `ALTER TABLE … MODIFY COLUMN` (non-portable). |

---

## Domain 2 — Clients, leads, campaigns, vault, contexts

### `clients`

Model: `app/Models/Client.php` · Migrations: `2023_07_15_010738`, `2025_07_29_122409`, `2025_09_05_000003`, `2026_01_30_005412`, `2026_03_14_044145`, `2026_04_05_172524`, `2026_05_12_000003`

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | bigint auto | | |
| `name` | string | NOT NULL | |
| `email` | string | NOT NULL, **unique** | hidden at runtime unless `edit_clients` |
| `telegram_link_code` | string | null, unique | |
| `xero_contact_id` | string | null, indexed | |
| `xero_contact_name` | string | null | |
| `xero_contact_email` | string | null | |
| `xero_sync_mode` | varchar(20) | null | free string |
| `xero_synced_at` | timestamp | null, indexed | **not cast** |
| `xero_synced_by_user_id` | ubigint | null, FK users nullOnDelete | |
| `phone` | string | null | hidden |
| `telegram_chat_id` | bigint | null, unique | |
| `address` | text | null | hidden |
| `notes` | text | null | |
| `lead_id` | ubigint | null, FK leads set null | guarded by `hasColumn` |
| `pin` | string | null | **plaintext portal PIN**, hidden |
| `timezone` | string | null | |
| `active_telegram_project_id` | ubigint | null, FK projects nullOnDelete | Telegram bot "current project" |
| timestamps | | | |

**No `$casts` at all** (F99). **No soft deletes** while `projects` (child) is soft-deletable (F101).
**Traits:** `HasCategories`, `HasFactory`, `Taggable`, `Notifiable` (`routeNotificationForMail()` reads the raw email to bypass the hidden-ness).
**Boot:** `static::retrieved` hides `email` when the current user lacks `edit_clients` — **a permission check on every hydrated Client row**, and it mutates `$hidden` (affects serialisation only, not the attribute).
**Relations:** `projects()` belongsToMany via `project_client` withPivot `role_id` · `activeTelegramProject()` belongsTo · `conversations()` morphMany `conversable` · `lead()` belongsTo · `deliverableInteractions()` hasMany · `deliverableComments()` hasMany DeliverableComment (dead table) · `notes()` morphMany ProjectNote **as `creator`** (notes authored by, not about) · `presentations()` morphMany `presentable` · `approvedDeliverables()` hasMany Deliverable `overall_approved_by_client_id` · `contexts()` morphMany `linkable` · `telegramAccount()` morphOne · `xeroSyncedBy()` belongsTo.
Note `projects.client_id` (1:N) coexists with `project_client` (M:N) (F40).

### `leads`

Model: `app/Models/Lead.php` · Migrations: `2025_09_03_131900`, `2025_09_07_183800`

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | bigint auto | | |
| `first_name`, `last_name` | string | null | |
| `email` | string | null, **unique** | nullable unique |
| `phone`, `company`, `title` | string | null | |
| `status` | string | default `'new'`, indexed | cast `LeadStatus` enum (11 cases) |
| `source` | string | null, indexed | free |
| `pipeline_stage` | string | null, indexed | free; overlaps `status` |
| `estimated_value` | decimal(12,2) | null | |
| `currency` | char(3) | default `'USD'` | |
| `assigned_to_id` | ubigint | null, FK users nullOnDelete | |
| `created_by_id` | ubigint | null, FK users nullOnDelete | |
| `campaign_id` | ubigint | null, FK campaigns nullOnDelete | plus `metadata->additional_campaign_ids` JSON list |
| `last_communication_at` | timestamp | null | **not fillable, not cast** |
| `contacted_at` | timestamp | null | set by EmailObserver |
| `next_follow_up_date` | timestamp | null, indexed | |
| `converted_at` | timestamp | null | |
| `lost_reason`, `website`, `country`, `state`, `city`, `address`, `zip` | string | null | |
| `tags` | string | null | **comma-string tags** (F28) |
| `notes` | text | null | |
| `metadata` | json | null | cast array; holds `additional_campaign_ids` |
| `email_thread_history` | json | null | cast array — duplicates `conversations`/`emails` |
| `deleted_at` | | | SoftDeletes |
| timestamps | | | |

Casts: `estimated_value` decimal:2, `contacted_at`/`converted_at`/`next_follow_up_date` datetime, `metadata`/`email_thread_history` array, `status` `LeadStatus`.
`$appends = ['full_name', 'name', 'lead_number']` — `full_name` and `name` are identical; `lead_number` = `'OZ'.id` (**same prefix as `Task::task_number`**).
**Relations:** `assignedTo()`, `creator()` belongsTo User · `conversations()` morphMany `conversable` · `campaign()` belongsTo · `presentations()` morphMany `presentable` · `contexts()` morphMany `linkable` · `latestContext()` morphOne latestOfMany.
**Scopes:** `status`, `source`, `assignedTo`, `search` (6 LIKEs incl. a raw concat), `campaigns($ids)` (FK OR `whereJsonContains` per id).
Constants `STATUS_OUTREACH_SENT = LeadStatus::Contacted->value` — **the constant named "outreach sent" maps to the `contacted` case**, while the enum also has an `OutreachSent` case.

### `campaigns`

Model: `app/Models/Campaign.php` · Migration: `2025_09_07_183700`
`id`, `name` string, `target_audience` text null, `services_offered` json null (array), `goal` string null, `ai_persona` text null, `email_template` longText null (**an inline template, not an FK to `email_templates`** — F32), `is_active` boolean default true indexed, timestamps.
Casts: `services_offered` array, `is_active` boolean. **Relations:** `leads()` hasMany; `shareableResources()` belongsToMany via `campaign_shareable_resource` withTimestamps. Scope `active`.

### `campaign_shareable_resource`

**No model.** Migration: `2025_09_08_202700`
`id`, `campaign_id` FK campaigns cascade, `shareable_resource_id` FK shareable_resources cascade, timestamps, unique `campaign_resource_unique (campaign_id, shareable_resource_id)`. Has its own `id` despite being a pure pivot.

### `client_vault_credentials`

Model: `app/Models/ClientVaultCredential.php` · Migrations: `2026_04_05_085603`, `2026_04_28_100000`, `2026_04_28_120000` (driver-branched: MySQL `MODIFY … NULL` / pgsql `DROP NOT NULL`)

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | **uuid PK** | | generated in `creating` hook; `$incrementing=false`, `$keyType='string'` |
| `client_id` | ubigint | FK clients cascade | |
| `project_id` | ubigint | null, FK projects nullOnDelete | |
| `created_by` | ubigint | null, FK users nullOnDelete | |
| `source` | varchar(20) | default `'client'` | `client` \| `team` (constants) |
| `is_visible_to_client` | boolean | default true | |
| `label` | string | | |
| `encrypted_username` | text | | app-level encryption via `VaultService` (salted) |
| `encrypted_password` | text | | |
| `encrypted_pin` | text | null | |
| `salt` | string | | |
| `expires_at` | timestamp | **null** (was NOT NULL) | |
| `last_viewed_at` | timestamp | null | |
| `deleted_at` | | | SoftDeletes |
| timestamps | | | |

Indexes: `(project_id, source)`, `(client_id, source)`, `(created_by, source)`.
Casts: `is_visible_to_client` boolean; `expires_at`, `last_viewed_at` datetime.
**Relations:** `client()`, `project()` belongsTo; `owner()` belongsTo User `created_by`; `sharedWithUsers()` belongsToMany User via `client_vault_credential_user` withPivot `granted_by` withTimestamps. Scope `notExpired`.
UUID PK is one of four PK strategies in the app (F69) and forced `activity_log.subject_id` to `varchar(191)` (F70).

### `client_vault_credential_user`

**No model.** Migration: `2026_04_28_100100`
`id`, `client_vault_credential_id` **uuid** FK (named `vault_credential_user_credential_fk`) cascade, `user_id` FK users cascade, `granted_by` ubigint null FK users nullOnDelete, timestamps. Unique `vault_credential_user_unique (client_vault_credential_id, user_id)`.
`granted_by` is an audit fact reachable only through `withPivot` (Appendix E in db.md).

### `contexts`

Model: `app/Models/Context.php` · Migration: `2025_09_06_000000`
`id`, `summary` text NOT NULL, `project_id` ubigint null FK projects nullOnDelete (`foreignIdFor`), `referencable_id`/`referencable_type` morphs (NOT NULL), `linkable_id`/`linkable_type` morphs (NOT NULL), `user_id` ubigint null FK users nullOnDelete, `meta_data` json null (array), `deleted_at` SoftDeletes, timestamps.
**Relations:** `referencable()` morphTo (Email, ProjectNote, Task); `linkable()` morphTo (Client, Lead, User); `user()` belongsTo.
Implements `CreatableViaWorkflow` (`requiredOnCreate`, `defaultsOnCreate` — defaults `referencable` to the triggering Email, `fieldMetaForWorkflow`). **Two morph pairs on one row** with no constraint on valid combinations (F68). Reverse relations exist only on Client/Lead/User (`linkable`) and Email (`referencable` via `contexts()`); Task and ProjectNote have no reverse `contexts()` relation.

### Domain 2 — smells / decisions needed

| # | Smell |
|---|---|
| 2.1 | **`Client` has no `$casts`** — `xero_synced_at` is a string, `telegram_chat_id` an int, no booleans. Only model in this domain with none besides `Document`/`Resource` (F99). |
| 2.2 | **`Client::boot()` `retrieved` hook runs a permission check per hydrated row** to hide `email`, and `routeNotificationForMail()` has to bypass it via `getRawOriginal`. |
| 2.3 | `clients.pin` is a **plaintext** portal PIN (hidden, not hashed); `login_attempts` rate-limits it. |
| 2.4 | **`clients` is not soft-deletable but `projects` is** — cascade delete of a client hard-deletes soft-deleted projects and everything under them (F101). |
| 2.5 | `projects.client_id` (1:N, cascade) coexists with `project_client` (M:N with `role`/`role_id`) — two models of "which client owns this project" (F40, F47). |
| 2.6 | **`Client::notes()` is `morphMany(ProjectNote, 'creator')`** — "notes written by this client", not "notes about this client"; the same method name on `User` means "notes about this user" (`noteable`). |
| 2.7 | `Client::deliverableComments()` targets the dead `deliverable_comments` table. |
| 2.8 | **Leads and clients are the same entity at different lifecycle stages** and already share `conversable`, `presentable`, `linkable` morphs plus `clients.lead_id`; yet `leads` has 27 columns and `clients` 18 with only `email`, `phone`, `address`, `notes`, `timezone` in common (name is split first/last on one side only). |
| 2.9 | `leads.tags` is a comma string (third tagging system, F28); `leads.email_thread_history` JSON duplicates `conversations`/`emails` (F31); `leads.metadata->additional_campaign_ids` is a JSON list of FKs queried with `whereJsonContains` in a loop. |
| 2.10 | `leads.status` (enum-cast) and `leads.pipeline_stage` (free string) both encode pipeline position. |
| 2.11 | `leads.last_communication_at` is neither fillable nor cast on `Lead`, but **is** fillable on `Email` (F11). |
| 2.12 | `Lead::STATUS_OUTREACH_SENT` maps to `LeadStatus::Contacted`, not `LeadStatus::OutreachSent`. |
| 2.13 | `Lead::$appends` has both `full_name` and `name` with identical bodies; `lead_number` (`OZ{id}`) collides with `Task::task_number` (`OZ{id}`). |
| 2.14 | `campaigns.email_template` is a `longText` inline template — a fourth email-template store (F32). |
| 2.15 | `client_vault_credentials` uses a UUID PK (F69) and application-level encryption with a per-row salt rather than the `encrypted` cast used elsewhere; `expires_at` nullability was changed by a driver-branched raw statement. |
| 2.16 | `client_vault_credential_user.granted_by` is unreachable without `withPivot` — pivot model warranted. |
| 2.17 | `contexts` carries two NOT NULL morph pairs; `Task`/`ProjectNote` have no reverse relation for `referencable`. |
| 2.18 | `campaign_shareable_resource` has a surrogate `id` on a pure pivot; every other pivot in the app uses a composite PK or none. |

---

## Domain 3 — Projects, services, deliverables (scope), expendables, resources, documents

### `projects`

Model: `app/Models/Project.php` · Migrations: `2023_07_15_010742`, `2023_07_15_010751` (all guarded by `hasColumn`, effectively a no-op except `departments`), `2023_07_15_233030`, `2023_10_25_000000`, `2025_07_29_122331`, `2025_08_05_093423`, `2025_08_09_034809`, `2025_08_20_125000`, `2025_08_26_205900`, `2025_08_26_222100`, `2026_02_17_220531`, `2026_02_26_214700`, `2026_03_14_044145`, `2026_03_14_050557`, `2026_03_14_051425`, `2026_05_30_000001`

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | bigint auto | | |
| `name` | varchar(255) | NOT NULL | |
| `description` | text | null | |
| `project_type` | varchar(255) | null | free |
| `departments` | json | null | **orphan** — not fillable, not cast, never read |
| `website`, `social_media_link` | string | null | |
| `preferred_keywords` | text | null | SEO |
| `reporting_sites` | text | null | SEO |
| `google_chat_id` | varchar(255) | null | Google Chat space |
| `telegram_group_id` | bigint | null, indexed | |
| `telegram_group_name` | string | null | |
| `telegram_link_code` | string | null, unique | third link-code column (F37) |
| `client_id` | ubigint | NOT NULL, FK clients cascade | |
| `project_manager_id` | ubigint | null, FK users nullOnDelete | duplicates `project_user.role_id` (F41) |
| `project_admin_id` | ubigint | null, FK users nullOnDelete | |
| `status` | enum(`active`,`completed`,`on_hold`,`archived`) | default `active` | cast `ProjectStatus` — **3 PHP cases unpersistable, `archived` unrepresentable** (F12) |
| `services` | json | null | array — duplicates `project_services` |
| `service_details` | json | null | array |
| `source` | varchar(255) | null | |
| `total_amount` | decimal(10,2) | null | |
| `contract_details` | text | null | |
| `google_drive_link` | string | null | |
| `google_drive_folder_id` | string | null | |
| `payment_type` | enum(`one_off`,`monthly`) | default `one_off` | cast string |
| `logo` | string | null | **accessor mutates `$attributes['logo']` to a `Storage::url()` on read** |
| `logo_google_drive_file_id` | string | null | |
| `documents` | json | null | cast array — **shadows `documents()` hasMany** (F4) |
| `last_email_sent`, `last_email_received` | timestamp | null | datetime; written by EmailObserver |
| `timezone` | string | null | |
| `project_tier_id` | ubigint | null, FK project_tiers set null | |
| `profit_margin_percentage` | decimal(5,2) | null | **hidden**; recomputed by TransactionObserver (F75) |
| `integrations` | **jsonb** | null | array (F103) |
| `data` | json | null | array — untyped catch-all |
| `public_share_token` | varchar(64) | null, unique | referenced by `otp_verifications.project_token` string |
| `public_share_enabled` | boolean | default false | |
| `deleted_at` | | | SoftDeletes |
| timestamps | | | |

**Traits:** `HasFactory`, `SoftDeletes`, `Taggable`. `$appends = ['logo_url']` — **runs a `files()` query per serialised project** (latest image file). `$hidden = ['profit_margin_percentage']`.
Constants `LEADS`, `MANAGEMENT`, `DEVELOPMENT` (department names — unused with `departments`), `SUPPORT = 'support'` (name of the auto-created support milestone).

**Relations (34):** `client()` belongsTo · `clients()` belongsToMany via `project_client` withPivot `role_id` · `users()` belongsToMany via `project_user` withPivot `role_id` · `manager()`/`admin()` belongsTo User · `googleChatMembers()` belongsToMany User via `project_google_chat_members` · `files()` morphMany FileAttachment `fileable` · `conversations()`, `telegramTopics()`, `transactions()`, `invoices()`, `bills()`, `projectServices()`, `notes()` (ProjectNote), `milestones()`, `wireframes()`, `meetings()`, `documents()`, `contexts()`, `bonusTransactions()`, `deliverables()`, `projectDeliverables()`, `points()` (PointsLedger) hasMany · `resources()` morphMany `resourceable` · `expendable()` morphMany ProjectExpendable `expendable` · `budget()` = `expendable()->whereNull('user_id')` · `bonusConfigurationGroups()` belongsToMany via `project_bonus_configuration_group` (`project_id`,`group_id`) withTimestamps · `tier()` belongsTo ProjectTier.
**Non-relation methods that look like relations:** `supportMilestone()` (find-or-create), `milestoneContracts()`, `projectContracts()`, `allContracts()` return Collections.
**Computed money accessors** (each runs queries and currency conversion, all hard-coded to **AUD**): `total_budget`, `total_assigned_milestone_amount`, `pending_contracts_amount`, `approved_contracts_amount`, `available_for_new_milestones`, `approved_milestone_expendables_total`, `remaining_spendables` (last two identical). `convertCurrency()` duplicates `HasFinancialCalculations::convertCurrency()` and uses the same cache key `currency_rates_to_usd`.
**`getUsersWithProjectAccess()`** merges pivot users + manager/admin + everyone with `view_all_projects` (**unseeded permission**), and mutates `pivot` objects with fake `role_data`.
**`uploadDocuments()`** writes to `documents` table via Google Drive; `getLogoAttribute()` writes into `$attributes` on read (**dirty-tracking hazard: `logo` becomes dirty after any read**).
`setIntegration()/getIntegration()` read-modify-write the `integrations` JSON.
`getProjectNumberAttribute()` = `'OZP'.id` (not appended).

### `project_user`

**No model.** Migrations: `2023_07_15_010746`, `2025_07_17_212739` (drops `role`)
`project_id` FK projects cascade, `user_id` FK users cascade, `role_id` ubigint null **no FK**, composite PK `(project_id, user_id)`, timestamps. Accessed via `withPivot('role_id')` on `User::projects()` / `Project::users()`. `User::getRoleForProject()` reads it; `hasProjectPermission()` does `Role::find()` per call.

### `project_client`

**No model.** Migrations: `2023_07_15_010752`, `2025_07_17_234016`
`project_id` FK cascade, `client_id` FK cascade, `role` string default `'Primary'` (**dead**, superseded), `role_id` ubigint null **no FK** (backfilled from `role` by a raw `UPDATE … (SELECT id FROM roles WHERE LOWER(name)=… OR LOWER(slug)=…)`), composite PK, timestamps. Unlike `project_user`, the string `role` was never dropped (F47).

### `project_tiers`

Model: `app/Models/ProjectTier.php` · Migration: `2025_08_09_034808`
`id`, `name` string, `point_multiplier` decimal(4,2), `min_profit_margin_percentage` decimal(5,2), `max_profit_margin_percentage` decimal(5,2), `min_client_amount_pkr` decimal(10,2), `max_client_amount_pkr` decimal(10,2), `deleted_at` SoftDeletes, timestamps.
All five numerics cast `decimal:2`. **Relations:** `projects()` hasMany `project_tier_id`. Currency baked into column names (F87). No unique on `name`; no seeder (Appendix D). Belongs conceptually to the points engine (§9) — it is the multiplier source for `PointsService`.

### `crm_services`

Model: `app/Models/CrmService.php` · Migrations: `2026_05_12_000000`, `2026_06_10_000000`
`id`, `name` string **unique**, `xero_item_code` string null, `default_amount` decimal(15,2) null, `default_currency` varchar(10) null, `default_frequency` varchar(20) null, `default_payment_breakdown` json null (array), `default_description` text null, `default_xero_account_code` varchar(50) null, timestamps.
Casts: `default_amount` decimal:2, `default_payment_breakdown` array. **Relations:** `projectServices()` hasMany. Catalogue table; no seeder, yet `project_services.crm_service_id` is NOT NULL.

### `project_services`

Model: `app/Models/ProjectService.php` · Migration: `2026_05_13_090000`

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | bigint auto | | |
| `project_id` | ubigint | FK projects cascade | |
| `enquiry_id` | varchar(100) | null, indexed | external enquiry system key |
| `crm_service_id` | ubigint | NOT NULL, FK crm_services cascade | **deleting a catalogue row deletes project services** |
| `description` | text | null | |
| `amount` | decimal(15,2) | default 0 | |
| `currency` | varchar(10) | null | |
| `frequency` | varchar(20) | default `'one_off'` | free string |
| `start_date` | date | null | |
| `payment_breakdown` | json | null | array — **payment milestones keyed into by `invoice_items.milestone_key`** (F58) |
| `status` | varchar(50) | default `'active'` | free |
| `service_tracking_type` | varchar(50) | default `'operational_service'` | free |
| `show_on_leads_board` | boolean | default false | |
| `enquiry_status` | varchar(50) | null | free |
| `enquiry_created_at`, `enquiry_updated_at` | timestamp | null | mirrored from foreign system |
| `enquiry_meta` | json | null | array — foreign payload |
| `xero_account_code` | varchar(50) | null | loose Xero account ref (F84) |
| timestamps | | | |

Indexes: `(project_id, enquiry_id)`, `enquiry_id`, `project_services_leads_lookup_idx (project_id, service_tracking_type, show_on_leads_board)`.
Casts: `amount` decimal:2, `payment_breakdown`/`enquiry_meta` array, `show_on_leads_board` boolean, `enquiry_created_at`/`enquiry_updated_at` datetime, `start_date` date.
**Relations:** `project()`, `crmService()` belongsTo; `invoiceItems()` hasMany.
Duplicated by `projects.services` + `projects.service_details` JSON (F39). Four free-string state columns.

### `project_deliverables`

Model: `app/Models/ProjectDeliverable.php` · Migration: `2025_08_09_104553`
`id`, `project_id` FK projects cascade, `milestone_id` null FK milestones set null, `name` string, `description` text null, `details` json null (array — checklist items), `status` string default `'pending'` (free), `due_date` date null, `completed_at` **date** null (not datetime), `deleted_at` SoftDeletes, timestamps.
Casts: `details` array, `due_date`/`completed_at` date. **Relations:** `project()`, `milestone()` belongsTo; `tasks()` hasMany Task `project_deliverable_id`.
This is the **scope checklist** ("what we promised"), unrelated to `deliverables` (§12, "artefact for client approval") despite the name (F34). `config/project_deliverable_types.php` defines a type vocabulary this table cannot store (F109).

### `project_expendables`

Model: `app/Models/ProjectExpendable.php` · Migrations: `2025_08_12_000000_create_project_expandables_table` (**misspelled filename**), `2026_05_29_124140`

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | bigint auto | | |
| `name` | string | | |
| `description` | text | null | |
| `payment_terms` | text | null | |
| `project_id` | ubigint | FK projects cascade | denormalised alongside the morph |
| `user_id` | ubigint | null, FK users nullOnDelete | **`NULL` = budget line, non-null = contractor contract** (F51) |
| `currency` | string | NOT NULL | free |
| `amount` | decimal(15,2) | default 0 | |
| `balance` | decimal(15,2) | default 0 | never written by the model |
| `status` | string | default `'Pending Approval'` | cast `ProjectExpendableStatus` (Title Case with spaces) |
| `expendable_id`/`expendable_type` | morphs | NOT NULL | Project \| Milestone (Task declares the reverse but nothing writes it) |
| `deleted_at` | | | SoftDeletes |
| timestamps | | | |

Casts: `amount`/`balance` decimal:2, `status` enum, `currency` string. `$appends = ['expendable_number']` (`'OZX'.id`).
**Traits:** `LogsActivity` (log name `project_expendable`, 10 attributes, dirty only). Boot `creating` defaults status.
**Relations:** `project()`, `user()` belongsTo; `expendable()` morphTo; `bills()` hasMany Bill `project_expendable_id`; `files()` morphMany FileAttachment.
**State machine as methods:** `accept/reject/shortlist/unshortlist/complete` each save + write an activity row with `reason`; `checkCompletionStatus()` sums `bills->paid_amount` (each of which runs currency conversion per transaction, F76) and auto-completes.
`Project::budget()` = `whereNull('user_id')`; `Milestone::budget()` = `morphOne … whereNull('user_id')`; `Milestone::expendable()` = `whereNotNull('user_id')`. **Nothing prevents two budget rows per milestone.**

### `resources`

Model: `app/Models/Resource.php` · Migrations: `2025_07_24_111212`, `2025_07_24_123650`
`id`, `name` string, `type` enum(`link`,`file`), `url` string null, `file_id` string null (**a Google Drive id string, not an FK to `files`**), `resourceable_id`/`resourceable_type` morphs (only Project), `description` text null, `requires_approval` boolean default false, `visible_to_client` boolean default false, timestamps.
**No `$casts`** — booleans come back as ints (F99). **Traits:** `Taggable`. **Relations:** `resourceable()` morphTo; `comments()` morphMany Comment `commentable`. Helpers `isLink()`, `isFile()`.

### `documents`

Model: `app/Models/Document.php` · Migrations: `2025_07_28_112950`, `2025_07_28_113335` (**empty stub — the JSON→model migration was never written**)
`id`, `project_id` FK projects cascade, `path` string, `filename` string, `google_drive_file_id` string null, `thumbnail` string null, `upload_error` text null, `mime_type` string null, `file_size` bigint null, timestamps.
**No `$casts`.** **Traits:** `Taggable`. **Relations:** `project()` belongsTo; `notes()` morphMany ProjectNote `noteable`. `getUrlAttribute()` = `asset('storage/'.path)`. `addNote()` writes `type => 'note'` — **not a value of the `project_notes.type` enum** (`standup`,`kudos`,`general`); MySQL strict mode rejects, non-strict stores `''`.
Fourth file store alongside `files`, `projects.documents` JSON, `deliverables.attachment_path` (F27).

### `project_notes` — see Domain 14

### `seo_reports` — see db.md §17 (belongs to Project via `project_id`, no `onDelete`)

### Domain 3 — smells / decisions needed

| # | Smell |
|---|---|
| 3.1 | **`projects.status` DB enum ≠ `ProjectStatus` PHP enum** (F12). `Project::supportMilestone()` writes `MilestoneStatus::InProgress` (`'in progress'`) which is **not in the `milestones.status` DB enum** either (F14). |
| 3.2 | **`Project::$appends['logo_url']` runs a `files()` query per serialised row**; `getLogoAttribute()` writes into `$attributes` on read, making `logo` dirty after any access. |
| 3.3 | `projects.documents` JSON cast shadows `documents()` hasMany (F4); the migration meant to fold JSON into the table is an empty stub. |
| 3.4 | `projects.departments` is an orphan column; the `LEADS/MANAGEMENT/DEVELOPMENT` constants that would populate it are unused. |
| 3.5 | `projects.services`/`service_details` JSON duplicate `project_services` (F39). |
| 3.6 | **Seven computed money accessors on `Project`** each hard-code AUD and run N queries + N conversions; `approved_milestone_expendables_total` and `remaining_spendables` are byte-identical. `Project::convertCurrency()` duplicates the `HasFinancialCalculations` trait's method and shares its cache key. |
| 3.7 | `projects.profit_margin_percentage` is hidden and recomputed by loading every project transaction on every transaction write (F75). |
| 3.8 | `project_user.role_id` and `project_client.role_id` have **no FK constraint**; `project_client.role` string is dead but retained (F47). |
| 3.9 | `projects.project_manager_id`/`project_admin_id` vs `project_user.role_id` — two models of project roles (F41); `getUsersWithProjectAccess()` fakes a `pivot` object to reconcile them and relies on the unseeded `view_all_projects` permission. |
| 3.10 | **`project_expendables` conflates budgets and contractor contracts via `user_id IS NULL`** (F51); nothing prevents two budget rows per milestone; `balance` is never maintained. |
| 3.11 | `project_expendables.status` is the only Title-Case-with-spaces enum; `project_deliverables.status`, `project_services.status/frequency/service_tracking_type/enquiry_status` are free strings. |
| 3.12 | `project_services.crm_service_id` is NOT NULL with **cascade delete from the catalogue** — deleting a `crm_services` row silently deletes project services and (via cascade) their `invoice_items`. |
| 3.13 | `project_services.payment_breakdown` JSON is the target of `invoice_items.milestone_key` (F58, F16). |
| 3.14 | **`deliverables` vs `project_deliverables`** — unrelated tables with near-identical names (F34); `project_deliverables.completed_at` is a `date`, not a datetime. |
| 3.15 | `resources.file_id` is a Google Drive id string, not a `files` FK; `Resource` and `Document` have no `$casts` (booleans as ints). |
| 3.16 | `Document::addNote()` writes `type='note'`, not a valid `project_notes.type` enum value. |
| 3.17 | `project_tiers` has PKR baked into two column names (F87) and no unique on `name`. |
| 3.18 | `projects.integrations` is `jsonb` (Postgres type) in a MySQL schema (F103); `projects.data` is a fully untyped catch-all. |
| 3.19 | `projects.public_share_token` is referenced from `otp_verifications.project_token` by string with no FK. |
| 3.20 | Three Telegram/Google-Chat identity columns on `projects` (`google_chat_id`, `telegram_group_id`, `telegram_group_name`, `telegram_link_code`) while `telegram_topics` exists — the group itself has no row (F91). |
| 3.21 | `Project::LEADS/MANAGEMENT/DEVELOPMENT` and `SUPPORT` constants are declared in two separate places in the class body. |

---

## Domain 4 — Milestones, tasks, subtasks, task types, daily tasks, schedules, meetings

### `milestones`

Model: `app/Models/Milestone.php` · Migrations: `2025_07_22_233200`, `2025_07_22_233900`, `2025_07_24_135014`, `2025_08_12_134200`

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | bigint auto | | |
| `project_id` | ubigint | **null**, FK projects cascade | added after create; nullable |
| `name` | string | | `'support'` is magic (auto-created support milestone) |
| `description` | text | null | |
| `completion_date` | date | null | accessor alias `due_date` |
| `actual_completion_date` | date | null | |
| `mark_completed_at` | timestamp | null | accessor alias `submitted_at`; **not fillable** |
| `approved_at` | timestamp | null | |
| `completed_at` | datetime | null, indexed | accessor alias `finalized_at`; **not cast** |
| `status` | enum(`Not Started`,`In Progress`,`Completed`,`Overdue`) | default `Not Started` | cast `MilestoneStatusCast` → `MilestoneStatus` (10 lowercase cases, **zero overlap** with the DB enum; the cast's synonym map bridges `not started→pending`, `inprogress→in progress`) |
| timestamps | | | |

Casts: `completion_date`/`actual_completion_date` date, `mark_completed_at`/`approved_at` datetime, `status` custom cast.
**No soft deletes** while `tasks` (child) has them (F101).
**Traits:** `Taggable`. Boot: `updated` → if status is `Approved`, dispatch `MilestoneApprovedEvent` (points) — **fires on every update while approved, not only on transition**.
**Relations:** `project()` belongsTo; `tasks()`, `projectDeliverables()` hasMany; `expendable()` morphMany ProjectExpendable whereNotNull user_id; `budget()` morphOne whereNull user_id; `notes()` morphMany ProjectNote `noteable`.
Five lifecycle date columns, three of them renamed by accessors (F55).

### `task_types`

Model: `app/Models/TaskType.php` · Migration: `2025_07_22_233300`
`id`, `name` string (**no unique**, but used with `firstOrCreate` — F22), `description` text null, `created_by_user_id` FK users NOT NULL (**no onDelete** → restrict), timestamps.
**Relations:** `createdBy()` belongsTo; `tasks()` hasMany. No casts. No seeder; `tasks.task_type_id` is NOT NULL → fresh install cannot create tasks (F24). `config/automation.defaults.task.task_type_id` is the workflow fallback.

### `tasks`

Model: `app/Models/Task.php` (1090 lines) · Migrations: `2025_07_22_233500`, `2025_07_22_233800`, `2025_07_22_233811`, `2025_07_24_135002`, `2025_07_28_072549`, `2025_08_05_054926`, `2025_08_05_080019`, `2025_08_09_104618`, `2025_08_09_125432`, `2025_08_22_113500`, `2025_09_12_181500`, `2025_10_29_000001`, `2026_02_13_235016` (**empty**), `2026_02_14_000001`, `2026_02_22_023734`

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | bigint auto | | |
| `parent_id` | ubigint | null, FK tasks nullOnDelete | second hierarchy alongside `subtasks` (F33) |
| `name` | string | | |
| `description` | text | null | |
| `assigned_to_user_id` | ubigint | null, FK users (**no onDelete**) | |
| `due_date` | date | null | |
| `actual_completion_date` | date | null | |
| `completed_at` | datetime | null, indexed | **not cast** |
| `status` | enum(`To Do`,`In Progress`,`Done`,`Blocked`,`Archived`) | default `To Do` | cast `MilestoneStatusCast:TaskStatus` — **`Paused` unpersistable** (F13), yet `booted()` writes it |
| `previous_status` | string | null | for un-block |
| `block_reason` | text | null | |
| `needs_approval` | boolean | default false | |
| `requires_qa` | boolean | default false | **not cast** |
| `task_type_id` | ubigint | NOT NULL, FK task_types (no onDelete) | |
| `milestone_id` | ubigint | null, FK milestones (no onDelete) | **no `project_id` — project derived via milestone** (F23) |
| `project_deliverable_id` | ubigint | null, FK set null | |
| `google_chat_space_id`, `google_chat_thread_id`, `chat_message_id` | string | null | Google Chat refs |
| `creator_id`/`creator_type` | ubigint/string | null, indexed | morph (User \| Client), set in `creating` from Auth or magic-link request attributes |
| `priority` | enum(`low`,`medium`,`high`) | default `medium` | no PHP enum |
| `deleted_by` | ubigint | null, FK users set null | |
| `details` | json | null | array |
| `effort` | int | null | "hours or points" |
| `manual_effort_override` | int | null | seconds |
| `source` | string | default `'local'` | pseudo-morph with `source_id` |
| `source_id` | string | null | |
| `additional_info` | **jsonb** | null | Attribute accessor handles **double-encoded** JSON; no `$casts` entry |
| `deleted_at` | | | SoftDeletes |
| timestamps | | | |

Casts: `due_date`/`actual_completion_date` date, `details` array, `needs_approval` boolean, `status` custom cast.
**`$appends = ['creator_name', 'total_time_spent', 'formatted_time_spent', 'task_number']`** — `total_time_spent` **replays `activity_log` rows on every serialisation** (F71); `creator_name` resolves the morph.
**Traits:** `HasFactory`, `HasUserTimezone`, `LogsActivity` (custom descriptions, `tapActivity`), `SoftDeletes`, `Taggable`. Implements `CreatableViaWorkflow`.
**Boot:** `created` (Google Chat + notifications, ~75 lines), `creating` (creator morph from Auth or `magic_link_*` request attributes), `saved` (**when a task goes In Progress, every other In Progress task of the same user is set to `Paused` — a value the DB enum cannot store**).
**Relations:** `assignedTo()` belongsTo User; `assignee()` hasOne User (**duplicate of `assignedTo` with inverted direction**); `taskType()`, `milestone()`, `projectDeliverable()`, `parent()` belongsTo; `subtasks()` hasMany Subtask `parent_task_id`; `children()` hasMany Task `parent_id`; `notes()` morphMany ProjectNote `noteable`; `expendable()` morphMany ProjectExpendable; `creator()` morphTo; `files()` morphMany FileAttachment; `userActivities()` hasMany UserActivity; `schedules()` morphMany Schedule **`scheduledItem`** (F18).
`getProjectIdAttribute()` = `milestone?->project_id` (a task without a milestone has no project); `addNote()` nevertheless writes `project_id` into `project_notes` and pushes to Google Chat.
State methods: `markAsCompleted()` (dispatches `TaskCompletedEvent`), `start()`, `block()`, `archive()`, `spawnChildFromTemplate()`.
`task_number` = `'OZ'.id` (collides with `Lead::lead_number`).

### `subtasks`

Model: `app/Models/Subtask.php` · Migration: `2025_07_22_233600`
`id`, `name` string, `description` text null, `assigned_to_user_id` null FK users (no onDelete), `due_date` date null, `actual_completion_date` date null, `status` enum(`To Do`,`In Progress`,`Done`,`Blocked`) default `To Do` (cast `MilestoneStatusCast:SubtaskStatus` ✓ matches), `parent_task_id` FK tasks cascade, timestamps.
Casts: dates, status. **Relations:** `parentTask()`, `assignedTo()` belongsTo. `addNote()` only posts to Google Chat — **no persistence**. No soft deletes, no tags, no activity log, no `completed_at` — a strictly poorer `Task`. Superseded by `tasks.parent_id` (F33).

### `daily_tasks`

Model: `app/Models/DailyTask.php` · Migration: `2026_02_22_034154`
`id`, `user_id` FK users cascade, `task_id` FK tasks cascade, `date` date indexed, `order` usmallint default 0, `status` varchar(30) default `'pending'` indexed (constants `pending`,`completed`,`pushed_to_next_day` — free string), `note` text null, timestamps. Unique `(user_id, task_id, date)`; index `(user_id, date)`.
Casts: `date` date, `order` integer. **Relations:** `user()`, `task()` belongsTo. Scopes `forUser`, `forDate`, `ordered`. A per-user daily plan; `status` here is independent of `tasks.status`.

### `schedules`

Model: `app/Models/Schedule.php` · Migration: `2025_09_12_174700`
`id`, `name` string, `description` text null, `start_at` datetime NOT NULL, `end_at` datetime null, `recurrence_pattern` string NOT NULL (cron expression, **indexed** — F105), `is_active` boolean default true, `is_onetime` boolean default false, `last_run_at` datetime null, `scheduled_item_id` ubigint NOT NULL, `scheduled_item_type` varchar(191) NOT NULL, `deleted_at` SoftDeletes, timestamps. Indexes `(is_active, start_at, end_at)`, `schedules_item_index (scheduled_item_type, scheduled_item_id)`.
Casts: three datetimes, two booleans. `$appends = ['recurrence_summary']` (human cron description). `next_run_at` accessor (not appended).
**Relations:** `scheduledItem()` morphTo — this side works because `morphTo()` snake-cases the method name to `scheduled_item_*`. **The reverse side is broken**: `Task::schedules()`, `Workflow::schedules()` (and Email) declare `morphMany(Schedule::class, 'scheduledItem')`, and `morphMany` does *not* snake-case, so they query `scheduledItem_type`/`scheduledItem_id` — columns that do not exist (F18). Targets: Task, Workflow, Email (each has `runScheduled(Schedule)`). Scopes `active`, `withinWindow`. `isDueAt()` uses `dragonmantank/cron-expression`.

### `meetings`

Model: `app/Models/Meeting.php` · Migrations: `2025_07_23_214228`, `2025_07_24_063405`, `2025_07_24_090106`
`id`, `project_id` FK projects cascade, `created_by_user_id` FK users cascade, `google_event_id` string NOT NULL indexed (**not unique**), `google_event_link` string NOT NULL, `google_meet_link` string null, `summary` string, `description` text null, `start_time`/`end_time` datetime, `location` string null, `timezone` string null, `enable_recording` boolean default false, `is_utc` boolean default false, timestamps.
Casts: `start_time`/`end_time` datetime, two booleans. **Relations:** `project()`, `creator()` belongsTo; `attendees()` hasMany MeetingAttendee; `users()` belongsToMany User via `meeting_attendees` (**without `withPivot`/`withTimestamps`**, while `User::meetings()` declares both). A Google Calendar mirror — the row cannot exist without `google_event_id`/`google_event_link`. `is_utc` + `timezone` both describe how to read `start_time`.

### `meeting_attendees`

Model: `app/Models/MeetingAttendee.php` · Migration: `2025_08_05_124933`
`id`, `meeting_id` FK meetings cascade, `user_id` FK users cascade, `notification_sent` boolean default false, `notification_sent_at` timestamp null, timestamps. Unique `(meeting_id, user_id)`.
Casts: boolean + datetime. **Relations:** `meeting()`, `user()` belongsTo. Attendees are staff only — clients cannot be invited.

### Domain 4 — smells / decisions needed

| # | Smell |
|---|---|
| 4.1 | **`tasks.status` cannot store `Paused`** but `Task::booted()` `saved` hook writes `Paused` to sibling tasks on every In-Progress transition (F13). In MySQL strict mode the save throws; in non-strict it stores `''`. |
| 4.2 | **`milestones.status` DB enum and `MilestoneStatus` share zero values** (F14); the custom cast's synonym table papers over two of ten. `Project::supportMilestone()` writes `'in progress'`. |
| 4.3 | `Milestone::booted()` dispatches `MilestoneApprovedEvent` on **every** update while status is `Approved`, not on the transition — points can be awarded repeatedly. |
| 4.4 | **`tasks` has no `project_id`** (F23); `project_id` is an accessor through `milestone`, `milestone_id` is nullable, and `Task::addNote()` writes a possibly-null `project_id` into `project_notes`. |
| 4.5 | **`Task::$appends['total_time_spent']` replays `activity_log`** on every serialisation (F71); `formatted_time_spent` does it again. |
| 4.6 | `tasks.additional_info` is `jsonb` with a hand-rolled accessor that un-double-encodes (F103, Appendix C). |
| 4.7 | `Task::assignedTo()` (belongsTo) and `Task::assignee()` (hasOne with inverted keys) are the same relation twice. |
| 4.8 | **`subtasks` vs `tasks.parent_id`** — two task hierarchies (F33); `Subtask::addNote()` persists nothing. |
| 4.9 | `tasks.task_type_id` NOT NULL with no seeder (F24); `task_types.name` has no unique but is `firstOrCreate`d (F22); `task_types.created_by_user_id` and `tasks.task_type_id`/`milestone_id`/`assigned_to_user_id` FKs have no `onDelete`. |
| 4.10 | `tasks.source`/`source_id` is a hand-rolled pseudo-morph (F67); `tasks.priority` DB enum has no PHP enum. |
| 4.11 | `tasks.completed_at`, `tasks.requires_qa`, `milestones.completed_at` are not cast; `milestones.mark_completed_at` is cast but not fillable. |
| 4.12 | `Task/Workflow/Email::schedules()` use `morphMany(…, 'scheduledItem')`, which queries non-existent `scheduledItem_type/_id` columns — the reverse relation is broken while `Schedule::scheduledItem()` works (F18); `recurrence_pattern` is indexed (F105); `scheduled_item_type` is `varchar(191)` unlike every other morph column. |
| 4.13 | `Task::task_number` and `Lead::lead_number` both produce `OZ{id}`. |
| 4.14 | `Meeting::users()` omits `withPivot`/`withTimestamps` that `User::meetings()` declares; `meetings.google_event_id` is indexed but not unique; `is_utc` and `timezone` both qualify the same datetime. |
| 4.15 | `daily_tasks.status` is a free string with three constants; it is a separate state from `tasks.status` with no reconciliation. |
| 4.16 | `milestones.project_id` is nullable (added after create) — an orphan milestone is representable. |
| 4.17 | Migration `2026_02_13_235016_add_productivity_fields_to_tasks_table` is empty; the real one is `2026_02_14_000001` with the same name. |

---

## Domain 5 — Emails, conversations, templates, email apps, external email logs

### `conversations`

Model: `app/Models/Conversation.php` · Migrations: `2023_07_15_010838_create_conversations_table`, `2025_07_22_230945`, `2025_09_03_173500`, `2026_08_19_100200`, `2026_08_20_150000`

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | bigint auto | | |
| `subject` | string | null | |
| `conversable_type` | string | null | morph, backfilled from dropped `client_id` |
| `conversable_id` | ubigint | null | Client \| Lead |
| `project_id` | ubigint | **null**, FK projects cascade | changed via `->change()` |
| `contractor_id` | ubigint | null, FK users cascade | "owner" staff user |
| `last_activity_at` | timestamp | null | |
| `ai_summary` | text | null | |
| `ai_summary_at` | timestamp | null | |
| `ai_summary_email_count` | usmallint | null | staleness check vs `emails()->count()` |
| `ai_summary_status` | varchar(16) | null | cast **`EmailDraftStatus`** (reused enum) |
| `ai_summary_requested_at` | timestamp | null | |
| `ai_task_suggestion` | json | null | array |
| timestamps | | | |

**No soft deletes** while `emails` has them (F101). **No index on the morph pair** (`nullableMorphs` was not used — two plain columns).
Casts: `last_activity_at`/`ai_summary_at`/`ai_summary_requested_at` datetime, `ai_summary_status` enum, `ai_task_suggestion` array.
**Relations:** `project()`, `contractor()` belongsTo; `conversable()` morphTo; `emails()` hasMany; `notes()` morphMany Comment `commentable` latest. `getClientAttribute()` returns the conversable only if it is a Client.

### `emails`

Model: `app/Models/Email.php` (453 lines) · Migrations: `2023_07_15_010838_create_emails_table`, `2023_07_17_084838`, `2025_07_22_224552`, `2025_07_22_231040`, `2025_08_03_230254`, `2025_08_10_033324`, `2025_09_05_172900`, `2025_09_11_044500`, `2026_06_15_120000`, `2026_08_19_100000`, `2026_08_19_100100`, `2026_08_19_100300`, `2026_08_20_140000`, `2026_08_26_100000`

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | bigint auto | | |
| `conversation_id` | ubigint | **null**, FK conversations cascade | FK dropped, column made nullable, orphans nulled, FK re-added — all raw SQL |
| `sender_id` | ubigint | NOT NULL, **FK dropped** | |
| `sender_type` | string | default `'App\Models\User'` | morph (User \| Client \| Lead); mutator on `sender_id` defaults it |
| `to` | **varchar(255)** | NOT NULL | cast **array** — silently truncates (F1) |
| `subject` | string | | |
| `body` | longText | | |
| `status` | enum(13 values) | default `draft` | cast `EmailStatus` — **`rejected_received` not in DB enum** (F15); widened 4× by raw MySQL `ALTER` |
| `approved_by` | ubigint | null, FK users set null | |
| `rejection_reason` | text | null | |
| `type` | string | default `'sent'` | cast `EmailType` (`received`/`sent`) |
| `sent_at` | timestamp | null | |
| `read_at` | timestamp | null | third read-tracking mechanism (F30) |
| `message_id` | string | null, unique | provider message id |
| `rfc_message_id` | varchar(512) | null, indexed | RFC 5322 Message-ID |
| `gmail_thread_id` | varchar(128) | null | |
| `in_reply_to_email_id` | ubigint | null, FK emails nullOnDelete, indexed | |
| `template_id` | ubigint | null, FK email_templates set null | |
| `template_data` | json | null | cast array — **double-encoded, three payloads** (F3; see `app/Support/TemplateData.php`) |
| `draft_meta` | json | null | array |
| `email_template` | string | null | **a template slug string, alongside `template_id`** (F32) |
| `is_private` | boolean | default false, indexed | |
| `ai_status` | varchar(24) | null | cast `EmailAiStatus` |
| `ai_reason` | text | null | |
| `ai_checked_at` | timestamp | null | |
| `ai_summary` | text | null | |
| `ai_draft` | text | null | |
| `ai_draft_at` | timestamp | null | |
| `ai_draft_status` | varchar(16) | null | cast `EmailDraftStatus` |
| `ai_draft_requested_at` | timestamp | null | |
| `deleted_at` | | | SoftDeletes |
| timestamps | | | |

Indexes: `emails_conv_type_created_idx (conversation_id, type, created_at)`, `emails_status_created_idx (status, created_at)`, `emails_ai_status_idx (ai_status, ai_checked_at)`, `emails_rfc_message_id_idx`, `emails_in_reply_to_idx`, `is_private`, unique `message_id`.
**`$fillable` includes `last_communication_at`, `contacted_at`** — columns on `leads`, not `emails` (F11).
**`$appends = ['can_approve', 'can_open', 'email_number']`** — `can_approve` runs up to four permission checks + loads `conversation.project`/`conversable` per serialised row (F77); **`can_open` always returns `false`** (both branches).
**Traits:** `HasCategories`, `HasFactory`, `SoftDeletes`, `Taggable`.
**Boot:** `updating` → `guardStatusRegression()` silently drops a `sent → draft|pending_approval|auto_send` change and logs a stack trace; `updated` → awards points via `PointsService` on transition to sent.
**Observer** (`app/Observers/EmailObserver.php`): `created` compares `$email->type === Email::TYPE_RECEIVED` — **enum instance vs string, always false**; `updated` compares `$newStatus === Email::STATUS_SENT` — **same bug**, so `last_email_sent`/`contacted_at` timestamp updates never fire. The notification methods (`notifyAdminsForApproval`, etc.) are defined but their call sites in `updated()` are empty `if` bodies. `markApprovalNotificationsAsRead` filters `notifications.data` by dot-path in PHP (db.md 17.1).
**Constants:** `STATUS_APPROVED = EmailStatus::Sent`, `STATUS_REJECTED = EmailStatus::RejectedReceived` (the unpersistable one), `STATUS_PENDING_APPROVAL = PendingApprovalReceived` (**the *received* variant**), `STATUS_PENDING_APPROVAL_SENT = PendingApproval`. Four permission slugs referenced, two unseeded.
**Relations:** `conversation()` belongsTo; `inReplyTo()` belongsTo self; `sender()` morphTo; `approver()` belongsTo User; `template()` belongsTo EmailTemplate; `files()` morphMany FileAttachment; `contexts()` morphMany Context `referencable`; `interactions()` morphMany UserInteraction. `project()` is **not a relation** (returns `conversation?->project`). Scope `visibleTo($user)`. `runScheduled()` moves `delayed → draft`.

### `email_templates`

Model: `app/Models/EmailTemplate.php` · Migrations: `2025_07_31_215356`, `2025_09_08_180500`
`id`, `name` varchar(255), `slug` varchar(255) unique, `subject` varchar(255), `body_html` longText, `description` text null, `is_default` boolean default false, `is_private` boolean default false, timestamps.
**No `$casts`** (booleans as ints, F99). **Relations:** `placeholders()` belongsToMany PlaceholderDefinition via `email_template_placeholder`; `emailApps()` belongsToMany EmailApp via `email_app_template` withTimestamps. Placeholders are auto-extracted from `body_html` by the seeder's regex (Appendix D).

### `placeholder_definitions`

Model: `app/Models/PlaceholderDefinition.php` · Migration: `2025_07_31_215423_create_template_placeholders_table` (**filename ≠ table name**)
`id`, `name` varchar(255) unique, `description` text null, `source_model` varchar(255) null (FQCN as data), `source_attribute` varchar(255) null, `is_dynamic`, `is_repeatable`, `is_link`, `is_selectable` boolean default false, timestamps.
**No `$casts`.** **Relations:** `emailTemplates()` belongsToMany. Schema-as-data (F63); rows are side effects of template text (F108).

### `email_template_placeholder`

**No working model** (`app/Models/EmailTemplatePlaceholder.php` is an empty class pointing at a non-existent plural table — delete). Migration: `2025_07_31_215423`
`email_template_id` FK cascade, `placeholder_definition_id` FK cascade, composite PK. No timestamps.

### `email_apps`

Model: `app/Models/EmailApp.php` · Migrations: `2026_04_26_000001`, `2026_04_27_000002`

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | bigint auto | | |
| `name` | string | | |
| `slug` | string | unique | |
| `description` | text | null | |
| `is_active` | boolean | default true | |
| `delivery_mode` | string | default `'smtp'` | `smtp` \| `api` discriminator (free string) |
| `hourly_send_limit` | uint | default 100 | |
| `smtp_host` | string | null | SMTP subtype (8 cols) |
| `smtp_port` | usmallint | null | |
| `smtp_username` | string | null | |
| `smtp_password` | text | null | cast `encrypted`, hidden |
| `smtp_encryption`, `smtp_from_address`, `smtp_from_name`, `smtp_reply_to` | string | null | |
| `api_provider`, `api_base_url` | string | null | API subtype (4 cols) |
| `api_key`, `api_secret` | text | null | cast `encrypted`, hidden |
| timestamps | | | |

Casts: `is_active` boolean, `smtp_port`/`hourly_send_limit` integer, three `encrypted`. **Relations:** `magicLinks()`, `externalEmailLogs()` hasMany; `templates()` belongsToMany EmailTemplate via `email_app_template` withTimestamps. Sparse two-subtype table (F54). Nothing persists the hourly counter — rate limiting must be cache-based.

### `email_app_template`

**No model.** Migration: `2026_04_26_000001`
`id`, `email_app_id` FK cascade, `email_template_id` FK cascade, timestamps, unique `(email_app_id, email_template_id)`.

### `external_email_logs`

Model: `app/Models/ExternalEmailLog.php` · Migration: `2026_04_26_000001`
`id`, `magic_link_id` null FK magic_links nullOnDelete, `email_app_id` null FK email_apps nullOnDelete, `project_id` null FK projects nullOnDelete, `status` string NOT NULL (free), `provider` string null, `to_email` string, `subject` string null, `error_message` text null, `request_payload` json null, `response_payload` json null, `attempted_at` timestamp null, `sent_at` timestamp null, timestamps. Index `(status, created_at)`.
Casts: two arrays, two datetimes. **Relations:** `emailApp()`, `magicLink()`, `project()` belongsTo. A second email record store with no link to `emails`/`conversations` (F31). All three FKs nullable — a log row can float free.

### Domain 5 — smells / decisions needed

| # | Smell |
|---|---|
| 5.1 | **`emails.to` is `varchar(255)` cast to `array`** (F1). |
| 5.2 | **`EmailObserver` compares enum instances to string constants with `===`** (`$email->type === Email::TYPE_RECEIVED`, `$newStatus === Email::STATUS_SENT`) — always false, so `projects.last_email_received/sent` and `leads.contacted_at` are never updated by the observer; the approval-notification branches are empty `if` bodies. |
| 5.3 | `emails.status` DB enum widened four times by raw MySQL `ALTER TABLE` and still lacks `EmailStatus::RejectedReceived` (F15), which is what `Email::STATUS_REJECTED` resolves to. |
| 5.4 | `Email::STATUS_PENDING_APPROVAL` is the *received* variant and `STATUS_PENDING_APPROVAL_SENT` is the plain one — constant names invert the enum names. |
| 5.5 | `Email::$appends['can_approve']` runs up to four permission checks and two relation loads per row (F77); `can_open` is a constant `false`. |
| 5.6 | `emails.template_data` is double-encoded and carries three unrelated payloads (F3); `emails.email_template` slug string coexists with `emails.template_id` FK and `campaigns.email_template` inline body (F32). |
| 5.7 | `Email::$fillable` lists `last_communication_at`/`contacted_at` (lead columns) (F11). |
| 5.8 | `emails.sender_id` had its FK dropped for the morph; `sender_type` defaults to a hard-coded FQCN; `User::sentEmails()` ignores `sender_type`. |
| 5.9 | `conversations` is not soft-deletable but `emails` is (F101); `conversations.conversable_*` has no index; `conversations.project_id` nullable so an email can belong to no project (and `can_approve` then falls through to lead permissions). |
| 5.10 | `conversations.ai_summary_status` reuses `EmailDraftStatus` (`queued/writing/ready/failed`) — a draft-state enum on a summary column. |
| 5.11 | `emails.conversation_id` was made nullable via `ALTER TABLE … MODIFY` after dropping and re-adding the FK by name (`emails_conversation_id_foreign`) — assumes MySQL's default constraint naming. |
| 5.12 | `email_templates`, `placeholder_definitions` have no `$casts` — six booleans return as ints. |
| 5.13 | `placeholder_definitions.source_model/source_attribute` are FQCN+attribute strings (F63); the catalogue is regenerated from template regex scans (F108). |
| 5.14 | `EmailTemplatePlaceholder` model is empty and targets a non-existent table (Appendix E). |
| 5.15 | `email_apps` is a sparse SMTP/API two-subtype table (F54) with a free-string `delivery_mode`; `hourly_send_limit` has no persisted counter. |
| 5.16 | `external_email_logs` duplicates the email record concept with no FK to `emails` (F31); all its FKs are nullable. |
| 5.17 | `emails.read_at` is one of three read-tracking mechanisms (F30) alongside `UserInteraction` rows on the same table. |
| 5.18 | `Email::project()` is a method returning a model, not a relation — cannot be eager-loaded or `whereHas`'d. |
| 5.19 | Migration filename `create_template_placeholders_table` creates `placeholder_definitions` + `email_template_placeholder` (F98). |

---

## Domain 6 — Workflows, steps, execution logs, prompts

### `workflows`

Model: `app/Models/Workflow.php` · Migrations: `2025_09_12_054900`, `2025_09_16_035500`
`id`, `name` string, `description` text null, `trigger_event` string NOT NULL (free string; matched by `WorkflowTriggerListener` against `$event->eventName`; `'schedule.run'` is special-cased in `WorkflowEngineService`), `is_active` boolean default true, `deleted_at` SoftDeletes, timestamps.
Casts: `is_active` boolean. Boot: `deleting` soft-deletes each step individually (N queries). **Relations:** `steps()` hasMany WorkflowStep ordered by `step_order`; `logs()` hasMany ExecutionLog; `schedules()` morphMany Schedule `scheduledItem` (**broken column names**, F18). `runScheduled()` dispatches `RunWorkflowJob`. No unique on `name`.

### `workflow_steps`

Model: `app/Models/WorkflowStep.php` · Migrations: `2025_09_12_055100`, `2025_09_16_035500`
`id`, `workflow_id` FK workflows cascade, `step_order` int, `name` string, `step_type` string default `'AI_PROMPT'` (free; handlers exist for `AI_PROMPT`, `CONDITION`, `FOR_EACH`, `DEFINE_VARIABLE`, `QUERY_DATA`, `TRANSFORM_CONTENT`, `ACTION_CREATE_RECORD`, `ACTION_UPDATE_RECORD`, `ACTION_SEND_EMAIL`, `ACTION_PROCESS_EMAIL`, `ACTION_FETCH_API_DATA`, `ACTION_SYNC_RELATIONSHIP`, plus `TRIGGER`/`ACTION` legacy — see `app/Services/StepHandlers/`), `prompt_id` null FK prompts nullOnDelete, `step_config` json null (array), `condition_rules` json null (array), `delay_minutes` int default 0, `deleted_at` SoftDeletes. **No `created_at`/`updated_at`** (`$timestamps = false`) yet has `deleted_at` (F100).
**Relations:** `workflow()`, `prompt()` belongsTo; **`children()`, `yes_steps()`, `no_steps()` are `hasMany(WorkflowStep, 'step_config->_parent_id')`** filtered on `step_config->_branch` — the tree and branching live at JSON paths (F57); no index is possible on MySQL without a generated column. No unique on `(workflow_id, step_order)`.

### `execution_logs`

Model: `app/Models/ExecutionLog.php` · Migrations: `2025_09_12_055200`, `2026_03_01_222556`
`id` bigIncrements, `workflow_id` FK workflows (**no onDelete**, and `workflows` is soft-deletable — force-delete fails), `execution_id` string null indexed (run correlation id), `step_id` FK workflow_steps (**no onDelete**), `triggering_object_id` string null (**no type column** — pseudo-morph, F67), `parent_execution_log_id` ubigint null FK self nullOnDelete, `status` string (free), `input_context` json null, `raw_output` json null, `parsed_output` json null, `error_message` text null, `duration_ms` int null, `token_usage` json null (**no cast**), `cost` decimal(10,6) null, `executed_at` timestamp `useCurrent()`. **No timestamps** (`$timestamps = false`).
Casts: three arrays. **Relations:** `workflow()`, `step()`, `parentLog()` belongsTo; `childLogs()` hasMany. No index on `(workflow_id, executed_at)` or `status`. Unbounded growth with JSON payloads per step.

### `prompts`

Model: `app/Models/Prompt.php` · Migrations: `2025_09_12_055000`, `2025_09_14_130900`, `2025_09_14_144500` (**duplicate of the previous, guarded by `hasColumn`**)
`id`, `name` string, `category` string null, `version` int default 1, `system_prompt_text` text, `model_name` string default `'gemini-2.5-flash-preview-05-20'` (F104), `generation_config` json null, `template_variables` json null, `response_variables` json null, `response_json_template` json null, `status` string default `'active'` (free), timestamps. Unique `(name, version)`.
Casts: four arrays. **Relations:** `steps()` hasMany WorkflowStep. `workflow_steps.prompt_id` points at a specific version row; there is no "current version" pointer, so bumping a prompt version orphans steps on the old one.

### Domain 6 — smells / decisions needed

| # | Smell |
|---|---|
| 6.1 | **`workflow_steps` tree lives at `step_config->_parent_id`** with yes/no branches at `step_config->_branch` — three `hasMany` relations keyed on JSON paths, unindexable on MySQL (F57). |
| 6.2 | `workflow_steps` and `execution_logs` have `$timestamps = false`; `workflow_steps` still carries `deleted_at` (F100). |
| 6.3 | `execution_logs.workflow_id` and `step_id` FKs have no `onDelete` while both parents are soft-deletable (F102). |
| 6.4 | `execution_logs.triggering_object_id` is a string id with no type column (F67); `token_usage` has no cast. |
| 6.5 | `workflows.trigger_event`, `workflow_steps.step_type`, `execution_logs.status`, `prompts.status` are free strings; the `step_type` vocabulary (14 values) exists only in handler class names. |
| 6.6 | `prompts.model_name` defaults to a dated model id as a DB column default (F104); `2025_09_14_144500` duplicates `2025_09_14_130900`. |
| 6.7 | Prompt versioning by `(name, version)` unique with steps pinned to a row id — no "latest" pointer; version bumps silently detach steps. |
| 6.8 | `Workflow::schedules()` is the broken `morphMany(…, 'scheduledItem')` (F18). |
| 6.9 | Workflow definitions also live in `config/automation.php` and the repo-root `workflow_steps.json` (F48). |
| 6.10 | No unique on `(workflow_id, step_order)`; `Workflow::booted()` soft-deletes steps one query at a time. |

---

## Domain 7 — Finance: transactions, invoices, bills, approvals, currency rates

### `transactions`

Model: `app/Models/Transaction.php` · Migrations: `2023_07_15_010753`, `2025_07_24_224825`, `2025_07_27_000726`, `2025_08_12_051700`, `2026_05_11_225320`, `2026_05_12_054135`, `2026_07_28_123104`, `2026_07_28_124820`, `2026_07_28_125810`, `2026_07_29_155915`

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | bigint auto | | |
| `project_id` | ubigint | NOT NULL, FK projects cascade | every transaction needs a project — even invoice/bill payments |
| `description` | varchar(255) | | |
| `currency` | string | default `'aud'` | **lowercase default**; compared uppercase elsewhere |
| `exchange_rate` | decimal(15,6) | null | added late; only `Bill::paid_amount` reads it, with AUD/PKR special cases |
| `amount` | **decimal(10,2)** | | vs `decimal(15,2)` on bills/invoices (F86) |
| `is_paid` | boolean | default false | **not cast** |
| `transaction_id` | ubigint | null, **no FK** | `foreignIdFor(Transaction)` self-reference; not fillable, no relation, only a backfill `WHERE … IS NULL` (dead) |
| `payment_date` | date | null | **not cast** |
| `user_id` | ubigint | null, FK users set null | |
| `client_id` | ubigint | null, FK clients set null | |
| `hours_spent` | decimal(8,2) | null | |
| `type` | enum(`income`,`expense`,`bonus`) | default `expense` | cast string; no PHP enum |
| `transaction_type_id` | ubigint | null, FK transaction_types nullOnDelete | |
| `bill_id` | ubigint | null, FK bills (**no onDelete**) | |
| `xero_payment_id` | string | null | |
| `invoice_id` | ubigint | null, FK invoices (**no onDelete**) | |
| `bank_transaction_id` | string | null | Airwallex/Stripe ref, free string |
| `deleted_at` | | | SoftDeletes (added `after('updated_at')`) |
| timestamps | | | |

Casts: `amount` decimal:2, `exchange_rate` decimal:6, `hours_spent` decimal:2, `currency`/`type` string.
**Traits:** `HasFinancialCalculations` (a controller concern mixed into the model — currency conversion + display processing). **Observer** `TransactionObserver`: on create/update/delete loads **all** project transactions and recomputes `projects.profit_margin_percentage` in USD (F75); deleted-event fires on soft delete too.
**Relations:** `project()`, `user()`, `client()`, `transactionType()`, `bill()`, `invoice()` belongsTo; `files()` morphMany. Five business meanings in one table (F52): project income, project expense, bonus payout, bill payment (`bill_id`), invoice payment (`invoice_id`). `Invoice::recalculateStatus()` and `Bill::paid_amount` both sum `where('is_paid', true)`.

### `transaction_types`

Model: `app/Models/TransactionType.php` · Migrations: `2025_08_12_051500`, `2026_05_12_054135`
`id`, `name` string unique, `slug` string unique, `xero_account_code` string null, `created_by_user_id` null FK users nullOnDelete, timestamps.
**Relations:** `creator()` belongsTo; `transactions()`, `bills()` hasMany. Seeded twice (seeder + data migration, Appendix D). Coexists with the `transactions.type` enum — two type axes.

### `invoices`

Model: `app/Models/Invoice.php` · Migrations: `2026_05_12_054135`, `2026_05_18_213926_add_xero_fields_to_invoices_table`, `2026_06_01_000001`, `2026_06_14_000001`, `2026_08_02_163350`

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | bigint auto | | |
| `client_id` | ubigint | NOT NULL, FK clients (**no onDelete**) | |
| `project_id` | ubigint | NOT NULL, FK projects (**no onDelete**; projects soft-deletable) (F102) | |
| `total_amount` | decimal(15,2) | | not derived from items |
| `status` | string | default `'draft'` | free; `recalculateStatus()` uses `approved/sent/paid/partial_paid` |
| `due_date` | date | null | |
| `currency` | char(3) | null | |
| `line_amount_type` | varchar(20) | default `'Exclusive'` | Xero `LineAmountTypes` |
| `xero_branding_theme_id` | string | null | |
| `xero_payment_service_ids` | json | null | array — **JSON array of `xero_payment_services` FKs** (F59) |
| `xero_invoice_id` | string | null | |
| `invoice_number` | string | null | **shadowed by `getInvoiceNumberAttribute()` = `'OZI'.id`** — the Xero number is unreachable (F5) |
| `deleted_at` | | | SoftDeletes |
| timestamps | | | |

Casts: `total_amount` decimal:2, `due_date` date, `xero_payment_service_ids` array.
**Relations:** `client()`, `project()` belongsTo; `invoiceItems()`, `comments()` (InvoiceComment), `transactions()` hasMany; `files()` morphMany. `recalculateStatus()` converts each paid transaction into the invoice currency via `CurrencyConversionService` and falls back to raw amount on failure.

### `invoice_items`

Model: `app/Models/InvoiceItem.php` · Migrations: `2026_05_14_090000`, `2026_05_14_120000`, `2026_05_18_120000`
`id`, `invoice_id` FK invoices cascade, `project_service_id` FK project_services cascade, `milestone_key` varchar(255) NOT NULL (**string key into `project_services.payment_breakdown` JSON**, F58), `label` string, `description` text null, `quantity` decimal(15,2) default 1, `unit_price` decimal(15,2) default 0, `tax_type` varchar(50) null, timestamps. Index `invoice_id`; **unique `(project_service_id, milestone_key)` was dropped** and replaced by a plain index `invoice_items_service_milestone_idx` (F16).
Casts: `quantity`, `unit_price` decimal:2. **Relations:** `invoice()`, `projectService()` belongsTo. No line total column; `invoices.total_amount` is not reconciled to `Σ quantity × unit_price`.

### `invoice_comments`

Model: `app/Models/InvoiceComment.php` · Migration: `2026_05_14_120100`
`id`, `invoice_id` FK cascade, `project_id` FK cascade (denormalised), `user_id` FK users cascade, `action` varchar(30) default `'comment'` (free — an audit-event kind), `content` text null, `meta` json null (array), timestamps. Index `(invoice_id, created_at)`.
**Relations:** `invoice()`, `project()`, `user()` belongsTo. Fifth comment store (F26); doubles as an audit log via `action`.

### `bills`

Model: `app/Models/Bill.php` · Migrations: `2026_05_12_054135`, `2026_05_12_054318`, `2026_05_27_000003`, `2026_05_27_031130` (**same name as the previous, different columns**), `2026_08_04_154639`

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | bigint auto | | |
| `contractor_id` | ubigint | **null** (was NOT NULL), FK users (no onDelete) | nullable via `->change()` |
| `project_id` | ubigint | NOT NULL, FK projects (no onDelete) | |
| `project_expendable_id` | ubigint | **null** (was NOT NULL), FK (no onDelete) | |
| `transaction_type_id` | ubigint | null, FK (no onDelete) | |
| `xero_account_code` | varchar(50) | null | |
| `xero_tax_type` | varchar(50) | null | |
| `amount` | decimal(15,2) | | |
| `status` | string | default `'pending_approval'` | cast `BillStatus` (5 cases) |
| `xero_invoice_id` | string | null | |
| `reference_number` | string | null | |
| `due_date` | date | null | |
| `currency` | string | null, default `'AUD'` | **uppercase default** (vs `transactions` lowercase) |
| `deleted_at` | | | SoftDeletes |
| timestamps | | | |

Casts: `amount` decimal:2, `status` enum, `due_date` date. **Traits:** `LogsActivity` (all fillable, dirty only).
**`$appends = ['paid_amount', 'remaining_amount', 'bill_number']`** — `paid_amount` queries transactions and converts currency per row with AUD/PKR special-casing (F76); `remaining_amount` calls it again.
**Relations:** `contractor()` belongsTo User; `project()`, `expendable()` (ProjectExpendable), `transactionType()` belongsTo; `transactions()` hasMany; `files()` morphMany; `paymentDetail()` hasOne BillPaymentDetail; `approvalInstance()` morphOne ApprovalInstance `approvable` — **the only `approvable` target in the app**.
`recalculateStatus()` mirrors `Invoice::recalculateStatus()`.

### `bill_payment_details`

Model: `app/Models/BillPaymentDetail.php` · Migration: `2026_05_27_000001`
`id`, `bill_id` FK bills cascade **unique**, `contractor_id` FK users cascade indexed, `payment_method` varchar(50) (free), `details` text (cast `encrypted:array` — bank details), timestamps.
**Relations:** `bill()`, `contractor()` belongsTo. One-to-one with `bills`; bank details are copied per bill rather than stored once per contractor (F80).

### `approval_flows`

Model: `app/Models/ApprovalFlow.php` · Migration: `2026_05_27_000002`
`id`, `name` string, `approvable_type` string NOT NULL (**bare FQCN, no id** — F65), `project_id` null FK projects nullOnDelete, `is_active` boolean default true, `is_default` boolean default false, timestamps. Index `(approvable_type, project_id, is_active)`.
Casts: two booleans. **Relations:** `project()` belongsTo; `steps()` hasMany ApprovalFlowStep ordered; `instances()` hasMany. Nothing enforces a single default per `(approvable_type, project_id)`.

### `approval_flow_steps`

Model: `app/Models/ApprovalFlowStep.php` · Migration: `2026_05_27_000002`
`id`, `approval_flow_id` FK cascade, `step_order` uint, `approver_type` varchar(20) (`role`|`user`, free), `approver_role_id` null FK roles nullOnDelete, `approver_user_id` null FK users nullOnDelete, `label` string null, timestamps. Unique `(approval_flow_id, step_order)`; index `(approval_flow_id, approver_type)`.
**Relations:** `flow()`, `approverRole()`, `approverUser()` belongsTo. No casts. No check that exactly one of the two approver FKs is set.

### `approval_instances`

Model: `app/Models/ApprovalInstance.php` · Migration: `2026_05_27_000002`
`id`, `approval_flow_id` FK cascade, `approvable_id`/`approvable_type` morphs, `current_step_order` uint null, `status` varchar(30) default `'pending'` (free; comment says `pending|in_progress|completed|rejected`), timestamps. Index `(approval_flow_id, status)`. **No unique on the morph pair** — one bill can have several instances.
**Relations:** `flow()` belongsTo; `approvable()` morphTo (Bill only); `steps()` hasMany ordered. `currentPendingStep()` returns a model. No casts.

### `approval_instance_steps`

Model: `app/Models/ApprovalInstanceStep.php` · Migration: `2026_05_27_000002`
`id`, `approval_instance_id` FK cascade, `step_order` uint, `approver_type` varchar(20), `approver_role_id` null FK roles nullOnDelete, `approver_user_id` null FK users nullOnDelete, `label` string null, `status` varchar(30) default `'pending'` (`pending|approved|rejected|skipped`, free), `acted_by_user_id` null FK users nullOnDelete, `acted_at` timestamp null, `comment` text null, timestamps. Unique `(approval_instance_id, step_order)`; index `(approval_instance_id, status)`.
Casts: `acted_at` datetime. **Relations:** `instance()`, `approverRole()`, `approverUser()`, `actedBy()` belongsTo. A snapshot copy of `approval_flow_steps` at instantiation (correct for auditability, but the two tables share 5 columns with no shared definition).

### `currency_rates`

Model: `app/Models/CurrencyRate.php` · Migration: `2025_07_26_205949`
`id`, `currency_code` char(3) unique, `rate_to_usd` decimal(15,6), `fetched_at` timestamp NOT NULL, timestamps.
Casts: `fetched_at` datetime, `rate_to_usd` decimal:6. No relations. **No history** — one row per currency, overwritten (F85); cached 24h under `currency_rates_to_usd` by two separate code paths (`Project::convertCurrency`, `HasFinancialCalculations`). `transactions.exchange_rate` was bolted on later to freeze a rate per transaction, but only bills read it.

### Domain 7 — smells / decisions needed

| # | Smell |
|---|---|
| 7.1 | **`transactions` is five things** (income, expense, bonus, bill payment, invoice payment) with a 3-value `type` enum (F52); `project_id` is NOT NULL so an invoice payment must also name a project. |
| 7.2 | `Invoice::getInvoiceNumberAttribute()` shadows the persisted `invoice_number` column (F5). |
| 7.3 | `invoice_items` unique `(project_service_id, milestone_key)` deliberately dropped (F16); `milestone_key` keys into `project_services.payment_breakdown` JSON (F58); `invoices.total_amount` is not derived from items. |
| 7.4 | `invoices.xero_payment_service_ids` is a JSON array of FKs (F59). |
| 7.5 | **Currency casing**: `transactions.currency` defaults to `'aud'`, `bills.currency` to `'AUD'`, comparisons in `Bill::paid_amount` are case-sensitive `===`. |
| 7.6 | **Money precision inconsistent**: `transactions.amount decimal(10,2)` vs `decimal(15,2)` elsewhere (F86); `transactions.is_paid` and `payment_date` are not cast. |
| 7.7 | `transactions.transaction_id` is a dead self-reference with no FK, no relation, not fillable. |
| 7.8 | `Bill::$appends['paid_amount']` runs a transactions query plus per-row currency conversion (with hard-coded AUD/PKR branches) on every serialisation; `remaining_amount` repeats it (F76). |
| 7.9 | `TransactionObserver` reloads every project transaction and recomputes `profit_margin_percentage` on each create/update/delete, including soft deletes (F75). `HasFinancialCalculations` is a controller concern mixed into a model and an observer. |
| 7.10 | **FKs with no `onDelete` on soft-deletable parents**: `invoices.client_id`/`project_id`, `bills.project_id`/`contractor_id`/`project_expendable_id`/`transaction_type_id`, `transactions.bill_id`/`invoice_id` (F102). |
| 7.11 | `bills.contractor_id` and `project_expendable_id` made nullable via `->change()` — a bill can now belong to no contractor and no contract, but `bill_payment_details.contractor_id` is NOT NULL. |
| 7.12 | Two migrations named `add_xero_fields_to_bills_table` (`2026_05_27_000003`, `2026_05_27_031130`) add different columns. |
| 7.13 | `bill_payment_details` stores encrypted bank details **per bill** (unique on `bill_id`) rather than per contractor (F80). |
| 7.14 | `approval_flows`/`instances`/`steps` are used **only by `Bill`** while eight other approval workflows are hand-rolled columns (F43); `approval_flows.approvable_type` is a bare FQCN (F65); `approval_instances` has no unique on `(approvable_type, approvable_id)`; `approver_type` and all four `status` columns are free strings. |
| 7.15 | `invoice_comments` is a fifth comment store doubling as an audit log via `action` (F26). |
| 7.16 | `currency_rates` keeps no history (F85); two code paths cache it under the same key with independent implementations. |
| 7.17 | `transaction_types` (FK) and `transactions.type` (enum) are two orthogonal type axes on the same row; `transaction_types` is seeded twice. |
| 7.18 | Xero account codes are loose strings on `transaction_types`, `bills`, `project_services`, `crm_services` with no `xero_accounts` table (F84). |

---

## Domain 8 — Xero, Airwallex, Stripe integration tables

### `xero_connections`

Model: `app/Models/XeroConnection.php` · Migrations: `2026_05_12_000001`, `2026_05_18_213926_add_default_branding_theme_to_xero_connections_table`

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | bigint auto | | |
| `provider` | string | **unique** | always `'xero'` (constant) — one row ever (F83) |
| `connected_by_user_id` | ubigint | null, FK users nullOnDelete | |
| `status` | string | default `'disconnected'` | free; `isConnected()` checks `'connected'` |
| `selected_tenant_id` | string | null, indexed | duplicates `xero_tenants.is_selected` (F42) |
| `selected_tenant_name` | string | null | |
| `default_branding_theme_id` | string | null | |
| `access_token`, `refresh_token`, `id_token` | text | null | cast `encrypted`, hidden |
| `scope` | text | null | |
| `token_type` | string | null | |
| `access_token_expires_at`, `refresh_token_expires_at`, `last_refreshed_at`, `last_connected_at`, `disconnected_at` | timestamp | null | datetime |
| `last_error` | text | null | |
| `metadata` | json | null | array |
| timestamps | | | |

Indexes: `(provider, status)`, `selected_tenant_id`. **Relations:** `connectedBy()` belongsTo; `tenants()`, `paymentServices()` hasMany. `AirwallexXeroBankMapping` also FKs here but no reverse relation is declared.

### `xero_tenants`

Model: `app/Models/XeroTenant.php` · Migration: `2026_05_12_000002`
`id`, `xero_connection_id` FK cascade, `tenant_id` string, `tenant_name`, `tenant_type`, `auth_event_id`, `connection_id` string null, `created_date_utc`, `updated_date_utc` timestamp null, `is_selected` boolean default false, timestamps. Unique `(xero_connection_id, tenant_id)`; index `(xero_connection_id, is_selected)`.
Casts: two datetimes, boolean. **Relations:** `connection()` belongsTo. Nothing enforces a single `is_selected` per connection.

### `xero_payment_services`

Model: `app/Models/XeroPaymentService.php` · Migration: `2026_06_01_000002`
`id`, `xero_connection_id` FK cascade, `payment_service_id` string, `name` string, `slug` string null, `status` string null (free), `provider` string null, `raw_payload` json null (array), `last_synced_at` timestamp null, timestamps. Unique `xps_conn_service_uidx (xero_connection_id, payment_service_id)`; index `xps_conn_status_idx`.
**Relations:** `connection()` belongsTo. Referenced from `invoices.xero_payment_service_ids` JSON array only (F59).

### `airwallex_xero_bank_mappings`

Model: `app/Models/AirwallexXeroBankMapping.php` · Migrations: `2026_07_29_140045`, `2026_07_29_141600`
`id`, `xero_connection_id` FK cascade, `airwallex_currency` char(3), `xero_account_id` uuid, `xero_account_name` varchar(255), `xero_currency_code` char(3), timestamps. Unique changed from `(xero_connection_id, airwallex_currency)` to `awx_xero_mapping_account_unique (xero_connection_id, xero_account_id)` — one currency may now map to several Xero accounts.
**Relations:** `connection()` belongsTo. No casts. Maps Airwallex settlement currency → Xero bank account; the only place a Xero account id is typed as `uuid` (elsewhere loose strings, F84). No `airwallex_*` table stores Airwallex accounts or transactions — `AirwallexService` works from API + `transactions.bank_transaction_id`.

### `stripe_configurations`

Model: `app/Models/StripeConfiguration.php` · Migration: `2026_03_15_064259`
`id`, `app_name` string, `app_id` string unique (business key), `stripe_secret_key` text (cast `encrypted`, hidden), `stripe_public_key` string, `settings` json null (array), timestamps. No relations — `stripe_subscriptions.app_id` matches by string with no FK (F82).

### `stripe_subscriptions`

Model: `app/Models/StripeSubscription.php` · Migration: `2026_03_25_212028_create_stripe_subscriptions_table`
`id`, `app_id` string indexed (**no FK to `stripe_configurations.app_id`**), `stripe_subscription_id` string unique, `stripe_customer_id` string null, `status` string (free — Stripe's vocabulary), `amount_total` int null (**minor units**), `currency` string default `'aud'`, `cancel_at`, `canceled_at`, `ended_at` timestamp null, `metadata` json null, timestamps.
Casts: three datetimes, `metadata` array. **Relations:** `payments()` hasMany StripeSubscriptionPayment on `stripe_subscription_id` ↔ `stripe_subscription_id` (string business key join). No link to `clients`/`projects`/`invoices` — the subscription is not attached to any domain entity except via `metadata`.

### `stripe_subscription_payments`

Model: `app/Models/StripeSubscriptionPayment.php` · Migration: `2026_03_25_212028_create_stripe_subscription_payments_table`
`id`, `stripe_subscription_id` string indexed (**no FK**), `stripe_invoice_id` string unique, `amount` int (**minor units**), `currency` string default `'aud'`, `status` string (free), `paid_at` timestamp null, timestamps.
Casts: `paid_at` datetime. **Relations:** `subscription()` belongsTo on the string key. Not linked to `transactions` — Stripe income never reaches the P&L unless re-keyed by hand.

### `stripe_payouts`

Model: `app/Models/StripePayout.php` · Migration: `2026_08_11_220000`
`id` **string PK** (`po_…`), `trace_id` string null indexed, `statement_descriptor` string null indexed, `amount` decimal(15,2) default 0, `currency` varchar(10) default `'AUD'`, `status` varchar(50) default `'PAID'` (free, uppercase), `arrival_date` date null, `total_gross`, `total_fees`, `total_net` decimal(15,2) default 0, `breakdown` json null (array — per-charge detail, the only link to underlying charges, F81), timestamps.
Casts: four decimal:2, `arrival_date` date, `breakdown` array. `$incrementing = false`, `$keyType = 'string'`. **No relations, no FKs.** Written by `StripePayoutService`; matched to `transactions.bank_transaction_id` by string. Third PK strategy (F69) and the reason `activity_log.subject_id` is a string (F70).

### Domain 8 — smells / decisions needed

| # | Smell |
|---|---|
| 8.1 | **`xero_connections.provider` is UNIQUE and always `'xero'`** — single-tenancy in the schema (F83); `selected_tenant_id/_name` duplicate `xero_tenants.is_selected` (F42), and nothing enforces one selected tenant. |
| 8.2 | **Stripe uses three money representations**: `int` minor units (`stripe_subscriptions.amount_total`, `stripe_subscription_payments.amount`) and `decimal(15,2)` major units (`stripe_payouts`) (F86); currency defaults are `'aud'` on two tables and `'AUD'` on the third. |
| 8.3 | `stripe_subscriptions.app_id` → `stripe_configurations.app_id` and `stripe_subscription_payments.stripe_subscription_id` → `stripe_subscriptions.stripe_subscription_id` are string business-key joins with no FK (F82). |
| 8.4 | **Stripe tables are islands**: no FK from any Stripe row to `clients`, `projects`, `invoices`, or `transactions`; `stripe_payouts.breakdown` JSON is the only reconciliation path (F81). |
| 8.5 | `stripe_payouts` uses Stripe's `po_…` id as a string PK (F69/F70). |
| 8.6 | `xero_payment_services` is only referenced from a JSON array on `invoices` (F59). |
| 8.7 | `airwallex_xero_bank_mappings.xero_account_id` is the only typed (`uuid`) Xero account ref; four other tables hold Xero account codes as loose strings (F84). The unique was flipped from per-currency to per-account, allowing ambiguous currency→account resolution. |
| 8.8 | All integration `status` columns (`xero_connections`, `xero_payment_services`, `stripe_subscriptions`, `stripe_subscription_payments`, `stripe_payouts`) are free strings mirroring provider vocabularies with no local enum. |
| 8.9 | `XeroConnection` declares no `bankMappings()` reverse relation despite the FK. |
| 8.10 | No Airwallex table at all — Airwallex balances/transactions exist only in API calls and `transactions.bank_transaction_id`. |

---

## Domain 9 — Bonus engine and points

### `bonus_configurations`

Model: `app/Models/BonusConfiguration.php` · Migration: `2025_07_24_110845`

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | bigint auto | | |
| `name` | string | | |
| `type` | enum(`bonus`,`penalty`) | | no PHP enum |
| `amountType` | enum(`percentage`,`fixed`,`all_related_bonus`) | | **camelCase column** (F94) |
| `value` | decimal(10,2) | default 0 | percentage or fixed money — unit depends on `amountType` |
| `appliesTo` | enum(`task`,`milestone`,`standup`,`late_task`,`late_milestone`,`standup_missed`) | | camelCase; maps to `bonus_transactions.source_type` (which has a *different* value set) |
| `targetBonusTypeForRevocation` | string | null | camelCase; free |
| `isActive` | boolean | default true | camelCase; cast boolean |
| `uuid` | string | **unique** | client-generated second identity (F69) |
| `user_id` | ubigint | null, FK users cascade | owner/creator |
| timestamps | | | |

Casts: `value` decimal:2, `isActive` boolean. **Relations:** `user()` belongsTo; `bonusConfigurationGroups()` belongsToMany via `bonus_configuration_group_items` (`configuration_id`,`group_id`) withPivot `sort_order`; `transactions()` hasMany BonusTransaction.
**Business logic on the model:** `isApplicableTo`, `calculateAmount` (returns 0 for `all_related_bonus`), `shouldApplyToStandup` (weekday check + one-per-day dedupe by `created_at` date), `shouldApplyToTask` / `shouldApplyToMilestone` — **dedupe by `(user, project, config, source_type)` with no `source_id`**, so a user can receive a given task bonus **once per project ever**, not once per task. No currency column — `value` is implicitly PKR.

### `bonus_configuration_groups`

Model: `app/Models/BonusConfigurationGroup.php` · Migration: `2025_07_24_112412`
`id`, `name` string, `description` text null, `user_id` FK users cascade NOT NULL, `is_active` boolean default true, timestamps.
Casts: `is_active` boolean. **`$with = ['bonusConfigurations']`** (global eager load) and **`$appends = ['configurations']`** whose accessor returns `$this->bonusConfigurations` — the appended key shadows the relation name in JSON (F6) and serialises every config twice.
**Relations:** `user()` belongsTo; `bonusConfigurations()` belongsToMany ordered by pivot `sort_order`; `projects()` belongsToMany via `project_bonus_configuration_group` (`group_id`,`project_id`). `duplicate()` replicates group + pivot rows.

### `bonus_configuration_group_items`

**No model.** Migration: `2025_07_24_112453`
`id`, `group_id` FK bonus_configuration_groups cascade, `configuration_id` FK bonus_configurations cascade, `sort_order` int default 0, timestamps. Unique `(group_id, configuration_id)`. Pivot naming (`_items`, `group_id`/`configuration_id`) differs from `project_bonus_configuration_group` (F95).

### `project_bonus_configuration_group`

**No model.** Migration: `2025_07_24_112535`
`id`, `project_id` FK projects cascade, `group_id` FK bonus_configuration_groups cascade, timestamps. Unique `(project_id, group_id)`. `Project::getActiveBonusConfigurations()` walks groups→configs in PHP with one query per group.

### `bonus_transactions`

Model: `app/Models/BonusTransaction.php` · Migration: `2025_07_24_132613`

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | bigint auto | | |
| `user_id` | ubigint | FK users cascade | |
| `project_id` | ubigint | FK projects cascade | |
| `bonus_configuration_id` | ubigint | null, FK set null | |
| `type` | enum(`bonus`,`penalty`) | | no PHP enum |
| `amount` | decimal(10,2) | | **no currency column** (F88) |
| `description` | string | | |
| `status` | enum(`pending`,`approved`,`rejected`,`processed`) | default `pending` | cast `BonusTransactionStatus` ✓ |
| `source_type` | enum(`standup`,`task`,`milestone`,`manual`,`other`) | | pseudo-morph with `source_id` (F67); values ≠ `bonus_configurations.appliesTo` (`late_task`, `late_milestone`, `standup_missed` have no `source_type`) |
| `source_id` | string | null | |
| `processed_at` | datetime | null | |
| `metadata` | json | null | array |
| timestamps | | | |

Casts: `amount` decimal:2, `processed_at` datetime, `metadata` array, `status` enum. **Relations:** `user()`, `project()`, `bonusConfiguration()` belongsTo. Scopes `ofType`, `withStatus`, `fromSource`. `User::calculateTotalBonus()` etc. and `Project::getBonusSummary()` aggregate this table in PHP. Money side of the reward system; `points_ledgers` is the points side, with no link between them.

### `kudos`

Model: `app/Models/Kudo.php` · Migration: `2025_08_09_034849`
`id`, `sender_id` FK users cascade, `recipient_id` FK users cascade, `project_id` FK projects cascade, `comment` text, `is_approved` boolean default false, `deleted_at` SoftDeletes, timestamps.
Casts: `is_approved` boolean. **Relations:** `sender()`, `recipient()`, `project()` belongsTo; `points()` morphMany PointsLedger `pointable`. **Observer** `KudoObserver`: on `updated` with `is_approved` false→true dispatches `KudoApprovedEvent` (points). Duplicates `project_notes.type='kudos'` (F35). No self-kudos guard.

### `points_ledgers`

Model: `app/Models/PointsLedger.php` · Migrations: `2025_08_09_035656`, `2025_08_10_234500`
`id`, `user_id` FK users cascade, `project_id` FK projects cascade NOT NULL (**points for non-project events like weekly streaks still need a project**), `points_awarded` decimal(10,2), `description` string, `pointable_id`/`pointable_type` morphs (Kudo, ProjectNote, Email via `PointsService`, **WeeklyStreak — no table**, F8), `status` enum(`pending`,`refunded`,`cancelled`,`paid`,`consumed`,`rejected`) default `pending` (no PHP enum; six string constants on the model), `meta` json null (array), `deleted_at` SoftDeletes, timestamps.
Casts: `points_awarded` decimal:2, `meta` array. **`created_at` is fillable** (backdating allowed). **Relations:** `user()`, `project()` belongsTo; `pointable()` morphTo. No unique on `(pointable_type, pointable_id, user_id)` — double-award is representable (and `Milestone::booted()` makes it likely, 4.3).

### `monthly_budgets`

Model: `app/Models/MonthlyBudget.php` · Migrations: `2025_08_09_035733`, `2025_08_10_215500`
`id`, `year` int, `month` int, `total_budget_pkr` decimal(10,2), `number_of_employees` int default 0, `number_of_contractors` int default 0, `employee_pool_input` string null (free — a formula/percent string), `employee_bonus_pool_pkr`, `contractor_bonus_pool_pkr` decimal(10,2) default 0, `consistent_contributor_pool_pkr`, `high_achiever_pool_pkr` decimal(10,2), `team_total_points` decimal(10,2), `points_value_pkr` decimal(10,4), `most_improved_award_pkr`, `first_place_award_pkr` decimal(10,2), `second_place_award_pkr`, `third_place_award_pkr`, `contractor_of_the_month_award_pkr` decimal(10,2) default 0, `deleted_at` SoftDeletes, timestamps.
**No unique on `(year, month)`** (F74). All numerics cast. **`monthlyPoints()` is `hasMany(MonthlyPoint, ['year','month'], ['year','month'])` — composite-key hasMany, unsupported by Eloquent** (F7); calling it throws or produces a wrong query. Scope `forPeriod`. Nine `_pkr` column names (F87). A materialised monthly snapshot with no invalidation.

### `monthly_points`

Model: `app/Models/MonthlyPoint.php` · Migration: `2025_08_09_035813`
`id`, `user_id` FK users cascade, `year` int, `month` int, `total_points` decimal(10,2), `deleted_at` SoftDeletes, timestamps. Unique `(user_id, year, month)`.
Casts: `year`/`month` integer, `total_points` decimal:2. **Relations:** `user()` belongsTo. Scopes `forPeriod`, `forUser`. A materialised aggregate of `points_ledgers` with no invalidation (F74); soft-deletable, so the unique can block re-creation after a soft delete.

### `project_tiers` — see §3 (point multiplier source; `min/max_client_amount_pkr`)

### `WeeklyStreak` (no table)

Model: `app/Models/WeeklyStreak.php` — `$fillable = ['id']`, `WEEKLY_STREAK_BONUS = 500`. Instantiated unsaved in `CalculateWeeklyStreakBonusCommand` to supply a `pointable_type/id`; `points_ledgers` rows reference it (F8). No migration ever created `weekly_streaks`.

### Domain 9 — smells / decisions needed

| # | Smell |
|---|---|
| 9.1 | **Five camelCase columns on `bonus_configurations`** (`amountType`, `appliesTo`, `targetBonusTypeForRevocation`, `isActive`) in a snake_case schema (F94); `uuid` is a second identity beside `id` (F69). |
| 9.2 | **`bonus_configurations.appliesTo` (6 values) ≠ `bonus_transactions.source_type` (5 values)** — `late_task`, `late_milestone`, `standup_missed` bonuses cannot be recorded with a matching `source_type`, and the model's dedupe queries compare `source_type` to `appliesTo`. |
| 9.3 | **`shouldApplyToTask/Milestone` dedupe ignores `source_id`** — a user can earn a task bonus once per project, ever. |
| 9.4 | `BonusConfigurationGroup::$appends['configurations']` shadows `bonusConfigurations()` (F6) and `$with` eager-loads it globally — every group serialises its configs twice. |
| 9.5 | `MonthlyBudget::monthlyPoints()` is a composite-key `hasMany` (F7). |
| 9.6 | `WeeklyStreak` has no table but is a `pointable_type` (F8). |
| 9.7 | **Three reward currencies** (money in `bonus_transactions` with no currency column, points in `points_ledgers`, PKR budgets in `monthly_budgets`) with no FK tying a ledger row to the budget that prices it (F88, F89). |
| 9.8 | `points_ledgers.project_id` is NOT NULL, so non-project events (weekly streak) must borrow a project; `created_at` is fillable; no unique prevents double-award. |
| 9.9 | `monthly_budgets` has no unique on `(year, month)`; `monthly_points` is soft-deletable under a unique that includes no `deleted_at` (F74). |
| 9.10 | `kudos` table vs `project_notes.type='kudos'` (F35); standups have no table at all (F90) yet drive `shouldApplyToStandup`. |
| 9.11 | Pivot naming: `bonus_configuration_group_items` (`group_id`/`configuration_id`, `sort_order`) vs `project_bonus_configuration_group` (`group_id`/`project_id`) (F95); `sort_order` unreachable without a pivot model. |
| 9.12 | `Project::getActiveBonusConfigurations()` and siblings issue one query per group; `User::getBonusSummary()` aggregates in PHP. |
| 9.13 | `bonus_transactions.type`, `bonus_configurations.type/amountType/appliesTo`, `points_ledgers.status` are DB enums with no PHP enum (Appendix B). |
| 9.14 | `bonus_configurations.value` is a percentage or a money amount depending on `amountType`, with no unit column. |

---

## Domain 10 — Availability, attendance, activity telemetry, productivity

### `user_availabilities`

Model: `app/Models/UserAvailability.php` · Migrations: `2025_07_24_030156`, `2026_05_11_120000`, `2026_05_11_123000`, `2026_05_11_125000`, `2026_05_11_130000`

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | bigint auto | | |
| `user_id` | ubigint | FK users cascade | |
| `date` | date | | **no unique on `(user_id, date)`** |
| `is_available` | boolean | default true | |
| `did_not_show_up` | boolean | default false | |
| `did_not_show_up_reason_category_id` | ubigint | null, FK categories | see note on `onDelete` |
| `was_late` | boolean | default false | |
| `was_late_reason_category_id` | ubigint | null, FK categories | |
| `left_early` | boolean | default false | |
| `left_early_reason_category_id` | ubigint | null, FK categories | |
| `admin_comments` | text | null | |
| `actual_start_time`, `actual_end_time` | time | null | not cast |
| `reason` | text | null | **dead** — superseded by the three category FKs, never dropped |
| `time_slots` | json | null | array — planned windows |
| timestamps | | | |

**The three category FKs call `->nullableOnDelete()`, which is not a `ForeignKeyDefinition` method.** `Fluent::__call` silently records it as an attribute the grammar ignores, so the constraints are created with **no `ON DELETE` clause** (restrict). Deleting a category with attendance rows fails.
Casts: `date` date, four booleans, `time_slots` array.
**Traits:** `HasCategories` (the `categorizables` morph). **`$appends = ['did_not_show_up_reason', 'was_late_reason', 'left_early_reason']`** — each accessor reads the FK id but resolves the name from the **`categories` relation if loaded, else `Category::find()`** — so a reason is displayed only if the same category is *also* attached via `categorizables` or a query is run per row (F49).
**Relations:** `user()` belongsTo. No relation methods for the three category FKs.

### `user_activities`

Model: `app/Models/UserActivity.php` · Migrations: `2026_02_16_131558`, `2026_02_16_132354`, `2026_02_16_140854`, `2026_02_16_145741`, `2026_02_16_151223`, `2026_03_01_065439`, `2026_03_04_061000`, `2026_04_07_130000`

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | bigint auto | | |
| `user_id` | ubigint | FK users cascade | |
| `task_id` | ubigint | null, FK tasks nullOnDelete | added NOT NULL then `->change()`d nullable four days later |
| `domain` | string | indexed | |
| `url` | text | | |
| `title` | text | null | |
| `is_incognito`, `is_audible` | boolean | default false | |
| `tab_count` | int | default 0 | |
| `duration` | uint | default 0 | seconds, updated by heartbeat |
| `idle_state` | string | null | free (`active`/`idle`/`locked` from the browser API) |
| `category` | string | null | free; vocabulary in `config/activity_categories.php` |
| `is_category_override` | boolean | default false | |
| `hostname`, `browser` | string | null | |
| `metadata` | json | null | array |
| `recorded_at` | timestamp | indexed | |
| `last_heartbeat_at` | timestamp | null | |
| timestamps | | | |

Casts: two booleans, `tab_count`/`duration`/`task_id` integer, two datetimes, `metadata` array. **Relations:** `user()`, `task()` belongsTo. Browser-extension telemetry: one row per tab-focus event, mutated by heartbeats (`duration`, `last_heartbeat_at`) — a high-volume, high-churn table with `updated_at`, two single-column indexes, and **no index on `(user_id, recorded_at)`** (F73). `url` is `text` and may contain query strings with tokens; `is_incognito` rows are still stored.

### `user_productivities`

Model: `app/Models/UserProductivity.php` · Migrations: `2026_03_01_214407`, `2026_03_03_154226`
`id`, `user_id` FK users cascade, `date` date indexed, `stats_json`, `tasks_json`, `timeline_json`, `ai_report_json`, `accuracy_json`, `feedback_json` json null, `status` string default `'pending'` (free; comment lists `pending, processing, completed, failed`), timestamps. Unique `(user_id, date)`.
Casts: `date` date, six arrays. **`getAiReportJsonAttribute()` overrides the array cast** with ~70 lines that try `json_decode`, then un-quote, then **regex-scan for known keys and slice substrings** to recover from malformed model output (F19). **Relations:** `user()` belongsTo. A daily report cache derived from `user_activities` + `tasks` + an LLM call; nothing invalidates it when the sources change.

### Domain 10 — smells / decisions needed

| # | Smell |
|---|---|
| 10.1 | **`user_availabilities` category FKs use `->nullableOnDelete()`, a non-existent method** silently swallowed by `Fluent` — the FKs have no `ON DELETE`, so attendance categories cannot be deleted. |
| 10.2 | **Attendance reason is modelled three ways**: dead `reason` text, three `*_reason_category_id` FKs, and the `categorizables` morph the accessors actually consult first (F49). |
| 10.3 | `user_availabilities` has no unique on `(user_id, date)`; `actual_start_time/end_time` are uncast `time` columns. |
| 10.4 | `UserAvailability::$appends` runs up to three `Category::find()` queries per row when `categories` is not eager-loaded. |
| 10.5 | `categories`/`category_sets` (the FK targets) have no seeder — the attendance vocabulary exists only in production (db.md 16.5). |
| 10.6 | **`user_activities` is an unpartitioned, heartbeat-mutated event log** with `updated_at`, `text` URLs, and no composite index on `(user_id, recorded_at)` (F73). |
| 10.7 | `user_activities.task_id` went NOT NULL → nullable within four days (`2026_03_01_065439` → `2026_03_04_061000`). |
| 10.8 | `user_activities.category` and `idle_state` are free strings; the category vocabulary lives only in `config/activity_categories.php`. |
| 10.9 | `user_productivities.ai_report_json` is not reliably valid JSON and is parsed by regex/substring (F19); the other five JSON blobs are opaque report caches with no invalidation. |
| 10.10 | `user_productivities.status` is a free string with the vocabulary in a migration comment. |
| 10.11 | Two timezone traits (`HasUserTimezone` — reads `Auth::user()->timezone`, i.e. the *viewer's* zone, not the row owner's; `HasTimezoneCalculations`) exist for this domain's date arithmetic; `UserAvailability` uses neither. |

---

## Domain 11 — Chat: internal chat, Telegram, Google Chat

### `chat_messages`

Model: `app/Models/ChatMessage.php` · Migrations: `2026_03_11_142554`, `2026_03_11_211612`, `2026_03_14_044145`

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | bigint auto | | |
| `project_id` | ubigint | FK projects cascade | |
| `telegram_topic_id` | ubigint | null, FK telegram_topics nullOnDelete, indexed | |
| `telegram_message_id` | bigint | null, indexed | first Telegram message id; further ids in `meta_data.telegram_responses[]` |
| `user_id` | ubigint | null, FK users set null | staff author (null = system) |
| `client_id` | ubigint | null, FK clients **cascade** | client author — **deleting a client deletes their chat messages but deleting a user keeps theirs** |
| `parent_id` | ubigint | null, FK chat_messages cascade | replies |
| `message` | text | | |
| `type` | string | default `'text'` | free (`text`, `email`, `system`) |
| `source` | string | default `'crm'` | free (`crm`, `telegram`) |
| `meta_data` | json | null | array — email refs, `telegram_responses[]` with per-chat message ids and `deleted_at` |
| `deleted_at` | | | SoftDeletes |
| timestamps | | | |

Casts: `meta_data` array. **Boot `created`:** writes a `user_interactions` `read` row for the author (`firstOrCreate`), then loads `user`, `parent.user` and broadcasts `ChatMessageSent` (two `Log::info` calls per message).
**Relations:** `parent()`, `replies()` self; `project()`, `user()`, `client()`, `telegramTopic()` belongsTo; `interactions()` morphMany UserInteraction; `files()` morphMany FileAttachment.
`addTelegramResponse()` / `deleteFromTelegram()` maintain a list of Telegram `(chat_id, message_id)` pairs inside `meta_data` — a fan-out log stored as JSON on the message row. Author is `user_id` **or** `client_id` (two nullable FKs, F45) while `project_notes` models the same choice as a `creator` morph.

### `telegram_topics`

Model: `app/Models/TelegramTopic.php` · Migration: `2026_03_14_044145`
`id`, `project_id` null FK projects cascadeOnDelete, `topicable_id`/`topicable_type` nullableMorphs (**never written anywhere** — `TelegramService` creates topics with `project_id`, `name`, `type`, `telegram_thread_id`, `is_private` only), `telegram_thread_id` bigint null (null for the group's General chat), `name` string, `type` string default `'general'` (cast `TelegramTopicType`: `general`,`client`,`custom`,`proxy`), `is_private` boolean default false, timestamps.
**No unique on `(project_id, telegram_thread_id)`** or `(project_id, name)`; `TelegramService` looks up the General topic by `name = 'General'` and then **stores the topic id in `projects.integrations->telegram_general_topic_id`** — an FK inside a JSON blob.
Casts: `type` enum, `is_private` boolean. **Relations:** `topicable()` morphTo (dead); `project()` belongsTo; `chatMessages()` hasMany.

### `telegram_accounts` — see §1 (morph `telegramable` → User | Client)

### `project_google_chat_members`

**No model.** Migration: `2025_10_16_113923`
`id`, `project_id` FK projects cascade, `user_id` FK users cascade, timestamps. Unique `(project_id, user_id)`. Accessed via `Project::googleChatMembers()` (no `withTimestamps`). Google Chat itself has no table: the space is `projects.google_chat_id`, threads are `tasks.google_chat_space_id`/`google_chat_thread_id`/`chat_message_id`, `project_notes.chat_message_id`, and `users.chat_name` (F91).

### Domain 11 — smells / decisions needed

| # | Smell |
|---|---|
| 11.1 | **Four chat/message channels with four storage models** (F44): `chat_messages` (internal + Telegram), `emails`/`conversations`, `project_notes` pushed to Google Chat, and Google Chat string refs on four tables. |
| 11.2 | `chat_messages` author is `user_id` **or** `client_id` (two nullable FKs, no check) while `project_notes` uses a `creator` morph for the same User/Client choice (F45); `client_id` cascades but `user_id` sets null. |
| 11.3 | `chat_messages.meta_data.telegram_responses[]` is a fan-out delivery log (chat id, message id, sent/deleted timestamps) stored as JSON per row; `telegram_message_id` duplicates its first element. |
| 11.4 | `telegram_topics.topicable_*` is a dead morph — declared, indexed, never written; `type` is the only enum-backed string column in the domain. |
| 11.5 | `projects.integrations->telegram_general_topic_id` holds a `telegram_topics` FK inside JSON; the fallback lookup is by `name = 'General'`. |
| 11.6 | Telegram identity: `users.telegram_chat_id` / `clients.telegram_chat_id` (bigint, unique) vs `telegram_accounts.telegram_id` (string) (F36); link codes on three tables (F37); `clients.active_telegram_project_id` is bot session state stored on the client row. |
| 11.7 | `ChatMessage::booted()` writes a self-`read` `user_interactions` row and broadcasts (with eager loads and two log lines) synchronously on every insert — including Telegram-ingested messages. |
| 11.8 | `chat_messages.type` and `source` are free strings; `project_google_chat_members` is a bare pivot with no model. |
| 11.9 | No Google Chat table at all — the Google Chat "space" identity is `projects.google_chat_id` and thread identities are three string columns on `tasks` (F91). |

---

## Domain 12 — Files, client deliverables, shareable resources, notice board

### `files`

Model: `app/Models/FileAttachment.php` (**class ≠ table name**, F97) · Migrations: `2025_08_18_214600`, `2026_08_19_100400`
`id`, `fileable_id` ubigint, `fileable_type` string (declared as two columns, indexed `(fileable_type, fileable_id)`), `project_id` null FK projects cascade (**denormalised** — a file on an Email or Bill also carries a project id, maintained by callers), `filename` string, `mime_type` string null, `file_size` bigint null, `path` string null (GCS object path *or* absolute URL — `path_url` accessor branches on `filter_var(FILTER_VALIDATE_URL)`), `google_drive_file_id` string null, `thumbnail` string null, `expires_at` timestamp null indexed (`files_expires_at_idx`, for chat attachments), timestamps.
Casts: `expires_at` datetime. **`$appends = ['path_url', 'thumbnail_url']`** — each generates a **signed GCS temporary URL (1 day) per serialised row**, swallowing all exceptions.
**Relations:** `fileable()` morphTo (Bill, Email, ChatMessage, Task, Project, ProjectExpendable, Transaction, Invoice); `project()` belongsTo. No soft deletes; no storage-driver column (GCS vs Google Drive vs URL inferred from which column is filled). Second of four file stores (F27).

### `deliverables`

Model: `app/Models/Deliverable.php` · Migrations: `2025_07_27_122902`, `2025_07_31_004453`

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | bigint auto | | |
| `project_id` | ubigint | FK projects cascade | |
| `team_member_id` | ubigint | null, FK users set null | author |
| `title` | string | | |
| `description` | text | null | |
| `type` | string | NOT NULL | free (`blog_post`, `design_mockup`, …) |
| `status` | string | default `'pending_review'` | free (`pending_review`, `approved`, `revisions_requested`, `completed`) |
| `content_url` | string | null | accessor rewrites Google Drive/Docs/YouTube/Vimeo URLs to embed form |
| `content_text` | longText | null | |
| `attachment_path` | string | null | third file store (F27) |
| `mime_type` | string | null | **actually a kind discriminator** (`video`, `other`) used by the `content_url` accessor, not a real MIME type |
| `version` | int | default 1 | |
| `parent_deliverable_id` | ubigint | null, FK deliverables set null | version chain |
| `submitted_at` | timestamp | null | |
| `overall_approved_at` | timestamp | null | |
| `overall_approved_by_client_id` | ubigint | null, FK clients set null | |
| `due_for_review_by` | timestamp | null | |
| `is_visible_to_client` | boolean | default true | |
| timestamps | | | |

**No soft deletes.** Casts: three datetimes, boolean. **Relations:** `project()`, `teamMember()`, `approvedByClient()`, `parent()` belongsTo; `children()` hasMany self; `clientInteractions()` hasMany; `comments()` morphMany **ProjectNote** `noteable` (replaced `DeliverableComment`).
Approval is hand-rolled (`status` + `overall_approved_at` + `overall_approved_by_client_id`) — one of eight parallel approval mechanisms (F43). This is the **client-facing artefact** table; `project_deliverables` (§3) is the unrelated scope checklist (F34).

### `deliverable_comments` — dead

Model: `app/Models/DeliverableComment.php` · Migration: `2025_07_27_123043`
`id`, `deliverable_id` FK cascade, `client_id` FK cascade, `comment_text` text, `context` string null ("paragraph 2, image 1"), `resolved_at` timestamp null, timestamps.
Casts: `resolved_at` datetime. **Relations:** `deliverable()`, `client()` belongsTo. Superseded by `project_notes` (`Deliverable::comments()` returns ProjectNote; `ProjectClientAction.php:18` comments out the import). `Client::deliverableComments()` and a controller still reference it. The `context` anchor concept migrated to `project_notes.context` (string cast to array, F2).

### `client_deliverable_interactions`

Model: `app/Models/ClientDeliverableInteraction.php` · Migration: `2025_07_27_122957`
`id`, `deliverable_id` FK cascade, `client_id` FK cascade, `read_at`, `approved_at`, `rejected_at`, `revisions_requested_at` timestamp null (**four mutually-exclusive timestamps encoding one state**, F56), `feedback_text` text null, timestamps. Unique `(deliverable_id, client_id)`.
Casts: four datetimes. **Relations:** `deliverable()`, `client()` belongsTo. Per-client verdict on a deliverable; `deliverables.overall_approved_*` is the roll-up. Second read-tracking mechanism (F30).

### `shareable_resources` (+ `NoticeBoard` view of the same table)

Models: `app/Models/ShareableResource.php`, `app/Models/NoticeBoard.php` (`extends ShareableResource`, same `$table`) · Migrations: `2025_07_31_040630`, `2025_08_14_184900_create_notice_boards_table` (**creates no table — adds `notice`**), `2025_08_15_104900`, `2025_09_08_180500`

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | bigint auto | | |
| `title` | string | | |
| `description` | text | null | |
| `url` | string | NOT NULL | notices may have no URL (`isClickable()`), so `''` is stored |
| `type` | string | NOT NULL | free: `youtube`/`website` for resources, `General`/`Warning`/`Updates`/`Final Notice` for notices — **two vocabularies, one column** |
| `thumbnail_url` | string | null | for notices this is a GCS path (`thumbnail_url_public` signs it for 3 days) |
| `created_by` | ubigint | FK users (**no onDelete**) | |
| `visible_to_client` | boolean | default true | `NoticeBoard::creating` forces `false` when null |
| `visible_to_team` | boolean | default false | not fillable on `NoticeBoard` |
| `is_private` | boolean | default false | not fillable on `NoticeBoard` |
| `sent_push` | boolean | default false | |
| `notice` | boolean | **NOT NULL, no default** | discriminator; not fillable on `ShareableResource` |
| `deleted_at` | | | SoftDeletes |
| timestamps | | | |

**Discrimination:** `ShareableResource::booted()` adds global scope `notice = false OR notice IS NULL`; `NoticeBoard::booted()` adds `notice = true OR notice IS NULL` (both named `exclude_notice`, both admit NULL — F17). Because `notice` is NOT NULL with no default and `ShareableResource` never sets it, **creating a `ShareableResource` under MySQL strict mode fails** ("Field 'notice' doesn't have a default value"); under non-strict it stores `0`. The `IS NULL` branches can never match in either case.
**Traits:** `HasFactory`, `SoftDeletes`, `Taggable` (no reverse relation on `Tag`, db.md 16.7); `NoticeBoard` re-declares `HasFactory, SoftDeletes` and narrows `$fillable`/`$casts`, dropping the `visible_to_team`/`is_private` casts it inherits the columns for.
**Relations:** `creator()` belongsTo User `created_by`; `campaigns()` belongsToMany via `campaign_shareable_resource`; `NoticeBoard::interactions()` morphMany UserInteraction (**stored with `interactable_type = App\Models\NoticeBoard`** — a class name for a table that is also `ShareableResource`).
`$appends` on `NoticeBoard`: `thumbnail_url_public` (signed GCS URL per row).

### `campaign_shareable_resource` — see §2

### Domain 12 — smells / decisions needed

| # | Smell |
|---|---|
| 12.1 | **Four file stores**: `files` (morph + denormalised `project_id`), `documents` (project-only), `projects.documents` JSON, `deliverables.attachment_path` (F27); plus `resources.file_id` and `shareable_resources.thumbnail_url` as ad-hoc object paths. |
| 12.2 | `FileAttachment` ↔ `files` name mismatch (F97); storage backend (GCS path vs Drive id vs absolute URL) is inferred from which column is non-empty; `$appends` signs a GCS URL per row per serialisation. |
| 12.3 | `files.project_id` is denormalised beside the morph and maintained by every caller independently. |
| 12.4 | **`deliverables` vs `project_deliverables`** (F34): unrelated tables, near-identical names; `deliverables.mime_type` is a kind discriminator (`video`/`other`), not a MIME type. |
| 12.5 | `deliverables` approval is a fourth hand-rolled approval mechanism (`status` + `overall_approved_at` + `overall_approved_by_client_id`) with per-client verdicts in `client_deliverable_interactions` as four exclusive timestamps (F43, F56). |
| 12.6 | `deliverable_comments` is dead but its model, controller, and `Client::deliverableComments()` remain (Appendix E). |
| 12.7 | **`ShareableResource` and `NoticeBoard` share a table with a NOT NULL, default-less `notice` discriminator** that the base model never sets — base-model inserts fail under strict mode; both global scopes' `IS NULL` branches are unreachable (F17). |
| 12.8 | `shareable_resources.type` carries two vocabularies (`youtube`/`website` vs `General`/`Warning`/`Updates`/`Final Notice`) depending on `notice`. |
| 12.9 | `shareable_resources.url` is NOT NULL but notices are allowed to have no URL. |
| 12.10 | `NoticeBoard` narrows `$fillable`/`$casts` so `visible_to_team`/`is_private` are uncast ints on notices; `UserInteraction` rows are stored with `interactable_type = NoticeBoard` while the row is also a `ShareableResource`. |
| 12.11 | `shareable_resources.created_by` FK has no `onDelete`; `users` is soft-deletable. |
| 12.12 | Migration `create_notice_boards_table` creates no table (F98). |
| 12.13 | `deliverables` has no soft deletes while its parent `projects` does; its `type` and `status` are free strings. |

---

## Domain 13 — Presentations, slides, content blocks, components, icons, wireframes

### `presentations`

Model: `app/Models/Presentation.php` · Migrations: `2025_09_03_000000`, `2025_09_04_142800`, `2025_09_05_000002` (**no-op**)
`id`, `presentable_id` ubigint, `presentable_type` string (index `presentations_presentable_index`; Lead | Client), `title` varchar(255), `type` varchar(50) indexed (free; constants `proposal`, `presentation`, `audit_report`), `share_token` varchar(64) unique (auto `Str::random(64)`), `is_template` boolean default false, `deleted_at` SoftDeletes, timestamps.
Casts: `is_template` boolean. **Boot `created`:** `$model->users()->attach(Auth::id(), ['role' => self::CONTACTED])` — **writes the lead-pipeline constant `'contacted'` into `presentation_user.role`** (documented `editor|viewer`) (F20), and attaches `null` when created outside a request (seeder, jobs). Constants `QUALIFIED/NEW/CONTACTED/CONVERTED/LOST` duplicate `LeadStatus` cases on an unrelated model.
**Relations:** `presentable()` morphTo; `metadata()` hasMany PresentationMetadata; `slides()` hasMany ordered by `display_order`; `users()` belongsToMany via `presentation_user` withPivot `role` withTimestamps. Template presentations (`is_template = true`) live in the same table and are cloned by `PresentationService`.

### `presentation_metadata`

Model: `app/Models/PresentationMetadata.php` (`$table` set explicitly — non-plural table) · Migration: `2025_09_03_000000`
`id`, `presentation_id` FK cascade, `meta_key` varchar(100), `meta_value` text null, `deleted_at` SoftDeletes, timestamps. **No unique on `(presentation_id, meta_key)`; no index on `meta_key`** (F61). **Relations:** `presentation()` belongsTo. No casts. A soft-deletable EAV — the same key can exist live and soft-deleted, and twice live.

### `slides`

Model: `app/Models/Slide.php` · Migration: `2025_09_03_000000`
`id`, `presentation_id` FK cascade, `template_name` varchar(100) (free; nine values from `config/presentation_templates.php`), `title` varchar(255) null, `display_order` int default 0, `deleted_at` SoftDeletes, timestamps. No unique on `(presentation_id, display_order)`.
No casts. **Relations:** `presentation()` belongsTo; `contentBlocks()` hasMany ordered.

### `content_blocks`

Model: `app/Models/ContentBlock.php` · Migration: `2025_09_03_000000`
`id`, `slide_id` FK cascade, `block_type` varchar(100) (free; 13 values), `content_data` json NOT NULL (array — **shape varies by `block_type`, documented only in `config/presentation_templates.php`**, F60), `display_order` int default 0, `deleted_at` SoftDeletes, timestamps.
Casts: `content_data` array. **Relations:** `slide()` belongsTo. Soft deletes on a leaf that is re-created wholesale by the seeder each run.

### `presentation_user`

**No model.** Migration: `2025_09_05_000001`
`id`, `presentation_id` ubigint FK presentations cascade, `user_id` ubigint FK users cascade, `role` string default `'editor'` (comment `editor | viewer`; actual writes store `'contacted'`), timestamps. Unique `(presentation_id, user_id)`. Payload `role` reachable only via `withPivot` (Appendix E).

### `icons`

Model: `app/Models/Icon.php` · Migration: `2025_08_06_005300_create_wireframe_tables`
`id`, `name` string unique, `svg_content` text, timestamps.
**No `$casts`.** **Traits:** `LogsActivity` (`name`, dirty only). **Relations:** `components()` hasMany. Static `validateSvgContent()` / `sanitizeSvgContent()` are regex-based SVG scrubbers (blocklist: `<script>`, `on*=`, `javascript:`, `eval(`) — **not called by the model itself**; sanitisation depends on every caller remembering. Seeded from `config/components.php` (config is authoritative, F50).

### `components`

Model: `app/Models/Component.php` · Migration: `2025_08_06_005300_create_wireframe_tables`
`id`, `name` string unique, `type` string NOT NULL, `category` string NOT NULL (**not in `$fillable`** — mass-assignment `create()` without setting it explicitly fails on a NOT NULL column with no default), `definition` json NOT NULL (array; `validateDefinition()` requires `default.size.width/height`), `icon_id` null FK icons set null, timestamps.
Casts: `definition` array. **Traits:** `LogsActivity` (`name`, `type`). **Relations:** `icon()` belongsTo. `ComponentSeeder` upserts on `type` while the unique is `name` (F21). `category` values include both `Form` and `Forms` (Appendix D).

### `wireframes`

Model: `app/Models/Wireframe.php` · Migration: `2025_08_06_005300_create_wireframe_tables`
`id`, `project_id` FK projects cascade, `name` string, timestamps. Unique `(project_id, name)`. **No soft deletes** (vs `presentations` which has them, F101).
No casts. **Traits:** `LogsActivity` (`name`). **Relations:** `project()` belongsTo; `versions()` hasMany. **`latestVersion()`, `latestDraftVersion()`, `latestPublishedVersion()` return models via `->first()`** — not relations, so they cannot be eager-loaded and cause N+1 in any list (F79). Wireframe annotations are `project_notes` rows (F53) with `context` anchors.

### `wireframe_versions`

Model: `app/Models/WireframeVersion.php` · Migrations: `2025_08_06_005300`, `2025_08_29_120900`
`id`, `wireframe_id` FK cascade, `version_number` uint, `name` string null, `data` json NOT NULL (array — the whole wireframe document, Appendix C), `status` enum(`draft`,`published`) default `draft` (no PHP enum; two string constants), timestamps. Unique `(wireframe_id, version_number)`.
Casts: `data` array, `version_number` integer. **Traits:** `LogsActivity` (`version_number`, `status`). **Relations:** `wireframe()` belongsTo. `publish()` flips status; nothing un-publishes the previous version, so "latest published" is `ORDER BY version_number DESC` (F79). `version_number` is assigned by callers — no sequence guarantee beyond the unique.

### Domain 13 — smells / decisions needed

| # | Smell |
|---|---|
| 13.1 | **`Presentation::booted()` writes `'contacted'` (a lead-status constant) into `presentation_user.role`** whose documented values are `editor|viewer` (F20); outside a request it attaches user `null`. |
| 13.2 | `Presentation` declares `QUALIFIED/NEW/CONTACTED/CONVERTED/LOST` constants that duplicate `LeadStatus` cases on the wrong model; `presentations.type` is a free string with three constants. |
| 13.3 | `presentation_metadata` is a soft-deletable EAV with no unique on `(presentation_id, meta_key)` and no index on `meta_key` (F61). |
| 13.4 | `content_blocks.content_data` is a 13-shape polymorphic JSON blob whose shapes exist only in config (F60); `slides.template_name` (9 values) and `content_blocks.block_type` are free strings. |
| 13.5 | Templates (`is_template = true`) and instances share `presentations`; the seeder deletes and re-creates template slides/blocks on every run (soft-deleting the old ones, so the table accumulates dead rows). |
| 13.6 | `presentation_user.role` payload is unreachable without a pivot model (Appendix E). |
| 13.7 | `components.category` is NOT NULL but not fillable; `ComponentSeeder` upserts on `type` while the unique is `name` (F21); `category` has a `Form`/`Forms` split. |
| 13.8 | `Icon::sanitizeSvgContent()` is a regex blocklist that the model never applies itself — stored SVG is only as safe as each caller. |
| 13.9 | `icons`/`components` duplicate `config/components.php` (F50); `Icon` has no `$casts`. |
| 13.10 | `Wireframe::latestVersion()/latestDraftVersion()/latestPublishedVersion()` return models, not relations — guaranteed N+1 (F79). |
| 13.11 | `wireframes` has no soft deletes while `presentations` does (F101); `wireframe_versions.status` DB enum has no PHP enum; `version_number` is caller-assigned. |
| 13.12 | `wireframe_versions.data` stores the entire wireframe document as one JSON value — every autosave rewrites the whole blob and every version is a full copy. |
| 13.13 | Wireframe annotations live in `project_notes` (type collision, F53) with `context` as a string cast to array (F2). |
| 13.14 | Migration `2025_09_05_000002_add_lead_id_to_presentations_table` is a no-op with a misleading name. |

---

## Domain 14 — Comments and notes

### `comments`

Model: `app/Models/Comment.php` · Migration: `2025_07_24_123834`
`id`, `content` text, `user_id` FK users cascade, `commentable_id`/`commentable_type` morphs (Conversation via `Conversation::notes()`, Resource via `Resource::comments()`), timestamps.
No casts, no soft deletes, no `parent_id`, no client author. **Relations:** `user()` belongsTo; `commentable()` morphTo. Plaintext `content` (vs `project_notes` and `user_notes`, encrypted). Named `comments` but exposed as `notes()` on `Conversation` and `comments()` on `Resource`. The generic comment table that the rest of the app did not adopt — `project_notes` became the de-facto comment store instead (F26).

### `project_notes`

Model: `app/Models/ProjectNote.php` (361 lines) · Migrations: `2023_07_15_010754`, `2025_07_22_043443`, `2025_07_22_123103`, `2025_07_24_012549`, `2025_07_28_063056`, `2025_07_28_080156`, `2025_07_31_021217`, `2025_08_09_034731`

| Column | Type | Null/Default | Notes |
|---|---|---|---|
| `id` | bigint auto | | |
| `project_id` | ubigint | **null** (was NOT NULL), FK projects cascade | `creating` hook backfills from `noteable->project_id` when possible |
| `noteable_id` | ubigint | null | morph: Task, Milestone, Deliverable, Document, User |
| `noteable_type` | string | null | index `(noteable_id, noteable_type)` |
| `content` | text | NOT NULL | **encrypted** via accessor/mutator (`Crypt::encryptString`); decrypt failure returns the literal `'UNABLE TO READ'`; unsearchable |
| `type` | enum(`standup`,`kudos`,`general`) | default `general` | dropped and re-created as enum; **model constants add `daily_summary`, `meeting_minutes`, `comment`, and `createAndNotify()` defaults to `'note'`** — four of seven values used in code are not storable |
| `chat_message_id` | string | null | Google Chat message name (`spaces/…/messages/…`) |
| `user_id` | ubigint | **null** (was NOT NULL), FK users cascade | **superseded by `creator_*` but still fillable and still written by `createAndNotify()`** (F46) |
| `creator_id` | ubigint | null | morph: User \| Client; index `(creator_id, creator_type)`; set in `creating` from Auth or magic-link request attributes |
| `creator_type` | string | null | |
| `parent_id` | ubigint | null, FK project_notes cascade | replies |
| `context` | **string** | null | comment "e.g., paragraph 2, image 1" — **cast `array`** (F2): JSON-encoded into a `varchar(255)` |
| timestamps | | | |

**No soft deletes.** Casts: `context` array only (`$casts` is declared *before* the `use HasUserTimezone` statement — legal but a sign of accretion).
`$appends = ['creator_name']` (resolves the `creator` morph per row).
**Traits:** `HasUserTimezone`, `HasFactory`, `Taggable`. Implements nothing, but `Context` and `PointsService` treat it as a source.
**Boot:** `creating` sets `creator_*` from Auth / magic-link request attributes and backfills `project_id`; `created` dispatches `StandupSubmittedEvent` when `type = standup` and creator is a User (bonus engine, F90), then **always** calls `pushToGoogleChat()` (network call in a model event).
**Relations:** `project()`, `user()` belongsTo; `creator()`, `noteable()` morphTo; `parent()` belongsTo self; `replies()` hasMany self; `points()` morphMany PointsLedger `pointable`; `contexts()` morphMany Context `referencable`; `tags()` via trait.
**Methods:** `createAndNotify()` (writes `user_id`, not `creator_*`, and `type='note'`), `createTaskFromComment()` (creates a Task from a wireframe annotation — `TaskType::firstOrCreate(['name'=>'New'])`, passes a non-existent `project_id` to `Task::create` which mass-assignment silently drops, `source='wireframe'`, `source_id=note id`), `pushToGoogleChat()`.
**This table is nine things** (F53): project note, standup, kudos, task comment, milestone comment, deliverable comment, document comment, note-about-user, wireframe annotation — discriminated by `type` (3 storable values) and `noteable_type` (5 targets).

### Other note/comment stores (documented elsewhere)

| Table | Domain | Author model | Encrypted | Threaded | Anchor |
|---|---|---|---|---|---|
| `project_notes` | 14 | `creator` morph (+ dead `user_id`) | yes | `parent_id` | `context` |
| `comments` | 14 | `user_id` | no | no | no |
| `deliverable_comments` (dead) | 12 | `client_id` | no | no | `context` |
| `invoice_comments` | 7 | `user_id` | no | no | `action` |
| `user_notes` | 1 | `author_id` → `user_id` | yes | no | no |
| `users.notes` JSON | 1 | — | no | — | — |
| `clients.notes` text | 2 | — | no | — | — |
| `leads.notes` text | 2 | — | no | — | — |
| `daily_tasks.note` | 4 | implicit | no | — | — |
| `conversations` via `comments` | 5 | `user_id` | no | no | — |

### Domain 14 — smells / decisions needed

| # | Smell |
|---|---|
| 14.1 | **`project_notes.type` enum stores 3 values; code uses 7** (`standup`, `kudos`, `general`, `daily_summary`, `meeting_minutes`, `comment`, `note`). `ProjectNote::createAndNotify()`, `Task::addNote()`, `Document::addNote()` all write `'note'`. Under MySQL strict mode these inserts fail; otherwise the row stores `''`. |
| 14.2 | `project_notes.context` is `varchar(255)` cast to `array` (F2). |
| 14.3 | `project_notes.user_id` and `creator_id/creator_type` both live, both fillable, written by different code paths (F46); `createAndNotify()` still writes only `user_id`, so those rows have no `creator` and `creator_name` is null. |
| 14.4 | **`project_notes` is nine entity kinds** (F53) with no soft deletes, encrypted content (unsearchable), and a network push to Google Chat inside the `created` model event. |
| 14.5 | `ProjectNote::createTaskFromComment()` passes `project_id` to `Task::create()` (silently dropped — no such column) and relies on `TaskType::firstOrCreate(['name'=>'New'])` against a table with no unique on `name` (F22). |
| 14.6 | `comments` (generic morph comment table) is used by only two targets; every other commentable adopted `project_notes` instead — two morph comment systems (F26). |
| 14.7 | **Ten note/comment stores** in total (table above) with three encryption policies, two threading models, two anchor conventions and four author modellings. |
| 14.8 | Encrypted `content` decrypt failure returns `'UNABLE TO READ'` on `project_notes` but the ciphertext on `user_notes` — inconsistent failure modes, both silent. |
| 14.9 | `project_notes.chat_message_id` and `tasks.chat_message_id` store Google Chat message resource names as strings — a foreign identity with no table (F91). |
| 14.10 | `$casts` on `ProjectNote` is declared above a trailing `use` statement; `type` constants are declared after a static method — the class body is out of order, a symptom of unreviewed accretion. |

---

## Domain 15 — Tags and categories (tables only; analysis in db.md §16)

db.md's Domain 16 narrative survived only from its final paragraph; the per-table facts are recorded here so the survey is complete. The smells for this domain are db.md 16.1–16.10 and are **not repeated**.

### `tags`

Model: `app/Models/Tag.php` · Migrations: `2025_07_22_233400`, `2025_07_29_083617`
`id`, `name` string **unique**, `slug` string **unique** (auto `Str::slug(name)` in `creating`), timestamps. `created_by_user_id` **dropped** (still targeted by `User::createdTags()`, F9).
`$hidden = ['created_at','updated_at','pivot']`. **Relations (`morphedByMany`, all `taggable`):** `tasks()`, `projects()`, `documents()`, `emails()`, `milestones()`, `projectNotes()`, `resources()`, `clients()`. `ShareableResource` and `Category` use the `Taggable` trait but have no reverse relation here (db.md 16.7).

### `taggables`

**No model.** Migration: `2025_07_29_083645`
`tag_id` ubigint FK tags cascade, `taggable_id` ubigint, `taggable_type` string. Composite PK `(tag_id, taggable_id, taggable_type)`. **No timestamps**, no index on `(taggable_type, taggable_id)` alone (the PK leads with `tag_id`, so "tags for this model" lookups cannot use it efficiently).

### `task_tag` — dead

**No model.** Migration: `2025_07_22_233700`
`id`, `task_id` FK tasks cascade, `tag_id` FK tags cascade, timestamps, unique `(task_id, tag_id)`. Zero references outside its migration (db.md 16.2).

### `category_sets`

Model: `app/Models/CategorySet.php` · Migration: `2025_10_10_000001`
`id`, `name` string, `slug` string unique (auto-generated with `-N` suffix loop in `creating`), timestamps. Implements `CreatableViaWorkflow`. **Relations:** `categories()`, `bindings()` hasMany. A set with zero bindings is implicitly global (db.md 16.10).

### `category_set_bindings`

Model: `app/Models/CategorySetBinding.php` · Migration: `2025_10_10_000002`
`category_set_id` ubigint FK category_sets cascade, `model_type` string (**FQCN as data**, db.md 16.4), timestamps. Composite PK `(category_set_id, model_type)`. **No `id` column** but the model does not set `$incrementing = false` / `$primaryKey` — `CategorySetBinding::find()`/`save()` on an existing row will target a non-existent `id` column. **Relations:** `set()` belongsTo.

### `categories`

Model: `app/Models/Category.php` · Migration: `2025_10_10_000003`
`id`, `category_set_id` ubigint FK category_sets cascade NOT NULL, `name` string, timestamps. Unique `(category_set_id, name)`.
**Traits:** `Taggable` (a category can itself be tagged — db.md 16.3). Implements `CreatableViaWorkflow` (`requiredOnCreate` = `['name']` only, though `category_set_id` is NOT NULL). `$appends = ['tag_name']` — **runs a `tags()` query per serialised category**. `$hidden = ['pivot','created_at','updated_at']`. **Relations:** `set()` belongsTo. Referenced by three FKs on `user_availabilities` (§10) whose `ON DELETE` clause was silently lost (10.1).

### `categorizables`

**No model.** Migration: `2025_10_10_000004`
`category_id` ubigint FK categories cascade, `categorizable_id` ubigint, `categorizable_type` string, timestamps. Unique `categorizables_unique (category_id, categorizable_id, categorizable_type)`; index `categorizables_cid_ctype_index (categorizable_id, categorizable_type)`. **No primary key.** Used by `HasCategories` on Client, Email, User, UserAvailability (`morphToMany … withTimestamps`).

### Domain 15 — additional smells not in db.md §16

| # | Smell |
|---|---|
| 15.1 | `CategorySetBinding` has a composite PK and no `id`, but the model keeps Eloquent's default `id` primary key — `find()`, `update()`, `delete()` on an instance generate `WHERE id = ?`. |
| 15.2 | `Category::$appends['tag_name']` runs a pivot query per serialised row. |
| 15.3 | `Category::requiredOnCreate()` omits `category_set_id` although the column is NOT NULL — workflow-created categories fail at the DB. |
| 15.4 | `taggables` composite PK leads with `tag_id`; model-side lookups (`$model->tags`) have no covering index. `categorizables` has no primary key at all. |
| 15.5 | `User::$with = ['role','categories']` means the `categorizables` join runs on **every** User query in the app (F78). |

---

## Cross-domain index — where each table is documented

| Table | Domain | | Table | Domain |
|---|---|---|---|---|
| `users` | 1 | | `deliverables` | 12 |
| `roles`, `permissions`, `role_permission` | 1 | | `deliverable_comments` | 12 (dead) |
| `user_remembered_devices`, `login_attempts` | 1 | | `client_deliverable_interactions` | 12 |
| `user_otps`, `otp_verifications`, `magic_links` | 1 | | `shareable_resources` (+NoticeBoard) | 12 |
| `google_accounts`, `telegram_accounts` | 1 | | `files` | 12 |
| `user_metadata_keys`, `user_notes` | 1 | | `presentations`, `presentation_metadata` | 13 |
| `clients`, `leads`, `campaigns` | 2 | | `slides`, `content_blocks` | 13 |
| `campaign_shareable_resource` | 2 | | `presentation_user` | 13 |
| `client_vault_credentials` (+`_user`) | 2 | | `icons`, `components` | 13 |
| `contexts` | 2 | | `wireframes`, `wireframe_versions` | 13 |
| `projects`, `project_user`, `project_client` | 3 | | `comments`, `project_notes` | 14 |
| `project_tiers` | 3 / 9 | | `tags`, `taggables`, `task_tag` | 15 / db.md 16 |
| `crm_services`, `project_services` | 3 | | `category_sets`, `category_set_bindings` | 15 / db.md 16 |
| `project_deliverables`, `project_expendables` | 3 | | `categories`, `categorizables` | 15 / db.md 16 |
| `resources`, `documents` | 3 | | `notifications`, `activity_log` | db.md 17 |
| `milestones`, `tasks`, `subtasks`, `task_types` | 4 | | `user_interactions`, `seo_reports` | db.md 17 |
| `daily_tasks`, `schedules` | 4 | | `cache*`, `jobs*`, `failed_jobs`, `sessions` | db.md 18 |
| `meetings`, `meeting_attendees` | 4 | | `password_reset_tokens`, `personal_access_tokens` | db.md 18 |
| `conversations`, `emails` | 5 | | `pulse_*` | db.md 18 |
| `email_templates`, `placeholder_definitions` | 5 | | `chat_messages`, `telegram_topics` | 11 |
| `email_template_placeholder`, `email_app_template` | 5 | | `project_google_chat_members` | 11 |
| `email_apps`, `external_email_logs` | 5 | | `user_availabilities`, `user_activities` | 10 |
| `workflows`, `workflow_steps` | 6 | | `user_productivities` | 10 |
| `execution_logs`, `prompts` | 6 | | `bonus_configurations`, `bonus_configuration_groups` | 9 |
| `transactions`, `transaction_types` | 7 | | `bonus_configuration_group_items` | 9 |
| `invoices`, `invoice_items`, `invoice_comments` | 7 | | `project_bonus_configuration_group` | 9 |
| `bills`, `bill_payment_details` | 7 | | `bonus_transactions`, `kudos` | 9 |
| `approval_flows`, `approval_flow_steps` | 7 | | `points_ledgers`, `monthly_budgets`, `monthly_points` | 9 |
| `approval_instances`, `approval_instance_steps` | 7 | | `WeeklyStreak` (no table) | 9 |
| `currency_rates` | 7 | | `EmailTemplatePlaceholder` (no table) | 5 |
| `xero_connections`, `xero_tenants` | 8 | | | |
| `xero_payment_services`, `airwallex_xero_bank_mappings` | 8 | | | |
| `stripe_configurations`, `stripe_subscriptions` | 8 | | | |
| `stripe_subscription_payments`, `stripe_payouts` | 8 | | | |

**Every model in `app/Models/` (100 files) and every table created by `database/migrations/` (247 files) is covered by this document or by db.md §16–18.**
