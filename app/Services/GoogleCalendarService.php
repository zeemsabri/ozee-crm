<?php

namespace App\Services;

use App\Traits\GoogleApiAuthTrait;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\EventAttendee;
use Google\Service\Calendar\ConferenceData;
use Google\Service\Calendar\ConferenceProperties;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon; // For easier date/time handling

class GoogleCalendarService
{
    use GoogleApiAuthTrait;

    protected $calendarService;
    protected $calendarId; // Default calendar ID, typically 'primary'

    public function __construct()
    {
        $this->initializeGoogleClient();
        $this->calendarService = new Calendar($this->getGoogleClient());
        $this->calendarId = 'primary'; // Default to the user's primary calendar
    }

    /**
     * Set the calendar ID to use for operations.
     *
     * @param string $calendarId
     * @return $this
     */
    public function setCalendarId(string $calendarId): self
    {
        $this->calendarId = $calendarId;
        return $this;
    }

    /**
     * Create a new meeting (event) in Google Calendar.
     *
     * @param string $summary The title of the event.
     * @param string $description The description of the event.
     * @param string $startDateTime Start date/time (e.g., 'YYYY-MM-DD HH:MM:SS').
     * @param string $endDateTime End date/time (e.g., 'YYYY-MM-DD HH:MM:SS').
     * @param array $attendeeEmails Array of attendee email addresses.
     * @param string|null $location Optional location string.
     * @param bool $withGoogleMeet Whether to generate a Google Meet link.
     * @return array Contains 'id' and 'htmlLink' of the created event.
     * @throws \Exception
     */
    public function createEvent(
        string $summary,
        string $description,
        string $startDateTime,
        string $endDateTime,
        array $attendeeEmails = [],
        ?string $location = null,
        bool $withGoogleMeet = true
    ): array {
        try {
            $event = new Event();
            $event->setSummary($summary);
            $event->setDescription($description);
            if ($location) {
                $event->setLocation($location);
            }

            // Set start and end times
            $start = new EventDateTime();
            $start->setDateTime(Carbon::parse($startDateTime)->toRfc3339String());
            $start->setTimeZone(config('app.timezone')); // Use your Laravel app's timezone
            $event->setStart($start);

            $end = new EventDateTime();
            $end->setDateTime(Carbon::parse($endDateTime)->toRfc3339String());
            $end->setTimeZone(config('app.timezone')); // Use your Laravel app's timezone
            $event->setEnd($end);

            // Add attendees
            $attendees = [];
            foreach ($attendeeEmails as $email) {
                $attendee = new EventAttendee();
                $attendee->setEmail($email);
                $attendees[] = $attendee;
            }
            $event->setAttendees($attendees);

            // Request conference data for Google Meet
            if ($withGoogleMeet) {
                $conferenceData = new ConferenceData();
                $conferenceData->setCreateRequest(new \Google\Service\Calendar\CreateConferenceRequest([
                    'requestId' => uniqid(), // Unique ID for the conference creation request
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'], // Request a Google Meet conference
                ]));
                $event->setConferenceData($conferenceData);
            }

            // Options for event creation
            $optParams = [
                'sendUpdates' => 'all', // Send notifications to attendees ('all', 'externalOnly', 'none')
                'conferenceDataVersion' => 1, // Required for conference data
            ];

            $createdEvent = $this->calendarService->events->insert($this->calendarId, $event, $optParams);

            Log::info('Google Calendar event created', [
                'event_id' => $createdEvent->getId(),
                'summary' => $summary,
                'html_link' => $createdEvent->getHtmlLink(),
                'attendees' => $attendeeEmails,
            ]);

            return [
                'id' => $createdEvent->getId(),
                'htmlLink' => $createdEvent->getHtmlLink(),
                'hangoutLink' => $createdEvent->getHangoutLink(), // Google Meet link
            ];

        } catch (\Exception $e) {
            Log::error('Error creating Google Calendar event: ' . $e->getMessage(), [
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
     * @param string $eventId The ID of the event to delete.
     * @return void
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
            Log::error('Error deleting Google Calendar event: ' . $e->getMessage(), [
                'event_id' => $eventId,
                'error' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
