# Legacy Laravel 12 + Inertia App — Complete Feature / Page Inventory

Root: `/home/claude/src` (no `vendor/`). Laravel 12 slim skeleton (`bootstrap/app.php`), Inertia with **dual Vue 3 + React 19 runtimes**.

---

## 0. Global architecture facts you must know before planning a rewrite

### 0.1 Dual-framework Inertia dispatch

`/home/claude/src/resources/js/app.js` reads `#app[data-page]`, and:

- component name starts with `React/` → dynamically imports `resources/js/react-entry.jsx` → resolves `./ReactPages/<name minus "React/">.jsx`
- otherwise → `resources/js/vue-entry.js` → resolves `./Pages/<name>.vue`

**Consequence (load-bearing for a rewrite):** only one framework is mounted per page load. Inertia `<Link>` / `router.visit` **cannot cross** the Vue↔React boundary — crossing links must be plain `<a>`, and server redirects that cross must use `Inertia::location`. `react-entry.jsx` contains an explicit `window.location.reload()` escape hatch for a non-`React/` component arriving in the React runtime (otherwise the page silently freezes — documented bug: signing out of `/inbox/beta`). `ReactComponents/app/navigation.js::signOut()` is a plain `axios.post` + `window.location.assign('/')` for the same reason.

Only **3 React page groups exist today**: `React/Inbox/Index`, `React/Portal/{Projects,Project,Profile}`, `React/TestPage`. Everything else is Vue.

### 0.2 Vue app bootstrap (`resources/js/vue-entry.js`)

- Pinia, Ziggy (`ZiggyVue`, requires `vendor/tightenco/ziggy` — absent here), `v-permission` directive from `resources/js/Directives/permissions`, `v-click-outside`
- `fetchGlobalPermissions()` after mount (calls `/api/user/permissions`)
- Laravel Echo/Reverb: subscribes `private App.Models.User.{id}` for notifications → `pushSuccess`
- `PushNotificationContainer.vue` + `Utils/notification`

### 0.3 Middleware aliases (`/home/claude/src/bootstrap/app.php`)

| Alias | Class |
|---|---|
| `permission:<slug>` | `App\Http\Middleware\CheckPermission` |
| `permissionInAnyProject:<slug>` | `App\Http\Middleware\CheckPermissionInAnyProject` |
| `auth.magiclink` | `VerifyMagicLinkToken` (client dashboard) |
| `auth.magiclink.external` | `VerifyExternalMagicLink` (3rd-party apps) |
| `auth.apikey` | `AuthenticateWithApiKey` (`X-API-KEY` header → `users.api_key`) |
| `client.throttle` | `ThrottleClientAuth` |
| `portal.user` | `EnsurePortalUser` (supplier portal) |
| `not.guest` | `EnsureNotGuest` |
| `process.tags` | `ProcessTags` |
| `process.basic` | `ProcessBasicProperty` |
| `google.chat.auth` | `AuthenticateGoogleChat` |

Global `web` stack appends: `HandleInertiaRequests`, `AddLinkHeadersForPreloadedAssets`, `RestoreRememberedDevice`, `EnsureNotGuest`. `statefulApi()` is on (Sanctum SPA).

### 0.4 Permission model as actually enforced

Three overlapping mechanisms, all present:

1. **Route middleware** `permission:<slug>` — `CheckPermission` (`app/Http/Middleware/CheckPermission.php`):
   - super admin (`$user->app_role === 'super-admin'`) bypasses everything
   - special case: `roles.index` + `?type=` query bypasses the check entirely (a real hole)
   - if a `project` route param or `project_id` input exists → project-scoped check (`project_user.role_id` → `Role.permissions`), **falling back to global first**
   - else global check via `users.role_id → role.permissions.slug`
   - throws `App\Exceptions\PermissionDeniedException`
2. **`permissionInAnyProject:<slug>`** — global OR the permission on *any* project role. Used only on `/project-expendables` (`add_expendables`).
3. **Policies** (`app/Policies/`) — `BillPolicy, ClientPolicy, EmailPolicy, InvoicePolicy, KudosPolicy, MonthlyBudgetPolicy, ProjectPolicy, ProjectTierPolicy, UserPolicy`, all built on `User::hasPermission($slug)` (which **only** consults `role_id → role`, not project roles) plus ad-hoc `isSuperAdmin()/isManager()` and project-membership checks.

`User` role helpers (`app/Models/User.php`): `isSuperAdmin()` (`app_role === 'super-admin'`), `isManager()` (`manager|assistant-manager`), `isEmployee()`, `isContractor()`, `isGuest()` (`user_type === 'guest'`), `hasPermission()`, `hasProjectPermission($projectId,$slug)`, `hasProjectPermissionOnAnyRole()`, `getRoleForProject()`.

Roles have a `type` of `application` vs `project` (`Admin/Roles/*` pages branch on it). `user_type` distinguishes `guest` (supplier/portal) accounts, which `EnsureNotGuest` force-logs-out of the internal app.

**Frontend enforcement** is separate and duplicated: `resources/js/Directives/permissions.js` (`v-permission`, `usePermissions`, `fetchGlobalPermissions`, `fetchProjectPermissions`) for Vue, `ReactComponents/app/usePermissions.js` for React. `HandleInertiaRequests::share()` builds `$globalPermissions` **and then never uses it** (the assignment is commented out at `app/Http/Middleware/HandleInertiaRequests.php:66`) — the frontend must fetch `/api/user/permissions` separately. **A v2 should ship permissions in the Inertia shared props.**

**Full permission slug vocabulary in use** (grepped from `hasPermission()` + middleware + `v-permission`):

```
add_expendables add_project_notes approve_all_emails approve_emails approve_expendables
approve_kudos approve_milestone_expendables approve_project_bills approve_project_invoices
approve_received_emails assign_permissions assign_project_tiers by_pass_extension
compose_emails configure_xero_settings contact_lead contact_leads create_automations
create_clients create_custom_emails create_kudos create_presentation create_project_bills
create_project_invoices create_project_tiers create_projects create_schedules create_users
delete_clients delete_emails delete_project_bills delete_project_tiers delete_projects
delete_users edit_clients edit_credential edit_emails edit_project_bills edit_project_invoices
edit_project_tiers edit_projects edit_users email_custom_recipients manage_bonus_configuration
manage_email_templates manage_monthly_budgets manage_notices manage_permissions
manage_placeholder_definitions manage_points manage_project_clients manage_project_expendable
manage_project_expenses manage_project_financial manage_project_income manage_project_users
manage_projects manage_roles restore_project restore_project_bills upload_project_documents
view_admin_dropdown view_all_credentials view_all_emails view_all_kudos view_all_projects
view_client_contacts view_client_financial view_clients view_emails view_kudos
view_monthly_budgets view_monthly_points view_own_kudos view_own_points view_permissions
view_points_ledger view_private_emails view_project_bills view_project_deliverables
view_project_documents view_project_expendable view_project_expendables_proposals
view_project_financial view_project_invoices view_project_notes
view_project_services_and_payments view_project_tiers view_project_transactions view_projects
view_shareable_resources view_users view_users_availability void_project_bills
void_project_invoices
```

Note: `manage_projects` is heavily overloaded — it gates Leads, Campaigns, Task Types, Automation, Reports, Media Files, Approval Flows and transaction linking. A v2 should split it.

### 0.5 Feature flags

| Config | Env | Effect |
|---|---|---|
| `config/inbox.php` → `beta` | `INBOX_BETA` (default **true**) | registers `/inbox/beta` React route + the "Try the new inbox" link on the Vue inbox |
| `config/inbox.php` → `sla_minutes`, `per_page`, `team_label`, `manual_approval_after_minutes`, `ai.*`, `sent_ingest.enabled` | | inbox behaviour |
| `config/portal.php` → `classic` | `PORTAL_CLASSIC` (default **true**) | registers `/projects/classic/*` legacy Vue project page |

---

## 1. Route files

| File | Lines | Purpose |
|---|---|---|
| `/home/claude/src/routes/web.php` | 913 | All Inertia pages, portal, public, magic links, OAuth, tracking |
| `/home/claude/src/routes/api.php` | 1046 | Everything JSON: sanctum, magic-link client API, native-app API, external API, webhooks |
| `/home/claude/src/routes/admin.php` | 124 | `require`d from web.php — **duplicates** many `admin/*` routes already in web.php |
| `/home/claude/src/routes/project_tiers.php` | 32 | **Orphan** — not `require`d anywhere; a third copy of the project-tier routes |
| `/home/claude/src/routes/auth.php` | 66 | Breeze auth (registration commented out) |
| `/home/claude/src/routes/channels.php` | 35 | Broadcast auth |
| `/home/claude/src/routes/console.php` | 57 | Scheduler |

⚠️ **Route duplication is real and must be resolved in v2.** `admin/project-tiers` and `admin/monthly-budgets` are declared in *both* `web.php` (named `admin.*`) and `admin.php` (mostly unnamed) — last registration wins for URL dispatch, and `project_tiers.php` is a dead third copy. Similarly, bills/invoices are declared twice inside `api.php` (lines ~194–212 with permission middleware, and again at ~751–766 **without** permission middleware on invoices).

### 1.1 Broadcast channels (`routes/channels.php`)

| Channel | Authorization |
|---|---|
| `App.Models.User.{id}` | own id |
| `project.{projectId}` | `view_all_projects` OR project membership |
| `topic.{topicId}` | `view_all_projects` OR membership of `TelegramTopic.project_id` |

### 1.2 Scheduler (`routes/console.php`)

`FetchEmails` (every minute) · `inbox:fetch-sent` (5 min, flagged) · `files:prune-expired` (daily 03:15) · `FetchCurrencyRatesJob` (daily) · `XeroPaymentSyncJob` (daily) · `xero:refresh-payment-services` (6h) · `xero:sync-invoices` (hourly) · `queue:work` + `queue:work --queue=emails` (every minute) · `points:calculate-streak` (weekly Sun) · `auth:cleanup-client-data` (hourly) · `app:run-scheduler` (every minute). Commented out: `leads:process-new`, `leads:process-follow-ups`.

**Note: there is NO Gmail pub/sub webhook.** Mail ingestion is polling-only (`FetchEmails` + `inbox:fetch-sent`).

---

## 2. Audiences

| Audience | Entry | Auth mechanism | Pages |
|---|---|---|---|
| **Internal staff** | `/dashboard`, `/workspace`, everything under `auth,verified` | Laravel session + OTP + `RestoreRememberedDevice` cookie | ~65 Vue pages + `React/Inbox` |
| **Clients** | `/client/dashboard/{token}`, `/client/dashboard`, `/magic-link` | `MagicLink` token → `auth.magiclink` on `/api/client-api/*`; PIN setup/verify | `Pages/ClientDashboard.vue` + 17 section components |
| **Suppliers / guests (portal)** | `/projects/public/{slug}/{code}` → `/portal/*` | OTP email code → HttpOnly cookie (`PortalSessionService`), or existing app login; `user_type='guest'` | `ReactPages/Portal/*` |
| **Suppliers (classic fallback)** | `/projects/classic/{code}` | same OTP, localStorage session | `Pages/Public/ProjectView.vue` |
| **Public / anonymous** | `/`, `/privacy-policy`, `/view/{share_token}`, `/email-preview/{slug}`, `/magic-link-error`, tracking pixels | none | `Welcome.vue`, `Presentations/PublicPresenter.vue`, blade `privacy-policy` |
| **API consumers (extension/native app)** | `/api/*` | `auth:sanctum` (SPA cookie or Bearer token via `/api/token`), `auth.apikey` for the Chrome extension | none |
| **External 3rd-party apps** | `/api/external/*` | `auth.magiclink.external` (`X-Magic-Token`, IP/domain whitelist, max_uses) | none |
| **Webhooks** | `/api/telegram/wh`, `/api/xero/webhook`, `/api/external/stripe/webhook/{app_id}`, `/api/public/lead/{firefly}`, `/api/famifyhub/*`, `/api/bugs/*` | none / signature / API key | none |

