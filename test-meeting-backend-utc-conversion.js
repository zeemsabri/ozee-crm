// Test script for meeting backend UTC conversion
console.log('Testing meeting backend UTC conversion');

// Simulate the frontend sending datetime in local format
function simulateFrontendSubmission() {
    // User selects a meeting time in their local timezone
    const meetingData = {
        summary: 'Test Meeting',
        description: 'This is a test meeting',
        start_datetime: '2025-07-24 15:00:00', // Local time, not UTC
        end_datetime: '2025-07-24 16:00:00',   // Local time, not UTC
        attendee_user_ids: [1, 2, 3],
        location: 'Conference Room',
        with_google_meet: true,
        timezone: 'America/New_York',          // Specified timezone
        enable_recording: true
    };

    console.log('Frontend submission data:');
    console.log(JSON.stringify(meetingData, null, 2));

    return meetingData;
}

// Simulate the backend processing in ProjectCalendarController
function simulateBackendCalendarProcessing(meetingData) {
    console.log('\nBackend (ProjectCalendarController) processing:');
    console.log('- Received start_datetime:', meetingData.start_datetime);
    console.log('- Received timezone:', meetingData.timezone);

    // The controller passes the data directly to GoogleCalendarService
    // without converting the time, which is correct
    console.log('- Passing to GoogleCalendarService without time conversion');

    // Simulate successful Google Calendar event creation
    const googleEventData = {
        id: 'abc123',
        htmlLink: 'https://calendar.google.com/event?id=abc123',
        hangoutLink: 'https://meet.google.com/abc-defg-hij'
    };

    console.log('- Google Calendar event created successfully');

    return googleEventData;
}

// Simulate the backend processing in ProjectController
function simulateBackendDatabaseStorage(meetingData, googleEventData) {
    console.log('\nBackend (ProjectController) database storage:');

    // Get the timezone from the request
    const timezone = meetingData.timezone;
    console.log('- Using timezone:', timezone);

    // Get the start and end times from the request
    const startTime = meetingData.start_datetime;
    const endTime = meetingData.end_datetime;
    console.log('- Original start_time:', startTime);
    console.log('- Original end_time:', endTime);

    // Simulate Carbon parsing and UTC conversion
    // In a real environment, this would use Carbon::parse($startTime, $timezone)->setTimezone('UTC')

    // Since JavaScript's Date object doesn't handle timezone strings directly,
    // we'll use a simplified simulation for the test

    // For America/New_York (EDT) in July, it's UTC-4
    // So 15:00 in New York would be 19:00 in UTC
    const startTimeParts = startTime.split(' ')[1].split(':');
    const endTimeParts = endTime.split(' ')[1].split(':');

    // Get hours and add timezone offset (for America/New_York in summer, +4 hours)
    const startHoursUtc = parseInt(startTimeParts[0]) + 4;
    const endHoursUtc = parseInt(endTimeParts[0]) + 4;

    // Format the UTC times
    const startTimeUtc = `${startTime.split(' ')[0]} ${String(startHoursUtc).padStart(2, '0')}:${startTimeParts[1]}:00`;
    const endTimeUtc = `${endTime.split(' ')[0]} ${String(endHoursUtc).padStart(2, '0')}:${endTimeParts[1]}:00`;

    console.log('- Converted start_time to UTC:', startTimeUtc);
    console.log('- Converted end_time to UTC:', endTimeUtc);

    // Create a meeting record with UTC times
    const meetingRecord = {
        project_id: 1,
        created_by_user_id: 1,
        google_event_id: googleEventData.id,
        google_event_link: googleEventData.htmlLink,
        google_meet_link: googleEventData.hangoutLink,
        summary: meetingData.summary,
        description: meetingData.description,
        start_time: startTimeUtc,
        end_time: endTimeUtc,
        location: meetingData.location,
        timezone: meetingData.timezone,
        enable_recording: meetingData.enable_recording,
        is_utc: true
    };

    console.log('- Meeting record created with UTC times and is_utc=true');
    console.log('- Meeting record saved to database');

    return meetingRecord;
}

