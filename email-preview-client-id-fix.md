# Email Preview Client ID Fix

## Issue
The email preview endpoint was expecting a `client_id` parameter, but the ComposeEmailModal.vue component was sending `recipient_id` instead, causing the following error:

```json
{
    "message": "The client id field is required.",
    "errors": {
        "client_id": [
            "The client id field is required."
        ]
    }
}
```

## Changes Made
1. Updated the `fetchPreview` function in ComposeEmailModal.vue to use `client_id` instead of `recipient_id` when making requests to the email-preview endpoint:

```javascript
// Before
const response = await window.axios.post(`/api/projects/${props.projectId}/email-preview`, {
    template_id: emailForm.template_id,
    recipient_id: emailForm.client_ids[0],
    dynamic_data: emailForm.dynamic_data,
});

// After
const response = await window.axios.post(`/api/projects/${props.projectId}/email-preview`, {
    template_id: emailForm.template_id,
    client_id: emailForm.client_ids[0],
    dynamic_data: emailForm.dynamic_data,
});
```

## Verification
Created and ran a test script to verify that the changes are working correctly, confirming that `client_id` is now being sent in the request.

The test script output:
```
Testing email preview with client_id...
POST request to: /api/projects/3/email-preview
Request data: {
  "template_id": 1,
  "client_id": 2,
  "dynamic_data": {}
}
✅ Success: client_id is being sent correctly
Preview content: <p>This is a test email preview</p>
Test completed
```

These changes ensure that the ComposeEmailModal.vue component correctly communicates with the API endpoints that expect `client_id` parameters, resolving the issue.
