# Invoices Status Filter Bug Fix

## Issue
On the `/admin/financials/invoices` page, when the status filter defaulted to empty (excluding void and rejected invoices), the API returned an empty list of invoices:
`GET /api/admin/invoices?status=&search=&project_id=&service_id=&date_from=&date_to=`

## Cause
- Laravel's `ConvertEmptyStringsToNull` middleware converts empty query parameters (`?status=`) to `null`.
- `$request->input('status', '')` returns `null` instead of the default value `''` when the parameter exists but is `null`.
- This led to querying for `WHERE status IS NULL`, yielding no results.

## Resolution
Modified [InvoiceController.php](file:///Volumes/Shared%20Data/laravel/email-approval-app/app/Http/Controllers/Api/InvoiceController.php) to default to `''` when the status is null/empty:
```php
$statusFilter = $request->input('status') ?? '';
```
This restores the default filter behavior (excluding `voided` and `rejected` statuses) when `status` is empty.

## Xero Invoice Status Sync Command

### Issue
- The manual sync option on `/admin/financials/invoices/{id}` updated the paid status of local invoices, but this did not run automatically in the background.
- The command `SyncXeroPaymentServices` (`xero:refresh-payment-services`) was designed to refresh cached payment service options, not to sync invoice payment statuses.

### Resolution
- Created [SyncXeroInvoices.php](file:///Volumes/Shared%20Data/laravel/email-approval-app/app/Console/Commands/SyncXeroInvoices.php) (`xero:sync-invoices`) to fetch pending/authorised invoices from the database and update their status from Xero.
- Scheduled the command to run hourly in [console.php](file:///Volumes/Shared%20Data/laravel/email-approval-app/routes/console.php):
  ```php
  Schedule::command('xero:sync-invoices')->hourly();
  ```


## Remaining Roles/Permissions Migration Checklist (isManager -> Permissions)

The following locations still need to be updated gradually:

### Phase 2: Email Management
- [ ] **EmailPolicy.php** (Line 65): Edit emails authorization check.
- [ ] **EmailController.php** (Lines 447, 478, 523, 606, 632, 1124): Check `! $user->isSuperAdmin() && ! $user->isManager()` before executing email-related actions.

### Phase 3: Project & Workspace Management
- [ ] **WorkspaceController.php** (Lines 32, 298): Checking for global manager.
- [ ] **ProjectController.php** (Lines 38, 202, 1665): Project access and status check.
- [ ] **ProjectReadController.php** (Lines 40, 731): Project read/details check.
- [ ] **ProjectSectionController.php** (Line 612): Section checks.
- [ ] **HasProjectPermissions.php** (Line 20): Check if user is Super Admin or Manager.

### Phase 4: Tasks, Permissions, API, Availability, & Client Enquiries
- [ ] **TaskController.php** (Line 78): Task detail read.
- [ ] **PermissionController.php** (Line 83): Modify permission assignments.
- [ ] **ExistingClientEnquiryController.php** (Line 230): Enquiries filtering.
- [ ] **AvailabilityController.php** (Lines 55, 92, 190, 208, 353, 372).
- [ ] **ExternalApiController.php** (Lines 109, 135, 176, 238, 389, 414, 446).
