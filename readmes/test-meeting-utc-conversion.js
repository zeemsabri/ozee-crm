// Test script for meeting UTC conversion
console.log('Testing meeting UTC conversion');

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

// Simulate the formatMeetingTime function from ProjectMeetingsList.vue
function formatMeetingTime(timeString, targetTimezone = null, isUtc = false) {
    if (!timeString) return '';

    try {
        // If the time is in UTC format (new approach) or has 'Z' suffix
        if (isUtc || timeString.endsWith('Z')) {
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

            console.log('CONVERSION: Converting UTC time to specified timezone:', targetTimezone || userTimezone);
            // Otherwise, perform the conversion to the specified timezone
            const options = {
                dateStyle: 'medium',
                timeStyle: 'short',
                timeZone: targetTimezone || userTimezone // Use targetTimezone if provided, else user's timezone
            };
            // The timeString from backend is UTC.
            // new Date() correctly interprets UTC time.
            // .toLocaleString() then converts this UTC time to the specified timeZone.
            return new Date(timeString).toLocaleString(undefined, options);
        }
        // Legacy format (not UTC)
        else {
            // If the meeting's timezone matches the user's timezone, no conversion needed
            if (targetTimezone && targetTimezone === userTimezone) {
                console.log('LEGACY: Using local timezone without conversion');
                // Just format the time in the user's local timezone without explicit conversion
                const options = {
                    dateStyle: 'medium',
                    timeStyle: 'short'
                };
                return new Date(timeString).toLocaleString(undefined, options);
            }

            console.log('LEGACY: Converting time to specified timezone:', targetTimezone || userTimezone);
            // Otherwise, perform the conversion to the specified timezone
            const options = {
                dateStyle: 'medium',
                timeStyle: 'short',
                timeZone: targetTimezone || userTimezone
            };
            return new Date(timeString).toLocaleString(undefined, options);
        }
    } catch (error) {
        console.error('Error formatting meeting time:', error);
        return new Date(timeString).toLocaleString(); // Fallback to default (user's local)
    }
}

// Test with different meeting scenarios
console.log('\n1. Testing with UTC time (new approach):');
const utcMeeting = {
    id: 1,
    summary: 'UTC Meeting',
    start_time: '2025-07-24 16:00:00', // This is stored as UTC
    end_time: '2025-07-24 17:00:00',   // This is stored as UTC
    timezone: 'America/New_York',       // Display in New York timezone
    is_utc: true                       // Flag indicating times are in UTC
};

console.log('Meeting details:');
console.log('- Start time (UTC):', utcMeeting.start_time);
console.log('- Timezone:', utcMeeting.timezone);
console.log('- is_utc flag:', utcMeeting.is_utc);

console.log('\nFormatted start time:');
const formattedUtcTime = formatMeetingTime(utcMeeting.start_time, utcMeeting.timezone, utcMeeting.is_utc);
console.log('Result:', formattedUtcTime);
console.log('Expected: Time converted from UTC to America/New_York');

console.log('\n2. Testing with legacy time (non-UTC):');
const legacyMeeting = {
    id: 2,
    summary: 'Legacy Meeting',
    start_time: '2025-07-24 16:00:00', // This is stored in the original timezone
    end_time: '2025-07-24 17:00:00',   // This is stored in the original timezone
    timezone: 'Europe/London',         // Original timezone is London
    is_utc: false                      // Flag indicating times are not in UTC
};

console.log('Meeting details:');
console.log('- Start time (original timezone):', legacyMeeting.start_time);
console.log('- Timezone:', legacyMeeting.timezone);
console.log('- is_utc flag:', legacyMeeting.is_utc);

console.log('\nFormatted start time:');
const formattedLegacyTime = formatMeetingTime(legacyMeeting.start_time, legacyMeeting.timezone, legacyMeeting.is_utc);
console.log('Result:', formattedLegacyTime);
console.log('Expected: Time converted from Europe/London to user timezone');

console.log('\n3. Testing with UTC time (Z suffix):');
const utcZMeeting = {
    id: 3,
    summary: 'UTC Z Meeting',
    start_time: '2025-07-24T16:00:00.000000Z', // UTC time with Z suffix
    end_time: '2025-07-24T17:00:00.000000Z',   // UTC time with Z suffix
    timezone: 'Asia/Tokyo',                    // Display in Tokyo timezone
    is_utc: false                              // Even with is_utc false, Z suffix should trigger UTC handling
};

console.log('Meeting details:');
console.log('- Start time (UTC with Z):', utcZMeeting.start_time);
console.log('- Timezone:', utcZMeeting.timezone);
console.log('- is_utc flag:', utcZMeeting.is_utc);

console.log('\nFormatted start time:');
const formattedUtcZTime = formatMeetingTime(utcZMeeting.start_time, utcZMeeting.timezone, utcZMeeting.is_utc);
console.log('Result:', formattedUtcZTime);
console.log('Expected: Time converted from UTC to Asia/Tokyo');

console.log('\n4. Testing with user timezone matching meeting timezone:');
const userTimezoneMeeting = {
    id: 4,
    summary: 'User Timezone Meeting',
    start_time: '2025-07-24 16:00:00',        // This is stored as UTC
    end_time: '2025-07-24 17:00:00',          // This is stored as UTC
    timezone: userTimezone,                   // Same as user's timezone
    is_utc: true                              // Flag indicating times are in UTC
};

console.log('Meeting details:');
console.log('- Start time (UTC):', userTimezoneMeeting.start_time);
console.log('- Timezone:', userTimezoneMeeting.timezone);
console.log('- is_utc flag:', userTimezoneMeeting.is_utc);
console.log('- User timezone:', userTimezone);

console.log('\nFormatted start time:');
const formattedUserTimezoneTime = formatMeetingTime(userTimezoneMeeting.start_time, userTimezoneMeeting.timezone, userTimezoneMeeting.is_utc);
console.log('Result:', formattedUserTimezoneTime);
console.log('Expected: Optimization applied, no explicit conversion needed');

console.log('\nTest completed. The UTC conversion should work correctly for both new and legacy meeting times.');
