# Email Composer Client Objects Fix

## Issue
The EmailController's store function requires client_ids to be an array of objects with id properties, not just an array of IDs. The validation rule in the controller specifies:

```php
'client_ids' => 'required|array|min:1', // Ensure at least one client is selected
'client_ids.*.id' => 'required|exists:clients,id', // Validate each client ID
```

This means each client in the client_ids array should be an object with an 'id' property. Previously, the ComposeEmailModal.vue component was storing client_ids as an array of primitive IDs and only converting them to objects during form submission, which could cause issues if client data was needed earlier in the process.

## Changes Made

1. Modified the CustomMultiSelect component to store full client objects by adding the `:object-value="true"` prop:

```html
<CustomMultiSelect
    id="client_ids"
    v-model="emailForm.client_ids"
    :options="clients"
    placeholder="Select clients to send to"
    label-key="name"
    track-by="id"
    :preserve-search="true"
    :object-value="true"
    class="mt-1"
/>
```

2. Removed the transformation of client_ids in the prepareFormData function since they're already objects:

```javascript
// Before
const prepareFormData = async () => {
    // Format client_ids to be an array of objects with id property
    emailForm.client_ids = emailForm.client_ids.map(clientId => ({ id: clientId }));
    // ...
}

// After
const prepareFormData = async () => {
    // Client IDs should already be objects with id properties
    // No need to transform them here
    // ...
}
```

3. Updated the fetchPreview function to handle client objects:

```javascript
const fetchPreview = async () => {
    // ...
    try {
        const firstClient = emailForm.client_ids[0];
        const clientId = typeof firstClient === 'object' ? firstClient.id : firstClient;
        
        const response = await window.axios.post(`/api/projects/${props.projectId}/email-preview`, {
            template_id: emailForm.template_id,
            client_id: clientId,
            dynamic_data: emailForm.dynamic_data,
        });
        // ... process response
    } catch (error) {
        // ... handle error
    }
}
```

4. Updated the code that sets the greeting_name to handle both object and primitive ID cases:

```javascript
// Set greeting_name from the first selected client if available
if (emailForm.client_ids.length > 0) {
    const firstClient = emailForm.client_ids[0];
    // Handle both object and primitive ID cases
    if (typeof firstClient === 'object' && firstClient !== null) {
        emailForm.greeting_name = firstClient.name || '';
    } else {
        // If we still have primitive IDs, find the client in props.clients
        const clientObj = props.clients.find(client => client.id === firstClient);
        if (clientObj) {
            emailForm.greeting_name = clientObj.name || '';
        }
    }
}
```

## Verification

Created and ran a test script to verify that client_ids are correctly formatted as objects in the payload. The test confirmed that the client_ids array contains objects with id properties as required by the EmailController.

Test output:
```
✅ Success: client_ids is correctly formatted as an array of objects with id property
First client object: { id: 1, name: 'Client One' }
```

These changes ensure that the ComposeEmailModal.vue component correctly formats client_ids as objects with id properties, meeting the requirements of the EmailController's store method.
