<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProjectCalendarController extends Controller
{
    protected GoogleCalendarService $googleCalendarService;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(GoogleCalendarService $googleCalendarService)
    {
        $this->googleCalendarService = $googleCalendarService;
    }

    /**
     * Create a new Google Calendar meeting for a project.
     *
     * @return JsonResponse
     */
    public function createProjectMeeting(Request $request, Project $project)
    {
        // You might want to add authorization checks here
        // $this->authorize('createMeeting', $project);

        $validated = $request->validate([
            'summary' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_datetime' => 'required|date_format:Y-m-d H:i:s|after_or_equal:now',
            'end_datetime' => 'required|date_format:Y-m-d H:i:s|after:start_datetime',
            'attendee_user_ids' => 'nullable|array', // User IDs from your system
            'attendee_user_ids.*' => 'exists:users,id',
            'location' => 'nullable|string|max:255',
            'with_google_meet' => 'boolean',
            'timezone' => 'nullable|string|max:100', // Timezone string (e.g., 'America/New_York')
            'enable_recording' => 'boolean', // Whether to enable recording for Google Meet
        ]);

        try {
            $attendeeEmails = [];
            // Add the authenticated user's email as an attendee by default
            if (Auth::check()) {
                $attendeeEmails[] = Auth::user()->email;
            }

            // Get emails of specified attendees from your User model
            if (! empty($validated['attendee_user_ids'])) {
                $users = User::whereIn('id', $validated['attendee_user_ids'])->get();
                foreach ($users as $user) {
                    if (! in_array($user->email, $attendeeEmails)) { // Avoid duplicates
                        $attendeeEmails[] = $user->email;
                    }
                }
            }

            $summary = $validated['summary'];
            $description = $validated['description'] ?? "Meeting for project: {$project->name}";
            $startDateTime = $validated['start_datetime'];
            $endDateTime = $validated['end_datetime'];
            $location = $validated['location'] ?? null;
            $withGoogleMeet = $validated['with_google_meet'] ?? true;
            $timezone = $validated['timezone'] ?? null;
            $enableRecording = $validated['enable_recording'] ?? false;

            $eventData = $this->googleCalendarService->createEvent(
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


            return response()->json([
                'message' => 'Meeting created successfully!',
                'event' => $eventData,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create project meeting:', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to create meeting: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a Google Calendar meeting associated with a project.
     *
     * @param  string  $googleEventId  The Google Calendar Event ID to delete.
     * @return JsonResponse
     */
    public function deleteProjectMeeting(Request $request, Project $project, string $googleEventId)
    {
        // You might want to add authorization checks here

        try {

            $this->googleCalendarService->deleteEvent($googleEventId);

            return response()->json([
                'message' => 'Meeting deleted successfully!',
                'event_id' => $googleEventId,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to delete project meeting:', [
                'project_id' => $project->id,
                'event_id' => $googleEventId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to delete meeting: '.$e->getMessage(),
            ], 500);
        }
    }
}
