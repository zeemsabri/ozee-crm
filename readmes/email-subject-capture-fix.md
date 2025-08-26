# Email Subject Capture Fix

## Issue
The email post API expects a subject field, but it wasn't being properly captured from the email preview response. When a user selects a template and previews an email, the response returns a subject that should be used when submitting the email.

```
email-preview-response
body_html: "Test body"
subject: "Project Update"
```

## Changes Made

1. Updated the `fetchPreview` function in `ComposeEmailModal.vue` to capture the subject from the preview response:

```javascript
// Before
const response = await window.axios.post(`/api/projects/${props.projectId}/email-preview`, {
    template_id: emailForm.template_id,
    client_id: emailForm.client_ids[0],
    dynamic_data: emailForm.dynamic_data,
});
previewContent.value = response.data.body_html;

// After
const response = await window.axios.post(`/api/projects/${props.projectId}/email-preview`, {
    template_id: emailForm.template_id,
    client_id: emailForm.client_ids[0],
    dynamic_data: emailForm.dynamic_data,
});
previewContent.value = response.data.body_html;
// Capture the subject from the preview response
if (response.data.subject) {
    emailForm.subject = response.data.subject;
}
```

2. Modified the `prepareFormData` function to only set the subject from the template if it hasn't already been set by the preview:

```javascript
// Before
// Set subject from template if available
if (selectedTemplate.value) {
    emailForm.subject = selectedTemplate.value.subject || '';
}

// After
// Set subject from template only if it hasn't been set by the preview
if (!emailForm.subject && selectedTemplate.value) {
    emailForm.subject = selectedTemplate.value.subject || '';
}
```

## Verification

Created and ran a test script to verify that the changes are working correctly, confirming that the subject is properly captured from the preview response and included in the email submission.

The test script output shows:
```
Subject captured from preview: Project Update
...
✅ Success: Subject "Project Update" is correctly included in the email submission
```

These changes ensure that when a user selects a template and previews an email, the subject from the preview response is captured and used when submitting the email, meeting the API's requirement for a subject field.
