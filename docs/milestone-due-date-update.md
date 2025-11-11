# Milestone Due Date Update Feature

## Overview
This feature allows users with the `update_milestone_due_date` permission to click on a milestone's due date in the Project Expendables dashboard and update it. The change is logged in the ProjectNotes system with both the old and new dates, along with the reason for the change.

## User Experience

### Frontend
1. Navigate to the **Project Financials** page (/admin/project-expendables)
2. Select a project
3. Under the **Active Milestones** tab, look for the milestone's due date in the milestone header
4. If you have the `update_milestone_due_date` permission, the due date appears as a clickable link (blue, underlined, with hover effect)
5. Click on the due date to open the **Update Milestone Due Date** modal
6. Fill in:
   - **New Due Date**: Select a future date from the date picker
   - **Reason for Change**: Enter at least 10 characters explaining why the date is being changed
7. Click **Update Due Date** to save
8. A success notification appears, and the milestone list automatically refreshes
9. A ProjectNote is created with type `'milestone'` and linked to the milestone via the `noteable` relationship

### Permission Check
- The due date is only clickable if the user has the `update_milestone_due_date` permission
- The modal only shows in the **Active Milestones** tab to prevent updates to completed/approved milestones
- The permission is checked via Vue's `usePermissions` directive with `canUpdateMilestoneDueDate`

## Backend

### Endpoint
**POST** `/api/milestones/{milestone}/update-due-date`

### Request Payload
```json
{
  "completion_date": "2024-12-31",
  "reason": "Extended timeline to accommodate additional testing requirements"
}
```

### Validation
- `completion_date`: Required, must be a valid date
- `reason`: Required, minimum 10 characters

### Response
Returns the updated milestone object with:
- Updated `completion_date`
- Loaded relationships (tasks)
- Status 200 on success
- Status 422 on validation error

### Logging
A ProjectNote is automatically created with:
- `type`: `'milestone'`
- `noteable_type`: `Milestone::class`
- `noteable_id`: The milestone ID
- `project_id`: The associated project
- `content`: Includes old date, new date, and the provided reason

Example content:
```
Milestone 'Phase 1' due date changed from 2024-12-15 to 2024-12-31. Reason: Extended timeline to accommodate additional testing requirements
```

## Files Modified

### Backend
1. **`app/Http/Controllers/Api/MilestoneController.php`**
   - Added `updateDueDate()` method
   - Validates input and updates milestone
   - Creates ProjectNote with type 'milestone'
   - Notifies Google Chat if configured

2. **`routes/api.php`**
   - Added route: `Route::post('milestones/{milestone}/update-due-date', [MilestoneController::class, 'updateDueDate']);`

### Frontend
1. **`resources/js/Components/ProjectExpendables/UpdateMilestoneDueDateModal.vue`** (New)
   - Modal component for updating due date
   - Form validation (date not in past, reason minimum 10 chars)
   - API integration with error handling

2. **`resources/js/Pages/Admin/ProjectExpendables/Index.vue`**
   - Added import for `UpdateMilestoneDueDateModal`
   - Added permission check: `canUpdateMilestoneDueDate`
   - Added state: `showUpdateDueDateModal`, `milestoneForDueDateUpdate`
   - Added methods: `openUpdateDueDateModal()`, `onDueDateUpdated()`
   - Updated milestone due date display to be clickable with permission check
   - Integrated modal into template

### Tests
**`tests/Feature/MilestoneDueDateUpdateTest.php`** (New)
- 7 comprehensive tests covering:
  - Successful date update
  - ProjectNote creation
  - Field validation
  - Reason minimum length
  - Date format validation
  - Notes include both old and new dates
  - Response structure

## Permission Management
The feature uses the existing permission system. Ensure the `update_milestone_due_date` permission is:
1. Created in the database
2. Assigned to appropriate roles
3. Referenced in the permission directive

## Google Chat Integration
If a project has a `google_chat_id` configured, a notification with the milestone prefix (📌) is sent to the Google Chat space.

## Future Enhancements
- Add bulk due date updates
- Add notification email to milestone stakeholders
- Add rollback/history view of date changes
- Add custom notification rules for different date change scenarios
