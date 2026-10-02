# 03 — Module specifications (R1)

Format per module: Entities → Actions → HTTP (web pages + API v1) → Policies/permissions → Events → Jobs/commands → Tests that must exist. "Legacy ref" points to survey files.

---

## 3.1 Platform

**Entities:** Setting, Sequence, WebhookEvent, ExchangeRate, Taxonomy, Term, TermAssignment, Comment, CommentMention, Attachment, ApprovalRequest, ApprovalFlow(+Step).

**Traits (modules/Platform/Support/Traits):** `HasComments` (`comments()` morphMany, `projectIdForComments(): ?int`), `HasAttachments`, `HasTerms(taxonomySlug)`, `HasApprovals`, `HasPublicId` (ULID on creating), `Searchable` (`search_text` rebuild on saved).

**Actions:** `AddComment(subject, author, body, kind, visibility, parentId?, anchor?)` → parses `@mentions` (users by name/email), stores mentions, dispatches `CommentAdded`. `EditComment`, `DeleteComment`. `StoreAttachment(subject|null, UploadedFile, purpose, uploadedBy)` → validates mime/size per purpose (`config/platform.attachments.php`), stores to disk with prefix, creates thumbnail for images (Intervention Image; webp allowed, svg rejected), dispatches `AttachmentStored`. `AttachPendingAttachments(subject, publicIds[])` (composer flow). `DeleteAttachment` (sole deleter: removes object + row). `RequestApproval(subject, kind, requestedBy, flowId?)` → creates pending request (one open per subject+kind; enforced). `DecideApproval(request, decider, decision, reason?)` → transitions, dispatches `ApprovalDecided(request)`. `AssignTerms(subject, taxonomySlug, termIds|names)` → creates missing terms for `tags` only. `ConvertMoney`.

**HTTP API v1:** `GET/POST /comments?subject_type&subject_id`, `PATCH/DELETE /comments/{id}`; `POST /attachments` (multipart, returns `{public_id, url}`), `DELETE /attachments/{public_id}`, `GET /attachments/{public_id}/download` (signed temp URL redirect); `GET /terms?taxonomy=`; `POST /approvals/{id}/decide`; `GET /search?q=` (global search, permission-aware); `GET /notifications`, `POST /notifications/read-all`, `POST /notifications/{id}/read`.

**Commands:** `attachments:prune` (daily 03:15), `rates:fetch` (daily; exchangeratesapi.io, base USD), `webhooks:prune` (30 days), `search:rebuild`.

**Tests:** comment on every commentable subject via policy; attachment size/mime rejection; approval single-open invariant; taxonomy assignment idempotent; money conversion uses the rate `as_of` the given date (falls back to latest earlier).

---

## 3.2 Identity

**Entities:** User, UserProfile, Role, Permission, ApiKey, RememberedDevice, GoogleAccount.

**Login flow (copy legacy behaviour, survey/services.md §8):** `POST /login` (email+password) → if `remembered_devices` cookie valid → session; else create `one_time_codes(purpose=staff_login)` (6 digits, 10 min, 5 attempts), send `StaffLoginCodeMail` **synchronously** (legacy lesson: queued OTP mail arrived late), return Inertia to `/login/verify`. `POST /login/verify` (code, remember_device bool) → session + optional device cookie (64-byte token, sha256 at rest, 30 days sliding, max 5 devices, HttpOnly/Secure/Lax). `POST /logout` clears session (device stays unless "sign out everywhere"). Rate limits per 01-architecture §11. Password reset = Laravel Breeze flow. Email verification retained.

