# Rejected.vue View/Edit Button Fix

## Issue Description

When clicking the View/Edit button in the Rejected Emails page, the following error was occurring:

```
Uncaught (in promise) TypeError: clients.value.find is not a function
    at ComputedRefImpl.fn (Rejected.vue:48:36)
```

The error was happening in the `selectedProjectClient` computed property, which was trying to call the `find()` method on `clients.value` without checking if it was actually an array.

## Root Cause

The issue occurred because:

1. When the View/Edit button is clicked, it calls the `openEditModal` function
2. This function sets `editForm.project_id` to the project ID of the selected email
3. A watcher is triggered that uses the `selectedProjectClient` computed property
4. The `selectedProjectClient` computed property tries to use `clients.value.find()`
5. If `clients.value` is not properly initialized as an array (e.g., if the API call failed or hasn't completed), this causes the error

The specific problematic code was in the `selectedProjectClient` computed property:

```javascript
const selectedProjectClient = computed(() => {
    const project = projects.value.find(p => p.id === editForm.project_id);
    return project ? clients.value.find(c => c.id === project.client_id) : null;
});
```

## Solution

The solution was to add a check to ensure that `clients.value` is an array before calling the `find()` method on it:

```javascript
const selectedProjectClient = computed(() => {
    const project = projects.value.find(p => p.id === editForm.project_id);
    // Add null check for clients.value to ensure it's an array before calling find()
    return project && Array.isArray(clients.value) ? clients.value.find(c => c.id === project.client_id) : null;
});
```

This change prevents the error by ensuring that `find()` is only called when `clients.value` is actually an array. If `clients.value` is not an array (e.g., if it's null, undefined, or some other type), the computed property will return null instead of trying to call `find()`.

## Testing

A test script has been created to verify that the fix works correctly. The script can be run in the browser console when on the Rejected Emails page:

1. Navigate to the Rejected Emails page in the browser
2. Open the browser console (F12 or right-click > Inspect > Console)
3. Copy and paste the contents of `test-rejected-view-edit-button.js` into the console
4. Press Enter to run the test

The test script will:
- Find all View/Edit buttons on the page
- Get the Vue component instance for the Rejected.vue component
- Log the initial state of the component
- Simulate clicking the first View/Edit button
- Check if the selectedProjectClient computed property works without errors
- Close the modal and log the results

Expected results:
- No errors should occur when clicking the View/Edit button
- The selectedProjectClient computed property should work correctly
- The test should complete successfully

## Impact

This fix ensures that the View/Edit button in the Rejected Emails page works correctly, even if the clients data is not fully loaded or properly initialized. It prevents the "clients.value.find is not a function" error and improves the robustness of the application.

## Related Files

- `resources/js/Pages/Emails/Rejected.vue` - Contains the fix in the `selectedProjectClient` computed property
- `test-rejected-view-edit-button.js` - Test script to verify the fix
