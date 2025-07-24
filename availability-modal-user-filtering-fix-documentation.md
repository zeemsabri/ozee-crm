# Availability Modal User Filtering Fix

## Issue Description
In the AvailabilityModal.vue component, we were fetching all availabilities instead of filtering for a single user. This could lead to performance issues and potentially display incorrect data, especially for admin/manager users who have access to all users' availabilities.

## Changes Made

1. Added a new `userId` prop to the AvailabilityModal.vue component:
```javascript
// User ID to fetch availabilities for
userId: {
    type: Number,
    default: null
}
```

2. Modified the fetchExistingAvailabilities function to include the user_id parameter in the API call:
```javascript
// Fetch existing availabilities for the date range for a specific user
const response = await axios.get('/api/availabilities', {
    params: {
        start_date: startDate,
        end_date: endDate,
        user_id: props.userId // This will ensure we only fetch availabilities for the specified user
    }
});
```

3. Updated the parent components to pass the current user's ID to the AvailabilityModal component:

   a. AvailabilityBlocker.vue:
   ```javascript
   // Added imports
   import { usePage } from '@inertiajs/vue3';
   import { computed } from 'vue';

   // Added computed property to get current user ID
   const currentUserId = computed(() => {
       return usePage().props.auth.user?.id || null;
   });

   // Updated AvailabilityModal component in template
   <AvailabilityModal
       :show="showAvailabilityModal"
       :next-week-dates="nextWeekDates"
       :date="nextWeekDates.length > 0 ? nextWeekDates[0].value : ''"
       :userId="currentUserId"
       @close="showAvailabilityModal = false"
       @availability-saved="handleAvailabilitySaved"
   />
   ```

   b. AvailabilityCalendar.vue:
   ```javascript
   // Updated AvailabilityModal component in template to pass selectedUserId
   <AvailabilityModal
       :show="showAvailabilityModal"
       :date="selectedDate"
       :userId="selectedUserId"
       @close="showAvailabilityModal = false"
       @availability-saved="handleAvailabilitySaved"
   />
   ```

   c. AvailabilityPrompt.vue:
   ```javascript
   // Added imports
   import { usePage } from '@inertiajs/vue3';
   import { computed } from 'vue';

   // Added computed property to get current user ID
   const currentUserId = computed(() => {
       return usePage().props.auth.user?.id || null;
   });

   // Updated AvailabilityModal component in template
   <AvailabilityModal
       :show="showAvailabilityModal"
       :next-week-dates="nextWeekDates"
       :date="nextWeekDates.length > 0 ? nextWeekDates[0].value : ''"
       :userId="currentUserId"
       @close="showAvailabilityModal = false"
       @availability-saved="handleAvailabilitySaved"
   />
   ```

## How This Fixes the Issue

The AvailabilityController.php already had logic to filter availabilities by user_id if it was provided in the request. However, the AvailabilityModal.vue component wasn't including this parameter in its API call, which meant that for admin/manager users, it was fetching all availabilities instead of just the ones for a specific user.

By adding the userId prop and including it in the API call, we ensure that the component always fetches availabilities for a specific user only, even for admin/manager users who would otherwise see all availabilities.

## Usage

When using the AvailabilityModal component, you should now pass the userId prop to specify which user's availabilities to fetch:

```html
<AvailabilityModal
  :show="showModal"
  :userId="currentUserId"
  @close="showModal = false"
  @availability-saved="handleAvailabilitySaved"
/>
```

If no userId is provided, the component will use the default value (null), which will cause the backend to use the authenticated user's ID.

## Testing

A test script has been created to verify that the component correctly includes the user_id parameter in its API calls:

```javascript
// Test that the component includes the user_id parameter in API calls
it('fetches availabilities for a specific user only', async () => {
  // Mount the component with a specific userId
  const wrapper = mount(AvailabilityModal, {
    props: {
      show: true,
      userId: 123 // Specific user ID
    }
  });

  // Check that axios.get was called with the correct parameters
  expect(axios.get).toHaveBeenCalledWith('/api/availabilities', {
    params: {
      start_date: expect.any(String),
      end_date: expect.any(String),
      user_id: 123 // Should include the user_id parameter
    }
  });
});
```
