// Test script for meeting timezone optimization
console.log('Testing meeting timezone optimization');

// Simulate the user's timezone detection
function detectUserTimezone() {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone;
    } catch (error) {
        console.error('Error detecting user timezone:', error);
        return 'UTC'; // Fallback if detection fails
    }
}

// Get the user's actual timezone
const userTimezone = detectUserTimezone();
console.log('User timezone detected:', userTimezone);

// Simulate the optimized formatMeetingTime function
function formatMeetingTime(timeString, targetTimezone = null) {
    if (!timeString) return '';

    try {
        // If the meeting's timezone matches the user's timezone, no conversion needed
        if (targetTimezone && targetTimezone === userTimezone) {
            console.log('OPTIMIZATION: Skipping timezone conversion because meeting timezone matches user timezone');
            // Just format the time in the user's local timezone without explicit conversion
            const options = {
                dateStyle: 'medium',
                timeStyle: 'short'
            };
            return new Date(timeString).toLocaleString(undefined, options);
        }

        // Otherwise, perform the conversion to the specified timezone
        console.log('CONVERSION: Converting time to specified timezone:', targetTimezone || userTimezone);
        const options = {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: targetTimezone || userTimezone
        };
        return new Date(timeString).toLocaleString(undefined, options);
    } catch (error) {
        console.error('Error formatting meeting time:', error);
        return new Date(timeString).toLocaleString(); // Fallback to default (user's local)
    }
}

// Simulate the optimized formatMeetingTimeOnly function
function formatMeetingTimeOnly(timeString, targetTimezone = null) {
    if (!timeString) return '';

    try {
        // If the meeting's timezone matches the user's timezone, no conversion needed
        if (targetTimezone && targetTimezone === userTimezone) {
            console.log('OPTIMIZATION: Skipping timezone conversion because meeting timezone matches user timezone');
            // Just format the time in the user's local timezone without explicit conversion
            const options = {
                timeStyle: 'short'
            };
            return new Date(timeString).toLocaleTimeString(undefined, options);
        }

        // Otherwise, perform the conversion to the specified timezone
        console.log('CONVERSION: Converting time to specified timezone:', targetTimezone || userTimezone);
        const options = {
            timeStyle: 'short',
            timeZone: targetTimezone || userTimezone
        };
        return new Date(timeString).toLocaleTimeString(undefined, options);
    } catch (error) {
        console.error('Error formatting meeting time (time only):', error);
        return new Date(timeString).toLocaleTimeString(); // Fallback to default (user's local)
    }
}

// Test with meetings in different timezones
const testMeetings = [
    {
        id: 1,
        summary: 'Meeting in user timezone',
        start_time: '2025-07-24T16:54:00.000000Z',
        end_time: '2025-07-24T17:54:00.000000Z',
        timezone: userTimezone // Same as user's timezone
    },
    {
        id: 2,
        summary: 'Meeting in different timezone',
        start_time: '2025-07-24T16:57:00.000000Z',
        end_time: '2025-07-24T17:57:00.000000Z',
        timezone: userTimezone === 'America/New_York' ? 'Europe/London' : 'America/New_York' // Different from user's timezone
    },
    {
        id: 3,
        summary: 'Meeting with no timezone',
        start_time: '2025-07-24T15:00:00.000000Z',
        end_time: '2025-07-24T16:00:00.000000Z',
        timezone: null // No timezone specified
    }
];

// Test the formatting functions
console.log('\nTesting timezone optimization:');
testMeetings.forEach(meeting => {
    console.log(`\nMeeting: ${meeting.summary}`);
    console.log(`Meeting timezone: ${meeting.timezone || 'Not specified'}`);
    console.log(`User timezone: ${userTimezone}`);
    console.log(`Optimization should be applied: ${meeting.timezone === userTimezone}`);

    console.log('\nFormatting start time:');
    const formattedStartTime = formatMeetingTime(meeting.start_time, meeting.timezone);
    console.log(`Formatted start time: ${formattedStartTime}`);

    console.log('\nFormatting end time (time only):');
    const formattedEndTime = formatMeetingTimeOnly(meeting.end_time, meeting.timezone);
    console.log(`Formatted end time: ${formattedEndTime}`);

    console.log('-----------------------------------');
});

// Compare performance (simplified)
console.log('\nPerformance comparison (simplified):');

// Function without optimization
function formatWithoutOptimization(timeString, targetTimezone) {
    const options = {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: targetTimezone || userTimezone
    };
    return new Date(timeString).toLocaleString(undefined, options);
}

// Function with optimization
function formatWithOptimization(timeString, targetTimezone) {
    if (targetTimezone && targetTimezone === userTimezone) {
        const options = {
            dateStyle: 'medium',
            timeStyle: 'short'
        };
        return new Date(timeString).toLocaleString(undefined, options);
    }

    const options = {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: targetTimezone || userTimezone
    };
    return new Date(timeString).toLocaleString(undefined, options);
}

// Test with meeting in user's timezone
const testTime = '2025-07-24T16:54:00.000000Z';

console.log('Testing with meeting in user timezone:');
console.time('Without optimization');
for (let i = 0; i < 1000; i++) {
    formatWithoutOptimization(testTime, userTimezone);
}
console.timeEnd('Without optimization');

console.time('With optimization');
for (let i = 0; i < 1000; i++) {
    formatWithOptimization(testTime, userTimezone);
}
console.timeEnd('With optimization');

console.log('\nTest completed. The optimization should skip timezone conversion when the meeting timezone matches the user timezone.');
