# Availability Blocking Feature Documentation

## Overview

This document describes the implementation of the availability blocking feature, which ensures that users submit their weekly availability on time. The feature has two main components:

1. **Availability Prompt**: Shows a prompt between Thursday and Saturday, allowing users to submit or update their availability for the next week.
2. **Availability Blocker**: Blocks users from accessing other features if they haven't submitted their availability by the end of Thursday.

## Requirements

The feature was implemented based on the following requirements:

1. Update the `shouldShowPrompt` method to keep showing the prompt between Thursday and Saturday even when the user submits data, so they can update if they want to.
2. Implement frontend logic that if users haven't updated by the end of Thursday, they can't do anything until they submit their next week availability.

## Implementation Details

### Backend Changes

#### AvailabilityController.php

The `shouldShowPrompt` method was updated to:

1. Check if the current day is between Thursday and Saturday (inclusive)
2. Determine if the user should be blocked from other features
3. Return additional information in the response

```php
public function shouldShowPrompt()
{
    $user = Auth::user();
    $today = Carbon::now();
    $nextWeekStart = Carbon::now()->addWeek()->startOfWeek();
    $nextWeekEnd = Carbon::now()->addWeek()->endOfWeek();

    // Check if today is between Thursday and Saturday (inclusive)
    $isThursday = $today->dayOfWeek === Carbon::THURSDAY;
    $isFriday = $today->dayOfWeek === Carbon::FRIDAY;
    $isSaturday = $today->dayOfWeek === Carbon::SATURDAY;
    $isThursdayToSaturday = $isThursday || $isFriday || $isSaturday;

    // Get all availability entries for next week
    $nextWeekAvailabilities = UserAvailability::where('user_id', $user->id)
        ->whereBetween('date', [$nextWeekStart->format('Y-m-d'), $nextWeekEnd->format('Y-m-d')])
        ->get();

    // Extract the weekdays (1-5 for Monday-Friday) for which the user has submitted availability
    $weekdaysWithAvailability = [];
    foreach ($nextWeekAvailabilities as $availability) {
        $weekday = Carbon::parse($availability->date)->dayOfWeek;
        // Only consider weekdays (Monday to Friday, which are 1-5 in Carbon)
        if ($weekday >= 1 && $weekday <= 5) {
            $weekdaysWithAvailability[] = $weekday;
        }
    }

    // Count unique weekdays with availability
    $uniqueWeekdaysWithAvailability = array_unique($weekdaysWithAvailability);

    // Check if all weekdays (Monday to Friday) have at least one availability entry
    $allWeekdaysCovered = count($uniqueWeekdaysWithAvailability) >= 5;

    // Determine if the user should be blocked from other features
    // Block if it's after Thursday and they haven't submitted availability for all weekdays
    $isAfterThursday = $today->dayOfWeek > Carbon::THURSDAY || 
                      ($today->dayOfWeek === Carbon::THURSDAY && $today->hour >= 23 && $today->minute >= 59);
    $shouldBlockUser = $isAfterThursday && !$allWeekdaysCovered;

    // Always show prompt between Thursday and Saturday, regardless of submission status
    $shouldShowPrompt = $isThursdayToSaturday;

    return response()->json([
        'should_show_prompt' => $shouldShowPrompt,
        'should_block_user' => $shouldBlockUser,
        'next_week_start' => $nextWeekStart->format('Y-m-d'),
        'next_week_end' => $nextWeekEnd->format('Y-m-d'),
        'weekdays_covered' => $uniqueWeekdaysWithAvailability,
        'all_weekdays_covered' => $allWeekdaysCovered,
        'current_day' => $today->dayOfWeek,
        'is_thursday_to_saturday' => $isThursdayToSaturday
    ]);
}
```

### Frontend Changes

#### AvailabilityPrompt.vue

The AvailabilityPrompt component was updated to:

1. Store additional information from the API response
2. Display different messages based on the day and submission status
3. Emit an event and store data in localStorage for other components to access

Key changes:

