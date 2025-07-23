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
     * @param GoogleCalendarService $googleCalendarService
     * @return void
     */
    public function __construct(GoogleCalendarService $googleCalendarService)
    {
        $this->googleCalendarService = $googleCalendarService;
    }

    /**
     * Create a new Google Calendar meeting for a project.
     *
     * @param Request $request
     * @param Project $project
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
        ]);

        try {
            $attendeeEmails = [];
            // Add the authenticated user's email as an attendee by default
            if (Auth::check()) {
                $attendeeEmails[] = Auth::user()->email;
            }

            // Get emails of specified attendees from your User model
            if (!empty($validated['attendee_user_ids'])) {
                $users = User::whereIn('id', $validated['attendee_user_ids'])->get();
                foreach ($users as $user) {
                    if (!in_array($user->email, $attendeeEmails)) { // Avoid duplicates
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

            $eventData = $this->googleCalendarService->createEvent(
                $summary,
                $description,
                $startDateTime,
                $endDateTime,
                $attendeeEmails,
                $location,
                $withGoogleMeet
            );

            // You might want to save the Google Calendar Event ID and Link
            // in your database, perhaps in a new 'meetings' table associated with the project.
            // For example:
            // $project->meetings()->create([
            //     'google_event_id' => $eventData['id'],
            //     'google_event_link' => $eventData['htmlLink'],
            //     'google_meet_link' => $eventData['hangoutLink'] ?? null,
            //     'summary' => $summary,
            //     'start_time' => $startDateTime,
            //     'end_time' => $endDateTime,
            //     'created_by_user_id' => Auth::id(),
            // ]);

            Log::info('Project meeting created successfully', [
                'project_id' => $project->id,
                'event_id' => $eventData['id'],
                'html_link' => $eventData['htmlLink'],
            ]);

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
                'message' => 'Failed to create meeting: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a Google Calendar meeting associated with a project.
     *
     * @param Request $request
     * @param Project $project
     * @param string $googleEventId The Google Calendar Event ID to delete.
     * @return JsonResponse
     */
    public function deleteProjectMeeting(Request $request, Project $project, string $googleEventId)
    {
        // You might want to add authorization checks here
        // $this->authorize('deleteMeeting', $project);

        try {
            // If you stored the event ID in your database, you could retrieve it here
            // to ensure it belongs to this project before attempting to delete.
            // Example:
            // $meeting = $project->meetings()->where('google_event_id', $googleEventId)->firstOrFail();
            // $this->googleCalendarService->deleteEvent($meeting->google_event_id);
            // $meeting->delete(); // Delete from your local database too

            $this->googleCalendarService->deleteEvent($googleEventId);

            Log::info('Project meeting deleted successfully', [
                'project_id' => $project->id,
                'event_id' => $googleEventId,
            ]);

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
                'message' => 'Failed to delete meeting: ' . $e->getMessage(),
            ], 500);
        }
    }
}
