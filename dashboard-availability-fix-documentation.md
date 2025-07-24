# Dashboard Availability Fix Documentation

## Issues Description

Two issues were addressed in this fix:

1. **Authentication Error on Dashboard Refresh**: When refreshing the Dashboard page, the API endpoint `/api/availability-prompt` returned "Unauthenticated". This prevented the availability prompt from being displayed correctly after a page refresh.

2. **Batch Submission for Weekly Availability**: The "Submit your weekly availability" modal was updated to allow users to submit availability for multiple days at once, but the API endpoint to handle this batch submission was missing.

## Root Causes

### Authentication Error on Dashboard Refresh

The issue was caused by a race condition between setting authentication headers and making API requests when the page is refreshed:

1. The AvailabilityPrompt component makes an API request to `/api/availability-prompt` in its `onMounted` hook
2. The AuthenticatedLayout component sets authentication headers in its own `onMounted` hook
3. When the page is refreshed, the component lifecycle starts over
4. If the AvailabilityPrompt's API request is made before the AuthenticatedLayout sets the headers, it will be unauthenticated

### Missing Batch Submission Endpoint

The AvailabilityModal component was updated to collect and submit availability data for multiple days at once, but the API endpoint to handle this batch submission was missing. The component was trying to submit to `/api/availabilities/batch`, but this endpoint didn't exist.

## Solutions Implemented

### Authentication Fix for Dashboard Refresh

1. Added an `ensureAuthHeaders` function to the AvailabilityPrompt component that:
   - Gets the auth token from localStorage
   - Sets it in axios headers if it's not already set

2. Updated the `checkShouldShowPrompt` function to:
   - Call `ensureAuthHeaders` before making the API request
   - Add specific error handling for authentication errors

3. Added the same authentication fix to the AvailabilityModal component:
   - Added an `ensureAuthHeaders` function
   - Updated the `submitForm` function to call `ensureAuthHeaders` before making API requests
   - Updated the `onMounted` hook to call `ensureAuthHeaders` as early as possible

### Batch Submission Endpoint

1. Added a new `batch` method to the AvailabilityController that:
   - Validates an array of availability records
   - Processes each record, checking if it already exists and creating or updating it as needed
   - Returns a response with the processed availabilities

2. Added a new route to the API routes:
   ```php
   Route::post('availabilities/batch', [AvailabilityController::class, 'batch']);
   ```

## Files Changed

1. `/resources/js/Components/Availability/AvailabilityPrompt.vue`
   - Added `ensureAuthHeaders` function
   - Updated `checkShouldShowPrompt` to call `ensureAuthHeaders`
   - Added error handling for authentication errors

2. `/resources/js/Components/Availability/AvailabilityModal.vue`
   - Added `ensureAuthHeaders` function
   - Updated `submitForm` to call `ensureAuthHeaders`
   - Updated `onMounted` to call `ensureAuthHeaders`

3. `/app/Http/Controllers/Api/AvailabilityController.php`
   - Added `batch` method to handle multiple availability submissions

4. `/routes/api.php`
   - Added route for the batch endpoint

## Testing

Two test scripts have been created to verify the fixes:

### 1. Dashboard Authentication Refresh Test

The `test-dashboard-refresh.js` script tests the authentication behavior when refreshing the Dashboard page:

```javascript
// Run this in the browser console after navigating to the Dashboard page
// Then refresh the page and run it again to verify authentication persists
```

Expected behavior:
- Before refresh: Authentication should be working
- After refresh: Authentication should still be working, no "Unauthenticated" errors

### 2. Availability Batch Submission Test

The `test-availability-batch.js` script tests the batch submission functionality:

```javascript
// Run this in the browser console on any page of the application
```

Expected behavior:
- The script should successfully submit multiple availability records in a single request
- The script should then fetch the saved records to verify they were saved correctly
- All test records should be present in the fetched data

## Manual Testing

1. **Dashboard Refresh Test**:
   - Navigate to the Dashboard page
   - Verify that the availability prompt is displayed (if today is Thursday)
   - Refresh the page
   - Verify that the availability prompt is still displayed correctly

2. **Batch Submission Test**:
   - Navigate to the Dashboard page
   - Click the "Submit Availability" button in the prompt
   - Select multiple dates in the modal
   - Fill in availability information for each selected date
   - Click "Save Availability"
   - Verify that all selected dates are saved correctly

## Additional Notes

This fix addresses the specific issues with the AvailabilityPrompt and AvailabilityModal components, but the same pattern could be applied to other components that make API requests and experience similar authentication issues after page refresh.

The solution is robust because it:
1. Sets headers as early as possible in the component lifecycle
2. Ensures headers are set immediately before each API request
3. Adds specific error handling for authentication errors
4. Provides a flexible batch submission endpoint that can handle multiple availability records
