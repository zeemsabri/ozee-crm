# Availability Calendar Authentication Fix

## Issue Description

When visiting the AvailabilityCalendar page, it works fine initially, but if the page is refreshed, an "Unauthenticated" error is returned from the API:

```
http://localhost:8000/api/availabilities?start_date=2025-07-20&end_date=2025-07-26&user_id=1
{
    "message": "Unauthenticated."
}
```

## Root Cause

The issue was caused by a race condition between setting authentication headers and making API requests when the page is refreshed:

1. The AvailabilityCalendar component makes API requests in its `onMounted` hook
2. The AuthenticatedLayout component sets authentication headers in its own `onMounted` hook
3. When the page is refreshed, the component lifecycle starts over
4. If the AvailabilityCalendar's API requests are made before the AuthenticatedLayout sets the headers, they will be unauthenticated

## Solution

The solution ensures that authentication headers are properly set before any API requests are made, even when the page is refreshed:

1. Added an `ensureAuthHeaders` function to the AvailabilityCalendar component that:
   - Gets the auth token from localStorage
   - Sets it in axios headers if it's not already set

2. Modified the `fetchAvailabilities` and `fetchUsers` functions to:
   - Call `ensureAuthHeaders` before making API requests
   - Add specific error handling for 401 (Unauthorized) errors

3. Updated the `onMounted` hook to:
   - Call `ensureAuthHeaders` as early as possible
   - Add a small delay before making API requests to ensure headers are set

## Changes Made

### 1. Added ensureAuthHeaders function

```javascript
// Ensure authentication headers are set
const ensureAuthHeaders = () => {
    const token = localStorage.getItem('authToken');
    if (token && !axios.defaults.headers.common['Authorization']) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        console.log('Auth headers set in AvailabilityCalendar');
    }
};
```

### 2. Modified fetchAvailabilities function

```javascript
const fetchAvailabilities = async () => {
    loading.value = true;
    error.value = '';
    
    try {
        // Ensure auth headers are set before making the request
        ensureAuthHeaders();
        
        // ... rest of the function ...
    } catch (err) {
        console.error('Error fetching availabilities:', err);
        if (err.response && err.response.status === 401) {
            error.value = 'Authentication error. Please refresh the page or log in again.';
        } else {
            error.value = 'Failed to load availabilities. Please try again.';
        }
    } finally {
        loading.value = false;
    }
};
```

### 3. Modified fetchUsers function

```javascript
const fetchUsers = async () => {
    try {
        // Ensure auth headers are set before making the request
        ensureAuthHeaders();
        
        // ... rest of the function ...
    } catch (err) {
        console.error('Error fetching users:', err);
        if (err.response && err.response.status === 401) {
            error.value = 'Authentication error. Please refresh the page or log in again.';
        }
    }
};
```

### 4. Updated onMounted hook

```javascript
onMounted(() => {
    // Ensure authentication headers are set as early as possible
    ensureAuthHeaders();
    
    // ... set up date range ...
    
    // Small delay to ensure auth headers are set before making API requests
    setTimeout(() => {
        fetchAvailabilities();
    }, 100);
});
```

### 5. Added data-test attribute for testing

```html
<div class="bg-white rounded-lg shadow-md p-6" data-test="availability-calendar">
```

## Testing

A test script has been created to verify the fix: `test-availability-refresh.js`

To test the fix:

1. Navigate to the Weekly Availability page (/availability)
2. Open the browser console (F12 or right-click > Inspect > Console)
3. Copy and paste the content of `test-availability-refresh.js` into the console
4. Check the console output for authentication status
5. Refresh the page
6. Run the script again to verify authentication persists after refresh

Expected behavior:
- Before refresh: Authentication should be working
- After refresh: Authentication should still be working, no "Unauthenticated" errors

## Additional Notes

This fix addresses the specific issue with the AvailabilityCalendar component, but the same pattern could be applied to other components that make API requests and experience similar authentication issues after page refresh.

The solution is robust because it:
1. Sets headers as early as possible in the component lifecycle
2. Adds a small delay before making API requests
3. Ensures headers are set immediately before each API request
4. Adds specific error handling for authentication errors
