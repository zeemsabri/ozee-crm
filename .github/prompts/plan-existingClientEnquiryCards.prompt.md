## Plan: Existing-Client Enquiry Cards via Project Services

Extend existing project service rows to optionally represent pre-sale enquiries for existing clients, then project only enquiry-enabled rows into the Leads board as a distinct card type. This keeps current leads intact, avoids a new table, and uses one source of truth while preserving normal service rows that should never behave like leads.

**Steps**
1. Phase 1: Contract and status model definition
2. Define and document a service-detail schema extension used in JSON: `service_tracking_type` (`operational_service` or `client_enquiry`), `show_on_leads_board` (boolean), `description`, `enquiry_status`, `enquiry_created_at`, `enquiry_updated_at`, optional `enquiry_meta` (e.g., source, created_by). *blocks step 2*
3. Standardize allowed statuses for existing-client enquiries: `pending_quote`, `quoted`, `approved`, `rejected`, `converted_to_service`; apply them only when `service_tracking_type=client_enquiry`, and define transition rules and guardrails (e.g., only approved can convert). *blocks step 2*
4. Phase 2: Backend read/write support for service enquiry fields
5. Update services/payment update validation and normalization so additional tracking fields are accepted and persisted safely for each service row, including defaults for legacy rows with missing fields and a safe default of `service_tracking_type=operational_service`, `show_on_leads_board=false`. *depends on 1-3*
6. Add backend transformer/helper to flatten only `service_details` rows marked as `service_tracking_type=client_enquiry` and `show_on_leads_board=true` into lead-like payloads for `/leads` UI consumption, including references to `project_id`, `client`, `service_id`, and row index/key. *depends on 1-3*
7. Add API endpoint(s) for existing-client enquiry cards and mutations:
8. List endpoint with leads-like filters/search and status filtering
9. Create endpoint from Leads page that requires selecting existing client + project, then creates one `service_detail` row preconfigured as `client_enquiry`
10. Update endpoint for enquiry status/description edits
11. Convert endpoint that marks `converted_to_service` and triggers post-convert object creation flow (Task/Milestone/Project chooser)
12. Reuse existing permission boundary (`manage_projects` + project service/payment capability checks where project mutation occurs). *depends on 5-6*
13. Phase 3: Leads page mixed-card integration
14. Extend leads composable state to fetch both native leads and projected existing-client enquiries, then merge into a single board/list collection with explicit `card_type` discriminator.
15. Update kanban grouping logic to keep current lead statuses unchanged while mapping enquiry statuses into dedicated columns or harmonized groups; ensure drag/move only applies where permitted by type.
16. Add distinct card visuals/badges for existing-client enquiries and show origin context (existing client + project + service).
17. Extend create/edit modal flow from `/leads` to support “Existing Client Enquiry” mode (project/client picker + service fields) without changing current “New Lead” defaults. *depends on 7-11*
18. Phase 4: Services & Payment UI updates
19. Add tracking-type control to each service card in Services & Payment tab so users can explicitly keep a row as a normal service or mark it as an enquiry that should appear on leads; preserve existing amount/frequency/payment-breakdown behavior.
20. Show description and enquiry status inputs only for enquiry-tracked services, and ensure save payload includes the new tracking fields while supporting legacy rows gracefully.
21. Add inline action for approved enquiries to open “Create from Enquiry” chooser (Task/Milestone/Project), then persist linked references in enquiry metadata for traceability. *depends on 7-11*
22. Phase 5: Conversion execution hooks
23. Implement conversion action handlers:
24. Task creation path (existing project)
25. Milestone/expendable creation path if project financial structure requires it
26. New project creation path for larger scoped work
27. Persist created object IDs in enquiry metadata and set status to `converted_to_service`; prevent duplicate conversion if already converted. *depends on 21*
28. Phase 6: Testing and rollout safety
29. Add feature tests for enquiry list/create/update/convert endpoints, permission checks, status transitions, opt-in lead visibility rules, and backward compatibility with older service JSON.
30. Add frontend tests (or targeted QA scripts) for mixed-card rendering, filters, drag restrictions, conversion chooser outcomes, and ensuring operational services never appear on the leads board.
31. Add migration-free data compatibility checks and optional backfill command to normalize old `service_details` rows at read-time/write-time without accidentally surfacing legacy services as leads.