```javascript
// State
const showPrompt = ref(false);
const loading = ref(true);
const nextWeekDates = ref([]);
const showAvailabilityModal = ref(false);
const error = ref('');
const shouldBlockUser = ref(false);
const allWeekdaysCovered = ref(false);
const currentDay = ref(null);
const isThursdayToSaturday = ref(false);
```

```javascript
// Check if we should show the prompt (between Thursday and Saturday)
const checkShouldShowPrompt = async () => {
    loading.value = true;
    error.value = '';

    try {
        // Ensure auth headers are set before making the request
        ensureAuthHeaders();

        const response = await axios.get('/api/availability-prompt');
        
        // Store all the values from the API response
        showPrompt.value = response.data.should_show_prompt;
        shouldBlockUser.value = response.data.should_block_user;
        allWeekdaysCovered.value = response.data.all_weekdays_covered;
        currentDay.value = response.data.current_day;
        isThursdayToSaturday.value = response.data.is_thursday_to_saturday;

        // Generate dates for next week if we should show the prompt
        if (showPrompt.value) {
            generateNextWeekDates(response.data.next_week_start, response.data.next_week_end);
        }

        // Store the blocking status in localStorage so other components can access it
        localStorage.setItem('shouldBlockUser', shouldBlockUser.value);
        localStorage.setItem('allWeekdaysCovered', allWeekdaysCovered.value);
        
        // Emit a custom event that can be listened to by other components
        const event = new CustomEvent('availability-status-updated', { 
            detail: { 
                shouldBlockUser: shouldBlockUser.value,
                allWeekdaysCovered: allWeekdaysCovered.value
            } 
        });
        window.dispatchEvent(event);
    } catch (error) {
        console.error('Error checking availability prompt:', error);
        showPrompt.value = false;

        if (error.response && error.response.status === 401) {
            error.value = 'Authentication error. Please refresh the page or log in again.';
        }
    } finally {
        loading.value = false;
    }
};
```

The template was updated to display different messages based on the day and submission status:

```html
<div v-if="showPrompt && !loading" 
     :class="[
        'border-l-4 p-4 mb-6 rounded-md shadow-sm',
        shouldBlockUser ? 'bg-red-50 border-red-500' : 'bg-indigo-50 border-indigo-500'
     ]">
    <div class="flex">
        <div class="flex-shrink-0">
            <svg v-if="shouldBlockUser" class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <svg v-else class="h-5 w-5 text-indigo-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
        </div>
        <div class="ml-3 flex-1 md:flex md:justify-between">
            <!-- Thursday - Not submitted or incomplete -->
            <p v-if="currentDay === 4 && !allWeekdaysCovered" class="text-sm text-indigo-700">
                Please submit your availability for next week. This helps in planning meetings and work schedules.
            </p>
            
            <!-- Thursday - Fully submitted -->
            <p v-else-if="currentDay === 4 && allWeekdaysCovered" class="text-sm text-green-700">
                Thank you for submitting your availability for next week! You can still update it if needed.
            </p>
            
            <!-- Friday/Saturday - Not submitted or incomplete -->
            <p v-else-if="(currentDay === 5 || currentDay === 6) && !allWeekdaysCovered" class="text-sm text-red-700 font-medium">
                <span class="font-bold">Action Required:</span> You must submit your availability for all weekdays of next week. 
                {{ shouldBlockUser ? 'Other features are currently blocked until you complete this task.' : '' }}
            </p>
            
            <!-- Friday/Saturday - Fully submitted -->
            <p v-else-if="(currentDay === 5 || currentDay === 6) && allWeekdaysCovered" class="text-sm text-green-700">
                Thank you for submitting your availability for next week! You can still update it if needed.
            </p>
            
            <p class="mt-3 text-sm md:mt-0 md:ml-6">
                <PrimaryButton 
                    @click="openAvailabilityModal" 
                    class="whitespace-nowrap"
                    :class="{ 'bg-red-600 hover:bg-red-700': shouldBlockUser }"
                >
                    {{ allWeekdaysCovered ? 'Update Availability' : 'Submit Availability' }}
                </PrimaryButton>
            </p>
        </div>
    </div>
</div>
```