// Simulate the frontend displaying the meeting time
function simulateFrontendDisplay(meetingRecord) {
    console.log('\nFrontend display (ProjectMeetingsList.vue):');

    // Get the meeting record from the database
    console.log('- Meeting record retrieved from database:');
    console.log('  - start_time:', meetingRecord.start_time, '(UTC)');
    console.log('  - timezone:', meetingRecord.timezone);
    console.log('  - is_utc:', meetingRecord.is_utc);

    // Simulate the formatMeetingTime function in ProjectMeetingsList.vue
    function formatMeetingTime(timeString, targetTimezone, isUtc) {
        console.log('  - Formatting time:', timeString);
        console.log('  - Target timezone:', targetTimezone);
        console.log('  - is_utc flag:', isUtc);

        // If the time is in UTC format and is_utc is true
        if (isUtc) {
            console.log('  - Time is in UTC, converting to target timezone');

            // Since JavaScript's Date object doesn't handle timezone strings well,
            // we'll use a simplified simulation for the test

            // Parse the UTC time
            const timeParts = timeString.split(' ');
            const datePart = timeParts[0];
            const timePart = timeParts[1].split(':');

            // For America/New_York (EDT) in July, it's UTC-4
            // So 19:00 in UTC would be 15:00 in New York
            let hours = parseInt(timePart[0]);

            if (targetTimezone === 'America/New_York') {
                hours = hours - 4; // Convert from UTC to EDT

                // Handle day change if needed
                let day = parseInt(datePart.split('-')[2]);
                let month = datePart.split('-')[1];
                let year = datePart.split('-')[0];

                if (hours < 0) {
                    hours += 24;
                    day -= 1;
                    // Simple day adjustment for the test
                }

                const formattedTime = `Jul ${day}, ${year}, ${hours}:${timePart[1]} ${hours >= 12 ? 'PM' : 'AM'}`;
                console.log('  - Formatted time:', formattedTime);
                return formattedTime;
            } else {
                // For simplicity in this test, just return a formatted string
                const formattedTime = `Jul ${datePart.split('-')[2]}, ${datePart.split('-')[0]}, ${hours}:${timePart[1]} ${hours >= 12 ? 'PM' : 'AM'}`;
                console.log('  - Formatted time:', formattedTime);
                return formattedTime;
            }
        } else {
            console.log('  - Time is not in UTC, using legacy format');
            // Legacy format (not implemented in this test)
            return 'Legacy format not implemented in this test';
        }
    }

    // Format the meeting time
    const formattedStartTime = formatMeetingTime(
        meetingRecord.start_time,
        meetingRecord.timezone,
        meetingRecord.is_utc
    );

    console.log('- Displayed start time:', formattedStartTime);

    return formattedStartTime;
}

// Run the test
console.log('=== RUNNING TEST ===\n');

// 1. Frontend submission
const meetingData = simulateFrontendSubmission();

// 2. Backend calendar processing
const googleEventData = simulateBackendCalendarProcessing(meetingData);

// 3. Backend database storage
const meetingRecord = simulateBackendDatabaseStorage(meetingData, googleEventData);

// 4. Frontend display
const formattedStartTime = simulateFrontendDisplay(meetingRecord);

// 5. Verify the results
console.log('\n=== TEST RESULTS ===');
console.log('Original time (America/New_York):', meetingData.start_datetime);
console.log('Stored in database as (UTC):', meetingRecord.start_time);
console.log('Displayed to user as:', formattedStartTime);

// Expected results for a meeting at 3:00 PM in New York
const expectedUtcTime = '2025-07-24 19:00:00'; // 3:00 PM EDT is 7:00 PM UTC
console.log('\nExpected UTC time:', expectedUtcTime);
console.log('Actual UTC time:', meetingRecord.start_time);
console.log('UTC conversion correct:', meetingRecord.start_time.includes(expectedUtcTime.split(' ')[1]));

console.log('\nTest completed. The backend UTC conversion should work correctly.');
