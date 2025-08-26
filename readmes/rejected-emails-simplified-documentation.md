# Rejected Emails Simplified Implementation

## Overview

This document describes the implementation of a simplified rejected emails feature that limits the information users have access to and prevents them from editing clients or email addresses during the rejection edit and resubmit process.

## Background

Previously, the rejected emails page displayed a lot of information that users shouldn't have access to, including project and client details. Users were also able to edit project and client selections when editing a rejected email. The requirement was to create a new API endpoint that provides only the necessary information (subject, body, rejection_reason, created_at) and to prevent users from editing clients or email addresses during the rejection edit and resubmit process.

## Implementation Details

### 1. Created a New API Endpoint

Added a new method `rejectedSimplified()` to the `EmailController` that returns only the required fields:

```php
/**
 * Display rejected emails with limited information.
 * Only returns subject, body, rejection_reason, created_at
 */
public function rejectedSimplified()
{
    $query = Auth::user()->isContractor()
        ? Email::where('sender_id', Auth::id())->where('status', '=', 'rejected')
        : Email::where('status', 'rejected');
    
    $emails = $query->get();
    
    // Transform the data to include only the required fields
    $simplifiedEmails = $emails->map(function ($email) {
        return [
            'id' => $email->id,
            'subject' => $email->subject,
            'body' => $email->body,
            'rejection_reason' => $email->rejection_reason,
            'created_at' => $email->created_at,
        ];
    });
    
    return response()->json($simplifiedEmails);
}
```

### 2. Added a New Route

Added a new route for the simplified rejected emails endpoint:

```php
Route::get('emails/rejected-simplified', [EmailController::class, 'rejectedSimplified']); // New route with limited information
```

### 3. Updated the Rejected.vue Component

#### 3.1 Updated the fetchInitialData Function

Modified the `fetchInitialData` function to use the new simplified endpoint:

```javascript
// Fetch initial data
const fetchInitialData = async () => {
    loading.value = true;
    generalError.value = '';
    try {
        // Use the simplified endpoint that returns only the required fields
        const emailsResponse = await window.axios.get('/api/emails/rejected-simplified');
        rejectedEmails.value = emailsResponse.data;
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

Removed the project and client columns from the table:

```html
<table class="min-w-full divide-y divide-gray-200">
    <thead class="bg-gray-50">
    <tr>
        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rejection Reason</th>
        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted On</th>
        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
    </tr>
    </thead>
    <tbody class="bg-white divide-y divide-gray-200">
    <tr v-for="email in rejectedEmails" :key="email.id">
        <td class="px-6 py-4 truncate max-w-xs">{{ email.subject }}</td>
        <td class="px-6 py-4 truncate max-w-xs">{{ email.rejection_reason }}</td>
        <td class="px-6 py-4 whitespace-nowrap">{{ new Date(email.created_at).toLocaleString() }}</td>
        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
            <PrimaryButton @click="openEditModal(email)">View/Edit</PrimaryButton>
        </td>
    </tr>
    </tbody>
</table>
```

#### 3.3 Updated the openEditModal Function

Simplified the `openEditModal` function to handle the simplified data structure:

```javascript
// Open edit modal
const openEditModal = (email) => {
    currentEmail.value = email;
    editForm.subject = email.subject;
    editForm.body = email.body;
    
    // With simplified data structure, we don't need to set project_id, client_id, or client_ids
    
    editErrors.value = {};
    showEditModal.value = true;
};
```

#### 3.4 Removed Project and Client Selection Fields from the Edit Modal

Removed the project and client selection fields from the edit modal:

```html
<form @submit.prevent="saveEditedEmail">
    <!-- Project and client selection fields removed as per requirements -->
    <!-- Users should not be allowed to edit clients or email addresses during rejection edit and resubmit process -->

    <div class="mb-4">
        <InputLabel for="subject" value="Subject" />
        <TextInput id="subject" type="text" class="mt-1 block w-full" v-model="editForm.subject" required />
        <InputError :message="editErrors.subject ? editErrors.subject[0] : ''" class="mt-2" />
    </div>

    <div class="mb-6">
        <InputLabel for="body" value="Email Body" />
        <textarea id="body" rows="10" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="editForm.body" required></textarea>
        <InputError :message="editErrors.body ? editErrors.body[0] : ''" class="mt-2" />
    </div>

    <div class="mt-6 flex justify-end space-x-2">
        <SecondaryButton @click="showEditModal = false">Cancel</SecondaryButton>
        <PrimaryButton type="submit">Save Changes</PrimaryButton>
        <PrimaryButton @click="resubmitEmail" v-if="successMessage" :disabled="!successMessage">Resubmit for Approval</PrimaryButton>
    </div>
