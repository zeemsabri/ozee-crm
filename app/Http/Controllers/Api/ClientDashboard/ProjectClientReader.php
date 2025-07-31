<?php

namespace App\Http\Controllers\Api\ClientDashboard;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Project; // Assuming you have a Project model
use App\Models\SeoReport;
use App\Models\ShareableResource;
use App\Models\Task;    // Assuming you have a Task model
use App\Services\GoogleDriveService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class ProjectClientReader extends Controller
{

    public function __construct(private GoogleDriveService $googleDriveService)
    {

    }

    /**
     * Verify that the requested project matches the authenticated project from magic link
     *
     * @param Request $request
     * @param int $projectId
     * @param string $context Context for logging (e.g., 'tasks', 'deliverables')
     * @return array|null Returns null if verification passes, or an error response array if it fails
     */
    private function verifyMagicLinkProject(Request $request, $projectId, string $context)
    {
        $authenticatedProjectId = $request->attributes->get('magic_link_project_id');

        // IMPORTANT SECURITY CHECK: Ensure the requested projectId matches the one from the magic link token
        if ((int)$projectId !== (int)$authenticatedProjectId) {
            Log::warning("Unauthorized access attempt to project $context.", [
                'requested_project_id' => $projectId,
                'authenticated_project_id' => $authenticatedProjectId,
                'magic_link_email' => $request->attributes->get('magic_link_email'),
                'ip_address' => $request->ip()
            ]);
            return [
                'status' => 403,
                'message' => "Unauthorized access to this project's $context."
            ];
        }

        return null;
    }

    public function getProject(Request $request)
    {

        try {
            $authenticatedProjectId = $request->attributes->get('magic_link_project_id');

            // No need to verify project ID here as we're directly using the authenticated project ID
            $project = Project::findOrFail($authenticatedProjectId);

            return [
                'name'  =>  $project->name,
                'google_drive_link' => $project->google_drive_link,
                'google_chat_id'    =>  $project->google_chat_id,
                'website'   => $project->website,
                'status'    =>  $project->status,
                'logo'  =>  $project->logo
            ];
        }
        catch (\Exception $e) {
            return response()->json(['message' => 'Failed to fetch project.', 'error' => $e->getMessage()], 500);
        }

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
            $projectId = $project->id;

            // Verify magic link project
            $verificationResult = $this->verifyMagicLinkProject($request, $projectId, 'tasks');
            if ($verificationResult) {
                return response()->json(['message' => $verificationResult['message']], $verificationResult['status']);
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
            $authenticatedClientEmail = $request->attributes->get('magic_link_email');
            $projectId = $project->id;

            // Verify magic link project
            $verificationResult = $this->verifyMagicLinkProject($request, $projectId, 'deliverables');
            if ($verificationResult) {
                return response()->json(['message' => $verificationResult['message']], $verificationResult['status']);
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
                ->with(['comments' => function($q) {
                        $q->orderBy('created_at', 'desc');
                }, 'teamMember']) // Eager load the team member who submitted it
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
    /**
     * Get documents for a specific project, accessible by magic link clients.
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectDocuments(Request $request, Project $project)
    {
        try {
            $projectId = $project->id;

            // Verify magic link project
            $verificationResult = $this->verifyMagicLinkProject($request, $projectId, 'documents');
            if ($verificationResult) {
                return response()->json(['message' => $verificationResult['message']], $verificationResult['status']);
            }

            // Get documents for the project
            $documents = $project->documents()->with('notes')->latest()->get();
            $drive = new GoogleDriveService();

            return response()->json($documents);

        } catch (\Exception $e) {
            Log::error('Error fetching client project documents: ' . $e->getMessage(), [
                'project_id' => $projectId,
                'authenticated_project_id' => $request->attributes->get('magic_link_project_id'),
                'error_trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to fetch documents.', 'error' => $e->getMessage()], 500);
        }

    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getShareableResources(Request $request, Project $project)
    {
        try {
            $projectId = $project->id;

            // Verify magic link project
            $verificationResult = $this->verifyMagicLinkProject($request, $projectId, 'shareable resources');
            if ($verificationResult) {
                return response()->json(['message' => $verificationResult['message']], $verificationResult['status']);
            }

            $query = ShareableResource::with('tags');

            // Filter by type if provided
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Filter by visibility if provided
            if ($request->has('visible_to_client')) {
                $query->where('visible_to_client', $request->visible_to_client);
            }

            // Filter by tag if provided
            if ($request->has('tag_id')) {
                $query->whereHas('tags', function ($q) use ($request) {
                    $q->where('tags.id', $request->tag_id);
                });
            }

            $resources = $query->get();

            return response()->json($resources);
        } catch (\Exception $e) {
            Log::error('Error fetching client project shareable resources: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'authenticated_project_id' => $request->attributes->get('magic_link_project_id'),
                'error_trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to fetch shareable resources.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get SEO Report data for a specific project and month.
     * For now, returns hardcoded data.
     *
     * @param  string  $projectId
     * @param  string  $month (YYYY-MM format)
     * @return \Illuminate\Http\JsonResponse
     */
    public function getReportData(Request $request, $projectId, $yearMonth)
    {
        try {
            // Verify magic link project
            $verificationResult = $this->verifyMagicLinkProject($request, $projectId, 'SEO reports');
            if ($verificationResult) {
                return response()->json(['message' => $verificationResult['message']], $verificationResult['status']);
            }

            // Validate year-month format
            if (!preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
                return response()->json(['error' => 'Invalid date format. Use YYYY-MM format.'], 400);
            }

            // Construct the report date
            $reportDate = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();

            // Find the report
            $report = SeoReport::where('project_id', $projectId)
                ->where('report_date', $reportDate)
                ->first();

            if (!$report) {
                return response()->json(['error' => 'Report not found'], 404);
            }

            // Return just the data field which is already cast to an array
            return response()->json($report->data, 200);
        } catch (\Exception $e) {
            Log::error('Error fetching SEO report data: ' . $e->getMessage(), [
                'project_id' => $projectId,
                'authenticated_project_id' => $request->attributes->get('magic_link_project_id'),
                'year_month' => $yearMonth,
                'error_trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to fetch SEO report data.', 'error' => $e->getMessage()], 500);
        }
    }

}