---

# FEATURE AREAS

---

## A. Authentication, Session & Profile

**Routes** — `/home/claude/src/routes/auth.php`, plus `web.php:637-639`, `api.php:96-125`

| Method | URI | Name | Controller@action | Middleware | Renders |
|---|---|---|---|---|---|
| GET | `/login` | `login` | `Auth\AuthenticatedSessionController@create` | `guest` | `Auth/Login` |
| POST | `/login` | — | `AuthenticatedSessionController@store` | `guest` | redirect / OTP step |
| POST | `/verify-otp` | `otp.verify` | `AuthenticatedSessionController@verifyOtp` | `guest` | JSON |
| POST | `/resend-otp` | `otp.resend` | `AuthenticatedSessionController@resendOtp` | `guest` | JSON |
| GET | `/forgot-password` | `password.request` | `PasswordResetLinkController@create` | `guest` | `Auth/ForgotPassword` |
| POST | `/forgot-password` | `password.email` | `PasswordResetLinkController@store` | `guest` | — |
| GET | `/reset-password/{token}` | `password.reset` | `NewPasswordController@create` | `guest` | `Auth/ResetPassword` |
| POST | `/reset-password` | `password.store` | `NewPasswordController@store` | `guest` | — |
| GET | `/verify-email` | `verification.notice` | `EmailVerificationPromptController` | `auth` | `Auth/VerifyEmail` |
| GET | `/verify-email/{id}/{hash}` | `verification.verify` | `VerifyEmailController` | `auth,signed,throttle:6,1` | redirect |
| POST | `/email/verification-notification` | `verification.send` | `EmailVerificationNotificationController@store` | `auth,throttle:6,1` | — |
| GET | `/confirm-password` | `password.confirm` | `ConfirmablePasswordController@show` | `auth` | `Auth/ConfirmPassword` |
| POST | `/confirm-password` | — | `ConfirmablePasswordController@store` | `auth` | — |
| PUT | `/password` | `password.update` | `PasswordController@update` | `auth` | — |
| POST | `/logout` | `logout` | `AuthenticatedSessionController@destroy` | `auth` | 302 → `/` |
| GET | `/profile` | `profile.edit` | `ProfileController@edit` | `auth,verified` | `Profile/Edit` |
| PATCH/DELETE | `/profile` | `profile.update` / `.destroy` | `ProfileController` | `auth,verified` | — |
| POST | `/api/login`, `/api/loginapp` | — | `AuthenticatedSessionController@store` / `@storeapp` | `guest,web` | JSON |
| POST | `/api/token` | — | `@getToken` | `guest` | Sanctum token |
| POST | `/api/logout-token` | — | `@revokeToken` | `auth:sanctum` | — |
| POST | `/api/forgot-password`, `/api/reset-password` | — | Breeze controllers | `guest` | JSON |
| POST | `/api/user/update-profile-field` | — | `Api\UserProfileController@updateField` | `auth:sanctum` | JSON |
| GET | `/api/me/status` | — | `Api\UserProfileController@status` | `auth:sanctum` | JSON |
| POST | `/api/presence/status` | — | `Api\UserProfileController@updateOnlineStatus` | `auth.apikey` | JSON |

**Google OAuth** (`web.php:146-162`): `/google/redirect`, `/google/callback` (`GoogleAuthController` — app-level Gmail authorization, renders `GoogleAuthSuccess`), `/user/google/redirect`, `/google/usercallback`, `/user/google/check`, `/user/google/disconnect` (`GoogleUserAuthController` — per-user). `/api/google/status` and `/api/auth/google/*` under sanctum.

**Pages** — `Pages/Auth/{Login,ForgotPassword,ResetPassword,VerifyEmail,ConfirmPassword,Register}.vue`; `Pages/Profile/Edit.vue` + `Partials/{UpdateProfileInformationForm,UpdatePasswordForm,DeleteUserForm,TelegramIntegrationForm}.vue`; `Pages/GoogleAuthSuccess.vue`; `Layouts/GuestLayout.vue`.

**Liveness** — Live. `Pages/Auth/Register.vue` is **dead** (routes commented out in `routes/auth.php:15-19`; `RegisteredUserController` still exists and still `Inertia::render('Auth/Register')` but is unreachable). Login has an OTP second factor + per-device remember-me (`RememberDeviceService`), which is unusual and must be reproduced.

**v2 module must cover:** email+password → OTP challenge → verify/resend; per-device remember-me cookie (multi-device, unlike Laravel native); password reset; email verification; profile edit incl. Telegram linking and Google account connect/disconnect; Sanctum token issuance for the desktop/native app; API-key generation for the Chrome extension; guest-account rejection (`EnsureNotGuest`).

---

## B. Dashboard & Landing

| Method | URI | Name | Handler | Middleware | Renders |
|---|---|---|---|---|---|
| GET | `/` | — | closure | — | `Welcome` |
| GET | `/dashboard` | `dashboard` | closure (counts `user->projects()`) | `auth,verified` | `Dashboard` (props: `projectCount`) |
| GET | `/react-test` | `react.test` | closure | `auth,verified` | `React/TestPage` |
| GET | `/dashboard/ceo-financial` | `dashboard.ceo-financial` | `Dashboard\CeoFinancialDashboardController@index` | `auth,verified` | `Dashboard/CeoFinancial` |
| POST | `/dashboard/ceo-financial/instruction` | `dashboard.ceo-financial.instruction` | `@storeInstruction` | `auth,verified` | — |
| GET | `/privacy-policy` | `privacy.policy` | closure | — | **Blade** `privacy-policy` (not Inertia) |

**Pages** — `Pages/Dashboard.vue` (the tasks/home page; React rail maps `Tasks` → `/dashboard`), `Pages/Dashboard/CeoFinancial.vue`, `Pages/Welcome.vue`, `ReactPages/TestPage.jsx`. Component dir `Components/Dashboard/`.

**Duplication / liveness:**
- `Pages/Dashboard.vue` (live) vs directory `Pages/Dashboard/CeoFinancial.vue` (live) — both real, confusingly co-named.
- `Pages/PrivacyPolicy.vue` — **dead** (no route references it; the route serves a Blade view instead).
- `ReactPages/TestPage.jsx` + `/react-test` — **experimental scaffolding**, explicitly marked "Remove once the redesign has real React pages". Delete in v2.
- `Pages/Test/FormModalTest.vue` at `/test/form-modal` (`test.form-modal`) with `POST /api/test-form` (`Api\TestFormController`) — **experimental**, a demo harness for `Components/BaseFormModal.vue`.
- `/test/user-project-role` → `TestController@testUserProjectRole` — **experimental**.
- `/api/playground`, `/api/test-reverb`, `/api/test-email-with-config`, `/test-google-auth`, `/receive-test-emails`, `/debug/list-drive-files` — **all unauthenticated debug endpoints**. Remove.

**CEO Financial dashboard** has no permission middleware on the route (`web.php:629`) — seeder `CeoDashboardPermissionSeeder.php` exists, so gating is presumably inside the controller/UI only. Verify in v2.

**v2 must cover:** a real landing/home; personal task dashboard; CEO financial overview with free-text "instruction" capture.

---

## C. Global Search, Notifications, Notice Board, Tags

| Method | URI | Handler | Middleware |
|---|---|---|---|
| GET | `/api/global-search` | `GlobalSearchController@search` | `auth:sanctum` |
| GET | `/api/tags/search` | `TagController@search` | `auth:sanctum` |
| GET | `/api/notifications` | `NotificationController@index` | `auth:sanctum` |
| POST | `/api/notifications/read-all` | `@markAllAsRead` | `auth:sanctum` |
| POST | `/api/notifications/{viewId}/read` | `@markAsReadByViewId` | `auth:sanctum` |
| DELETE | `/api/notifications/{notificationId}` | `@destroy` | `auth:sanctum` |
| GET | `/admin/notice-board` (`admin.notice-board.index`) | closure | `auth,verified,permission:manage_notices` → `Admin/NoticeBoard/Index` |
| GET | `/notices/{notice}/redirect` (`notices.redirect`) | `NoticeBoardController@redirect` | `auth,verified` |
| GET/POST | `/api/notices`, `/api/notices` | `Api\NoticeBoardController@index/@store` | `permission:manage_notices` |
| GET | `/api/notices/unread` | `@unread` | `auth:sanctum` |
| POST | `/api/notices/acknowledge` | `@acknowledge` | `auth:sanctum` |
| GET | `/api/notices/{notice}/redirect` (`api.notices.redirect`) | `@redirect` | `auth:sanctum` |

**Pages/Components** — `Pages/Admin/NoticeBoard/Index.vue` (570 lines); `Components/GlobalSearch.vue`, `Components/NotificationsSidebar.vue`, `Components/NotificationContainer.vue`, `Components/StandardNotificationContainer.vue`, `Components/PushNotificationContainer.vue`, `Components/Notification.vue`, `Components/Notices/`, `Components/TagInput.vue`. **Duplicate** notification component at `resources/js/src/Components/Notification.vue` (a stray one-file `src/` directory — dead).

**Resources** — `app/Http/Resources/Notifications.php`.

**Audience** — internal staff. **Liveness** — live.

**v2 must cover:** cross-entity global search (projects/clients/users/tasks/emails — see `GlobalSearchController`); realtime notification feed over Reverb private user channel with read/dismiss; notice board with acknowledgement + click-tracking redirects; polymorphic tag search & `ProcessTags` middleware behaviour on create/update.

---

## D. Projects (core module)

### D.1 Inertia pages

| Method | URI | Name | Handler | Middleware | Renders |
|---|---|---|---|---|---|
| GET | `/projects` | `projects.index` | closure | `permission:view_projects` | `Projects/Index` |
| GET | `/projects/create` | `projects.create` | closure | `permission:create_projects` | `Projects/Create` (`sourceOptions`) |
| GET | `/projects/{project}/edit` | `projects.edit` | closure | `permission:create_projects` | `Projects/Edit` |
| GET | `/projects/{id}` | `projects.show` | closure | `permission:view_projects` | `Projects/Show` |
| GET | `/projects/{project}/wireframe` | `projects.wireframe` | closure | `permission:view_projects` | `Projects/Show` (`showWireframe`) |
| GET | `/projects/{project}/wireframe/{wireframe}` | `projects.wireframe.show` | closure | `permission:view_projects` | `Projects/Show` |
| GET | `/projects/{project}/wireframe/{wireframe}/version/{version}` | `projects.wireframe.version` | closure | `permission:view_projects` | `Projects/Show` |

⚠️ `sourceOptions` (UpWork, AirTasker, Direct, Agency, Reference, Wix Marketplace, Fiver, Social Media, Advertising, Website, Other) is **hardcoded inline in `routes/web.php:32-77`**. Move to config/DB in v2.

### D.2 API — read (`Api\ProjectReadController`)

