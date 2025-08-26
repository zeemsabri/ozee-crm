# Pending Approval Simplified Implementation

## Overview

This document describes the implementation of a simplified pending approval feature that limits the information displayed to users and improves the layout of the UI. The changes ensure that only the necessary information is returned from the API and displayed in the UI, making the page more efficient and user-friendly.

## Background

Previously, the pending approval page displayed too much information and had layout issues:

1. The API was returning all information about emails, conversations, projects, and clients
2. The table was displaying more information than needed
3. The table layout was not using screen space efficiently, causing the reject button to be cut off
4. The edit and approve modal was showing unnecessary fields

The requirement was to create a new API endpoint that provides only the required fields (Project Name, Client Name, Subject, Sender, Submitted On) and to update the UI to display only this information in a more efficient layout.

## Implementation Details

### 1. Created a New API Endpoint

Added a new method `pendingApprovalSimplified()` to the `EmailController` that returns only the required fields:

```php
/**
 * Display a listing of emails pending approval with limited information.
 * Only returns Project Name, Client Name, Subject, Sender, Submitted On
 * Accessible by: Super Admin, Manager
 */
public function pendingApprovalSimplified()
{
    $user = Auth::user();

    if (!$user->isSuperAdmin() && !$user->isManager()) {
        return response()->json(['message' => 'Unauthorized to view pending approvals.'], 403);
    }

    $pendingEmails = Email::with([
        'conversation.project:id,name',  // Load only project id and name
        'conversation.client:id,name',   // Load only client id and name
        'sender:id,name'                 // Load only sender id and name
    ])
        ->whereIn('status', ['pending_approval', 'pending_approval_received'])
        ->orderBy('created_at', 'asc')
        ->get();

    // Transform the data to include only the required fields
    $simplifiedEmails = $pendingEmails->map(function ($email) {
        return [
            'id' => $email->id,
            'project' => $email->conversation->project ? [
                'id' => $email->conversation->project->id,
                'name' => $email->conversation->project->name
            ] : null,
            'client' => $email->conversation->client ? [
                'id' => $email->conversation->client->id,
                'name' => $email->conversation->client->name
            ] : null,
            'subject' => $email->subject,
            'sender' => $email->sender ? [
                'id' => $email->sender->id,
                'name' => $email->sender->name
            ] : null,
            'created_at' => $email->created_at,
            'body' => $email->body, // Include body for the modal
        ];
    });

    return response()->json($simplifiedEmails);
}
```

### 2. Added a New Route

Added a new route for the simplified pending approval endpoint:

```php
Route::get('emails/pending-approval-simplified', [EmailController::class, 'pendingApprovalSimplified']); // New route with limited information
```

### 3. Updated the PendingApprovals.vue Component

#### 3.1 Updated the fetchInitialData Function

Modified the `fetchInitialData` function to use the new simplified endpoint:

```javascript
// Fetch initial data
const fetchInitialData = async () => {
    loading.value = true;
    generalError.value = '';
    try {
        // Use the simplified endpoint that returns only the required fields
        const emailsResponse = await window.axios.get('/api/emails/pending-approval-simplified');
        pendingEmails.value = emailsResponse.data;
        
        // We still need to fetch projects for the edit modal
        const projectsResponse = await window.axios.get('/api/projects');
        projects.value = projectsResponse.data;
    } catch (error) {
        generalError.value = 'Failed to load data.';
        console.error('Error fetching initial data:', error);
        if (error.response && (error.response.status === 401 || error.response.status === 403)) {
            generalError.value = 'You are not authorized to view this content or your session expired. Please log in.';
        }
    } finally {
        loading.value = false;
    }
};
```

#### 3.2 Updated the Table

Modified the table to display only the required fields and improved the layout:

```html
<div class="overflow-x-auto">
    <table class="w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
        <tr>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sender</th>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted On</th>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
        </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
        <tr v-for="email in pendingEmails" :key="email.id">
            <td class="px-4 py-4 whitespace-nowrap">{{ email.project?.name || 'N/A' }}</td>
            <td class="px-4 py-4 whitespace-nowrap">{{ email.client?.name || 'N/A' }}</td>
            <td class="px-4 py-4 truncate max-w-xs">{{ email.subject }}</td>
            <td class="px-4 py-4 whitespace-nowrap">{{ email.sender?.name || 'N/A' }}</td>
            <td class="px-4 py-4 whitespace-nowrap">{{ new Date(email.created_at).toLocaleString() }}</td>
            <td class="px-4 py-4 whitespace-nowrap flex space-x-2">
                <PrimaryButton @click="approveEmail(email)" class="text-xs px-2 py-1">Approve</PrimaryButton>
                <PrimaryButton @click="openEditModal(email)" class="text-xs px-2 py-1">Edit & Approve</PrimaryButton>
                <SecondaryButton @click="openRejectModal(email)" class="text-xs px-2 py-1">Reject</SecondaryButton>
            </td>
        </tr>
        </tbody>
    </table>
</div>
```

Key changes:
- Added an overflow-x-auto div wrapper for horizontal scrolling if needed
- Changed table width to w-full to use full available space
- Reduced padding in cells from px-6 to px-4
- Updated data binding to use the new structure (email.project?.name, email.client?.name, etc.)
- Added fallback values with || 'N/A'
- Made buttons smaller with text-xs, px-2, py-1 classes
- Used flex and space-x-2 for the actions column

#### 3.3 Updated the openEditModal Function

