<?php

namespace App\Http\Controllers\Api\ClientDashboard;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Project; // Assuming you have a Project model
use App\Models\ShareableResource;
use App\Models\Task;    // Assuming you have a Task model
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class ProjectClientReader extends Controller
{

    public function __construct(private GoogleDriveService $googleDriveService)
    {

    }

    public function getProject(Request $request)
    {

        try {
            $authenticatedProjectId = $request->attributes->get('magic_link_project_id');

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
            // Retrieve the project ID associated with the magic link token
            // This attribute is set by the VerifyMagicLinkToken middleware
            $authenticatedProjectId = $request->attributes->get('magic_link_project_id');

            $projectId = $project->id;
            // IMPORTANT SECURITY CHECK: Ensure the requested projectId matches the one from the magic link token
            if ((int)$projectId !== (int)$authenticatedProjectId) {
                Log::warning("Unauthorized access attempt to project documents.", [
                    'requested_project_id' => $projectId,
                    'authenticated_project_id' => $authenticatedProjectId,
                    'magic_link_email' => $request->attributes->get('magic_link_email'),
                    'ip_address' => $request->ip()
                ]);
                return response()->json(['message' => 'Unauthorized access to this project\'s documents.'], 403);
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
    }

    /**
     * Get SEO Report data for a specific project and month.
     * For now, returns hardcoded data.
     *
     * @param  string  $projectId
     * @param  string  $month (YYYY-MM format)
     * @return \Illuminate\Http\JsonResponse
     */
    public function getReportData(Request $request, $projectId, $month)
    {
        // In a real application, you would fetch data from a database
        // based on $projectId and $month.
        // For now, we return the hardcoded sample data.

        $reportData = json_decode('{
  "clientName": "The Good Liar",
  "reportingPeriod": "June 2025",
  "authorityScoreValue": 19,
  "totalClicksValue": "750",
  "totalImpressionsValue": "81,200",
  "averagePositionValue": 25.4,
  "totalBacklinksValue": "2,000",
  "clicksImpressions": {
    "labels": [
      "2025-03-31", "2025-04-01", "2025-04-02", "2025-04-03", "2025-04-04",
      "2025-04-05", "2025-04-06", "2025-04-07", "2025-04-08", "2025-04-09",
      "2025-04-10", "2025-04-11", "2025-04-12", "2025-04-13", "2025-04-14",
      "2025-04-15", "2025-04-16", "2025-04-17", "2025-04-18", "2025-04-19",
      "2025-04-20", "2025-04-21", "2025-04-22", "2025-04-23", "2025-04-24",
      "2025-04-25", "2025-04-26", "2025-04-27", "2025-04-28", "2025-04-29",
      "2025-04-30", "2025-05-01", "2025-05-02", "2025-05-03", "2025-05-04",
      "2025-05-05", "2025-05-06", "2025-05-07", "2025-05-08", "2025-05-09",
      "2025-05-10", "2025-05-11", "2025-05-12", "2025-05-13", "2025-05-14",
      "2025-05-15", "2025-05-16", "2025-05-17", "2025-05-18", "2025-05-19",
      "2025-05-20", "2025-05-21", "2025-05-22", "2025-05-23", "2025-05-24",
      "2025-05-25", "2025-05-26", "2025-05-27", "2025-05-28", "2025-05-29",
      "2025-05-30", "2025-05-31", "2025-06-01", "2025-06-02", "2025-06-03",
      "2025-06-04", "2025-06-05", "2025-06-06", "2025-06-07", "2025-06-08",
      "2025-06-09", "2025-06-10", "2025-06-11", "2025-06-12", "2025-06-13",
      "2025-06-14", "2025-06-15", "2025-06-16", "2025-06-17", "2025-06-18",
      "2025-06-19", "2025-06-20", "2025-06-21", "2025-06-22", "2025-06-23",
      "2025-06-24", "2025-06-25", "2025-06-26", "2025-06-27", "2025-06-28",
      "2025-06-29", "2025-06-30"
    ],
    "clicks": [
      6, 11, 4, 5, 9, 11, 8, 7, 19, 9, 12, 9, 9, 6, 2, 5, 12, 7, 12, 14,
      10, 5, 10, 0, 3, 12, 9, 8, 5, 3, 8, 11, 8, 9, 5, 3, 11, 9, 6, 7, 12,
      9, 13, 9, 7, 4, 14, 16, 14, 4, 9, 8, 8, 14, 8, 6, 5, 5, 8, 10, 5, 7,
      10, 9, 2, 5, 10, 13, 7, 5, 3, 6, 13, 6, 3, 8, 17, 14, 10, 13, 9, 6, 3,
      8, 7, 6, 11, 9, 7, 6, 8, 11, 6, 8, 4, 9, 8, 3, 9, 8, 6, 7, 13, 11, 6,
      3, 13, 8, 4, 9, 7, 10, 3, 6, 8, 2, 9, 11, 8, 9, 4
    ],
    "impressions": [
      796, 684, 584, 540, 545, 639, 620, 729, 691, 585, 1862, 1134, 965, 1567,
      1398, 1049, 799, 723, 758, 755, 760, 788, 746, 699, 650, 668, 613, 684,
      709, 634, 826, 698, 708, 650, 729, 712, 755, 783, 708, 764, 679, 711,
      795, 726, 739, 824, 715, 798, 772, 784, 1006, 1038, 2563, 1094, 1012,
      948, 951, 958, 810, 947, 799, 994, 1007, 858, 1056, 887, 854, 798, 890,
      872, 894, 912, 982, 1344, 1734, 1419, 999, 901, 838, 849, 899, 759, 982,
      1020, 1076, 1104, 915, 901, 999, 1006, 958, 810, 947, 799, 994, 1007,
      858, 1056, 887, 854, 798, 890, 872, 894, 912, 982
    ]
  },
  "trafficSources": {
    "labels": ["Direct", "Organic Search", "Referral", "Unassigned", "Organic Social"],
    "sessions": [423, 383, 115, 79, 46],
    "colors": ["#4f46e5", "#3b82f6", "#6b7280", "#c8b496", "#22c55e"]
  },
  "deviceUsage": {
    "labels": ["Mobile", "Desktop", "Tablet"],
    "clicks": [438, 296, 8],
    "colors": ["#4f46e5", "#3b82f6", "#6b7280"]
  },
  "countryPerformance": {
    "labels": [
      "United States", "Canada", "Australia", "India", "United Kingdom",
      "France", "Germany", "Turkey", "Pakistan", "Netherlands"
    ],
    "clicks": [657, 14, 14, 12, 5, 4, 3, 3, 3, 3]
  },
  "coreVitals": {
    "mobile": {
      "labels": ["Performance", "Accessibility", "Best Practices", "SEO"],
      "scores": [66, 85, 92, 98]
    },
    "desktop": {
      "labels": ["Performance", "Accessibility", "Best Practices", "SEO"],
      "scores": [68, 100, 100, 100]
    }
  },
  "topQueries": [
    {"query": "magic show dc", "clicks": 49, "impressions": 589, "ctr": 8.32, "position": 5.54},
    {"query": "the good liar brian curry", "clicks": 28, "impressions": 179, "ctr": 15.64, "position": 2.51},
    {"query": "brian curry the good liar", "clicks": 27, "impressions": 177, "ctr": 15.25, "position": 2.77},
    {"query": "magic show washington dc", "clicks": 18, "impressions": 252, "ctr": 7.14, "position": 5.18},
    {"query": "brian curry magic", "clicks": 15, "impressions": 116, "ctr": 12.93, "position": 3.79},
    {"query": "brian curry", "clicks": 13, "impressions": 1404, "ctr": 0.93, "position": 5.44},
    {"query": "brian curry mentalist", "clicks": 12, "impressions": 47, "ctr": 25.53, "position": 2.26},
    {"query": "dc magic show", "clicks": 10, "impressions": 133, "ctr": 7.52, "position": 5.63},
    {"query": "magic shows washington dc", "clicks": 6, "impressions": 73, "ctr": 8.22, "position": 5.88},
    {"query": "mentalist dc", "clicks": 6, "impressions": 9, "ctr": 66.67, "position": 1.44}
  ],
  "keywordRankings": [
    {"keyword": "Washington DC magic show", "ranking": 1},
    {"keyword": "Washington magic show", "ranking": 1},
    {"keyword": "magic show Washington", "ranking": 1},
    {"keyword": "DC magic show Washington", "ranking": 1},
    {"keyword": "DC magic show", "ranking": 1}
  ],
  "backlinks": [],
  "zeroClickKeywords": []
}');

        // You can dynamically set the reporting period based on the requested month
        $date = \DateTime::createFromFormat('Y-m', $month);
        if ($date) {
            $reportData->reportingPeriod = $date->format('F Y');
        }

        return Response::json($reportData);

    }

}