`GET /api/projects` · `projects/with-wireframes` · `projects/{project}` · `projects-simplified` · `projects-for-email` · `projects-for-invoicing` (`permission:create_project_invoices`) · `projects/{project}/notes` · `/standups` · `/notes/{note}/replies` · `/tasks` · `/meetings` · `/contexts` · `/users` · `/clients` · `/google-chat-members` · `/contract-details` · `/expendable-budget` · sections: `basic`, `clients-users`, `meeting-attendees`, `clients`, `users`, `services-payment`, `transactions`, `documents`, `notes` · `/user/meetings` · `/user/standups` · `wireframes/{id}/comments`.

### D.3 API — actions (`Api\ProjectActionController`)

`POST projects` · `PUT projects/{project}` · `PUT projects/{project}/update-data` · `DELETE projects/{project}` · `POST projects/{id}/restore` · `attach-users` / `detach-users` / `attach-clients` / `detach-clients` · `attach-google-chat-members` / `detach-google-chat-members` · `notes` · `notes/{note}/reply` · `document` / `documents` · `logo` · `standup` · `meeting-minutes` · `meetings` (POST/DELETE `{googleEventId}`) · `PATCH convert-payment-type` · `PATCH expendable-budget` · `POST archive` · `PATCH assign-leads` (`permission:manage_projects`) · `POST generate-telegram-code` · `PUT sections/basic` (`process.tags`) · `sections/services-payment` · `sections/transactions` · `sections/notes` · `addWireframeComment` / `resolveWireframeComment`.

⚠️ Bug to carry over knowingly, not replicate: `api.php:379` — `Route::post('projects/{project}/detach-clients', [ProjectActionController::class, 'detach-clients'])` uses an invalid method name `'detach-clients'` (hyphen). This route is broken.

### D.4 Project sharing (`Api\ProjectShareController`)

`GET projects/{project}/share` · `/share/recipients` · `/share/tracking` · `POST /share/token` · `/share/regenerate` · `POST /share/email` (`throttle:20,1`). Generates the 64-char `projects.public_share_token`; invite links carry the **first 12 characters**.

### D.5 Other project sub-APIs

- **Resources/comments**: `apiResource projects/{project}/resources` (`ResourceController`); `resources/{resource}/comments` (`CommentController` index/store), `apiResource resources.comments` (except index/store), `POST resources/{resource}/approve`, `POST resources/{resource}/toggle-visibility`
- **Notes** (polymorphic): `GET/POST /api/project_notes` (`ProjectNoteController`)
- **Files**: `GET/POST /api/files`, `DELETE /api/files/{file}` (`FileAttachmentController`, polymorphic); `POST /api/upload-image` (`ImageUploadController`)
- **Deliverables**: `GET/POST projects/{project}/deliverables`, `GET .../{deliverable}`, `POST .../comments` (`Api\ProjectDashboard\ProjectDeliverableAction`); plus a **second** deliverables API: `projects/{projectId}/project-deliverables` + `project-deliverables/{id}` (`Api\ProjectDeliverableController`) and `GET project-deliverable-types` (returns `config/project_deliverable_types.php`)
- **Wireframes** (`Api\WireframeController`): `projects/{projectId}/wireframes` index/latest/{id}/store/update/destroy, `{id}/versions` (GET/POST/PUT `{versionNumber}`), `{id}/{publish}`, `{id}/logs`, `{id}/comments` GET/POST + `comments/{commentId}/resolved_comment`
- **Calendar**: `Api\ProjectCalendarController` — **UNROUTED / dead**
- **Sections**: `Api\ProjectSectionController` — **UNROUTED / dead**
- **BugHerd**: `GET /api/bugherd/projects` (`Api\BugHerdController`)
- **Telegram topics**: `GET/POST projects/{project}/topics` (`Api\TelegramTopicController`)

### D.6 Pages & components