Modified the `openEditModal` function to handle the simplified data structure:

```javascript
// Open edit modal
const openEditModal = (email) => {
    currentEmail.value = email;
    // With the simplified API, we need to handle the data differently
    editForm.project_id = email.project?.id;
    editForm.subject = email.subject;
    editForm.body = email.body;
    editErrors.value = {};
    showEditModal.value = true;
};
```

#### 3.4 Updated the Edit Modal

Simplified the edit modal to show only Project, client name(s), subject and body:

```html
<Modal :show="showEditModal" @close="showEditModal = false" max-width="3xl">
    <div class="p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Edit and Approve Email</h2>
        <div v-if="currentEmail">
            <form @submit.prevent="saveAndApproveEmail">
                <div class="mb-4">
                    <InputLabel for="project_id" value="Project" />
                    <select id="project_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="editForm.project_id" required>
                        <option value="" disabled>Select a Project</option>
                        <option v-for="project in assignedProjects" :key="project.id" :value="project.id">
                            {{ project.name }}
                        </option>
                    </select>
                    <InputError :message="editErrors.project_id ? editErrors.project_id[0] : ''" class="mt-2" />
                </div>

                <div class="mb-4">
                    <InputLabel for="client_name" value="Client" />
                    <div id="client_name" class="mt-1 p-2 border border-gray-300 rounded-md bg-gray-50">
                        {{ currentEmail.client?.name || 'N/A' }}
                    </div>
                </div>

                <div class="mb-4">
                    <InputLabel for="subject" value="Subject" />
                    <TextInput id="subject" type="text" class="mt-1 block w-full" v-model="editForm.subject" required />
                    <InputError :message="editErrors.subject ? editErrors.subject[0] : ''" class="mt-2" />
                </div>

                <div class="mb-6">
                    <InputLabel for="body" value="Email Body" />
                    <RichTextEditor
                        id="body"
                        v-model="editForm.body"
                        placeholder="Edit your email here..."
                        height="300px"
                    />
                    <InputError :message="editErrors.body ? editErrors.body[0] : ''" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end space-x-2">
                    <SecondaryButton @click="showEditModal = false">Cancel</SecondaryButton>
                    <PrimaryButton type="submit">Save & Approve</PrimaryButton>
                </div>
            </form>
        </div>
    </div>
</Modal>
```

Key changes:
- Simplified the Project dropdown to only show project names
- Replaced the client email input with a read-only display of the client name
- Kept the subject input and rich text editor for the body

#### 3.5 Updated the saveAndApproveEmail Function

Modified the `saveAndApproveEmail` function to work with the simplified data structure:

```javascript
// Save and approve email
const saveAndApproveEmail = async () => {
    editErrors.value = {};
    generalError.value = '';
    try {
        const payload = {
            project_id: editForm.project_id,
            subject: editForm.subject,
            body: editForm.body,
        };
        await window.axios.post(`/api/emails/${currentEmail.value.id}/edit-and-approve`, payload);
        successMessage.value = 'Email updated and approved successfully!';
        showEditModal.value = false;
        await fetchInitialData();
    } catch (error) {
        if (error.response && error.response.status === 422) {
            editErrors.value = error.response.data.errors;
        } else if (error.response && error.response.data.message) {
            generalError.value = error.response.data.message;
        } else {
            generalError.value = 'Failed to update and approve email.';
            console.error('Error updating and approving email:', error);
        }
    }
};
```

Key changes:
- Removed the client_id from the payload
- Only sends project_id, subject, and body in the payload

## Testing

### Backend API Test

A PHP script (`test-pending-approval-simplified.php`) was created to test the simplified pending approval API endpoint. The script:

1. Authenticates as a super admin user
2. Makes a request to the simplified pending approval endpoint
3. Checks if the response is an array
4. If there are no pending approval emails, creates a test one
5. Checks the structure of the first email to ensure it has all the required fields
6. Verifies that the project, client, and sender fields have the correct structure
7. Prints the email data for manual verification

To run the test script:

```bash
php test-pending-approval-simplified.php
```

### Frontend UI Test

A JavaScript script (`test-pending-approval-simplified-frontend.js`) was created to test the frontend UI changes. The script:

1. Gets the Vue component instance for PendingApprovals.vue
2. Tests that the component is using the simplified API endpoint
3. Verifies that the table displays the correct fields
4. Checks that the edit modal shows only Project, client name(s), subject and body
5. Verifies that the saveAndApproveEmail function sends the correct payload

To run the test script:

1. Navigate to the Pending Approvals page in the browser
2. Open the browser console (F12 or right-click > Inspect > Console)
3. Copy and paste the contents of `test-pending-approval-simplified-frontend.js` into the console
4. Press Enter to run the tests

## Impact

This implementation:

1. Reduces the amount of data transferred between the server and client
2. Improves the performance of the page by loading only the necessary data
3. Makes the UI more user-friendly by displaying only the required information
4. Fixes the layout issues, preventing the reject button from being cut off
5. Simplifies the edit modal, making it easier to use

## Related Files

- `app/Http/Controllers/Api/EmailController.php` - Added the `pendingApprovalSimplified()` method
- `routes/api.php` - Added a new route for the simplified pending approval endpoint
- `resources/js/Pages/Emails/PendingApprovals.vue` - Updated to use the simplified endpoint and improve the UI
- `test-pending-approval-simplified.php` - Backend API test script
- `test-pending-approval-simplified-frontend.js` - Frontend UI test script