**Relevant files**
- `/Users/zeeshansabri/laravel/email-approval-app/app/Http/Controllers/Api/ProjectActionController.php` — extend `updateServicesAndPayment()` validation/normalization for enquiry fields.
- `/Users/zeeshansabri/laravel/email-approval-app/app/Http/Controllers/Api/ProjectReadController.php` — reuse/extend services read logic and potential transformer entrypoint.
- `/Users/zeeshansabri/laravel/email-approval-app/routes/api.php` — add existing-client enquiry list/create/update/convert endpoints.
- `/Users/zeeshansabri/laravel/email-approval-app/app/Http/Controllers/Api/LeadController.php` — keep untouched for classic leads; optionally add coordinated mixed-feed endpoint if chosen.
- `/Users/zeeshansabri/laravel/email-approval-app/resources/js/Composables/useLeads.js` — merge mixed datasets and filter/state handling with `card_type`.
- `/Users/zeeshansabri/laravel/email-approval-app/resources/js/Pages/Admin/Leads/Index.vue` — mode toggles and create-flow branching for classic lead vs existing-client enquiry.
- `/Users/zeeshansabri/laravel/email-approval-app/resources/js/Pages/Admin/Leads/components/LeadCard.vue` — distinct rendering for enquiry cards.
- `/Users/zeeshansabri/laravel/email-approval-app/resources/js/Pages/Admin/Leads/components/LeadKanban.vue` — column mapping and move behavior by card type.
- `/Users/zeeshansabri/laravel/email-approval-app/resources/js/Components/ServicesAndPaymentForm.vue` — add enquiry status + description fields and convert action entry point.
- `/Users/zeeshansabri/laravel/email-approval-app/tests/Feature` — add new feature tests for enquiry lifecycle.

**Verification**
1. API verification: create existing-client enquiry from leads flow, verify one new `service_detail` row is persisted on selected project with `service_tracking_type=client_enquiry`, `show_on_leads_board=true`, and `enquiry_status=pending_quote`.
2. API verification: transition enquiry through `quoted -> approved -> converted_to_service` and assert invalid transitions are rejected.
3. API verification: conversion chooser creates selected artifact (Task/Milestone/Project) and stores linkage metadata in the same service row.
4. UI verification: `/leads` board displays both normal leads and existing-client enquiry cards with distinct badge/type; filters and search work for both.
5. UI verification: `/projects/{id}/edit` Services & Payment lets users choose whether a service is a normal service or an enquiry, and only enquiry rows expose description/status fields without regressing payment breakdown validation.
6. Regression verification: existing lead CRUD/convert flow remains unchanged; existing service rows without enquiry fields still render and save safely and do not appear on `/leads` by default.
7. Permission verification: unauthorized users cannot mutate enquiries from either Leads page or Services tab.

**Decisions**
- Keep source of truth in project `service_details` JSON; no new table/entity.
- Only service rows explicitly marked as `client_enquiry` and opted into `show_on_leads_board` become enquiry cards.
- Existing operational/running services remain normal service rows and never qualify as lead-like cards unless the user explicitly changes their tracking type.
- One existing-client enquiry card maps to exactly one service row.
- Existing-client enquiry cards appear mixed on `/leads` with distinct card styling/type.
- Creation supported from both `/leads` and project Services & Payment.
- Status set: `pending_quote`, `quoted`, `approved`, `rejected`, `converted_to_service`.
- On conversion: show chooser and immediately create Task/Milestone/Project, then mark converted.

**Further Considerations**
1. Recommended technical boundary: use a dedicated `ExistingClientEnquiryController` instead of overloading `LeadController`, to avoid accidental regressions in legacy lead endpoints.
2. Recommended stable row identity: store a generated `enquiry_id` inside each service row (UUID) so updates/conversions don’t depend on array index order.
3. Recommended UX safeguard: default new service rows created inside project management to `operational_service`, and require explicit opt-in before they surface on `/leads`.
4. Recommended auditability: log status, tracking-type, and conversion events to activity log for accountability.
