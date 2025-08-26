# Rejected Email Modal Multi-Select Dropdown Implementation

## Overview

This document describes the implementation of a multi-select dropdown for the "To (Client Email)" field in the Rejected Email modal, similar to the one in the Composer.vue component. This change allows users to select multiple clients when editing a rejected email.

## Background

Previously, the Rejected Email modal had a read-only text input for the "To (Client Email)" field, which only displayed a single email address. The requirement was to update this to a multi-select dropdown similar to the one in the Composer.vue component, allowing users to select multiple clients and prefilling it with the existing client email information.

## Implementation Details

### Changes Made

#### 1. Added Required Imports

Added imports for the vue-multiselect component and its CSS:

```javascript
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';
```

#### 2. Updated Data Model

Updated the editForm reactive object to use client_ids array instead of to string:

```javascript
const editForm = reactive({
    project_id: '',
    client_id: '',
    client_ids: [], // Array for multi-select
    subject: '',
    body: '',
});
```

#### 3. Added filteredClients Computed Property

Added a computed property to filter clients based on the selected project:

```javascript
// Filter clients based on selected project
const filteredClients = computed(() => {
    if (!editForm.project_id) {
        return [];
    }
    
    const selectedProject = projects.value.find(p => p.id === editForm.project_id);
    if (!selectedProject || !selectedProject.clients) {
        return [];
    }
    
    const projectClientIds = selectedProject.clients.map(c => c.id);
    return clients.value.filter(client => projectClientIds.includes(client.id));
});
```

#### 4. Updated Email Recipient Handling

Updated the getRecipientEmail function to getRecipientEmails (now returning an array) and added a findClientsByEmails function:

```javascript
// Parse the 'to' field, handling both plain strings, JSON-encoded arrays, and direct arrays
const getRecipientEmails = (email) => {
    if (!email || !email.to) return [];

    // If email.to is already an array, return it
    if (Array.isArray(email.to)) {
        return email.to;
    }

    // If email.to is a string, try to parse it as JSON
    if (typeof email.to === 'string') {
        try {
            // Try parsing as JSON (e.g., "[\"email@example.com\"]")
            const parsed = JSON.parse(email.to);
            return Array.isArray(parsed) ? parsed : [email.to];
        } catch (e) {
            // If parsing fails, assume it's a plain email string
            return [email.to];
        }
    }

    return [];
};

// Find client objects based on email addresses
const findClientsByEmails = (emailAddresses) => {
    if (!Array.isArray(clients.value)) return [];
    
    return emailAddresses.map(email => {
        const client = clients.value.find(c => c.email === email);
        return client ? client : null;
    }).filter(client => client !== null);
};
```

#### 5. Updated openEditModal Function

Updated the openEditModal function to set client_ids based on the email's recipients:

```javascript
// Open edit modal
const openEditModal = (email) => {
    currentEmail.value = email;
    editForm.project_id = email.conversation.project_id;
    editForm.client_id = email.conversation.client_id;
    editForm.subject = email.subject;
    editForm.body = email.body;
    
    // Get email recipients and find corresponding client objects
    const emailAddresses = getRecipientEmails(email);
    const clientObjects = findClientsByEmails(emailAddresses);
    
    // Set client_ids to the found client objects
    editForm.client_ids = clientObjects;
    
    editErrors.value = {};
    showEditModal.value = true;
};
```

#### 6. Updated saveEditedEmail Function

Updated the saveEditedEmail function to format client_ids correctly for submission:

