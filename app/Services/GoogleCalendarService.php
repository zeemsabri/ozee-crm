<?php

namespace App\Services;

use App\Traits\GoogleApiAuthTrait;
use Carbon\Carbon;
use Google\Service\Calendar;
use Google\Service\Calendar\ConferenceData;
use Google\Service\Calendar\CreateConferenceRequest;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventAttendee;
use Google\Service\Calendar\EventDateTime;
use Illuminate\Support\Facades\Log; // For easier date/time handling

class GoogleCalendarService
{
    use GoogleApiAuthTrait;

    /**
     * Create a new Google Calendar event.
     *
     * @param  string  $summary  The event summary (title).
     * @param  string  $description  The event description.
     * @param  string  $startDateTime  The start date and time (e.g., 'YYYY-MM-DD HH:MM:SS').
     * @param  string  $endDateTime  The end date and time (e.g., 'YYYY-MM-DD HH:MM:SS').
     * @param  array  $attendeeEmails  An array of email addresses for attendees.
     * @param  string|null  $location  The event location.
     * @param  bool  $withGoogleMeet  True to automatically add a Google Meet link.
     * @param  string|null  $timezone  The IANA timezone identifier for the event (e.g., 'Australia/Perth'). Defaults to app.timezone.
     * @param  bool  $enableRecording  Note: Google Calendar API does not directly enable recording. This just adds a note.
     * @return array Contains event ID, HTML link, and Google Meet link.
     *
     * @throws \Exception
     */
    public function createEvent(
        string $summary,
        string $description,
        string $startDateTime,
        string $endDateTime,
        array $attendeeEmails = [],
        ?string $location = null,
        bool $withGoogleMeet = true,
        ?string $timezone = null,
        bool $enableRecording = false // Still just adds a note, no API control
    ): array {
        try {
            // --- TIMEZONE DEBUG LOGGING START ---
            Log::debug('CALENDAR_SERVICE_DEBUG: createEvent initiated.');
            Log::debug('CALENDAR_SERVICE_DEBUG: Raw Input startDateTime: '.$startDateTime);
            Log::debug('CALENDAR_SERVICE_DEBUG: Raw Input endDateTime: '.$endDateTime);
            Log::debug('CALENDAR_SERVICE_DEBUG: timezone parameter received: '.($timezone ?? 'null (will use config fallback)'));
            Log::debug('CALENDAR_SERVICE_DEBUG: config(\'app.timezone\') value: '.config('app.timezone'));
            Log::debug('CALENDAR_SERVICE_DEBUG: PHP\'s current default timezone (date_default_timezone_get()): '.date_default_timezone_get());
            // --- TIMEZONE DEBUG LOGGING END ---

            // Determine the effective timezone to use for this event
            $eventTimeZone = $timezone ?? config('app.timezone');

            // --- TIMEZONE DEBUG LOGGING START ---
            Log::debug('CALENDAR_SERVICE_DEBUG: Effective eventTimeZone determined: '.$eventTimeZone);
            // --- TIMEZONE DEBUG LOGGING END ---

            $event = new Event;
            $event->setSummary($summary);
            $event->setDescription($description);
            if ($location) {
                $event->setLocation($location);
            }

            // --- Start Time Processing ---
            $start = new EventDateTime;
            // Parse the datetime string WITH the intended timezone
            $startCarbon = Carbon::parse($startDateTime, $eventTimeZone);

            // Set the dateTime and ensure the timezone is specified for Google
            $start->setDateTime($startCarbon->toRfc3339String());
            $start->setTimeZone($eventTimeZone);

            // --- TIMEZONE DEBUG LOGGING START ---
            Log::debug('CALENDAR_SERVICE_DEBUG: Start Carbon object interpreted as: '.$startCarbon->toDateTimeString().' in '.$startCarbon->getTimezone()->getName().' (Offset: '.($startCarbon->offset / 3600).' hours)');
            Log::debug('CALENDAR_SERVICE_DEBUG: Start DateTime sent to Google: '.$start->getDateTime());
            Log::debug('CALENDAR_SERVICE_DEBUG: Start TimeZone sent to Google: '.$start->getTimeZone());
            // --- TIMEZONE DEBUG LOGGING END ---

            $event->setStart($start);

            // --- End Time Processing ---
            $end = new EventDateTime;
            // Parse the datetime string WITH the intended timezone
            $endCarbon = Carbon::parse($endDateTime, $eventTimeZone);

            // Set the dateTime and ensure the timezone is specified for Google
            $end->setDateTime($endCarbon->toRfc3339String());
            $end->setTimeZone($eventTimeZone);

            // --- TIMEZONE DEBUG LOGGING START ---
            Log::debug('CALENDAR_SERVICE_DEBUG: End Carbon object interpreted as: '.$endCarbon->toDateTimeString().' in '.$endCarbon->getTimezone()->getName().' (Offset: '.($endCarbon->offset / 3600).' hours)');
            Log::debug('CALENDAR_SERVICE_DEBUG: End DateTime sent to Google: '.$end->getDateTime());
            Log::debug('CALENDAR_SERVICE_DEBUG: End TimeZone sent to Google: '.$end->getTimeZone());
            // --- TIMEZONE DEBUG LOGGING END ---

            $event->setEnd($end);

            // Add attendees
            $attendees = [];
            foreach ($attendeeEmails as $email) {
                $attendee = new EventAttendee;
                $attendee->setEmail($email);
                $attendees[] = $attendee;
            }
            $event->setAttendees($attendees);

            // Request conference data for Google Meet
            if ($withGoogleMeet) {
                $conferenceData = new ConferenceData;

                $createRequest = new CreateConferenceRequest([
                    'requestId' => uniqid(), // Unique ID for the conference creation request
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'], // Request a Google Meet conference
                ]);

                $conferenceData->setCreateRequest($createRequest);
                $event->setConferenceData($conferenceData);

                // Add recording instructions to the description if enabled (as API doesn't allow direct control)
                if ($enableRecording) {
                    $recordingNote = "\n\n[Note: Recording has been enabled for this meeting. The host will need to start the recording during the meeting.]";
                    // Ensure you are appending to the description, not overwriting it
                    $event->setDescription(($event->getDescription() ?: '').$recordingNote);
                }
            }

            // Options for event creation
            $optParams = [
                'sendUpdates' => 'all', // Send notifications to attendees ('all', 'externalOnly', 'none')
                'conferenceDataVersion' => 1, // Required for conference data
            ];

            $createdEvent = $this->calendarService->events->insert($this->calendarId, $event, $optParams);

            Log::info('Google Calendar event created successfully', [
                'event_id' => $createdEvent->getId(),
                'summary' => $summary,
                'html_link' => $createdEvent->getHtmlLink(),
                'attendees' => $attendeeEmails,
                'effective_timezone_used' => $eventTimeZone, // Log the actual timezone used for clarity
            ]);

            return [
                'id' => $createdEvent->getId(),
                'htmlLink' => $createdEvent->getHtmlLink(),
                'hangoutLink' => $createdEvent->getHangoutLink(), // Google Meet link
            ];

        } catch (\Exception $e) {
            Log::error('Error creating Google Calendar event: '.$e->getMessage(), [
                'summary' => $summary,
                'attendees' => $attendeeEmails,
                'error' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete a Google Calendar event.
     *
     * @param  string  $eventId  The ID of the event to delete.
     *
     * @throws \Exception
     */
    public function deleteEvent(string $eventId): void
    {
        try {
            // Options for event deletion
            $optParams = [
                'sendUpdates' => 'all', // Send cancellation notifications to attendees
            ];

            $this->calendarService->events->delete($this->calendarId, $eventId, $optParams);

            Log::info('Google Calendar event deleted', [
                'event_id' => $eventId,
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting Google Calendar event: '.$e->getMessage(), [
                'event_id' => $eventId,
                'error' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