**Permissions config (`modules/Identity/Config/permissions.php`)** — groups & slugs (R1 set; deferred groups omitted):
- dashboard: `dashboard.view`, `reports.view`
- users: `users.view`, `users.manage`, `roles.manage`, `permissions.manage`
- contacts: `contacts.view`, `contacts.manage`, `contacts.view_financial`, `contacts.view_private`, `leads.manage`, `campaigns.manage`
- projects: `projects.view` (assigned), `projects.view_all`, `projects.create`, `projects.edit`, `projects.archive`, `projects.delete`, `projects.manage_members`, `projects.manage_contacts`, `projects.view_financial`, `projects.manage_financial`
- work: `tasks.view`, `tasks.manage`, `tasks.assign`, `milestones.manage`, `milestones.approve`, `scope.manage`, `deliverables.manage`, `standups.view_all`, `meetings.manage`
- comms: `inbox.view`, `inbox.view_all_projects`, `inbox.view_private`, `inbox.compose_template`, `inbox.compose_custom`, `inbox.address_manually`, `inbox.approve`, `inbox.approve_all`, `inbox.screen_inbound`, `inbox.delete`, `inbox.mark_private`, `templates.manage`, `mailboxes.manage`
- finance: `invoices.view`, `invoices.manage`, `invoices.approve`, `invoices.void`, `bills.view`, `bills.manage`, `bills.approve`, `bills.void`, `payments.manage`, `ledger.view`, `ledger.manage`, `services.manage`, `catalogue.manage`, `budgets.manage`, `proposals.view`, `proposals.decide`, `xero.manage`, `stripe.manage`, `finance.dashboard`
- access: `access_links.manage`, `vault.view_all`, `vault.edit`, `settings.manage`, `admin.view`
Roles seeded (global): `super-admin` (all), `manager`, `employee`, `contractor`; (project): `project-manager`, `project-member`, `project-viewer` with grant matrices in the same config. `PermissionResolver::global(user)` and `::project(user, project)` = global ∪ project-role permissions (legacy fallback-to-global semantics kept). Middleware `permission:{slug}` resolves project from route param `project`.

**HTTP web:** `/login`, `/login/verify`, `/logout`, `/forgot-password`, `/reset-password/{token}`, `/profile` (edit name/timezone/avatar/password/preferences, Google connect/disconnect, API keys list/create/revoke), `/admin/users` (index/create/edit, deactivate, roles), `/admin/roles` (index/create/edit + permission matrix + compare), `/admin/settings`.
**API v1:** `POST /auth/token` (Sanctum, for desktop app), `GET /me`, `GET /me/permissions`, `POST /api-keys`.

**Events:** `UserCreated`, `UserRoleChanged` (→ permission cache flush), `UserDeactivated`.
**Tests:** OTP happy/expired/too-many; remembered device restores session; permission matrix resolution incl. project override; super-admin bypass.

---

## 3.3 Crm

**Entities:** Contact, Campaign, Enquiry.
**Actions:** `CreateContact`, `UpdateContact`, `ConvertLeadToClient(contact)` (stage=client, converted_at, keeps id; dispatches `ContactConverted`), `MarkLeadLost`, `MergeContacts(primary, duplicate)` (repoints threads/projects/comments; audit), `CreateEnquiry`, `ConvertEnquiry(to: task|milestone|project_service)`, `SetContactPortalPin`.
**Queries:** `ContactListQuery(filters: stage, pipeline_status, owner, source, q, has_projects)`, `LeadPipelineQuery` (grouped by pipeline_status for kanban), `ContactDetailQuery` (projects, open threads count, last activity, invoices summary if permitted).
**HTTP web:** `/contacts` (Client Board — grouped table per design "Client Board"), `/contacts/{id}` (tabs: Overview, Projects, Mail (Comms provides `ThreadsForContact` via contract), Invoices (Finance contract), Vault (Access contract), Notes), `/leads` (pipeline kanban + list), `/campaigns`.
**API v1:** `apiResource contacts`, `POST contacts/{id}/convert`, `POST contacts/{id}/merge`, `GET contacts/search?q` (picker), `apiResource campaigns`, `POST campaigns/{id}/contacts`, `apiResource enquiries`, `POST enquiries/{id}/convert`; public intake `POST /api/v1/public/leads` (api-key middleware, honeypot, throttle 10/min).
**Policies:** ContactPolicy (view: contacts.view OR member of a project of that contact — legacy `client-authorization-changes.md` rule), CampaignPolicy.
**Events:** `ContactCreated`, `ContactConverted`, `ContactMerged`.
**Tests:** conversion keeps id and threads; merge repoints all FKs; project-member can view contact without `contacts.view`; email hidden without `contacts.view_private`.

