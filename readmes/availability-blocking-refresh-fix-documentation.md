# Availability Blocking Refresh Fix

## Issue Description
When a user is blocked from submitting availability, they continue to see the same blocked screen until they refresh the page. Additionally, if they access any other page and submit availability, even if they refresh the screen, the block screen doesn't disappear until they go to the dashboard.

## Root Cause
The issue was caused by the way the availability blocking components were handling state updates:

1. The `AvailabilityBlocker.vue` and `AvailabilityPrompt.vue` components were only checking the blocking status when they were first mounted.
2. When a user submitted availability on one page, the localStorage values were updated, but components on other pages weren't detecting these changes.
3. The custom event 'availability-status-updated' only works within the same page, not across different pages.

## Solution
The solution was to add event handlers to both components to detect:

1. When a page becomes visible (using the Page Visibility API)
2. When localStorage values change from other pages (using the Storage API)

This ensures that when a user navigates between pages or returns to a tab, the components will check if they should still block the user based on the latest localStorage values.

## Changes Made

### 1. AvailabilityBlocker.vue
- Added imports for `onUnmounted` and `nextTick`
- Added `handleVisibilityChange` function to check blocking status when page becomes visible
- Added `handleStorageChange` function to detect localStorage changes from other pages
- Added event listeners in `onMounted` hook
- Added cleanup in `onUnmounted` hook

```javascript
// Handle visibility change - check blocking status when page becomes visible
const handleVisibilityChange = () => {
    if (document.visibilityState === 'visible') {
        // When the page becomes visible, check if we should block
        nextTick(() => {
            checkShouldBlock();
        });
    }
};

// Handle storage changes from other tabs/windows
const handleStorageChange = (event) => {
    if (event.key === 'shouldBlockUser' || event.key === 'allWeekdaysCovered') {
        // When localStorage changes, check if we should block
        nextTick(() => {
            checkShouldBlock();
        });
    }
};

// Initialize component
onMounted(() => {
    checkShouldBlock();
    window.addEventListener('availability-status-updated', handleAvailabilityStatusUpdated);
    
    // Add event listeners for visibility and storage changes
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('storage', handleStorageChange);
});

// Clean up event listeners
onUnmounted(() => {
    window.removeEventListener('availability-status-updated', handleAvailabilityStatusUpdated);
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    window.removeEventListener('storage', handleStorageChange);
});
```

### 2. AvailabilityPrompt.vue
- Added imports for `onUnmounted` and `nextTick`
- Added `handleVisibilityChange` function to check prompt status when page becomes visible
- Added `handleStorageChange` function to detect localStorage changes from other pages
- Added event listeners in `onMounted` hook
- Added cleanup in `onUnmounted` hook

```javascript
// Handle visibility change - check prompt status when page becomes visible
const handleVisibilityChange = () => {
    if (document.visibilityState === 'visible') {
        // When the page becomes visible, check if we should show the prompt
        nextTick(() => {
            checkShouldShowPrompt();
        });
    }
};

// Handle storage changes from other tabs/windows
const handleStorageChange = (event) => {
    if (event.key === 'shouldBlockUser' || event.key === 'allWeekdaysCovered') {
        // When localStorage changes, check if we should show the prompt
        nextTick(() => {
            checkShouldShowPrompt();
        });
    }
};

// Initialize component
onMounted(() => {
    checkShouldShowPrompt();
    
    // Add event listeners for visibility and storage changes
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('storage', handleStorageChange);
});

// Clean up event listeners
onUnmounted(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    window.removeEventListener('storage', handleStorageChange);
});
```

## Testing
To test this fix:

1. Navigate to a page with the availability blocking screen
2. Open another page in a different tab
3. Submit availability on that page
4. Return to the first tab
5. The blocking screen should disappear without requiring a page refresh

## Notes
- The Page Visibility API is used to detect when a user returns to a tab
- The Storage API is used to detect when localStorage values change from other tabs/windows
- The `nextTick` function is used to ensure that the check happens after the Vue component has updated
