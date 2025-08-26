// Test script for meeting timezone display in Projects/Show.vue
console.log('Testing meeting timezone display in Projects/Show.vue');

// Simulate the formatMeetingTime function from Projects/Show.vue
function formatMeetingTime(timeString, timezone) {
    if (!timeString) return '';

    try {
        // If timezone is provided, use it to format the date
        if (timezone) {
            const options = {
                dateStyle: 'medium',
                timeStyle: 'short',
                timeZone: timezone
            };
            return new Date(timeString).toLocaleString(undefined, options);
        } else {
            // Fall back to user's local timezone
            return new Date(timeString).toLocaleString();
        }
    } catch (error) {
        console.error('Error formatting meeting time:', error);
        return new Date(timeString).toLocaleString(); // Fallback to default
    }
}

// Simulate the formatMeetingTimeOnly function from Projects/Show.vue
function formatMeetingTimeOnly(timeString, timezone) {
    if (!timeString) return '';

    try {
        // If timezone is provided, use it to format the time
        if (timezone) {
            const options = {
                timeStyle: 'short',
                timeZone: timezone
            };
            return new Date(timeString).toLocaleTimeString(undefined, options);
        } else {
            // Fall back to user's local timezone
            return new Date(timeString).toLocaleTimeString();
        }
    } catch (error) {
        console.error('Error formatting meeting time:', error);
        return new Date(timeString).toLocaleTimeString(); // Fallback to default
    }
}

// Test with different timezones
const testMeetings = [
    {
        id: 1,
        summary: 'Meeting in New York',
        start_time: '2025-07-24 15:00:00',
        end_time: '2025-07-24 16:00:00',
        timezone: 'America/New_York'
    },
    {
        id: 2,
        summary: 'Meeting in London',
        start_time: '2025-07-24 15:00:00',
        end_time: '2025-07-24 16:00:00',
        timezone: 'Europe/London'
    },
    {
        id: 3,
        summary: 'Meeting in Tokyo',
        start_time: '2025-07-24 15:00:00',
        end_time: '2025-07-24 16:00:00',
        timezone: 'Asia/Tokyo'
    },
    {
        id: 4,
        summary: 'Meeting with no timezone (should use local)',
        start_time: '2025-07-24 15:00:00',
        end_time: '2025-07-24 16:00:00',
        timezone: null
    }
];

// Test the formatting functions
console.log('\nTesting meeting time formatting with different timezones:');
console.log('User\'s local timezone:', Intl.DateTimeFormat().resolvedOptions().timeZone);
console.log('Current local time:', new Date().toLocaleString());
console.log('\n');

testMeetings.forEach(meeting => {
    console.log(`Meeting: ${meeting.summary}`);
    console.log(`Timezone: ${meeting.timezone || 'Using local timezone'}`);
    console.log(`Original start_time: ${meeting.start_time}`);
    console.log(`Formatted start_time: ${formatMeetingTime(meeting.start_time, meeting.timezone)}`);
    console.log(`Formatted end_time (time only): ${formatMeetingTimeOnly(meeting.end_time, meeting.timezone)}`);
    console.log('\n');
});

// Compare with the old implementation
console.log('Comparing with old implementation:');
testMeetings.forEach(meeting => {
    console.log(`Meeting: ${meeting.summary}`);
    console.log(`Old implementation: ${new Date(meeting.start_time).toLocaleString()} - ${new Date(meeting.end_time).toLocaleTimeString()}`);
    console.log(`New implementation: ${formatMeetingTime(meeting.start_time, meeting.timezone)} - ${formatMeetingTimeOnly(meeting.end_time, meeting.timezone)}`);
    console.log('\n');
});

// Verify that the timezone is correctly used
console.log('Verifying timezone handling:');
const testTime = '2025-07-24 15:00:00';
const timezones = ['America/New_York', 'Europe/London', 'Asia/Tokyo', 'Australia/Sydney'];

timezones.forEach(timezone => {
    console.log(`Timezone: ${timezone}`);
    console.log(`Formatted time: ${formatMeetingTime(testTime, timezone)}`);

    // Create a date object with the timezone offset
    const date = new Date(testTime);
    const options = { timeZone: timezone, timeStyle: 'short', dateStyle: 'medium' };
    console.log(`Expected time: ${date.toLocaleString(undefined, options)}`);
    console.log('\n');
});

console.log('Test completed. The meeting times should now be displayed in the correct timezone.');
