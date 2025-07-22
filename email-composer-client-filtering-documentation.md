# Email Composer Client Filtering Implementation

## Overview

This document describes the implementation of client filtering in the Email Composer page. The changes ensure that only clients related to the selected project are displayed in the client multi-select dropdown, creating consistency and preventing validation errors.

## Background

Previously, the Email Composer page showed all clients in the multi-select dropdown, regardless of which project was selected. This could lead to users selecting clients that weren't related to the selected project, resulting in validation errors when submitting the form.

The requirement was to filter the clients dropdown to only show clients that are related to the selected project, ensuring consistency and preventing validation errors.

## Implementation Details

### Changes Made

#### 1. Added filteredClients computed property to Composer.vue

A new computed property called `filteredClients` was added to the Composer.vue component to filter clients based on the selected project:

```javascript
// Filter clients based on selected project
const filteredClients = computed(() => {
    if (!emailForm.project_id) {
        return [];
    }
    
    const selectedProject = projects.value.find(p => p.id === emailForm.project_id);
    if (!selectedProject || !selectedProject.clients) {
        return [];
    }
    
    const projectClientIds = selectedProject.clients.map(c => c.id);
    return clients.value.filter(client => projectClientIds.includes(client.id));
});
```

This computed property:
- Returns an empty array if no project is selected
- Finds the selected project from the projects array
- Extracts the client IDs from the selected project's clients
- Filters the clients array to only include clients that are related to the selected project

#### 2. Updated client multi-select to use filteredClients

The client multi-select component was updated to use the `filteredClients` computed property instead of all clients:

```html
<Multiselect
    id="client_ids"
    v-model="emailForm.client_ids"
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
```

This change ensures that only clients related to the selected project are displayed in the multi-select dropdown.

#### 3. Updated project selection watch function

The watch function for project_id was updated to always clear the selected clients when the project changes:

```javascript
// Watch for project selection change
watch(() => emailForm.project_id, (newProjectId) => {
    // Always clear selected clients when project changes
    emailForm.client_ids = [];
});
```

This change ensures that when a user switches from one project to another, they have to select clients that are related to the newly selected project.

#### 4. Updated submitEmailForApproval function

The submitEmailForApproval function was updated to format the client_ids as an array of objects with an id property, which matches what the server expects:

```javascript
try {
    // Format client_ids as array of objects with id property
    const formattedClientIds = emailForm.client_ids.map(clientId => {
        return { id: clientId };
    });

    const payload = {
        project_id: emailForm.project_id,
        client_ids: formattedClientIds,
        subject: emailForm.subject,
        body: emailForm.body,
        status: 'pending_approval',
    };

    await window.axios.post('/api/emails', payload);
    // ...
}
```

This change ensures that the client_ids are formatted correctly when submitting the form, matching the validation rules in the EmailController's store method.

### How It Works

1. When a user navigates to the Email Composer page, the component makes a request to the `/api/projects-for-email` endpoint to fetch projects and clients.
2. When a user selects a project from the dropdown, the `filteredClients` computed property filters the clients to only show those related to the selected project.
3. The client multi-select dropdown only displays clients that are related to the selected project.
4. If the user changes the selected project, the selected clients are cleared, forcing the user to select clients that are related to the newly selected project.
5. When the user submits the form, the client_ids are formatted correctly as an array of objects with an id property, matching what the server expects.

## Testing

A test script (`test-email-composer-client-filtering.js`) has been created to verify that the client filtering works correctly. The script:

1. Tests that the `filteredClients` computed property works correctly, showing only clients related to the selected project
2. Tests that switching projects clears the selected clients
3. Tests that the client_ids are formatted correctly when submitting the form

To run the test script:

1. Navigate to the Email Composer page in the browser
2. Open the browser console (F12 or right-click > Inspect > Console)
3. Copy and paste the contents of `test-email-composer-client-filtering.js` into the console
4. Press Enter to run the tests

Expected results:
- When no project is selected, filteredClients should be empty
- When a project is selected, filteredClients should only contain clients related to that project
- When switching projects, the selected clients should be cleared
- When submitting the form, the client_ids should be formatted correctly as an array of objects with an id property

## Impact on Other Parts of the Application

The changes made are isolated to the Composer.vue component and do not affect how clients are filtered or displayed in other parts of the application.

## Future Considerations

If similar filtering needs to be implemented in other parts of the application, a similar approach can be used:

1. Add a computed property to filter the data based on the selected item
2. Update the UI component to use the filtered data
3. Add a watch function to clear selections when the filter changes
4. Ensure the data is formatted correctly when submitting forms

This approach ensures that users only see and interact with data that is relevant to their current selection, preventing validation errors and improving the user experience.