`Pages/Projects/{Index,Create,Edit,Show}.vue` (Show = 810 lines and is the app's largest feature surface). Show composes: `ProjectForm`, `ProjectGeneralInfoCard`, `ProjectStatsCards`, `ProjectTabsNavigation`, `ProjectTasks/ProjectTasksTab`, `ProjectEmailsTab`, `ProjectNotesTab`, `ProjectOverviewCards/{ProjectFinancialsCard,ProjectClientsCard,ProjectTeamCard,UserFinancialCard}`, `ProjectFinancials/UserTransactionsModal`, `ProjectsEmails/ComponseEmailModal` *(sic — typo in filename)*, `ProjectTasks/CreateTaskModal`, `WorkspaceBulkTaskModal`, `ProjectsSeoReports/SeoReportTab`, `ProjectVaultCredentialsTab`, `ProjectDashboard/ProjectDeliverablesOverviewCard`, `ProjectsDeliverables/DeliverableDetailSidebar`, `DailyStandups/DailyStandups`, `MeetingModal`, `StandupModal`, `NotesModal`, `ProjectMagicLinkModal`, `ProjectMeetingsList`, `ProjectTaskNotificationPrompt`, `RightSidebar`, `TaskList`.
Component dirs: `Components/ProjectForm/`, `ProjectTasks/`, `ProjectOverviewCards/`, `ProjectFinancials/`, `ProjectInvoices/`, `ProjectExpendables/`, `ProjectsDeliverables/`, `ProjectsEmails/`, `ProjectsSeoReports/`, `ProjectDashboard/`, `DailyStandups/`.
Utils: `Utils/currency.js`, `Utils/sidebar.js`, `Utils/taskState.js`. `Composables/useEmbeddedScheduler.js`.

`Pages/Wireframe.vue` (593 lines) — **dead**: no route renders `'Wireframe'`; wireframes are shown through `Projects/Show` with `showWireframe`.

**Permissions** — `view_projects`, `view_all_projects`, `create_projects`, `edit_projects`, `delete_projects`, `restore_project`, `manage_projects`, `manage_project_users`, `manage_project_clients`, `view_project_documents`, `upload_project_documents`, `add_project_notes`, `view_project_notes`, `manage_project_financial`, `add_expendables`, `view_project_deliverables`, `view_project_services_and_payments`. Policy: `app/Policies/ProjectPolicy.php`. Request: `app/Http/Requests/StoreProjectRequest.php`.

**Audience** — internal staff. **Liveness** — very live; the heart of the app.

**v2 module must cover:** project list with filters; create/edit (with source options, tiers, currencies PKR/AUD/INR/USD/EUR/GBP, payment type conversion, contract details, bonus configuration group attachment); the tabbed project detail (Overview, Tasks, Emails, Notes/Standups, Deliverables, SEO Reports, Financials, Vault Credentials, Wireframes, Chat, Documents, Meetings); user & client attach/detach with per-project roles; document/logo upload; meetings (Google Calendar) and standups (Google Chat); magic-link generation for clients; public share token + invite email + tracking; wireframe versioning, publishing, commenting and resolution; archive/restore; expendable budget.

---

## E. Tasks, Subtasks, Milestones, Daily Work Log, Workspace

| Method | URI | Handler | Middleware |
|---|---|---|---|
| GET | `/workspace` (`workspace.index`) | closure → `Workspace/Index` | `auth,verified` |
| GET | `/workspace/team-pulse` (`workspace.team-pulse`) | closure → `Workspace/TeamPulseDashboard` | `auth,verified` |
| GET | `/task-types` (`task-types.page`) | closure → `TaskTypes/Index` | `permission:manage_projects` |
| GET | `/api/workspace/projects`, `/api/workspace/projects/{project}/completed-tasks` | `Api\WorkspaceController` | `auth:sanctum` |
| GET/PUT | `/api/user/workspace`, `/api/user/checklist`, `/api/user/notes` | `Api\UserWorkspaceController` | `auth:sanctum` |
| — | `/api/daily-tasks` (index, history, store, reorder, `PATCH {id}`, `push-to-tomorrow`, delete) | `Api\DailyTaskController` | `auth:sanctum` |
| — | `apiResource /api/tasks` (`process.tags`) + `bulk`, `bulk-workspace`, `quick`, `{task}/notes`, `PATCH {task}/complete`, `start`, `pause`, `resume`, `block`, `unblock`, `archive`, `revise` | `Api\TaskController` | `auth:sanctum` |
| GET | `/api/task-statistics`, `/api/assigned-tasks`, `/api/projects/{projectId}/due-and-overdue-tasks` | `Api\TaskController` | `auth:sanctum` |
| — | `apiResource /api/subtasks` + `{subtask}/notes`, `/complete`, `/start`, `/block` | `Api\SubtaskController` | `auth:sanctum` |
| — | `apiResource /api/milestones` + `complete`, `approve`, `reject`, `reopen`, `start`, `update-due-date`, `reasons`; `projects/{project}/milestones`, `/milestones-with-expendables` | `Api\MilestoneController` | `auth:sanctum` |
| — | `apiResource /api/task-types` | `Api\TaskTypeController` | `auth:sanctum` |

**Pages** — `Pages/Workspace/Index.vue` (1455 lines — the "My work" hub) with `components/{Sidebar,Filters,ProjectCards,ProjectCard,DailyWorkLog}.vue`; `Pages/Workspace/TeamPulseDashboard.vue` (900 lines); `Pages/TaskTypes/Index.vue`; `Pages/Dashboard.vue`. Shared: `Components/TaskList.vue`, `Components/KanbanBoard.vue`, `Components/ChecklistComponent.vue`, `Components/ChecklistCreator.vue`, `Components/WorkspaceBulkTaskModal.vue`, `Components/TaskNotificationPrompt.vue`, `Components/BlockReasonModal.vue`, `Components/EffortEstimationGuide.vue`, `Utils/taskState.js`.

**Liveness** — live; `workspace.index` is the React rail's primary "My work" destination. `Workspace/TeamPulseDashboard` is reachable but not in the main nav dropdowns — likely low-traffic/semi-experimental.

**v2 must cover:** per-user workspace (projects, filters, personal checklist, personal notes, daily work log with reorder + push-to-tomorrow + history); task lifecycle (create/quick/bulk/bulk-workspace, start/pause/resume, block with reason, unblock, complete, revise, archive) with time tracking; subtasks; milestones with approval/rejection/reopen + reason codes and expendable linkage; task types admin; task statistics & due/overdue queries; tags on tasks.

---

## F. Inbox / Emails (two full implementations)

### F.1 Legacy Vue inbox & email approval

| Method | URI | Name | Handler | Middleware | Renders |
|---|---|---|---|---|---|
| GET | `/inbox` | `inbox` | closure (passes `betaUrl`) | `permission:view_emails` | `Emails/Inbox/Index` |
| GET | `/emails/compose` | `emails.compose` | closure | `permission:compose_emails` | `Emails/Composer` |
| GET | `/emails/pending` | `emails.pending` | closure | `permission:approve_emails` | `Emails/PendingApprovals` |
| GET | `/emails/rejected` | `emails.rejected` | closure | `permission:compose_emails` | `Emails/Rejected` |
| GET | `/emails/{email}/preview` | `emails.preview` | `Api\EmailController@reviewEmail` | `auth` | — |
| GET | `/email-preview/{slug?}` | `email.preview` | `EmailPreviewController@preview` | **none (public)** | — |

**API (`Api\EmailController`)**: `emails/pending-approval`, `pending-approval-simplified`, `rejected`, `rejected-simplified`, `projects/{project}/emails`, `projects/{project}/emails-simplified`, `emails/{email}/edit-content`, `/approve`, `/edit-and-approve`, `/reject`, `/update`, `/resubmit`, `/tasks/bulk`, `apiResource emails` (except destroy), `PATCH conversations/{conversation}/project`, `PATCH emails/{email}/privacy`, `DELETE emails/{email}`, `POST emails/templated`, `POST projects/{project}/email-preview` (`Api\SendEmailController@preview`).

**Legacy inbox API (`Api\InboxController`)**: `inbox/new-emails`, `all-emails`, `waiting-approval`, `counts`, `category-stats`, `POST inbox/emails/{email}/mark-as-read`.

**Pages** — `Pages/Emails/Inbox/Index.vue` (470) + `Components/{EmailList,EmailFilters,EmailCategoryFilters,EmailDetailsContent,EmailActionContent,ReceivedEmailActionContent,ComposeEmailContent,CustomComposeEmailContent,CustomEmailApprovalContent,EmailBulkTaskContent,EmailAttachmentsList,TaskCreationForm}.vue`, plus **`Components/Archived/{TabPanel,AllEmailsTab,NewEmailsTab,WaitingApprovalTab}.vue`** — self-labelled archived/dead. `Pages/Emails/Composer.vue`, `Pages/Emails/PendingApprovals.vue`, `Pages/Emails/Rejected.vue`. Shared: `Components/ComposeEmailModal.vue`, `Components/EmailEditor.vue`, `Components/ContentEditor.vue`, `Components/RichTextEditor.vue`, `Components/CustomRichTextEditor.vue`, `Components/TiptapEditor.vue`, `Components/PlaceholderInserter.vue`, `Components/PreviewModal.vue`. Composables: `useEmailSignature.js`, `useEmailTemplate.js`.

### F.2 React beta inbox (`/inbox/beta`)

| Method | URI | Name | Handler | Middleware | Renders |
|---|---|---|---|---|---|
| GET | `/inbox/beta` | `inbox.beta` | `InboxBetaController` (`__invoke`) | `auth,verified,permission:view_emails`, **registered only if `config('inbox.beta')`** | `React/Inbox/Index` |

Props are a `settings` object driven by `App\Services\Inbox\InboxAccess`: `sla_minutes`, `per_page`, `is_manager`, `can_see_private`, `can_compose_template`, `can_compose_custom`, `is_super_admin`, `can_compose_blocks`, `can_mark_private`, `can_see_deleted`, `can_address_manually`, `ai.{enabled,summarise,drafts,check_outbound}`, `sign_off.{name,role}`, `classic_url`; plus `initialThreadId` from `?thread=`.

**API** (`api.php:519-582`, all `auth:sanctum`, prefix `inbox`):
`GET filters` · `GET threads` · `GET threads/{conversation}` · `POST threads/{conversation}/read` · `POST threads/{conversation}/notes` · `POST threads/{conversation}/summarise` · `GET threads/{conversation}/recipients` · `POST threads/{conversation}/reply` · `POST bulk` (`InboxThreadController` / `InboxReplyController`) · `GET saved`, `POST saved`, `PUT saved/{email}`, `DELETE saved/{email}` (`InboxSavedController`) · `GET deleted`, `POST emails/{emailId}/restore` (`InboxDeletedController`) · `GET emails/{email}/preview` · `POST emails/{email}/approve` · `POST emails/{email}/resend-to-ai` · `POST emails/{email}/draft` · `POST block-images`, `POST blocks/preview` (`InboxBlockImageController`) · `POST attachments` (`InboxAttachmentController`).

**Key semantics** (documented at length in `routes/api.php:503-582`): the beta paginates **conversations**, not emails. There is **no second send path** — a reply is created as `status = draft`, the existing automation workflow picks it up, runs AI approval and either sends or parks at `pending_approval`; a human approval routes through `emails/{email}/edit-and-approve`. Beta-only rule: the automation owns a submitted draft for `inbox.manual_approval_after_minutes`.

**React components** — `ReactPages/Inbox/Index.jsx`; `ReactComponents/inbox/`: `DesktopInbox`, `ThreadList`, `ThreadView`, `FilterRail`, `ReplyBox`, `ComposeModal`, `BlockBuilder`, `MarkdownEditor`, `TemplateFields`, `Salutation`, `EmailBody`, `EmailNumber`, `ClientViewDialog`, `InboxDialogs`, `modals.jsx`, `SavedList`, `DeletedList`, `format.js`; hooks `useInbox`, `useInboxPage`, `useComposeDrafts`, `useBlocks`, `useTemplates`; mobile: `MobileInbox`, `MobileShell`, `MobileThreadList`, `MobileThreadView`, `MobileComposer`, `MobileFilterSheet`, `Sheet`. Design system: `ReactComponents/ds/*`, shell `ReactComponents/app/AppShell.jsx`.

### F.3 Ingestion, tracking, templates

- `EmailReceiveController@receiveEmails` at `GET /receive-test-emails` — **unauthenticated test endpoint**
- `EmailTrackingController`: `GET /email/track/{id}`, `/notice/track/{id}/{email?}`, `/project/track/{id}/{email?}` (public tracking pixels)
- Ingestion is scheduled (`FetchEmails` every minute; `inbox:fetch-sent` every 5 min when `inbox.sent_ingest.enabled`) — **no Gmail push/pubsub webhook exists**
- Email Apps admin (multi-mailbox config): see §O
- `Api\FamifyHub\MailController` — public contact-form mailer for a separate product (`/api/famifyhub/contact`, `/contactform`)

**Permissions** — `view_emails`, `view_all_emails`, `view_private_emails`, `compose_emails`, `create_custom_emails`, `email_custom_recipients`, `edit_emails`, `approve_emails`, `approve_all_emails`, `approve_received_emails`, `delete_emails`. Policy: `app/Policies/EmailPolicy.php` (the most complex policy — per-project fallbacks, `approveOrView`).

**Liveness / duplication assessment** — **This is the single biggest duplication in the codebase.** Two complete inboxes run side by side against the same tables and read markers. `/inbox` (Vue) is the default; `/inbox/beta` (React) is flagged on by default. `Emails/Inbox/Components/Archived/*` is dead within the Vue one. `Emails/Composer.vue`, `PendingApprovals.vue`, `Rejected.vue` are legacy-only pages the beta subsumes.

**v2 must cover:** conversation-threaded inbox (not email-list) with SLA countdown/breach; filter rail (needs-reply, waiting approval, saved, deleted, private, by project/client/category); thread view with cleaned fragment + full branded preview in sandboxed iframe; reply composer with templates, placeholders, markdown, block builder with expiring image uploads (CID at send), attachments, salutation, sign-off; saved drafts + autosave; bulk actions; approve/reject/edit-and-approve with the AI-ownership window; resend-to-AI, request-AI-draft, AI summarise; private-email marking; deleted bin + restore; manual addressing gate; per-thread notes; mobile shell; bulk task creation from an email; conversation→project reassignment; tracking pixels.

---

## G. Clients / Leads / Campaigns (CRM)

| Method | URI | Name | Handler | Middleware | Renders |
|---|---|---|---|---|---|
| GET | `/clients` | `clients.page` | closure | `permission:view_clients` | `Clients/Index` |
| GET | `/clients/{id}` | `clients.show` | closure | `permission:view_clients` | `Clients/Show` |
| GET | `/leads` | `leads.page` | closure (`sourceOptions`) | `permission:manage_projects` | `Admin/Leads/Index` |
| GET | `/leads/{leadId}` | `leads.show` | closure | `permission:manage_projects` | `Admin/Leads/LeadDetails` |
| GET | `/campaigns` | `campaigns.page` | closure | `permission:manage_projects` | `Admin/Campaigns/Index` |
| GET | `/campaigns/create` | `campaigns.create` | closure | `permission:manage_projects` | `Admin/Campaigns/CreateEdit` (`mode:create`) |
| GET | `/campaigns/{campaign}/edit` | `campaigns.edit` | closure | `permission:manage_projects` | `Admin/Campaigns/CreateEdit` (`mode:edit`) |

**API** — `apiResource clients` (`api.clients`, `Api\ClientController`) + `clients/{client}/{email,emails,details,xero-contact-candidates}`, `POST clients/{client}/{xero-contact-sync,xero-contact-create,generate-telegram-code}`; `apiResource leads` (`api.leads`) + `leads/search`, `POST leads/{lead}/contexts`, `leads/{lead}/emails`, `leads/{lead}/presentations`, `POST leads/{lead}/convert`; `apiResource campaigns` + `campaigns/{campaign}/leads` GET/POST/DELETE; existing-client enquiries: `GET /api/existing-client-enquiries/supporting-data`, index/store/`PUT {id}`/`DELETE {id}`/`POST {id}/convert` (`Api\ExistingClientEnquiryController`); CRM services: `GET/POST /api/crm-services`, `PUT crm-services/bulk`, `PUT crm-services/{crmService}`, `POST crm-services/{crmService}/merge` (`Api\CrmServiceController`).

**Public lead intake** — `POST /api/public/lead-intake` (`Api\PublicLeadIntakeController`, **no auth**, fed by `PublicPresenter`); `POST /api/public/lead/{firefly}` (`Api\PublicLeadApiController`, API-key protected; see `config/public_api.php`).

**Pages** — `Pages/Clients/{Index,Show}.vue`; `Pages/Admin/Leads/{Index,LeadDetails}.vue` + `components/{LeadCard,LeadKanban,LeadsPipelineView,LeadsFilters,LeadFormModal}.vue`; `Pages/Admin/Campaigns/{Index,CreateEdit}.vue`. Composables: `useClientDetails.js`, `useLeadDetails.js`, `useLeads.js`. Resource: `app/Http/Resources/ClientResource.php`. Policy: `ClientPolicy`.

**Liveness** — live. **`Pages/Clients/Dashboard.vue` is DEAD** — no route or controller references it (distinct from `Pages/ClientDashboard.vue`). Lead auto-processing commands are commented out of the scheduler.

**Permissions** — `view_clients`, `create_clients`, `edit_clients`, `delete_clients`, `view_client_contacts`, `view_client_financial`, `contact_lead`/`contact_leads`; leads/campaigns gated only by `manage_projects`.

**v2 must cover:** client CRUD + detail (contacts, financials, projects, emails, vault, Xero contact matching/sync/creation, Telegram linking); lead list + kanban pipeline + filters + lead detail with contexts and email history; lead → client/project conversion; campaigns with lead attach/detach; existing-client enquiry intake and conversion; CRM service taxonomy with merge; public lead intake endpoints (unauthenticated + API-key).

---

## H. Client Dashboard (magic link) — client audience

| Method | URI | Name | Handler | Middleware | Renders |
|---|---|---|---|---|---|
| GET | `/client/dashboard/{token}` | `client.magic-link-login` | `Api\MagicLinkController@handleMagicLink` | none (signature checked in controller) | `ClientDashboard` or `Errors/MagicLinkError` |
| GET | `/magic-link` | `client.magic-link` | `Api\MagicLinkController@handleMagicLink` | none | as above |
| GET | `/client/dashboard` | `client.dashboard` | `ClientDashboardController@index` | none | `ClientDashboard` |
| GET | `/magic-link-error` | `magic-link.error` | closure | none | `Errors/MagicLinkError` |

**Auth API** (`client.throttle`): `POST /api/client-magic-link`, `/api/client-api/check-status`, `/api/client-api/verify-pin`, `/api/client-api/resend-magic-link`. Unthrottled: `POST /api/client-api/verify`, `/api/client-api/setup-pin`.
**Data API** (`prefix client-api`, `auth.magiclink`, `withoutMiddleware(EnsureFrontendRequestsAreStateful)`):
`GET project/{project}` · `/wireframes` · `/wireframe/{id}` · `/wireframe/{id}/comments` · `POST` the same + `comments/{commentId}/{status}` (`ProjectClientAction`) · `GET project/{project}/{tasks,deliverables,documents,shareable-resources}` · `GET project/{projectId}/seo-report/{month}` · `GET projects/{project}/seo-reports/{available-months,count,{yearMonth}}` (`Api\Client\SeoReportController`) · `POST deliverables/{deliverable}/{mark-read,approve,request-revisions,comments}` · `POST tasks`, `POST tasks/{task}/notes` · `POST documents`, `POST documents/{document}/notes` · `POST switch-project` · `GET me/status` · `POST me/generate-telegram-code` · vault: `GET/POST /api/client-api/vault`, `DELETE vault/{id}`, `GET vault/{id}/logs` (`Client\VaultController`).

Also internal: `POST /api/projects/{projectId}/magic-link` (`auth:sanctum`) to issue a link.

**Pages** — `Pages/ClientDashboard.vue` (393) + `Pages/ClientDashboard/`: `Sidebar`, `HomeSection`, `TicketsSection`, `TicketNotesSidebar`, `ApprovalsSection`, `DocumentsSection`, `ResourceSection`, `InvoicesSection`, `AnnouncementsSection`, `VaultSection`, `SEOReport` (1091 lines), `DeliverableViewerModal`, `CreateTaskModal`, `PinSetupModal`, `TelegramPrompt`, `BaseModal`, `LoadingOverlay`. Sections: `home | seo | tickets | approvals | documents | resources | invoices | announcements | vault`.

**Liveness** — live, Vue, and **completely separate from the supplier portal**. `Api\ClientDeliverableInteractionController` and `Api\DeliverableCommentController` are **UNROUTED/dead**.

**v2 must cover:** magic-link email → token landing → optional PIN setup/verify → session; project switcher; home overview; SEO monthly report viewer; tickets (client-raised tasks) with notes; deliverable approvals (approve / request revisions / comment / mark read); documents upload + notes; shareable resources; invoices; announcements; credential vault (client-owned); Telegram linking prompt; error page for expired/used links. Note `VerifyMagicLinkToken` currently rejects **any** already-used token (403) — a known friction point.

---

## I. Supplier Portal (React) + Classic fallback — supplier/guest audience

### I.1 Front door (public, `throttle:120,1`)

| Method | URI | Name | Handler | Renders |
|---|---|---|---|---|
| GET | `/projects/public/{slug}/{code}` | `public.projects.pretty` | `Public\PublicProjectController@showPretty` | `React/Portal/Project` (signed-out) or redirect |
| GET | `/projects/public/{token}` | `public.projects.show` | `@show` | same |
| POST | `/projects/public/{token}/otp` | `public.projects.otp.send` | `@sendOtp` (`throttle:6,1`) | JSON |
| POST | `/projects/public/{token}/otp/verify` | `public.projects.otp.verify` | `@verifyOtp` (`throttle:10,1`) | JSON |

Token constraints: `[A-Za-z0-9]{12,64}`; `MIN_SHARE_CODE_LENGTH = 12`; LIKE wildcards escaped via `addcslashes`.

### I.2 Signed-in portal (`prefix portal`, `portal.user`, `throttle:120,1`)

| Method | URI | Name | Action | Renders |
|---|---|---|---|---|
| GET | `/portal` | `portal.projects.index` | `PortalController@index` | `React/Portal/Projects` |
| GET | `/portal/profile` | `portal.profile` | `@profile` | `React/Portal/Profile` |
| POST | `/portal/profile` | `portal.profile.update` | `@updateProfile` | — |
| POST | `/portal/sign-out` | `portal.sign-out` | `@signOut` | — |
| POST | `/portal/payment-methods` | `portal.payment-methods.store` | `@storePaymentMethod` | — |
| POST | `/portal/payment-methods/{method}/default` | `...default` | `@defaultPaymentMethod` | — |
| DELETE | `/portal/payment-methods/{method}` | `...destroy` | `@destroyPaymentMethod` | — |
| GET | `/portal/projects/{project}` | `portal.projects.show` | `@show` | `React/Portal/Project` |
| POST | `/portal/projects/{project}/proposals` | `portal.projects.proposals.store` | `@storeProposal` | — |
| POST | `/portal/projects/{project}/bills` | `portal.projects.bills.store` | `@storeBill` | — |

Services: `PortalSessionService` (HttpOnly cookie session, `resolvedVia` = `login|code`), `PortalAccessService` (per-project access — **not** token-knowledge; supports team-membership projects), `PortalProjectPresenter` (project cards, project payload, proposals, branding, `CURRENCIES`), `PortalProfileService` (`ID_TYPES`), `GuestPaymentMethodService` (masked list), `BillApprovalFlowService`, `CurrencyConversionService`, `OtpService`.

### I.3 Classic fallback (registered only if `config('portal.classic')`)

`GET /projects/classic/{code}` (`classic.projects.show`) → `Public\LegacyProjectViewController@showClassic` → `Public/ProjectView` (Vue, 667 lines); plus `POST {token}/otp`, `/otp/verify`, `/session`, `/profile`, `/track`, `/proposals`.

**Pages** — `ReactPages/Portal/{Projects,Project,Profile}.jsx`; `ReactComponents/portal/`: `PortalChrome`, `ProjectHero`, `PhasesTab`, `ProposalsTab`, `ProposalsPanel`, `ProposalModal`, `BillsTab`, `BillModal`, `SignInPanel`, `ProfileView`, `format.js`, `paymentMethods.js`, `usePortal.js`, `useTheme.js`.

**Liveness** — React portal is the live target; classic Vue page is an explicitly-temporary fallback slated for deletion (`config/portal.php` comment names `Pages/Public/ProjectView.vue` as the follow-up delete).

**v2 must cover:** share-link entry with OTP email sign-in; portal project list; project brief with phases/milestones; proposal submission (against expendables); bill submission with contract-limit enforcement (`BillExceedsContractException`) and approval flow; payment methods (add / set default / delete, masked); profile with ID types and currency; sign-out that only ends the code session; branding/theming; interaction tracking (`UserInteraction`, `page_view`); guest notification mail (`GuestBillSubmittedMail`).

---

## J. Billing, Financials, Ledger (money)

### J.1 Admin financial pages (`prefix admin`, `auth,verified`)

| URI | Name | Handler | Middleware | Renders |
|---|---|---|---|---|
| `/admin/financials` | `admin.financials.dashboard` | `Admin\FinancialController@dashboard` | *(none)* | `Admin/Financials/Dashboard` |
| `/admin/financials/bills` | `admin.financials.bills` | `@bills` | `permission:view_project_bills` | `Admin/Financials/Bills` |
| `/admin/financials/bills/{id}` | `admin.financials.bills.show` | `@showBill` | `view_project_bills` | `Admin/Financials/BillDetails` |
| `/admin/financials/transactions` | `admin.financials.transactions` | `@transactions` | `view_project_transactions` | `Admin/Financials/Transactions` |
| `/admin/financials/invoices` | `admin.financials.invoices` | `@invoices` | `view_project_invoices` | `Admin/Financials/Invoices` |
| `/admin/financials/invoices/{id}` | `admin.financials.invoices.show` | `@showInvoice` | `view_project_invoices` | `Admin/Financials/InvoiceDetails` |
| `/admin/financials/project-services` | `admin.financials.project-services` | `@projectServices` | *(none)* | `Admin/Financials/ProjectServices` |
| `/admin/financials/invoices-xero-sync` | `admin.financials.invoices.xero-sync` | `@xeroSync` | `create_project_invoices` | `Admin/Financials/XeroInvoiceSync` |
| `/admin/financials/proposals` | `admin.financials.proposals` | `@proposals` | `view_project_expendables_proposals` | `Admin/Financials/Proposals` |

### J.2 Financial APIs (`auth:sanctum`)

- Aggregates: `admin/financial-pending-counts`, `admin/bills`, `admin/transactions`, `admin/outstanding-docs`, `admin/bank-transactions[/{id}]`, `admin/invoices/stats`, `admin/invoices`, `admin/proposals/stats`, `admin/proposals`
- **Bills** (`Api\BillController`): `projects/{project}/bills` GET/POST, `PUT .../{bill}`, `POST bills/{bill}/{approve,void,restore,attachments}`, `DELETE bills/{bill}` — each with its own slug (`view/create/edit/approve/void/delete/restore_project_bills`). **Declared twice** (again at `api.php:751-755` with a partially different shape).
- **Invoices** (`Api\InvoiceController`): `projects/{project}/invoices` GET/POST, `PUT/GET .../{invoice}`, `/notes` GET/POST, `/approve`, `/reject`, `/comment`, `POST invoices/{invoice}/void`. ⚠️ **Declared twice** — the second block (`api.php:758-766`) carries **no permission middleware**.
- **Transactions** (`Api\TransactionsController`): `POST projects/{project}/transactions` (`process.basic:transaction_type,App\Models\TransactionType`), `PATCH .../{transaction}/process-payment`, `POST transactions/{transaction}/{link-bill,unlink-bill,link-invoice,unlink-invoice,attachments}`, `DELETE transactions/{transaction}`, `POST transactions/{id}/restore` — all `permission:manage_projects`
- **Transaction types**: `GET/POST /api/transaction-types`, `PUT {id}`, `GET transaction-types/search`
- **Currency**: `GET /api/currency-rates` (`Api\CurrencyController`); `FetchCurrencyRatesJob` daily
- **Expendables/proposals** (`Api\ProjectExpendableController`): `GET projects/{project}/expendables`, `POST`, `PUT {expendable}`, `POST {expendable}/{accept,reject,complete,shortlist}`, `DELETE`
- **Project services quick-add** via `XeroReverseSyncController`

### J.3 Bonus / Points / Kudos / Leaderboard

| URI | Name | Middleware | Renders |
|---|---|---|---|
| `/bonus_system` | *(unnamed)* | `auth,verified` | `BonusSystem/Index` — **duplicate route** |
| `/bonus-system` | `bonus-system.index` | `permission:view_own_points` | `BonusSystem/Index` |
| `/leaderboard` | `leaderboard.index` | `permission:view_own_points` | `Leaderboard/Index` |
| `/ledger` | `ledger.index` | `permission:view_own_points` | `Ledger/Index` |
| `/kudos` | `kudos.index` | `permission:view_kudos` | `Kudos/Index` |
| `/admin/bonus-calculator` | `admin.bonus-calculator.index` | `permission:view_monthly_budgets` | `Admin/BonusCalculator/Index` |
| `/admin/bonus-calculator/calculate` | `admin.bonus-calculator.calculate` | `view_monthly_budgets` | JSON |
| `/admin/pm-payout-calculator` | `admin.pm-payout-calculator.index` | *(none)* | `Admin/PMPayoutCalculator/Index` |
| `/admin/monthly-budgets` (+ store/update/destroy/all/current/{id}) | `admin.monthly-budgets.*` | `view_monthly_budgets` / `manage_monthly_budgets` | `Admin/MonthlyBudgets/Index` |

APIs: `GET /api/leaderboard/{monthly,stats}`; `GET /api/points-ledger`, `/points-ledger/total`; `GET /api/kudos/{pending,mine}`, `POST /api/kudos`, `POST kudos/{kudo}/{approve,reject}`; `apiResource bonus-configurations`, `apiResource bonus-configuration-groups` + `{id}/duplicate`, `POST projects/{projectId}/{attach,detach}-bonus-configuration-group`.

**Pages/Components** — `Pages/BonusSystem/Index.vue` (861), `Pages/Leaderboard/Index.vue`, `Pages/Ledger/Index.vue`, `Pages/Kudos/Index.vue`, `Pages/Admin/BonusCalculator/Index.vue`, `Pages/Admin/PMPayoutCalculator/Index.vue`, `Pages/Admin/MonthlyBudgets/Index.vue` + `budgetCalculations.js`; `Components/BonusCalculator/`, `Components/Kudos/`, `Components/PMPayoutCalculator.vue`, `Components/Financial/`, `Components/ProjectFinancials/`, `Components/ProjectInvoices/`, `Components/ProjectExpendables/` + `ProjectExpendables.vue`, `Components/ChartComponent.vue`.
`Pages/Admin/ProjectExpendables/Index.vue` (1668) at `/project-expendables` (`permissionInAnyProject:add_expendables`).

**Permissions** — `view_own_points`, `view_monthly_points`, `view_points_ledger`, `manage_points`, `view_monthly_budgets`, `manage_monthly_budgets`, `view_kudos`/`view_own_kudos`/`view_all_kudos`/`create_kudos`/`approve_kudos`, plus the bill/invoice/expendable families. Policies: `BillPolicy`, `InvoicePolicy`, `MonthlyBudgetPolicy`, `KudosPolicy`.

**Liveness** — live and large (the four biggest Vue pages in the app are Transactions 2057, Bills 1873, ProjectExpendables 1668, Invoices 1340). `/bonus_system` is an unnamed duplicate of `/bonus-system` — drop it. Bonus **configuration** page route is commented out (`web.php:815-817`) although the API and `manage_bonus_configuration` permission exist — a partially-dead feature.

**v2 must cover:** financial dashboard + P&L; contractor bills (create/edit/approve/void/delete/restore/attachments, approval flows, contract-limit checks); sales invoices (create/approve/reject/comment/void, notes, Xero sync both directions); bank + project transactions with document linking (bill/invoice), payment processing, attachments, soft-delete/restore, outstanding docs; project services catalogue; expendables & supplier proposals (shortlist/accept/reject/complete); monthly budgets; bonus configurations & groups (per-project attachment, duplication); bonus & PM payout calculators; points ledger, monthly leaderboard, streak calculation; kudos submission/approval; multi-currency with daily FX rates.

---

## K. Xero / Stripe / Airwallex integrations

**Xero** — `Admin\XeroConnectionController`: `GET /admin/xero` (`admin.xero.index` → `Admin/Xero/Index`, 1315 lines), `/xero/connect`, `/xero/status`, `POST /xero/select-tenant`, `DELETE /xero/disconnect`, `GET /xero/bank-accounts`, `POST /xero/bank-accounts/mappings`, `GET /xero/branding-themes`, `POST /xero/default-branding-theme`; OAuth callback at `GET /ozee-xero/callback` (`xero.callback`, **web.php, no auth**).
API: `Api\XeroAccountController` (`xero/accounts` GET/POST, `xero/items` GET/POST), `Api\XeroPaymentServiceController` (`xero/payment-services`, `/refresh`), `Api\XeroReverseSyncController` (`admin/xero/invoices`, `{id}`, `POST .../sync`, `POST admin/xero/contacts/create-client`, `projects/{project}/services-quick-list`, `POST projects/{project}/services/quick-add`).
Webhook: `POST /api/xero/webhook` (`Api\XeroWebhookController@handle`, **no middleware**).
Scheduled: `XeroPaymentSyncJob` daily, `xero:refresh-payment-services` 6h, `xero:sync-invoices` hourly. Config `config/xero.php`. Permission `configure_xero_settings`, `create_project_invoices`.

**Stripe** — `Admin\StripeConfigurationController`: `GET/POST /admin/stripe-configurations`, `PUT/DELETE {id}` → `Admin/StripeConfigurations/Index`. Webhook `POST /api/external/stripe/webhook/{app_id}` (`Api\External\StripeWebhookController`). Services `StripeService`, `StripePayoutService`.

**Airwallex** — **no routes**; service-layer only (`app/Services/AirwallexService.php`, `app/Models/AirwallexXeroBankMapping.php`, referenced from `Api\TransactionsController` and `Admin\XeroConnectionController`).

⚠️ Stray file: `app/Http/Controllers/Api/TransactionsController.php.tmp` — leftover, delete.

**v2 must cover:** Xero OAuth connect/disconnect + tenant selection, bank account ↔ Airwallex mapping, branding themes, account/item catalogue, invoice push and reverse-sync, contact matching/creation from Xero; Stripe configuration per external app + webhook handling; Airwallex bank feed reconciliation.

---

## L. Automations / Workflows / Prompts

| URI | Name | Middleware | Renders |
|---|---|---|---|
| `/automation` | `automation.page` | `permission:manage_projects` | `Automation/Index` |
| `/automations/v2` | `automations.v2` | `permission:manage_projects` | `AutomationsV2/Index` |
| `/prompt` | `prompts.page` | `auth,verified` | `Automation/Prompts/Index` |
| `/admin/prompts` | `admin.prompts.page` | `auth,verified` | `Automation/Prompts/Index` |

**API** — `GET/POST/PUT/DELETE workflows` + `workflows/{workflow}/run`, `workflows/{workflow}/logs` (`Api\WorkflowController`, `Api\WorkflowLogController`) — **declared twice** (`api.php:315-321` explicit and `api.php:927-928` as `apiResource`); `POST workflows/triggers/{event}` (`Api\AutomationTriggerController`); `apiResource prompts` (`Api\PromptController`); `apiResource workflow-steps` (`Api\WorkflowStepController`); `GET /api/automation/schema` (`Api\AutomationSchemaController`); `GET /api/models/available` and `GET projects/{project}/model-data/{shortModelName}`, `GET source-models/{modelName}` (`Api\ModelDataController`).

**Pages** — **Two parallel builders:**
- `Pages/Automation/` — Index + `Components/{AutomationHub,AutomationBuilder,Workflow,WorkflowNode,WorkflowMinimap,VueFlowCanvas,WorkflowLogsModal,VariablePicker,ConfirmModal}` + `Components/Steps/{TriggerStep,TriggerSelectionModal,ActionStep(824),AIStep(599),ConditionStep(512),ForEachStep,FetchRecordsStep,TransformStep,DefineVariableStep,ScheduleTriggerStep,AddStepButton,StepCard,DataTokenInserter,RelatedDataPicker}` + `Components/Configuration/QueryDataConfig` + `Api/automationApi.js` + `Store/workflowStore.js`
- `Pages/AutomationsV2/` — Index + `Components/{Hub,Builder,Canvas,CustomNode,PropertiesSidebar,JsonViewerV2,TokenInputField,DataTokenInserter,WorkflowLogsModalV2}` + `Components/StepConfigs/{TriggerConfig,ActionConfig,AIConfig,ConditionConfig,ForEachConfig,FetchRecordsConfig,TransformConfig,ModelPickerModalV2,StepPickerModalV2,TokenPickerModalV2,RelatedDataPickerV2}` + `Store/storeV2.js` + `index.css`
- Prompts: `Pages/Automation/Prompts/{Index,PromptEditor,PromptForm,FieldBuilder,ResponseBuilder,PromptResponseBuilder}.vue`; also `resources/js/Prompts/` and `Components/Prompts/`, `Components/DataTokenPicker.vue`, `Components/RelationshipPathPicker.vue`, `Components/RepeatableDynamicField.vue`

Both builders hit the same API. Nav (`TopNavigation`/`AdminDropdown`) links only `automation.page` — **`/automations/v2` is not in the menu**, i.e. V2 is an in-progress rewrite reachable only by URL. `workflow_steps.json` (173 KB) sits at the repo root as a schema/fixture. Config `config/automation.php`.

**Liveness** — V1 live, V2 **experimental/duplicate**. Two prompt routes (`/prompt`, `/admin/prompts`) render the same page — an explicitly documented dev-router workaround.

**v2 must cover:** a single workflow builder — trigger selection (model events + schedule + manual `workflows/triggers/{event}`), steps (Action, AI, Condition, ForEach, FetchRecords, Transform, DefineVariable), a data-token/variable system with relationship-path picking against the model schema (`/api/automation/schema`, `/api/models/available`), canvas + minimap, run + logs viewer, prompt library with structured response builders. This is what drives email approval/sending — it is not optional.

---

## M. Email Templates & Placeholder Definitions

| URI | Name | Middleware | Renders |
|---|---|---|---|
| `/email-templates` | `email-templates.page` | `permission:manage_email_templates` | `EmailTemplates/Index` |
| `/email-templates/create` | `email-templates.create` | `permission:manage_email_templates` | `EmailTemplates/Create` ⚠️ **page file does not exist** — only `Pages/EmailTemplates/Index.vue` is present. Broken route. |
| `/placeholder-definitions` | `placeholder-definitions.page` | `permission:manage_placeholder_definitions` | `PlaceholderDefinitions/Index` |

**API** — `apiResource email-templates` + `POST email-templates/{t}/placeholders`, `POST email-templates/{t}/preview` (`Api\EmailTemplateController`); `GET placeholder-definitions/models-and-columns`, `apiResource placeholder-definitions` (`permission:manage_placeholder_definitions`); `GET value-dictionaries`, `GET value-dictionaries/{model}/{field}` (`Api\ValueDictionaryController`, same permission). Config `config/values.php`, `config/value_sets.php`, `config/forms.php`, `config/options.php`; `GET /api/options/{key}` (`Api\OptionsController`). Seeder `EmailTemplateSeeder`.

**Components** — `Components/EmailTemplates/`, `Components/PlaceholderInserter.vue`, `Components/DataTokenPicker.vue`. Concerns: `Api/Concerns/HandlesTemplatedEmails.php`, `HandlesAiTemplatedEmails.php`, `HandlesEmailCreation.php`.

**Liveness** — live, but the create route is broken (missing component).

**v2 must cover:** template CRUD with subject/body editor, placeholder syncing, live preview against a chosen source model; placeholder definitions bound to model+column with allowed-value dictionaries; centralised options endpoint.

---

## N. Presentations & Shareable Resources

| URI | Name | Middleware | Renders |
|---|---|---|---|
| `/presentations` | `presentations.index` | `auth,verified` | `Presentations/PresentationList` |
| `/presentations/{id}/edit` | `presentations.edit` | `auth,verified` | `Presentations/EditorView` (`presentationId`) |
| `/view/{share_token}` | *(unnamed)* | **none (public)** | `Presentations/PublicPresenter` — closure queries `Presentation` with slides + ordered content blocks inline in `web.php:120-128` |
| `/shareable-resources` | `shareable-resources.page` | `permission:view_shareable_resources` | `ShareableResources/Index` |
| `/team-resources` | `team-resources.page` | `permission:view_shareable_resources` | `TeamResources/Index` |

**API** — `prefix v1`: `presentations` CRUD, `{id}/invite`, `{id}/collaborators`, `{presentationId}/slides`, `PUT slides/{id}`, `POST slides/reorder`, `DELETE slides/{id}`, `slides/{slideId}/content_blocks`, `PUT/DELETE content_blocks/{id}`, `POST content_blocks/reorder`, `templates`, `{id}/duplicate`, `{id}/save-as-template`, `{targetId}/copy-slides` — **and the same four template/duplication routes again un-versioned** (`api.php:905-908`). AI: `POST /api/presentations/{presentation}/generate-slide`, `/create-slide-from-ai` (`Api\PresentationAIController`), `POST /api/presentations/generate` (`Api\PresentationGeneratorController`, "Surprise Me").
Shareable resources: `apiResource shareable-resources` (`process.tags`), `POST shareable-resources/{resource}/copy-to-project` (`Api\ShareableResourceCopyController`), `GET /debug/list-drive-files` (**public debug route — remove**).
Components API: `GET/POST/PUT/DELETE /api/components/*` (`Api\ComponentController`, config `config/components.php`, `ComponentSeeder`).

**Pages** — `Pages/Presentations/{PresentationList,EditorView,PublicPresenter,SlideManager,SlideEditor,SlidePreview,SlideSummaryModal,PresentationForm,ContentBlockForm,CreationChoiceModal,TemplateBrowser}.vue` + `Components/{Toolbar,BlockToolbox,TiptapEditor,SlideThumbnail,ZoomControls,IconPicker,Modal,ShareModal,CollaborateModal,TemplateSelector,AsyncSearchDropdown}.vue`; store `resources/js/Stores/presentationStore.js`; service `Services/presentationsApi.js`. Duplicated top-level copies of several of these exist in `resources/js/Components/` (`SlideThumbnail.vue`, `BlockToolbox.vue`, `Toolbar.vue`, `ZoomControls.vue`, `IconPicker.vue`, `TiptapEditor.vue`, `TemplateSelector.vue`, `PresentationCard.vue`) — resolve the duplication in v2.
`Pages/ShareableResources/Index.vue`, `Pages/TeamResources/Index.vue` (387), `Components/ShareableResource/`, `Components/ResourceModal.vue`.

Config: `config/presentation_templates.php`; seeders `PresentationSeeder`, `IconSeeder`, `ComponentSeeder`. Permission `create_presentation`, `view_shareable_resources`.

**Liveness** — live. `PublicPresenter` is the public lead-generation surface (feeds `POST /api/public/lead-intake`). `TeamResources` vs `ShareableResources` share one permission and look like near-duplicates worth merging.

**v2 must cover:** presentation list, slide/content-block editor with reorder + drag, templates + duplication + copy-slides + save-as-template, collaborators & invites, public share-token viewer with lead capture, AI slide generation and full-deck generation; shareable/team resource library with tags and copy-to-project (Google Drive backed).

---

## O. Users, Roles, Permissions, Admin & Settings

### O.1 Users

| URI | Name | Middleware | Renders |
|---|---|---|---|
| `/users` | `users.page` | `permission:create_users` | `Users/Index` |
| `/users/{id}` | `users.show` | `permission:create_users` | `Users/Show` |

API: `apiResource users` (`api.users`); `POST users/{user}/restore`, `/generate-api-key`, `/generate-telegram-code`, `/xero-contact-sync`, `/xero-contact-create`; `GET users/{user}/{xero-contact-candidates,latest-payment-details,emails,metadata,notes}`; `POST users/{user}/{metadata,notes}`; `GET/POST /api/user-metadata-keys` (`Api\UserWidgetController`). Pages: `Pages/Users/{Index(995),Show}.vue` + `Components/{UserMetadataWidget,UserNotesWidget}.vue`. Policy `UserPolicy`. ⚠️ Both user pages are gated by `create_users` rather than `view_users` — a real mismatch with `UserPolicy::viewAny`.

### O.2 Roles & Permissions (all `permission:manage_roles`, `prefix admin`)

`/admin/roles` (`admin.roles.index` → `Admin/Roles/Index`), `/admin/roles/compare` (`Admin/Roles/Compare`), `/admin/roles/create` (`Admin/Roles/Create`), `/admin/roles/{role}/duplicate` (`Admin/Roles/Create` prefilled), `/admin/roles/{role}` (`Admin/Roles/Show`), `/admin/roles/{role}/edit` (`Admin/Roles/Edit`), `/admin/roles/{role}/permissions` (`Admin/Roles/Permissions`), `POST/PUT/DELETE /admin/roles*` → `Api\RoleController`, `POST /admin/roles/{role}/permissions` → `@updatePermissions`, `POST /admin/roles/revoke-user` → `Admin\RoleController@revokeUser`, `POST /admin/permissions/revoke-user` → `Admin\PermissionController@revokeUser`.
`/admin/permissions` (`Admin/Permissions/Index`), `/create` (`Create`), `/bulk-create` (`BulkCreate`), `/{permission}/edit` (`Edit`), `POST /admin/permissions`, `POST /admin/permissions/bulk`, `PUT/DELETE /admin/permissions/{permission}` → `Admin\PermissionController`.

⚠️ **All of these page routes are closures containing raw Eloquent + a raw `DB::table` join in `routes/web.php:416-585`** — ~170 lines of query logic living in the route file. v2 must move this into controllers.

API: `GET /api/permissions` (`permission:view_permissions`), `GET /api/user/permissions`, `GET /api/projects/{project}/permissions` (`Api\PermissionController`); `apiResource roles` (`permission:manage_roles`), `POST roles/{role}/permissions` (`permission:manage_permissions`).

### O.3 Other admin/settings pages

| URI | Name | Middleware | Renders |
|---|---|---|---|
| `/admin/project-tiers` | `admin.project-tiers.index` | `permission:view_project_tiers` | `Admin/ProjectTiers/Index` |
| `/admin/categories` | `admin.categories.index` | *(none)* | `Admin/Categories/Index` |
| `/admin/media-files` | `admin.media-files.index` | `permission:manage_projects` | `Admin/MediaFiles/Index` |
| `/admin/live-status` (+ `/{user}/logs`) | `admin.live-status.*` | *(none beyond auth)* | `Admin/LiveStatus/Index` |
| `/admin/external-tokens` (+ store/update/destroy) | `admin.external-tokens.*` | *(none)* | `Admin/ExternalTokens/Index` |
| `/admin/email-apps` (+ CRUD, `/{id}/logs`) | `admin.email-apps.*` | *(none)* | `Admin/EmailApps/Index` |
| `/admin/stripe-configurations` (+ CRUD) | `admin.stripe-configurations.*` | *(none)* | `Admin/StripeConfigurations/Index` |
| `/admin/xero` (+ connect/status/etc.) | `admin.xero.*` | *(none)* | `Admin/Xero/Index` |
| `/admin/approval-flows` (resource, except show) | `admin.approval-flows.*` | `permission:manage_projects` | `Admin/ApprovalFlows/{Index,CreateEdit}` |
| `/admin/vault-credentials` | `admin.vault-credentials.index` | `permission:view_all_credentials` | `Admin/VaultCredentials/Index` |

⚠️ **A large set of admin routes in `routes/admin.php` carry no permission middleware at all** (live-status, external tokens, email apps, Stripe configs, Xero) — only `auth,verified`. That is a genuine authorization gap the v2 must close.

**Categories/tags API**: `GET/POST/PUT/DELETE /api/category-sets`, `GET category-sets/{set}/categories`, `POST/PUT/PATCH categories` (`process.tags`), `DELETE categories/{category}` (`Api\CategorySetController`, `Api\CategoryController`). See `/home/claude/src/Category.md`.

**Vault (credentials manager)** — `Admin\VaultController`: `GET admin/vault-credentials/stats`, `admin/vault-credentials`, `clients/{client}/vault`, `projects/{project}/vault-credentials` GET/POST, `POST vault/unlock`, `PUT vault/{credential}`, `GET vault/{credential}/{logs,shared-users}`, `POST vault/{credential}/share`, `DELETE vault/{credential}/share/{user}`, `DELETE vault/{credential}`. Chrome-extension side: `GET /api/extension/vault/labels`, `POST /api/extension/vault/resolve` (`Api\ExtensionVaultController`, `auth.apikey`). Client side: `Client\VaultController` under `client-api`. Permissions `view_all_credentials`, `edit_credential`. Pages: `Admin/VaultCredentials/Index.vue` (728), `Components/ProjectVaultCredentialsTab.vue`, `ClientDashboard/VaultSection.vue`.

**Chrome extension** — `GET /api/extension/version`, `/api/extension/download` (public); `by_pass_extension` permission (`ExtensionBypassPermissionSeeder`); `Composables/useExtensionStatus.js`; `chrome_extension_link` shared via Inertia.

**Seeders defining the access model** — `RolePermissionSeeder`, `UserSeeder`, `FinancialPermissionSeeder`, `PointsSystemPermissionsSeeder`, `CeoDashboardPermissionSeeder`, `AddEditCredentialPermissionSeeder`, `ExtensionBypassPermissionSeeder`.

**v2 must cover:** user CRUD + detail with metadata widgets, notes, API key generation, Telegram code, Xero contact linking, payment details, restore; role CRUD with application/project types, permission matrix editing, role comparison (up to 3), duplication, and per-user role/permission revocation; permission CRUD incl. bulk create and category grouping; project tiers; categories & category sets; media file browser with bulk delete and signed view URLs; live user status + activity logs; external magic-link tokens (with whitelist, max uses); email app (mailbox) configuration + logs; Stripe configs; Xero connection; approval flows; credential vault with unlock, per-user sharing, audit logs and Chrome-extension resolution.

---

## P. Schedules, Availability, Attendance, Live Status

| URI | Name | Middleware | Renders |
|---|---|---|---|
| `/schedules` | `schedules.index` | `auth,verified` | `Schedules/Index` |
| `/schedules/create` | `schedules.create` | `auth,verified` | `Schedules/Create` |
| `POST /schedules` | `schedules.store` | `auth,verified` | — |
| `/schedules/{schedule}/edit` | `schedules.edit` | `auth,verified` | `Schedules/Edit` |
| `PUT /schedules/{schedule}` | `schedules.update` | `auth,verified` | — |
| `PATCH /schedules/{schedule}/toggle` | `schedules.toggle` | `auth,verified` | — |
| `DELETE /schedules/{schedule}` | `schedules.destroy` | `auth,verified` | — |
| `/availability` | `availability.index` | `permission:create_users` | `Availability/Index` |
| `/attendance` | `attendance.index` | `auth,verified` | `Attendance/Index` |
| `/admin/live-status` | `admin.live-status.index` | `auth,verified` | `Admin/LiveStatus/Index` |

API: `apiResource availabilities` + `availabilities/reason-options`, `POST availabilities/batch`, `GET weekly-availabilities`, `GET availability-prompt` (`Api\AvailabilityController`); `GET /api/me/attendance` (`Api\UserAttendanceController`); `POST /api/schedules` (`Api\ScheduleApiController@store`, polymorphic schedule attachment, `api.schedules.store`); `POST /api/activityData` (`Api\ActivityDataController@store`, `auth.apikey` — desktop tracker ingest), `PATCH /api/activities/{id}/category`.
Controller concern: `app/Http/Controllers/Concerns/HandlesSchedules.php`.
Pages/Components: `Pages/Schedules/{Index,Create,Edit}.vue`, `Pages/Availability/Index.vue`, `Pages/Attendance/Index.vue`, `Pages/Admin/LiveStatus/Index.vue` (680), `Components/Availability/`, `Components/Scheduler/`, `Composables/useEmbeddedScheduler.js`, `Components/TimezoneSelect.vue`.
Scheduler command `app:run-scheduler` runs every minute; `auth:cleanup-client-data` hourly. Permission `view_users_availability`, `create_schedules`. ⚠️ `/availability` is gated by `create_users`, which is semantically wrong.

**Liveness** — live. Availability has a very long fix history in `readmes/` (10+ documents) — treat as a known problem area to redesign rather than port.

**v2 must cover:** recurring/one-off schedule CRUD with enable/disable toggle and polymorphic attachment to other records; weekly availability calendar with reason codes, batch edit, and the "should I prompt you" nudge; attendance records; live user status board fed by the desktop tracker's `activityData` ingest, with per-user log drill-down.

---

## Q. Reports & Analytics

| URI | Name | Middleware | Renders |
|---|---|---|---|
| `/admin/productivity` | `admin.productivity.index` | `permission:manage_projects` | `Admin/Productivity/Index` (1060) |
| `/admin/productivity-projects` | `admin.productivity-projects.index` | `permission:manage_projects` | `Admin/Productivity/ProjectIndex` (852) |
| `/admin/project-time-cost` | `admin.project-time-cost.index` | `permission:manage_projects` | `Admin/Reports/ProjectTimeCost` |
| `/admin/api/project-time-cost` | `admin.api.project-time-cost.fetch` | `permission:manage_projects` | JSON |
| `/admin/activity-report` | `admin.activity-report.index` | `permission:manage_projects` | `Admin/Activity/Index` (612) |
| `/admin/cto-report` | `admin.cto-report.index` | `permission:manage_projects` | `Admin/Reports/CtoReport` |
| `/admin/api/cto-report` | `admin.api.cto-report.fetch` | `permission:manage_projects` | JSON |

API (all `permission:manage_projects` unless noted): `GET productivity/report`, `productivity/project-report`, `productivity/projects/{project}/messages`, `productivity/snapshots` GET/POST/`{id}` GET/DELETE/`{id}/feedback`, `GET activity-report`, `GET /api/activities` (`Api\ActivityController`, no permission), `POST tasks/{task}/productivity-meta`, `POST tasks/manual-effort`; personal: `GET productivity/yesterday-report`, `POST productivity/yesterday-feedback` (no permission); standups: `GET /api/standups/analytics/{filters,matrix,feed,stats}` (`Api\StandupAnalyticsController`, no permission).
Components: `Components/Productivity/`, `Components/ChartComponent.vue`, `Components/DailyStandups/`. Config `config/activity_categories.php`, `config/activitylog.php`, `config/pulse.php`.

**Liveness** — live. Standup analytics has API endpoints but **no dedicated page** — surfaced inside `Workspace/TeamPulseDashboard` and project standups.

**v2 must cover:** per-user productivity report + snapshots with manager feedback; per-project productivity + message volume; project time & cost; activity report from tracker data with category re-assignment; CTO availability+productivity report; standup compliance matrix/feed/stats; yesterday-report self-review loop; manual effort entry.

---

## R. Chat (project chat + Telegram + Google Chat)

**API (`auth:sanctum`)** — `GET/POST projects/{project}/chat`, `POST .../chat/attachments`, `POST .../chat/drive-documents/create`, `POST .../chat/drive-documents/reference`, `GET .../chat/drive-documents/browse`, `DELETE projects/{project}/chat/{chat_message}`, `POST .../chat/mark-read`, `GET chat/unread-counts` (`Api\ChatController`).
**Telegram** — `POST /api/telegram/wh` (webhook, **no auth**), `/api/telegram/test-telegram`, `/api/telegram/test-topic`, `/api/telegram/message-thread` (`Api\TelegramWebhookController`, **all unauthenticated**); topics `GET/POST projects/{project}/topics` (`Api\TelegramTopicController`); linking codes for projects/users/clients.
**Google Chat** — `prefix user/google-chat`: `check-credentials`, `POST spaces`, `POST spaces/members`, `POST messages`, `POST standups`, `POST notes` (`GoogleChatUserController`); `AuthenticateGoogleChat` middleware exists but is **not attached to any route**.
**Broadcast** — `project.{projectId}`, `topic.{topicId}` private channels.
**Components** — `Components/CommunicationSidebar.vue`, `Components/TelegramAccount/`, `Components/GoogleAccount/`, `Profile/Partials/TelegramIntegrationForm.vue`, `Components/LeftSidebar.vue` (project chat list, 414 lines). Nav item "Team Chat" in `TopNavigation.vue`.

**Dead code** — `app/Http/Controllers/ChatController.php` (root namespace) and `app/Http/Controllers/TelegramWebhookController.php` (root namespace) are **unreferenced duplicates** of the `Api\*` versions. The root `TelegramWebhookController` additionally has a block of obviously hallucinated imports (`Telegram\Bot\Objects\Sticker\StickerIdGeneratorTraitTraitTraitInterface` etc.) — delete both files.

**v2 must cover:** per-project realtime chat over Reverb with attachments, Google Drive document creation/reference/browse, unread counts and read markers, message deletion; Telegram topic-per-project bridging with account linking codes for staff, clients and projects; Google Chat spaces creation, membership, standup and note posting.

---

## S. Native app / external / extension APIs & webhooks

| Group | Middleware | Endpoints |
|---|---|---|
| `prefix native-app` | `auth:sanctum` | `projects`, `projects/{project}/topics` GET/POST, full chat set (`indexNative`), `users`, `projects/{project}/users` (`indexSimplified`, `projectUsersSimplified`) |
| `auth.apikey` (Chrome extension / desktop tracker) | API key header | `POST activityData`, `POST presence/status`, `GET extension/vault/labels`, `POST extension/vault/resolve`, and the whole `Api\ExternalApiController` set: `activity/projects`, `activity/projects/{project}/tasks`, `activity/tasks`, `activity/tasks/activeTask`, `POST activity/tasks/quick`, `activity/tasks/{task}`, `POST activity/tasks/{task}/status`, `activity/tasks/{task}/notes` GET/POST, `activity/tasks/{task}/time` |
| `prefix external` | `auth.magiclink.external` | `POST email/send` (`External\ExternalEmailController`); `External\ExternalPaymentController`: `POST payment/create-session`, `create-price`, `update-configuration`, `GET payment/status/{activityId}`, `payment/subscriptions/{appId}`, `POST payment/cancel-subscription`, `GET activities/{appId}` |
| Webhooks (no auth) | — | `POST /api/telegram/wh`, `POST /api/xero/webhook`, `POST /api/external/stripe/webhook/{app_id}`, `POST /api/public/lead-intake`, `POST /api/public/lead/{firefly}` (API key), `POST /api/famifyhub/contact`, `/contactform`, `POST /api/bugs/report`, `GET /api/bugs`, `GET /api/bugs/status` |

Docs: `config/scribe.php`, `readmes/running-api-documentation.md`, `readmes/api-token-authentication-documentation.md`, `readmes/native-app-chat-drive-attachments-apis.md`.

**v2 must cover:** these are external contracts — the native desktop app, the Chrome extension and third-party integrator apps depend on exact shapes. Version them explicitly rather than reshaping.

---

## 3. Dead / duplicated / experimental — consolidated kill list

**Definitely dead (no route, no reference):**
- `resources/js/Pages/Clients/Dashboard.vue`
- `resources/js/Pages/PrivacyPolicy.vue` (route serves a Blade view)
- `resources/js/Pages/Wireframe.vue` (593 lines)
- `resources/js/src/Components/Notification.vue` (stray one-file `src/` tree)
- `resources/js/Pages/Emails/Inbox/Components/Archived/*` (4 files, self-labelled)
- `app/Http/Controllers/ChatController.php` (root namespace duplicate)
- `app/Http/Controllers/TelegramWebhookController.php` (root namespace duplicate, hallucinated imports)
- `app/Http/Controllers/Api/ProjectCalendarController.php`
- `app/Http/Controllers/Api/ProjectSectionController.php`
- `app/Http/Controllers/Api/ClientDeliverableInteractionController.php`
- `app/Http/Controllers/Api/DeliverableCommentController.php`
- `app/Http/Controllers/Api/TransactionsController.php.tmp`
- `routes/project_tiers.php` (never `require`d)
- `resources/js/Pages/Auth/Register.vue` + `Auth\RegisteredUserController` (registration disabled)
- `resources/js/archive/` (empty directory)

**Broken:**
- `GET /email-templates/create` → renders `EmailTemplates/Create`, **file missing**
- `POST /api/projects/{project}/detach-clients` → method name `'detach-clients'` is not a valid PHP method

**Duplicated implementations to collapse:**
- `/inbox` (Vue) vs `/inbox/beta` (React) — the big one
- `Pages/Automation/` (V1, live, in nav) vs `Pages/AutomationsV2/` (not in nav)
- React Portal (`/portal`, `/projects/public`) vs classic Vue (`/projects/classic`, `Pages/Public/ProjectView.vue`)
- Client Dashboard (magic link, Vue) vs Supplier Portal (OTP, React) — two separate external-user systems
- `Api\ProjectDeliverableController` vs `Api\ProjectDashboard\ProjectDeliverableAction`
- Bills/invoices route blocks declared twice in `api.php` (second invoice block **unprotected**)
- Workflows declared twice in `api.php`
- Presentation template/duplication routes declared twice (`v1` + un-versioned)
- Project-tier + monthly-budget routes in `web.php` **and** `admin.php` (**and** `project_tiers.php`)
- `/bonus_system` (unnamed) vs `/bonus-system`
- `/prompt` vs `/admin/prompts`
- `Pages/ShareableResources/Index.vue` vs `Pages/TeamResources/Index.vue` (same permission)
- Presentation sub-components duplicated between `Pages/Presentations/Components/` and `Components/`

**Experimental / debug (remove before or during v2):**
- `/react-test` + `ReactPages/TestPage.jsx`
- `/test/form-modal` + `Pages/Test/FormModalTest.vue` + `POST /api/test-form`
- `/test/user-project-role`, `/test-google-auth`, `/receive-test-emails`, `/debug/list-drive-files`
- `/api/playground` (GET+POST), `/api/test-reverb`, `/api/test-email-with-config`
- `/api/telegram/test-telegram`, `/api/telegram/test-topic`, `/api/telegram/message-thread` (unauthenticated)

**Security gaps worth fixing rather than porting:**
- `routes/admin.php`: live-status, external-tokens, email-apps, stripe-configurations, xero — **no permission middleware**
- `api.php:758-766`: second invoice block with no permission middleware
- `CheckPermission`: `roles.index` + `?type=` bypass
- `/users`, `/users/{id}` gated by `create_users` instead of `view_users`; `/availability` gated by `create_users`
- `/admin/financials` dashboard and `/admin/financials/project-services` have no permission middleware
- `HandleInertiaRequests` computes but never shares permissions (frontend must round-trip)

---

## 4. Cross-cutting concerns a v2 must design for up front

1. **Permission delivery** — ship `permissions` (global + per-project) in Inertia shared props instead of the current `/api/user/permissions` fetch-after-mount; the current pattern causes a visible flash of unauthorized UI and is why `v-permission` and `usePermissions` had to be reimplemented twice.
2. **Route-file logic** — ~250 lines of Eloquent/`DB::table` queries and hardcoded option arrays live in `routes/web.php`. All of it belongs in controllers.
3. **Naming a single canonical external-user model** — today there are three (`client` via magic link, `guest`/supplier via portal OTP, `user_type` guest via login). `EnsureNotGuest`, `EnsurePortalUser`, `VerifyMagicLinkToken`, `VerifyExternalMagicLink` and `ThrottleClientAuth` are five separate auth paths.
4. **Encryption** — project notes and vault credentials are encrypted at rest (many `readmes/*-encryption-*` docs, `readmes/fix-note-encryption.php`); carry the scheme forward deliberately.
5. **Realtime** — Reverb (`config/reverb.php`, `config/broadcasting.php`) for notifications and project/topic chat.
6. **Polymorphism** — notes (`ProjectNote`), file attachments (`FileAttachment`), schedules and tags are all polymorphic across Task/Project/Email/Deliverable. A v2 module boundary that ignores this will fragment them.
7. **Docs** — `/home/claude/src/readmes/` holds **284** incident/fix markdown files; `/home/claude/src/docs/`, `Redesign/`, `WARP.md`, `work.md`, `compact.md`, `Category.md`, `client-authorization-changes.md`, and the 173 KB `workflow_steps.json` are additional context for the automation and permission subsystems.