```javascript
// Save edited email
const saveEditedEmail = async () => {
    editErrors.value = {};
    generalError.value = '';
    
    if (!editForm.client_ids || editForm.client_ids.length === 0) {
        editErrors.value.client_ids = ['Please select at least one client.'];
        return;
    }
    
    try {
        // Format client_ids as array of objects with id property
        const formattedClientIds = editForm.client_ids.map(clientId => {
            // Check if clientId is already an object with an id property
            if (typeof clientId === 'object' && clientId !== null) {
                return { id: clientId.id };
            }
            // Otherwise, assume it's a simple ID value
            return { id: clientId };
        });
        
        const payload = {
            project_id: editForm.project_id,
            client_ids: formattedClientIds,
            subject: editForm.subject,
            body: editForm.body,
        };
        await window.axios.put(`/api/emails/${currentEmail.value.id}`, payload);
        successMessage.value = 'Email updated successfully! You can now resubmit it.';
        await fetchInitialData();
    } catch (error) {
        // Error handling...
    }
};
```

#### 7. Updated Watch Function

Updated the watch function to clear selected clients when the project changes:

```javascript
// Watch for project selection change to clear selected clients
watch(() => editForm.project_id, (newProjectId) => {
    // Always clear selected clients when project changes
    editForm.client_ids = [];
});
```

#### 8. Updated Template

Replaced the TextInput with a Multiselect component in the template:

```html
<div class="mb-4">
    <InputLabel for="client_ids" value="To (Clients)" />
    <Multiselect
        id="client_ids"
        v-model="editForm.client_ids"
        :options="filteredClients"
        :multiple="true"
        :close-on-select="true"
        :clear-on-select="false"
        :preserve-search="true"
        placeholder="Select one or more clients"
        label="name"
        track-by="id"
        :searchable="true"
        :allow-empty="true"
    >
        <template #option="{ option }">
            {{ option.name }}
        </template>
        <template #tag="{ option, remove }">
            <span class="multiselect__tag">
                {{ option.name }}
                <i class="multiselect__tag-icon" @click="remove(option)"></i>
            </span>
        </template>
    </Multiselect>
    <InputError :message="editErrors.client_ids ? editErrors.client_ids[0] : ''" class="mt-2" />
</div>
```

#### 9. Added CSS Styling

Added CSS styling for the Multiselect component:

```css
.multiselect {
    min-height: 38px;
}
.multiselect__tags {
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    padding: 0.5rem;
}
.multiselect__tag {
    background: #e5e7eb;
    color: #374151;
}
.multiselect__tag-icon:after {
    color: #6b7280;
}
```

### How It Works

1. When a user clicks the View/Edit button for a rejected email, the openEditModal function is called.
2. The function sets the editForm fields based on the email's data, including finding client objects based on the email's recipients.
3. The modal opens, displaying a multi-select dropdown for the "To (Clients)" field, prefilled with the found client objects.
4. The filteredClients computed property ensures that only clients related to the selected project are displayed in the dropdown.
5. When the user changes the selected project, the watch function clears the selected clients.
6. When the user saves the edited email, the saveEditedEmail function formats the client_ids correctly for submission.

## Testing

A test script (`test-rejected-email-multiselect.js`) has been created to verify that the multi-select dropdown works correctly. The script:

1. Tests that the filteredClients computed property works correctly
2. Tests that the openEditModal function sets client_ids correctly
3. Tests that the saveEditedEmail function formats client_ids correctly

To run the test script:

1. Navigate to the Rejected Emails page in the browser
2. Open the browser console (F12 or right-click > Inspect > Console)
3. Copy and paste the contents of `test-rejected-email-multiselect.js` into the console
4. Press Enter to run the tests

Expected results:
- The filteredClients computed property should return an empty array when no project is selected
- The openEditModal function should set client_ids correctly based on the email's recipients
- The saveEditedEmail function should format client_ids correctly as an array of objects with id property

## Impact

This change improves the user experience when editing rejected emails by:

1. Allowing users to select multiple clients, similar to the Composer.vue component
2. Filtering clients based on the selected project, preventing validation errors
3. Prefilling the dropdown with the existing client email information

## Related Files

- `resources/js/Pages/Emails/Rejected.vue` - Contains all the changes described above
- `resources/js/Pages/Emails/Composer.vue` - Used as a reference for implementing the multi-select dropdown
- `test-rejected-email-multiselect.js` - Test script to verify the implementation
