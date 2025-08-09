# Google Account Prompt Fix

## Issue Description
The API endpoint `/api/user/google-chat/check-credentials` was returning `{"has_credentials": true}`, but the "Connect your Google account" prompt was still showing on the dashboard.

## Root Cause
After investigating, I found that the GoogleAccountPrompt.vue component was correctly setting the `showPrompt` value based on the API response, but it wasn't using this value to conditionally render the prompt. The component had a comment "Always show during development" and was missing a `v-if` directive to hide the prompt when credentials exist.

## Changes Made

### 1. Added Conditional Rendering to GoogleAccountPrompt.vue

Modified the template section of the component to only show the prompt when `showPrompt` is true:

```vue
<template>
    <!-- Only show when user doesn't have valid Google credentials -->
    <div v-if="showPrompt" class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <!-- Existing content -->
    </div>
</template>
```

The component already had the correct logic to set `showPrompt` based on the API response:

```javascript
const checkGoogleCredentials = async () => {
    try {
        isLoading.value = true;
        const response = await axios.get('/api/user/google-chat/check-credentials');
        hasGoogleCredentials.value = response.data.has_credentials;
        showPrompt.value = !hasGoogleCredentials.value;
        console.log('Google credentials check:', { hasGoogleCredentials: hasGoogleCredentials.value, showPrompt: showPrompt.value });
    } catch (error) {
        console.error('Error checking Google credentials:', error);
        showErrorNotification('Failed to check Google account status');
        // If there's an error, we'll show the prompt anyway
        showPrompt.value = true;
    } finally {
        isLoading.value = false;
    }
};
```

## Testing
Created a test script (`test-google-chat-credentials.php`) to verify:
1. The API endpoint returns the correct response
2. The controller implementation is correct
3. The User::hasGoogleCredentials method works as expected

The test confirmed that the API endpoint correctly returns `{"has_credentials": true}` when the user has valid Google credentials.

## Summary
This fix ensures that the "Connect your Google account" prompt is only shown when the user doesn't have valid Google credentials, improving the user experience by not prompting users to connect their Google account when they already have valid credentials.
