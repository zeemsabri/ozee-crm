<?php

namespace App\Http\Controllers\Api\ClientDashboard;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Project; // Assuming you have a Project model
use App\Models\Task;    // Assuming you have a Task model
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProjectClientReader extends Controller
{

    public function __construct(private GoogleDriveService $googleDriveService)
    {

    }

   /**
     * Get tasks for a specific project, accessible by magic link clients.
     *
     * @param Request $request
     * @param int $projectId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectTasks(Request $request, Project $project)
    {

        try {
            // Retrieve the project ID associated with the magic link token
            // This attribute is set by the VerifyMagicLinkToken middleware
            $authenticatedProjectId = $request->attributes->get('magic_link_project_id');

            $projectId = $project->id;
            // IMPORTANT SECURITY CHECK: Ensure the requested projectId matches the one from the magic link token
            if ((int)$projectId !== (int)$authenticatedProjectId) {
                Log::warning("Unauthorized access attempt to project tasks.", [
                    'requested_project_id' => $projectId,
                    'authenticated_project_id' => $authenticatedProjectId,
                    'magic_link_email' => $request->attributes->get('magic_link_email'),
                    'ip_address' => $request->ip()
                ]);
                return response()->json(['message' => 'Unauthorized access to this project\'s tasks.'], 403);
            }

            $milestoneIds = $project->milestones()->where('name', Project::SUPPORT)->pluck('id')->toArray();

            $tasks = \App\Models\Task::whereIn('milestone_id', $milestoneIds)
                ->with(['notes' => function ($query) {
                    $query->orderBy('created_at', 'asc'); // Order notes chronologically
                }])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($tasks);

        } catch (\Exception $e) {
            Log::error('Error fetching client project tasks: ' . $e->getMessage(), [
                'project_id' => $projectId,
                'authenticated_project_id' => $request->attributes->get('magic_link_project_id'),
                'error_trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to fetch tasks.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get deliverables for a specific project, accessible by magic link clients.
     * Includes client-specific interaction status for the authenticated client.
     *
     * @param Request $request
     * @param int $projectId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectDeliverables(Request $request, Project $project)
    {
        try {
            $authenticatedProjectId = $request->attributes->get('magic_link_project_id');
            $authenticatedClientEmail = $request->attributes->get('magic_link_email');

            $projectId = $project->id;
            // Security check: Ensure the requested projectId matches the authenticated one
            if ((int)$projectId !== (int)$authenticatedProjectId) {
                Log::warning("Unauthorized access attempt to project deliverables.", [
                    'requested_project_id' => $projectId,
                    'authenticated_project_id' => $authenticatedProjectId,
                    'magic_link_email' => $authenticatedClientEmail,
                    'ip_address' => $request->ip()
                ]);
                return response()->json(['message' => 'Unauthorized access to this project\'s deliverables.'], 403);
            }

            // Find the client based on the authenticated email
            $client = Client::where('email', $authenticatedClientEmail)->first();

            if (!$client) {
                Log::error("Client not found for authenticated email.", [
                    'email' => $authenticatedClientEmail,
                    'project_id' => $projectId
                ]);
                return response()->json(['message' => 'Authenticated client not found.'], 404);
            }

            // Fetch deliverables for the project that are visible to clients
            $deliverables = Deliverable::where('project_id', $projectId)
                ->where('is_visible_to_client', true)
                ->with('teamMember') // Eager load the team member who submitted it
                ->orderBy('submitted_at', 'desc')
                ->get();

            // Append client-specific interaction status to each deliverable
            $deliverablesWithInteraction = $deliverables->map(function ($deliverable) use ($client) {
                $interaction = $deliverable->clientInteractions()
                    ->where('client_id', $client->id)
                    ->first();

                $deliverable->client_interaction = $interaction ? $interaction->toArray() : null;

                // Add flags for quick checks on frontend
                $deliverable->has_been_read_by_client = (bool)($interaction && $interaction->read_at);
                $deliverable->has_been_approved_by_client = (bool)($interaction && $interaction->approved_at);
                $deliverable->has_been_rejected_by_client = (bool)($interaction && $interaction->rejected_at);
                $deliverable->has_revisions_requested_by_client = (bool)($interaction && $interaction->revisions_requested_at);

                return $deliverable;
            });

            return response()->json($deliverablesWithInteraction);

        } catch (\Exception $e) {
            Log::error('Error fetching client project deliverables: ' . $e->getMessage(), [
                'project_id' => $projectId,
                'authenticated_project_id' => $request->attributes->get('magic_link_project_id'),
                'authenticated_client_email' => $request->attributes->get('magic_link_email'),
                'error_trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to fetch deliverables.', 'error' => $e->getMessage()], 500);
        }
    }
}

