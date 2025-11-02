<?php

require_once __DIR__.'/vendor/autoload.php';

use App\Services\GoogleCalendarService;

// This is a test script to verify the fix for the Google Calendar recording issue
echo "Testing Google Calendar recording fix...\n";

try {
    // Create a new instance of the GoogleCalendarService
    $calendarService = new GoogleCalendarService;

    // Test creating an event with recording enabled
    $summary = 'Test Meeting with Recording';
    $description = 'This is a test meeting to verify the recording fix';
    $startDateTime = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $endDateTime = date('Y-m-d H:i:s', strtotime('+2 hours'));
    $attendeeEmails = ['test@example.com']; // Replace with a valid email for testing
    $location = 'Test Location';
    $withGoogleMeet = true;
    $timezone = 'America/New_York';
    $enableRecording = true;

    echo "Creating test event with recording enabled...\n";

    $eventData = $calendarService->createEvent(
        $summary,
        $description,
        $startDateTime,
        $endDateTime,
        $attendeeEmails,
        $location,
        $withGoogleMeet,
        $timezone,
        $enableRecording
    );

    echo "Event created successfully!\n";
    echo 'Event ID: '.$eventData['id']."\n";
    echo 'Event Link: '.$eventData['htmlLink']."\n";
    echo 'Google Meet Link: '.($eventData['hangoutLink'] ?? 'N/A')."\n";

    echo "Test completed successfully.\n";

} catch (\Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
    echo 'Stack trace: '.$e->getTraceAsString()."\n";
}
