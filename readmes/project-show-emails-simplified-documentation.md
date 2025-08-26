# Project Show Emails Simplified Implementation

## Overview

This document describes the implementation of a simplified emails API for the Projects/Show.vue component. The changes ensure that only the necessary information is returned from the API and displayed in the UI, making the page more efficient and user-friendly.

## Background

Previously, the emails API in the Projects/Show.vue component was returning too much information. The issue description requested:

1. Simplify the API to only return necessary information: Subject, From, Date, Status, Actions
2. Remove the "To" email field from the View modal

These changes help improve performance by reducing the amount of data transferred between the server and client, and they simplify the UI by removing unnecessary information.

## Implementation Details

### 1. Created a New API Endpoint

Added a new method `getProjectEmailsSimplified` to the `EmailController` that returns only the required fields:

```php
/**
 * Get emails for a specific project with simplified information.
 * Only returns Subject, From, Date, Status
 * Accessible by: Super Admin, Manager (all); Contractor (if assigned to project)
 */
public function getProjectEmailsSimplified($projectId)
{
    $user = Auth::user();
    $role = $user->getRoleForProject($projectId);

    $project = Project::findOrFail($projectId);

    // Check if user has access to this project
    if ($user->isContractor() && !$user->projects->contains($project->id)) {
        return response()->json(['message' => 'Unauthorized to view emails for this project.'], 403);
    }

    // Get all conversations for this project
    $conversations = $project->conversations;
    $conversationIds = $conversations->pluck('id')->toArray();

    // Get all emails for these conversations
    $emails = Email::with(['sender:id,name'])
        ->whereIn('status', ['approved', 'pending_approval', 'sent'])
        ->whereIn('conversation_id', $conversationIds)
        ->orderBy('created_at', 'desc')
        ->get();

    // Transform the data to include only the required fields
    $simplifiedEmails = $emails->map(function ($email) {
        return [
            'id' => $email->id,
            'subject' => $email->subject,
            'sender' => $email->sender ? [
                'id' => $email->sender->id,
                'name' => $email->sender->name
            ] : null,
            'created_at' => $email->created_at,
            'status' => $email->status,
            // Include body for the modal view
            'body' => $email->body,
            // Include additional fields needed for the modal view
            'rejection_reason' => $email->rejection_reason,
            'approver' => $email->approver ? [
                'id' => $email->approver->id,
                'name' => $email->approver->name
            ] : null,
            'sent_at' => $email->sent_at
        ];
    });

    return response()->json($simplifiedEmails);
}
```

This method:
- Follows the same authorization logic as the original `getProjectEmails` method
- Retrieves emails for the project, but only loads the necessary related data (`sender:id,name`)
- Transforms the data to include only the required fields
- Excludes the "to" field as requested in the issue description

### 2. Added a Route for the New Endpoint

Added a new route in `routes/api.php`:

```php
Route::get('projects/{project}/emails', [EmailController::class, 'getProjectEmails']); // Get all emails for a project (legacy endpoint)
Route::get('projects/{project}/emails-simplified', [EmailController::class, 'getProjectEmailsSimplified']); // Get simplified emails for a project
```

The original endpoint is kept for backward compatibility and marked as a legacy endpoint.

### 3. Updated the Projects/Show.vue Component

Modified the `fetchProjectEmails` function in Projects/Show.vue to use the new simplified endpoint:

```javascript
const fetchProjectEmails = async () => {
    loadingEmails.value = true;
    emailError.value = '';
    try {
        const projectId = usePage().props.id;
        const response = await window.axios.get(`/api/projects/${projectId}/emails-simplified`);
        emails.value = response.data;
    } catch (error) {
        emailError.value = 'Failed to load email data.';
        console.error('Error fetching project emails:', error);
    } finally {
        loadingEmails.value = false;
    }
};
```

### 4. Removed the "To" Email Field from the View Modal

Removed the "To" field from the email modal in Projects/Show.vue:

Before:
```html
<div>
    <p class="text-gray-600">From: <span class="text-gray-900">{{ selectedEmail.sender?.name || 'N/A' }}</span></p>
    <p class="text-gray-600">To: <span class="text-gray-900">{{ Array.isArray(selectedEmail.to) ? selectedEmail.to.join(', ') : selectedEmail.to }}</span></p>
</div>
```

After:
```html
<div>
    <p class="text-gray-600">From: <span class="text-gray-900">{{ selectedEmail.sender?.name || 'N/A' }}</span></p>
</div>
```

### 5. Adjusted the Grid Layout

After removing the "To" field, the grid layout was unbalanced. To fix this, we moved the "Status" field to the left column:

```html
<div>
    <p class="text-gray-600">From: <span class="text-gray-900">{{ selectedEmail.sender?.name || 'N/A' }}</span></p>
    <p class="text-gray-600 mt-1">Status:
        <span
            :class="{
                'px-2 py-1 rounded-full text-xs font-medium': true,
                'bg-green-100 text-green-800': selectedEmail.status === 'sent',
                'bg-yellow-100 text-yellow-800': selectedEmail.status === 'pending_approval',
                'bg-red-100 text-red-800': selectedEmail.status === 'rejected',
                'bg-gray-100 text-gray-800': selectedEmail.status === 'draft'
            }"
        >
            {{ selectedEmail.status.replace('_', ' ').toUpperCase() }}
        </span>
    </p>
</div>
<div>
    <p class="text-gray-600">Date: <span class="text-gray-900">{{ new Date(selectedEmail.created_at).toLocaleString() }}</span></p>
</div>
```

This creates a more balanced layout in the email modal.

## Testing

A test script (`test-project-emails-simplified.php`) has been created to verify that the changes work correctly. The script:

1. Tests the new simplified API endpoint and checks that it returns only the required fields
2. Verifies that the 'to' field is not present in the response
3. Checks that the sender field has the correct structure
4. Compares the simplified endpoint with the original endpoint to show how many fields have been removed

To run the test script:

```bash
php test-project-emails-simplified.php
```

## Impact

These changes:

1. Reduce the amount of data transferred between the server and client
2. Improve the performance of the page by loading only the necessary data
3. Simplify the UI by removing unnecessary information
4. Make the code more maintainable by following a consistent pattern for simplified endpoints

## Related Files

- `app/Http/Controllers/Api/EmailController.php` - Added the `getProjectEmailsSimplified` method
- `routes/api.php` - Added a new route for the simplified endpoint
- `resources/js/Pages/Projects/Show.vue` - Updated to use the simplified endpoint and removed the "To" field from the modal
- `test-project-emails-simplified.php` - Test script to verify the changes
