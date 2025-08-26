// Test script for meeting timezone display issue
console.log('Testing meeting timezone display issue');

// Example meeting data from the issue description
const meetings = [
    {
        id: 14,
        project_id: 11,
        created_by_user_id: 1,
        google_event_id: "skao7au2cf39fvq0q0g7psuhsk",
        google_event_link: "https://www.google.com/calendar/event?eid=c2thbzdhdTJjZjM5ZnZxMHEwZzdwc3Voc2sgaW5mb0BvemVld2ViLmNvbS5hdQ",
        google_meet_link: "https://meet.google.com/wtg-pdym-xrd",
        summary: "Second Meeting",
        description: null,
        start_time: "2025-07-24T16:54:00.000000Z",
        end_time: "2025-07-24T17:54:00.000000Z",
        location: null,
        timezone: "Australia/Perth",
        enable_recording: false,
        created_at: "2025-07-24T07:57:48.000000Z",
        updated_at: "2025-07-24T07:57:48.000000Z"
    },
    {
        id: 15,
        project_id: 11,
        created_by_user_id: 1,
        google_event_id: "rioumsrt27uphv103quc3bvpog",
        google_event_link: "https://www.google.com/calendar/event?eid=cmlvdW1zcnQyN3VwaHYxMDNxdWMzYnZwb2cgaW5mb0BvemVld2ViLmNvbS5hdQ",
        google_meet_link: "https://meet.google.com/cxy-thto-whs",
        summary: "Second Meeting 1",
        description: null,
        start_time: "2025-07-24T16:57:00.000000Z",
        end_time: "2025-07-24T17:57:00.000000Z",
        location: null,
        timezone: "Australia/Melbourne",
        enable_recording: false,
        created_at: "2025-07-24T07:59:14.000000Z",
        updated_at: "2025-07-24T07:59:14.000000Z"
    }
];

// Current implementation of formatMeetingTime
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

// Current implementation of formatMeetingTimeOnly
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

// Test the current implementation
console.log('\nTesting current implementation:');
meetings.forEach(meeting => {
    console.log(`Meeting: ${meeting.summary}`);
    console.log(`Timezone: ${meeting.timezone}`);
    console.log(`Start time (UTC): ${meeting.start_time}`);
    console.log(`Formatted start time: ${formatMeetingTime(meeting.start_time, meeting.timezone)}`);
    console.log(`Formatted end time (time only): ${formatMeetingTimeOnly(meeting.end_time, meeting.timezone)}`);
    console.log('\n');
});

// Expected output based on issue description
console.log('Expected output based on issue description:');
console.log('Meeting 1: 25 Jul 2025, 00:54 - 01:54 (Australia/Perth)');
console.log('Meeting 2: 25 Jul 2025, 02:57 - 03:57 (Australia/Melbourne)');
console.log('\n');

// Let's try to understand the timezone offsets
console.log('Timezone offsets:');
console.log('Australia/Perth UTC offset: +8 hours');
console.log('Australia/Melbourne UTC offset: +10 hours');
console.log('\n');

// Calculate expected times manually
console.log('Manual calculation:');
console.log('Meeting 1: 16:54 UTC + 8 hours = 00:54 next day in Perth');
console.log('Meeting 2: 16:57 UTC + 10 hours = 02:57 next day in Melbourne');
console.log('\n');

// Let's try a modified implementation that ensures the date is interpreted as UTC
function formatMeetingTimeFixed(timeString, timezone) {
    if (!timeString) return '';

    try {
        // Ensure the date is interpreted as UTC
        const utcDate = new Date(timeString);

        // If timezone is provided, use it to format the date
        if (timezone) {
            const options = {
                dateStyle: 'medium',
                timeStyle: 'short',
                timeZone: timezone
            };
            return utcDate.toLocaleString(undefined, options);
        } else {
            // Fall back to user's local timezone
            return utcDate.toLocaleString();
        }
    } catch (error) {
        console.error('Error formatting meeting time:', error);
        return new Date(timeString).toLocaleString(); // Fallback to default
    }
}

// Test the fixed implementation
console.log('Testing fixed implementation:');
meetings.forEach(meeting => {
    console.log(`Meeting: ${meeting.summary}`);
    console.log(`Timezone: ${meeting.timezone}`);
    console.log(`Start time (UTC): ${meeting.start_time}`);
    console.log(`Formatted start time: ${formatMeetingTimeFixed(meeting.start_time, meeting.timezone)}`);
    console.log('\n');
});

// Let's try another approach using explicit UTC parsing
function formatMeetingTimeExplicitUTC(timeString, timezone) {
    if (!timeString) return '';

    try {
        // Parse the UTC time string
        const [datePart, timePart] = timeString.split('T');
        const [year, month, day] = datePart.split('-').map(Number);
        const [hours, minutes] = timePart.split(':').map(Number);

        // Create a UTC date object
        const utcDate = new Date(Date.UTC(year, month - 1, day, hours, minutes));

        // If timezone is provided, use it to format the date
        if (timezone) {
            const options = {
                dateStyle: 'medium',
                timeStyle: 'short',
                timeZone: timezone
            };
            return utcDate.toLocaleString(undefined, options);
        } else {
            // Fall back to user's local timezone
            return utcDate.toLocaleString();
        }
    } catch (error) {
        console.error('Error formatting meeting time:', error);
        return new Date(timeString).toLocaleString(); // Fallback to default
    }
}

// Test the explicit UTC implementation
console.log('Testing explicit UTC implementation:');
meetings.forEach(meeting => {
    console.log(`Meeting: ${meeting.summary}`);
    console.log(`Timezone: ${meeting.timezone}`);
    console.log(`Start time (UTC): ${meeting.start_time}`);
    console.log(`Formatted start time: ${formatMeetingTimeExplicitUTC(meeting.start_time, meeting.timezone)}`);
    console.log('\n');
});