---

## 3.4 Work

**Entities:** Project, ProjectMember, ProjectContact, Milestone, TaskType, Task, TaskTimeEntry, DailyPlanItem, PersonalChecklistItem, ScopeItem, Deliverable, DeliverableReview, Standup, Meeting, MeetingAttendee.

**Actions (each = one class):** Projects: `CreateProject` (creates support milestone `is_support`, adds creator as project-manager, primary contact row), `UpdateProject`, `ArchiveProject`, `RestoreProject`, `DeleteProject` (soft), `AddProjectMember/RemoveProjectMember/ChangeMemberRole`, `AttachProjectContact/DetachProjectContact`, `UploadProjectLogo`. Milestones: `CreateMilestone`, `UpdateMilestone`, `ReorderMilestones`, `StartMilestone`, `SubmitMilestone` (→ RequestApproval kind complete_milestone), `ApproveMilestone`/`RejectMilestone` (via ApprovalDecided listener), `CompleteMilestone`, `CancelMilestone`, `UpdateMilestoneDueDate(reason)` (system comment). Tasks: `CreateTask` (defaults: type General, project's support milestone when none), `QuickCreateTask(title, projectId)`, `BulkCreateTasks`, `UpdateTask`, `AssignTask`, `StartTask` (opens time entry; **pauses other in-progress tasks of the same user** — legacy rule, now storable), `PauseTask`, `ResumeTask`, `BlockTask(reason)`, `UnblockTask`, `SubmitTaskForReview`, `CompleteTask` (closes time entry, dispatches `TaskCompleted`), `ReopenTask`, `ArchiveTask`, `ReorderTasks`, `AddManualTime`. Plan: `PlanTaskForDay`, `ReorderDailyPlan`, `CarryOverPlanItem`, `CompletePlanItem`. Scope: CRUD + `ToggleChecklistItem`. Deliverables: `SubmitDeliverable` (creates reviews for project contacts, RequestApproval kind client_review, sends `DeliverableReadyMail` via template), `ReviewDeliverable(contact, decision, feedback)` (updates review; when all primary contacts approve → approved), `NewDeliverableVersion`. Standups: `SubmitStandup` (dedupe per user/project/day; `is_late` after 11:00 local). Meetings: `CreateMeeting` (Google Calendar event via Integrations; attendees notified), `DeleteMeeting`.

**Queries:** `ProjectListQuery` (filters status/client/manager/q; counts of open tasks, next milestone, unread threads via Comms contract), `ProjectOverviewQuery` (stats tiles per design: tasks open, milestones, awaiting approval, billed — finance via contract), `ProjectTasksQuery` (list/kanban/grouped by milestone), `WorkspaceQuery` (Home A "Today": needs-you queue = tasks due/overdue + approvals pending + threads needing reply (contract) + today's plan; Home B stats), `TeamWorkloadQuery`, `TaskDetailQuery`, `ProjectStandupsQuery`, `DeliverableReviewQueueQuery`.

**HTTP web:** `/` → `/home` (Home A default; `?view=glance` Home B — ship both, setting `home.variant` chooses, since the guide marks them "Option"), `/projects` (index), `/projects/create`, `/projects/{id}` (tabs: Overview, Tasks, Milestones, Scope, Deliverables, Mail, Money, Documents, Standups, Meetings, Team, Settings — tabs from other modules are injected via `ProjectTabProvider` contract), `/projects/{id}/tasks/{task}` (task detail as side panel route), `/tasks` (My tasks), `/tasks/board`, `/admin/task-types`.
**API v1 (used by pages + extension + desktop tracker):** `apiResource projects` (+ `archive`, `restore`, `members`, `contacts`, `logo`), `apiResource milestones` (+ `start|submit|approve|reject|complete|cancel|due-date|reorder`), `apiResource tasks` (+ `quick`, `bulk`, `start|pause|resume|block|unblock|review|complete|reopen|archive|reorder`, `time` GET/POST), `GET tasks/active` (extension), `GET tasks/assigned`, `GET projects/{id}/tasks/due`, `plan` (GET by date, POST, PATCH reorder, POST carry-over), `checklist` CRUD, `scope-items` CRUD, `deliverables` (+ `submit`, `version`), `standups` (GET/POST), `meetings` (GET/POST/DELETE), `GET workspace/summary`.
**Policies:** ProjectPolicy (view: member OR projects.view_all; edit: projects.edit global or project-manager role), TaskPolicy (member of project; assignee may transition own task), MilestonePolicy (approve requires milestones.approve), DeliverablePolicy, StandupPolicy, MeetingPolicy.
**Events:** `ProjectCreated`, `ProjectArchived`, `MilestoneSubmitted`, `MilestoneApproved`, `TaskCreated`, `TaskAssigned` (→ notification), `TaskStatusChanged`, `TaskCompleted`, `DeliverableSubmitted`, `DeliverableReviewed`, `StandupSubmitted`, `MeetingCreated`.
**Listeners:** `ApprovalDecided` → milestone approve/reject, deliverable status; `TaskAssigned` → `TaskAssignedNotification` (database+broadcast+mail opt-in).
**Tests:** start-task pauses siblings; time entries sum; milestone approval flow; deliverable approval requires all primary contacts; project view policy for member vs non-member; workspace query returns ordered "needs you" list.

---

## 3.5 Comms (mail)

**Entities:** Mailbox, Thread, Message, MessageRecipient, MessageDraft, AiReview, EmailTemplate, ThreadRead, MessageEvent.

**Contracts (Integrations):** `MailProvider { listNewMessages(mailbox, since): iterable; getMessage(id); send(OutgoingMail): SendResult{providerMessageId, providerThreadId}; trash(providerMessageId) }` — `GmailProvider` implements (port legacy `GmailService` MIME builder: multipart/mixed > related > html, CID inline images, header sanitisation; `SmtpProvider` via Laravel mailer for SMTP mailboxes).

**Message status machine (`MessageStatus`)** — outbound: `draft` → `saved` (explicitly saved, no thread yet) | `submitted` (user clicked Send/Submit) → `ai_checking` (if feature ai.check_outbound) → `approved` (AI ok AND `mail.auto_send_when_approved`) or `pending_approval` (AI held, or AI off, or author lacks self-approve) → `approved` (human) → `scheduled` (if scheduled_for) → `sending` → `sent` | `failed`; `returned` (rejected by approver with reason; author can edit → submitted again); `deleted` (bin). Inbound: `received` → (`screening` when sender unknown/lead and inbox.screen_inbound) → `received` or `screened_out`. **Invariant `guardStatusRegression`:** a `sent` message can never move to any pre-send status (enforced in `MessageStateMachine::transition`, the *only* writer of `status`). Legacy ref: survey/services.md §3.3, project memory `approval_gate_and_sent_status.md`.

**Actions:** `ComposeMessage(thread|new, mailbox, recipientsSpec, subject, bodyFormat, body|blocks|template+values, greeting, attachments[], isPrivate, scheduledFor?)` → creates `draft`; `SaveDraft`; `SubmitMessage` → `submitted`, dispatches `MessageSubmitted`; `RunOutboundAiCheck` (job) → `AiReview` + verdict; `ApproveMessage(user)` / `ReturnMessage(user, reason≥10)` / `EditAndApproveMessage`; `SendMessage` (job, queue `mail`, unique per message, tries 3): renders (`MessageRenderer` — template/blocks/markdown/html → branded Blade layout; quoted thread appended at send; **not stored in body**, stored in `rendered_html`), attaches, sends once per recipient (records per-recipient provider ids), one atomic update to `sent`. `ScreenInbound` (AI or manual), `MarkThreadRead`, `MarkThreadUnread`, `MoveThreadToProject`, `SetThreadPrivacy`, `DeleteMessage`/`RestoreMessage` (bin; Gmail copy trashed via `GmailCopy` logic: rfc822msgid search then thread walk; never trash by thread), `CategoriseThread` (terms), `CreateTaskFromMessage`, `SummariseThread` (job), `DraftReply` (job), `IngestInboundMessage(rawGmailMessage)` (dedupe by provider id; sender → contact by email; thread match: `provider_thread_id` → `rfc In-Reply-To/References` → normalised subject + same contact within 30 days; unknown sender → thread with project NULL + `screening`), `IngestSentFromGmailUi` (OURS/THEIRS/SEEN logic from legacy `IngestSentMail`).

**Reply clock (`ReplyClock` service, single definition):** thread `needs_reply_since` = latest inbound `received_at` if newer than latest outbound `sent_at`; SLA = `settings inbox.sla_minutes` (default 60); cutover `inbox.reply_clock_since` excludes older threads. Recomputed by listener on `MessageReceived`/`MessageSent` (denormalised columns on `threads`, plus nightly `inbox:audit-reply-clock`).

**Templates & placeholders:** `PlaceholderRegistry` (config): `contact.first_name|full_name|company`, `project.name|number|manager_name`, `user.name|role|signature`, `magic_link` (mints an `AccessLink` kind client_dashboard **only at send**; preview shows placeholder), `invoice.number|total|due_date|link`, `deliverables.list` (repeatable), `report.month`, `custom.*` (asked at compose). Template preview endpoint renders against a chosen project/contact.

**Blocks:** block document JSON `[{id,type: paragraph|bullets|link|image|button, text?, items?, href?, label?, attachment_public_id?, alt?}]`, inline syntax `(Label)[url]` and `*bold*` (legacy EmailBlocks mock). Images uploaded as `attachments(kind=inline_image, purpose=email_block, expires_at=+7d)`; at send → CID + attached fallback; expiry cleared on send.

**Queries:** `ThreadListQuery(view: needs_reply|new|with_ai|approval|received|sent|drafts|saved|deleted|all; project; categories; unread_only; overdue_only; from/to; sort: newest|breach)` → paginates threads (not messages) with correlated last-message columns; `ThreadDetailQuery` applies **server-side redaction**: screening messages hidden without `inbox.screen_inbound`; private threads hidden without `inbox.view_private`; bodies never sent to the client when redacted. `InboxCountersQuery` (cached 30s). `RecipientCandidatesQuery(thread)` (project contacts, thread participants; manual addresses only with `inbox.address_manually`). Legacy ref: project memory `inbox_beta_react.md`, `email_reply_recipients.md`, `email_privacy_flag.md`.

**HTTP web:** `/inbox` (desktop + mobile layouts, same route; `?thread=`), `/inbox/settings` (mailboxes, sign-off, SLA, AI switches — permission mailboxes.manage), `/admin/email-templates` (list/edit/preview).
**API v1 (`/inbox/*`):** `GET filters`, `GET threads`, `GET threads/{id}`, `POST threads/{id}/read|unread`, `POST threads/{id}/notes` (Comment), `POST threads/{id}/summarise`, `GET threads/{id}/recipients`, `POST threads/{id}/reply`, `POST threads/{id}/move`, `POST threads/{id}/privacy`, `POST threads/{id}/categories`, `POST bulk` (read/approve/categorise/delete), `GET saved`, `POST saved`, `PUT/DELETE saved/{id}`, `GET deleted`, `POST messages/{id}/restore`, `GET messages/{id}/preview` (rendered HTML for sandboxed iframe), `POST messages/{id}/approve`, `POST messages/{id}/return`, `POST messages/{id}/edit-approve`, `POST messages/{id}/recheck`, `POST messages/{id}/draft` (AI), `POST messages/{id}/task`, `POST compose` (new thread), `POST blocks/preview`, `GET templates`, `POST templates/{id}/preview`, `GET counters`. Tracking: `GET /t/o/{signed message public_id}.gif`.
**Policies:** ThreadPolicy (view: inbox.view AND (project member OR inbox.view_all_projects OR thread.project null with leads.manage); private/screening rules above), MessagePolicy (approve: inbox.approve on project OR inbox.approve_all; cannot approve own unless approve_all; delete: inbox.delete).
**Jobs (queue `mail`/`ai`):** `PollMailboxJob` (every minute per active gmail mailbox, `WithoutOverlapping`), `IngestSentMailJob` (5 min, flag), `RunOutboundAiCheckJob`, `SendMessageJob`, `SendScheduledMessagesJob` (every minute: scheduled_for ≤ now → sending), `SummariseThreadJob`, `DraftReplyJob`, `ScreenInboundJob`.
**Events:** `MessageReceived`, `MessageSubmitted`, `MessageApproved`, `MessageReturned`, `MessageSent`, `MessageFailed`, `ThreadNeedsReply` (SLA breach → notification to owner/managers, once per breach).
**Tests:** state machine table test (every allowed/forbidden transition); regression guard; ingestion threading (4 matching strategies) with fixtures; redaction (private, screening) per permission; send renders quote but stores clean body; per-recipient ids; reply clock with cutover; block image CID; template placeholders incl. magic link minted only at send; AI check held → pending_approval and never auto-sends when flag off.

---

## 3.6 Finance

**Entities:** ServiceCatalogueItem, ProjectService, ServiceMilestone, Invoice, InvoiceItem, Bill, PayoutMethod, Budget, Proposal, Payment, LedgerEntry, LedgerCategory, XeroConnection, XeroAccount, XeroPaymentService, XeroLink, AirwallexBankMapping, BankTransaction, StripeApp, StripeSubscription, StripeSubscriptionPayment.

**Actions:** Services: `AddProjectService(from catalogue or custom)` (creates service milestones from catalogue schedule), `UpdateProjectService`, `ReorderServiceMilestones`. Invoices: `CreateInvoice(project, contact, items[] each pointing at a service milestone or free text)` (number from Sequence; **rejects a service milestone already on a non-void invoice**), `UpdateInvoice` (draft only), `SubmitInvoiceForApproval`, `ApproveInvoice`, `SendInvoice` (Xero push + email via template `invoice-notification`), `VoidInvoice`, `RecordInvoicePayment` → `Payment` + `amount_paid` + status (partially_paid/paid), `SyncInvoiceFromXero`. Bills: `SubmitBill(project, supplier, contract?, amount, currency, reference, attachments, payoutMethod)` (portal or staff; **`BillExceedsContract`** when Σ bills > contract amount unless `bills.manage` override flag), `StartBillApproval` (RequestApproval with flow), `ApproveBillStep`, `RejectBill`, `ScheduleBillPayment`, `RecordBillPayment` (Airwallex ref) → Xero ACCPAY sync, `VoidBill`. Budgets/Proposals: `CreateBudget`, `SubmitProposal(supplier, milestone(s), amount, terms, attachment)` (portal: one per phase, several phases = several proposals), `ShortlistProposal`, `AcceptProposal` (→ status accepted = contract; rejects other proposals on the phase optionally), `RejectProposal(reason)`, `WithdrawProposal`, `CompleteProposal` (auto when billed_amount ≥ amount and all bills paid). Payout: `AddPayoutMethod`, `SetDefaultPayoutMethod`, `RemovePayoutMethod`. Ledger: `RecordLedgerEntry`, `DeleteLedgerEntry`. Xero: `ConnectXero` (OAuth), `SelectTenant`, `DisconnectXero`, `SyncXeroAccounts`, `SyncXeroPaymentServices`, `PushContactToXero`, `PushInvoiceToXero`, `PushBillToXero`, `PullInvoiceStatuses` (hourly), `HandleXeroWebhook` (HMAC, idempotent). Stripe: `HandleStripeWebhook(app)` (subscriptions, invoices paid → StripeSubscriptionPayment → optional Payment), `ImportStripePayouts`. Airwallex: `ImportAirwallexTransactions(since)` → `bank_transactions`, `MatchBankTransaction(payment)`.

**Queries:** `FinanceDashboardQuery` (P&L by month/project, outstanding invoices, bills due, cash timeline — port `ProfitLossService` logic), `InvoiceListQuery`, `BillListQuery`, `ProposalListQuery`, `ProjectMoneyQuery` (project Money tab: services + schedule, invoices, bills, budgets vs contracts, profitability = Σ base payments in − Σ base payments out − ledger expenses), `LedgerQuery`, `ReconciliationQuery` (unmatched bank transactions).

**HTTP web:** `/finance` (dashboard), `/finance/invoices` (+ `/{id}`), `/finance/bills` (+ `/{id}`), `/finance/proposals`, `/finance/ledger`, `/finance/reconciliation`, `/admin/catalogue`, `/admin/xero`, `/admin/stripe`, project Money tab (injected). **Every route carries a permission** (legacy gap).
**API v1:** `apiResource invoices` (+ submit/approve/send/void/payments), `apiResource bills` (+ approve/reject/schedule/payments/void/attachments), `apiResource proposals` (+ shortlist/accept/reject/withdraw), `apiResource budgets`, `project-services` CRUD + `milestones`, `catalogue` CRUD (+ merge), `ledger` CRUD, `payout-methods` CRUD, `xero/*` (connect, callback, status, accounts, payment-services, sync), `GET finance/dashboard`; webhooks `POST /webhooks/xero`, `POST /webhooks/stripe/{app_key}`.
**Policies:** InvoicePolicy, BillPolicy (supplier sees own bills), ProposalPolicy (supplier sees own), PaymentPolicy, LedgerPolicy, XeroPolicy (xero.manage).
**Events:** `InvoiceSent`, `InvoicePaid`, `BillSubmitted` (→ `BillSubmittedMail` to finance), `BillApproved`, `BillPaid`, `ProposalSubmitted` (→ notify PM), `ProposalAccepted`, `PaymentRecorded`.
**Jobs:** `PullXeroInvoiceStatusesJob` (hourly), `SyncXeroPaymentServicesJob` (6h), `ImportAirwallexTransactionsJob` (daily), `ImportStripePayoutsJob` (daily), `PushToXeroJob` (on events, retry 3).
**Tests:** invoice total = Σ items; milestone double-invoicing rejected; payment updates status and FX snapshot; bill exceeds contract; approval flow multi-step; Xero push idempotent via xero_links; webhook idempotency; dashboard numbers against a fixture ledger.

---

## 3.7 Access

**Entities:** AccessLink, OneTimeCode, PortalSession, ClientSession, AuthAttempt, VaultCredential, VaultCredentialShare.
**Actions:** `IssueAccessLink(kind, project?, contact?, expires, maxUses)` (returns URL with raw token once), `RevokeAccessLink`, `ResolveAccessLink(token)` (hash lookup, expiry, uses, whitelist), `IssueOneTimeCode(purpose, identifier, scope)`, `VerifyOneTimeCode`, `StartPortalSession(user, via, project?)`, `EndPortalSession`, `StartClientSession(contact, link, pinVerified)`, `SetClientPin`, `VerifyClientPin`, `RecordAuthAttempt`, `CreateVaultCredential`, `UnlockVault(pin)` (derives key; audit), `ShareVaultCredential(user, grantedBy)`, `RevokeVaultShare`, `DeleteVaultCredential`. Throttle policies per channel.
**Guards/middleware:** `portal.session` (supplier), `client.session` (contact), `access-link:{kind}`, `api-key`, `external-token` (kind external_api with whitelist/max_uses).
**HTTP web:** `/admin/access-links` (external tokens + share links), `/admin/vault` (all credentials, stats), project Vault tab, contact Vault tab.
**API v1:** `POST projects/{id}/share-link` (staff), `POST projects/{id}/client-link` (staff, mints client_dashboard link + optional email), `vault/*` (unlock, CRUD, share, logs), extension: `GET /extension/vault/labels`, `POST /extension/vault/resolve` (api-key).
**Commands:** `access:prune` (hourly: expired codes/sessions/attempts), `vault:prune`.
**Tests:** token hashing round-trip; whitelist/max_uses; PIN attempts lockout; vault decrypt requires PIN; share visibility.

---

## 3.8 Portal (composition module — pages only)

**Supplier portal (`/portal`, guard portal.session; entry `/p/{project public_id}/{code}` → sign-in panel + OTP):** Projects list ("Projects shared with you" cards), Project (hero, phases = milestones with `portal_visible` & status not completed/cancelled, open scope items per phase, "Propose for this phase", right rail: sign-in or "Your proposals" + phases not yet quoted, contact block), Proposal modal (multi-phase: one proposal per phase, distribute/split evenly, payment terms, supporting document), Bills tab (Upload your bill: reference, amount, file, pay-to method; 7-day terms), Profile (details/verification, payout methods add/default/remove, masked), Sign out (ends portal session only). Legacy ref: project memory `portal_auth_and_access.md`, `guest_proposals_page.md`, `guest_bills_and_profile.md`, mock `Guest Project Proposals.dc.html`.

**Client OS (`/client`, guard client.session; entry `/c/{token}` → PIN setup/verify → session):** per Design Guide screens — Login (split brand panel + form: email → magic link / PIN), Home (what happened & what's next: recent deliverables, approvals waiting, next milestone, announcements), Product Plan (milestones + scope items module by module), SEO Reports (monthly list + detail read with `<ozee-chart>`; data source = `seo_reports` legacy JSON → **R1 keeps a `seo_reports` table (project_id, month, data JSON, published_at)** in Work module and renders it — ingestion stays manual upload/API as today), Announcement (notice post — **R1 minimal: `announcements` table (title, body markdown, published_at, audience, project_id nullable)** in Platform), Approvals (deliverable review: approve / request changes / comment / mark read), Documents (attachments kind document on project; upload), Invoices (list + download; Stripe pay link if enabled), Vault (contact-owned credentials), Profile/Telegram deferred. Signature Suite/Studio/Builder are **Exploration** in the guide → deferred (07).
**Tests:** Playwright journeys: supplier OTP → proposal → bill; client link → PIN → approve deliverable.

---

## 3.9 Ai

`GeminiClient::generate(promptName, vars, schema?)` — prompts as versioned files `modules/Ai/Prompts/<name>.v<N>.md` with frontmatter (model, temperature, response schema). Prompts in R1: `outbound_check.v1` (port legacy `EmailAiAnalysisService::buildSystemPrompt`: flags personal contact details, rude language, unclear CTA; returns `{approval_required, reason, summary}`), `inbound_screen.v1`, `summarise_thread.v1`, `summarise_message.v1`, `draft_reply.v1`, `task_suggestion.v1`. Every call writes `ai_reviews` + `ai_usage`. Never throws to callers (returns `AiResult{ok,error}`); trimming: 4000 chars/message, 12 messages/thread. Kill switches in `config/ozee.features`.

## 3.10 Integrations

`GoogleOAuth` (per-user + app mailbox; scopes gmail.modify, calendar.events, drive.file), `GmailProvider`, `GoogleCalendarClient` (create/delete event with Meet), `GoogleDriveClient` (folder create, upload, share) — used by Work Documents tab when `settings.drive_folder_id` set, `GcsStorage` helpers (signed URLs). Token refresh centralised; **never delete a Google account row on transient errors** (legacy bug).