#### AvailabilityBlocker.vue

A new component was created to block user actions if they haven't submitted availability by the end of Thursday:

```javascript
// Check if we should block the user
const checkShouldBlock = async () => {
    loading.value = true;
    
    // First check localStorage which is updated by AvailabilityPrompt
    const storedShouldBlock = localStorage.getItem('shouldBlockUser');
    const storedAllWeekdaysCovered = localStorage.getItem('allWeekdaysCovered');
    
    if (storedShouldBlock === 'true' && storedAllWeekdaysCovered === 'false') {
        shouldBlock.value = true;
        await fetchNextWeekDates();
    } else {
        // If not found in localStorage, fetch from API
        try {
            ensureAuthHeaders();
            const response = await axios.get('/api/availability-prompt');
            shouldBlock.value = response.data.should_block_user && !response.data.all_weekdays_covered;
            
            if (shouldBlock.value) {
                await fetchNextWeekDates();
            }
        } catch (error) {
            console.error('Error checking if user should be blocked:', error);
            shouldBlock.value = false;
        }
    }
    
    loading.value = false;
};
```

```html
<div v-if="shouldBlock && !loading" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-6 max-w-lg w-full mx-4">
        <div class="flex items-center mb-4">
            <div class="flex-shrink-0 mr-3">
                <svg class="h-8 w-8 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-900">Action Required</h2>
        </div>
        
        <p class="mb-6 text-gray-700">
            You must submit your availability for all weekdays of next week before you can continue using the application. 
            This is required for planning meetings and work schedules.
        </p>
        
        <div class="flex justify-center">
            <PrimaryButton 
                @click="openAvailabilityModal" 
                class="bg-red-600 hover:bg-red-700"
            >
                Submit Availability Now
            </PrimaryButton>
        </div>
    </div>
</div>
```

#### AuthenticatedLayout.vue

The AvailabilityBlocker component was integrated into the main layout:

```javascript
import AvailabilityBlocker from '@/Components/Availability/AvailabilityBlocker.vue';
```

```html
<div>
    <!-- Notification Container -->
    <NotificationContainer ref="notificationContainerRef" />
    
    <!-- Availability Blocker -->
    <AvailabilityBlocker />

    <div class="min-h-screen bg-gray-100">
        <!-- Rest of the layout -->
    </div>
</div>
```

## Testing

A test script has been created to verify the implementation: `test-availability-blocking.js`

To test the feature:

1. Navigate to the Dashboard page (/)
2. Open the browser console (F12 or right-click > Inspect > Console)
3. Copy and paste the content of `test-availability-blocking.js` into the console
4. Run `window.testAvailabilityBlocking.runTests()` to start the tests
5. To simulate different conditions, run `window.testAvailabilityBlocking.simulateDifferentConditions()`

### Manual Testing

To manually test the feature:

1. **Thursday Testing**:
   - On Thursday, navigate to the Dashboard
   - Verify that the availability prompt is displayed
   - Submit availability for some weekdays but not all
   - Verify that the prompt is still displayed with the appropriate message
   - Submit availability for all weekdays
   - Verify that the prompt is still displayed but with a "thank you" message

2. **Friday Testing**:
   - On Friday, if you haven't submitted availability for all weekdays:
     - Verify that the availability prompt is displayed with a warning message
     - Verify that the AvailabilityBlocker is displayed, blocking access to other features
     - Submit availability for all weekdays
     - Verify that the blocker is removed and the prompt shows a "thank you" message
   - On Friday, if you have submitted availability for all weekdays:
     - Verify that the availability prompt is displayed with a "thank you" message
     - Verify that the AvailabilityBlocker is not displayed

3. **Saturday Testing**:
   - Similar to Friday testing

## Conclusion

The availability blocking feature ensures that users submit their weekly availability on time by:

1. Showing a prompt between Thursday and Saturday, allowing users to submit or update their availability
2. Blocking users from accessing other features if they haven't submitted their availability by the end of Thursday

This helps in planning meetings and work schedules more effectively by ensuring that all users have submitted their availability for the next week.
