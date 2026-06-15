# Review of Uncommitted Work: Email Scheduling & Timezone Support

This document details the uncommitted changes present in the repository, implementing **Send Later** (delayed email scheduling) and **Timezone Support** across clients and projects.

---

## Summary of Changes

1. **Email Status `delayed`**: Added a new `delayed` status to the `emails` status column via a migration and updated the `EmailStatus` enum.
2. **Scheduling Integration**: Integrated emails into the existing `Schedule` system, enabling them to be scheduled (via `SchedulePickerModal`) when composed.
3. **Automatic Unlocking**: Implemented `runScheduled()` on the `Email` model so that when a schedule triggers, the status is updated from `delayed` back to `draft` (or pending approval) to be sent out.
4. **Timezone Displays**: 
   - Users can now see the Project's timezone and the Client's timezone (with their respective current local times) when composing/editing emails.
   - Timezones are visible on client detail cards and the client list.
5. **Timezone Configuration**: Added the ability to set and update the timezone of a Client.
6. **Console Scheduler Cleanup**: Cleaned up `routes/console.php` to run `app:run-scheduler` every minute, commenting out older individual scheduler commands.

---

## File-by-File Breakdown

### 1. Database & Configuration

#### [NEW] [2026_06_15_120000_add_delayed_to_emails_status_enum.php](file:///Volumes/Shared%20Data/laravel/email-approval-app/database/migrations/2026_06_15_120000_add_delayed_to_emails_status_enum.php)
* Alters the `emails` table `status` column to include `delayed` in the ENUM definition.

#### [MODIFY] [EmailStatus.php](file:///Volumes/Shared%20Data/laravel/email-approval-app/app/Enums/EmailStatus.php)
* Adds `case Delayed = 'delayed';` to the `EmailStatus` enum.

#### [MODIFY] [AppServiceProvider.php](file:///Volumes/Shared%20Data/laravel/email-approval-app/app/Providers/AppServiceProvider.php)
* Adds `email` and `Email` mapping to `Relation::morphMap` for polymorphic relationships.

#### [MODIFY] [routes/console.php](file:///Volumes/Shared%20Data/laravel/email-approval-app/routes/console.php)
* Comments out various individual scheduled tasks and retains only `app:run-scheduler` executing every minute.

---

### 2. Backend Logic (Controllers & Models)

#### [MODIFY] [Email.php](file:///Volumes/Shared%20Data/laravel/email-approval-app/app/Models/Email.php)
* Implements `runScheduled(Schedule $schedule)`:
  ```php
  public function runScheduled(Schedule $schedule): void
  {
      $this->refresh();
      if ($this->status === \App\Enums\EmailStatus::Delayed) {
          $this->update([
              'status' => \App\Enums\EmailStatus::Draft,
          ]);
      }
  }
  ```

#### [MODIFY] [HandlesSchedules.php](file:///Volumes/Shared%20Data/laravel/email-approval-app/app/Http/Controllers/Concerns/HandlesSchedules.php) and [ScheduleController.php](file:///Volumes/Shared%20Data/laravel/email-approval-app/app/Http/Controllers/ScheduleController.php)
* Extends the schedule system's type-mappings to support `email` (`App\Models\Email`).
* Implements descriptive name retrieval for emails (`$model->subject ?? "Email #{$model->id}"`) in the scheduling UI.

#### [MODIFY] [EmailController.php](file:///Volumes/Shared%20Data/laravel/email-approval-app/app/Http/Controllers/Api/EmailController.php) and [HandlesEmailCreation.php](file:///Volumes/Shared%20Data/laravel/email-approval-app/app/Http/Controllers/Api/Concerns/HandlesEmailCreation.php)
* Modifies request validation to accept `delayed` as a valid status.
* Dynamically sets `status` to `$validated['status'] ?? EmailStatus::Draft` instead of hardcoding `EmailStatus::Draft`.

#### [MODIFY] [ClientController.php](file:///Volumes/Shared%20Data/laravel/email-approval-app/app/Http/Controllers/Api/ClientController.php) and [ClientResource.php](file:///Volumes/Shared%20Data/laravel/email-approval-app/app/Http/Resources/ClientResource.php)
* Adds `timezone` validation to client store and update endpoints.
* Exposes `timezone` in `ClientResource`.

#### [MODIFY] [RunScheduler.php](file:///Volumes/Shared%20Data/laravel/email-approval-app/app/Console/Commands/RunScheduler.php)
* Adds execution logs to track when the scheduler runs and dispatches jobs.

---

### 3. Frontend Components & UI

#### [MODIFY] [ComposeEmailContent.vue](file:///Volumes/Shared%20Data/laravel/email-approval-app/resources/js/Pages/Emails/Inbox/Components/ComposeEmailContent.vue) & [CustomComposeEmailContent.vue](file:///Volumes/Shared%20Data/laravel/email-approval-app/resources/js/Pages/Emails/Inbox/Components/CustomComposeEmailContent.vue)
* Integrated `useEmbeddedScheduler` composable.
* Added "Send Later" card allowing the user to configure a schedule using `SchedulePickerModal`.
* Submits emails with status `delayed` if scheduled, or status `draft` if unscheduled (without a schedule). Attaches the schedule polymorphic association after email creation when scheduled.
* Fetches and displays project and primary client timezones along with their active local times.

#### [MODIFY] [Clients/Index.vue](file:///Volumes/Shared%20Data/laravel/email-approval-app/resources/js/Pages/Clients/Index.vue)
* Integrates `TimezoneSelect` component in the client creation/edit forms.
* Displays client's timezone in the client list.

#### [MODIFY] [ProjectGeneralInfoCard.vue](file:///Volumes/Shared%20Data/laravel/email-approval-app/resources/js/Components/ProjectGeneralInfoCard.vue)
* Fetches the project's basic section config to display its active timezone and local time in the header.

#### [MODIFY] [ProjectClientsCard.vue](file:///Volumes/Shared%20Data/laravel/email-approval-app/resources/js/Components/ProjectOverviewCards/ProjectClientsCard.vue)
* Renders each client's timezone and current local time alongside client contacts.

#### [MODIFY] [Inbox/Index.vue](file:///Volumes/Shared%20Data/laravel/email-approval-app/resources/js/Pages/Emails/Inbox/Index.vue)
* Minor UI correction: Renamed "Custom Emails" button text to "Custom Email".

---

## Next Steps / Verification Plan

- [ ] **Run Migrations**: Run `php artisan migrate` to update the `emails` table.
- [ ] **Test Email Creation (Immediate)**: Verify sending emails without scheduling still works as intended.
- [ ] **Test Email Scheduling (Send Later)**: 
  - Compose an email and attach a schedule.
  - Check that the email is created with status `delayed`.
  - Check that a corresponding row is added to the `schedules` table with `schedulable_type = 'email'`.
- [ ] **Verify Scheduler Trigger**: Run `php artisan app:run-scheduler` manually or run `php artisan schedule:work` to confirm that the `delayed` email transitions to `draft` when the schedule conditions are met.
- [ ] **Check Timezone Utilities**: Verify timezone labels and local clocks render correctly and tick in real-time.
