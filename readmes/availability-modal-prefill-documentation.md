# Availability Modal Pre-filling Documentation

## Overview

This document describes the enhancement made to the AvailabilityModal component to pre-fill the form with existing availability data when a user opens the modal again after submitting partial availability data. This allows users to update their existing entries or add more entries until the end of Saturday.

## Issue Description

Previously, when a user submitted availability for some days of the week and then opened the modal again, their previously submitted data was not pre-filled. This made it difficult for users to remember which days they had already submitted availability for and to update their existing entries.

The enhancement addresses this issue by:
1. Fetching the user's existing availability data when the modal is opened
2. Pre-filling the form with this data
3. Adding visual indicators to show which dates already have entries
4. Allowing users to update existing entries and add new ones

## Implementation Details

### Fetching Existing Availability Data

A new `fetchExistingAvailabilities` method was added to the AvailabilityModal component. This method:
1. Gets the start and end dates from the `nextWeekDatesComputed` array
2. Fetches existing availabilities for this date range using the `/api/availabilities` endpoint
3. Updates the `dailyAvailabilities` object with the fetched data

```javascript
// Fetch existing availabilities for the date range
const fetchExistingAvailabilities = async () => {
    try {
        // Ensure auth headers are set before making the request
        ensureAuthHeaders();
        
        // Get the start and end dates from nextWeekDatesComputed
        if (nextWeekDatesComputed.value.length === 0) return;
        
        const startDate = nextWeekDatesComputed.value[0].value;
        const endDate = nextWeekDatesComputed.value[nextWeekDatesComputed.value.length - 1].value;
        
        // Fetch existing availabilities for the date range
        const response = await axios.get('/api/availabilities', {
            params: {
                start_date: startDate,
                end_date: endDate
            }
        });
        
        // Process the response
        if (response.data && response.data.availabilities) {
            const existingAvailabilities = response.data.availabilities;
            
            // Initialize dailyAvailabilities with default values
            nextWeekDatesComputed.value.forEach(dateObj => {
                dailyAvailabilities.value[dateObj.value] = {
                    isSelected: false,
                    isAvailable: true,
                    reason: '',
                    timeSlots: [{ start_time: '', end_time: '' }]
                };
            });
            
            // Update dailyAvailabilities with existing data
            existingAvailabilities.forEach(availability => {
                // Extract date part from ISO date string
                const dateString = availability.date.split('T')[0];
                
                if (dailyAvailabilities.value[dateString]) {
                    // Mark as selected since it already exists
                    dailyAvailabilities.value[dateString].isSelected = true;
                    dailyAvailabilities.value[dateString].isAvailable = availability.is_available;
                    
                    if (availability.is_available) {
                        // Copy time slots
                        dailyAvailabilities.value[dateString].timeSlots = 
                            availability.time_slots && availability.time_slots.length > 0 
                                ? [...availability.time_slots] 
                                : [{ start_time: '', end_time: '' }];
                    } else {
                        // Copy reason
                        dailyAvailabilities.value[dateString].reason = availability.reason || '';
                    }
                }
            });
        }
    } catch (error) {
        console.error('Error fetching existing availabilities:', error);
        // Initialize with default values if there's an error
        nextWeekDatesComputed.value.forEach(dateObj => {
            dailyAvailabilities.value[dateObj.value] = {
                isSelected: false,
                isAvailable: true,
                reason: '',
                timeSlots: [{ start_time: '', end_time: '' }]
            };
        });
    }
};
```

### Updating the Watch Handler

The watch handler for `props.show` was modified to call the `fetchExistingAvailabilities` method when the modal is opened:

```javascript
// Reset form when modal is opened/closed
watch(() => props.show, async (newValue) => {
    if (newValue) {
        // Modal opened - reset errors and success message
        errors.value = {};
        successMessage.value = '';
        
        // Fetch existing availabilities
        await fetchExistingAvailabilities();
    }
}, { immediate: true }); // Run immediately on component mount
```

### Visual Indicators for Existing Entries

The template was updated to add visual indicators for dates that already have entries:

1. A conditional class was added to the date card div:
   ```html
   <div 
       v-for="dateObj in nextWeekDatesComputed" 
       :key="dateObj.value" 
       class="mb-6 p-4 border rounded-lg shadow-sm"
       :class="[
           dailyAvailabilities[dateObj.value].isSelected ? 
               'border-indigo-300 bg-indigo-50' : 'border-gray-200',
           // Add a special class for dates that were pre-filled from existing data
           dailyAvailabilities[dateObj.value].isSelected && 
           !errors[dateObj.value] ? 
               'border-l-4 border-l-indigo-500' : ''
       ]"
   >
   ```

2. A "Saved" badge was added next to the date label:
   ```html
   <label :for="`select_date_${dateObj.value}`" class="ml-2 block text-base font-medium text-gray-800">
       {{ dateObj.label }}
       <!-- Badge for dates with existing data -->
       <span 
           v-if="dailyAvailabilities[dateObj.value].isSelected"
           class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800"
       >
           Saved
       </span>
   </label>
   ```

## Usage

The enhanced AvailabilityModal component works the same way as before from a user's perspective, but with the added benefit of pre-filling existing data:

1. When a user opens the modal, their existing availability data is automatically fetched and pre-filled.
2. Dates with existing entries are visually indicated with a blue border and a "Saved" badge.
3. Users can update their existing entries by modifying the pre-filled data.
4. Users can add new entries for days they haven't submitted availability for yet.
5. When the form is submitted, both new and updated entries are saved.

## Testing

A test script has been created to verify the pre-filling functionality: `test-availability-prefill.js`

To use the test script:
1. Navigate to the Dashboard page (/)
2. Open the browser console (F12 or right-click > Inspect > Console)
3. Copy and paste the content of `test-availability-prefill.js` into the console
4. Follow the instructions in the console output

The script will:
1. Create a test availability entry for tomorrow
2. Provide instructions for manually testing the pre-filling functionality
3. Provide a command to verify the updated entries in the API

## Benefits

The pre-filling functionality provides several benefits:

1. **Improved User Experience**: Users can see which dates they've already submitted availability for and which ones they still need to fill in.
2. **Reduced Errors**: Users are less likely to forget which days they've already submitted availability for.
3. **Increased Efficiency**: Users can update their existing entries without having to re-enter all the data.
4. **Better Visibility**: The visual indicators make it clear which dates already have entries.

## Limitations and Future Enhancements

1. **Conflict Resolution**: The current implementation doesn't handle conflicts if multiple users try to update the same availability entry. This could be addressed in a future enhancement.
2. **Offline Support**: The component requires an internet connection to fetch and save availability data. Offline support could be added in a future enhancement.
3. **Bulk Operations**: The component doesn't support bulk operations like copying availability from one day to another. This could be added in a future enhancement.

## Conclusion

The enhanced AvailabilityModal component provides a better user experience by pre-filling existing availability data and adding visual indicators for dates with existing entries. This makes it easier for users to update their existing entries and add new ones, improving the overall usability of the weekly availability feature.