</form>
```

#### 3.5 Updated the saveEditedEmail Function

Modified the `saveEditedEmail` function to only send subject and body in the payload:

```javascript
// Save edited email
const saveEditedEmail = async () => {
    editErrors.value = {};
    generalError.value = '';

    try {
        // Only send subject and body in the payload
        // Users should not be allowed to edit clients or email addresses during rejection edit and resubmit process
        const payload = {
            subject: editForm.subject,
            body: editForm.body,
        };
        await window.axios.put(`/api/emails/${currentEmail.value.id}`, payload);
        successMessage.value = 'Email updated successfully! You can now resubmit it.';
        await fetchInitialData();
    } catch (error) {
        if (error.response && error.response.status === 422) {
            editErrors.value = error.response.data.errors;
        } else if (error.response && error.response.data.message) {
            generalError.value = error.response.data.message;
        } else {
            generalError.value = 'Failed to update email.';
            console.error('Error updating email:', error);
        }
    }
};
```

#### 3.6 Updated the resubmitEmail Function

Ensured the `resubmitEmail` function works with the simplified data structure:

```javascript
// Resubmit email
const resubmitEmail = async () => {
    editErrors.value = {};
    generalError.value = '';
    try {
        // The resubmit endpoint only needs the email ID, which we still have in currentEmail.value.id
        await window.axios.post(`/api/emails/${currentEmail.value.id}/resubmit`);
        successMessage.value = 'Email resubmitted for approval successfully!';
        showEditModal.value = false;
        await fetchInitialData();
    } catch (error) {
        if (error.response && error.response.data.message) {
            generalError.value = error.response.data.message;
        } else {
            generalError.value = 'Failed to resubmit email.';
            console.error('Error resubmitting email:', error);
        }
    }
};
```

### 4. Created Test Scripts

#### 4.1 Backend Test Script

Created a PHP script (`test-rejected-simplified-api.php`) to test the simplified rejected emails API endpoint. The script:

1. Authenticates as a user
2. Makes a request to the simplified rejected emails endpoint
3. Checks if the response is an array
4. If there are no rejected emails, creates a test one
5. Checks the structure of the first email to ensure it only contains the required fields (id, subject, body, rejection_reason, created_at)

#### 4.2 Frontend Test Script

Created a JavaScript script (`test-rejected-simplified-frontend.js`) to test the simplified Rejected.vue component. The script:

1. Verifies that the component is using the simplified API endpoint
2. Checks that the table only displays the required fields (Subject, Rejection Reason, Submitted On, Actions)
3. Confirms that the edit modal doesn't have project and client selection fields
4. Verifies that the saveEditedEmail function only sends subject and body in the payload

## Testing

To test the backend implementation:

```bash
php test-rejected-simplified-api.php
```

To test the frontend implementation:

1. Navigate to the Rejected Emails page in the browser
2. Open the browser console (F12 or right-click > Inspect > Console)
3. Copy and paste the contents of `test-rejected-simplified-frontend.js` into the console
4. Press Enter to run the tests

## Impact

This implementation:

1. Limits the information users have access to on the rejected emails page
2. Prevents users from editing clients or email addresses during the rejection edit and resubmit process
3. Simplifies the UI by removing unnecessary fields
4. Improves security by restricting access to sensitive information

## Related Files

- `app/Http/Controllers/Api/EmailController.php` - Added the `rejectedSimplified()` method
- `routes/api.php` - Added a new route for the simplified rejected emails endpoint
- `resources/js/Pages/Emails/Rejected.vue` - Updated to use the simplified endpoint and restrict editing
- `test-rejected-simplified-api.php` - Backend test script
- `test-rejected-simplified-frontend.js` - Frontend test script
