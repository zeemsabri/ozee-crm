<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HasProjectPermissions;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ExistingClientEnquiryService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ExistingClientEnquiryController extends Controller
{
    use HasProjectPermissions;

    public function __construct(protected ExistingClientEnquiryService $enquiryService) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('manage_projects')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $campaignIdsParam = $request->input('campaign_ids');
        if ((is_string($campaignIdsParam) && trim($campaignIdsParam) !== '') || (is_array($campaignIdsParam) && count($campaignIdsParam) > 0)) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => (int) $request->input('per_page', 1000),
                    'total' => 0,
                ],
            ]);
        }

        $projects = $this->accessibleProjects($user)
            ->filter(fn (Project $project) => $this->canViewProjectServicesAndPayments($user, $project));

        if ($request->filled('project_id')) {
            $projectId = (int) $request->input('project_id');
            $projects = $projects->where('id', $projectId);
        }

        $q = strtolower(trim((string) $request->input('q', '')));
        $status = strtolower(trim((string) $request->input('status', '')));
        $source = strtolower(trim((string) $request->input('source', '')));
        $assignedToId = $request->input('assigned_to_id');

        $items = $projects
            ->flatMap(fn (Project $project) => $this->enquiryService->flattenProjectEnquiries($project))
            ->filter(function (array $item) use ($q, $status, $source, $assignedToId) {
                if ($status !== '' && strtolower((string) ($item['status'] ?? '')) !== $status) {
                    return false;
                }
                if ($source !== '' && strtolower((string) ($item['source'] ?? '')) !== $source) {
                    return false;
                }
                if ($assignedToId && (int) ($item['assigned_to_id'] ?? 0) !== (int) $assignedToId) {
                    return false;
                }
                if ($q === '') {
                    return true;
                }

                $haystack = strtolower(implode(' ', array_filter([
                    $item['service_name'] ?? null,
                    $item['project_name'] ?? null,
                    $item['client_name'] ?? null,
                    $item['description'] ?? null,
                    $item['lead_number'] ?? null,
                ])));

                return str_contains($haystack, $q);
            })
            ->sortByDesc(fn (array $item) => $item['updated_at'] ?? $item['created_at'])
            ->values();

        $perPage = max(1, min((int) $request->input('per_page', 1000), 1000));
        $page = max(1, (int) $request->input('page', 1));
        $total = $items->count();
        $paged = $items->slice(($page - 1) * $perPage, $perPage)->values();
        $paginator = new LengthAwarePaginator($paged, $total, $perPage, $page, [
            'path' => url('/api/existing-client-enquiries'),
            'query' => $request->query(),
        ]);

        return response()->json($paginator);
    }

    public function supportingData()
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('manage_projects')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $projects = $this->accessibleProjects($user)
            ->filter(fn (Project $project) => $this->canManageProjectServicesAndPayments($user, $project));

        return response()->json($this->enquiryService->supportingData($projects));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('manage_projects')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'client_id' => 'nullable|exists:clients,id',
            'service_id' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'frequency' => 'nullable|string|in:monthly,one_off',
            'start_date' => 'nullable|date',
            'enquiry_status' => 'nullable|string',
            'show_on_leads_board' => 'nullable|boolean',
            'source' => 'nullable|string|max:100',
        ]);

        $project = Project::with(['client:id,name', 'clients:id,name'])->findOrFail($validated['project_id']);
        if (! $this->canAccessProject($user, $project) || ! $this->canManageProjectServicesAndPayments($user, $project)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->assertClientBelongsToProject($project, $validated['client_id'] ?? null);
        $detail = $this->enquiryService->createEnquiry($project, $validated, $user);

        return response()->json([
            'message' => 'Existing client enquiry created successfully.',
            'data' => $this->enquiryService->flattenProjectEnquiries($project->fresh())->firstWhere('enquiry_id', $detail['enquiry_id']),
        ], 201);
    }

    public function update(Request $request, string $enquiryId)
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('manage_projects')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'service_id' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'frequency' => 'nullable|string|in:monthly,one_off',
            'start_date' => 'nullable|date',
            'enquiry_status' => 'nullable|string',
            'show_on_leads_board' => 'nullable|boolean',
            'service_tracking_type' => 'nullable|string|in:operational_service,client_enquiry',
        ]);

        $project = Project::with(['client:id,name', 'clients:id,name'])->findOrFail($validated['project_id']);
        if (! $this->canAccessProject($user, $project) || ! $this->canManageProjectServicesAndPayments($user, $project)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $detail = $this->enquiryService->updateEnquiry($project, $enquiryId, $validated);

        return response()->json([
            'message' => 'Existing client enquiry updated successfully.',
            'data' => $this->enquiryService->flattenProjectEnquiries($project->fresh())->firstWhere('enquiry_id', $detail['enquiry_id']),
        ]);
    }

    public function destroy(Request $request, string $enquiryId)
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('manage_projects')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
        ]);

        $project = Project::findOrFail($validated['project_id']);
        if (! $this->canAccessProject($user, $project) || ! $this->canManageProjectServicesAndPayments($user, $project)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->enquiryService->deleteEnquiry($project, $enquiryId);

        return response()->json(['message' => 'Existing client enquiry deleted successfully.']);
    }

    public function convert(Request $request, string $enquiryId)
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('manage_projects')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'conversion_type' => 'required|string|in:task,milestone,project',
            'assigned_to_user_id' => 'nullable|exists:users,id',
            'name' => 'nullable|string|max:255',
            'due_date' => 'nullable|date',
        ]);

        $project = Project::with(['client:id,name', 'clients:id,name'])->findOrFail($validated['project_id']);
        if (! $this->canAccessProject($user, $project) || ! $this->canManageProjectServicesAndPayments($user, $project)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if ($validated['conversion_type'] === ExistingClientEnquiryService::CONVERSION_PROJECT && ! $this->canCreateProjects($user)) {
            return response()->json(['message' => 'Unauthorized to create projects.'], 403);
        }

        $result = $this->enquiryService->convertEnquiry($project, $enquiryId, $validated, $user);

        return response()->json([
            'message' => 'Existing client enquiry converted successfully.',
            'data' => $this->enquiryService->flattenProjectEnquiries($project->fresh())->firstWhere('enquiry_id', $enquiryId),
            'created' => $result['created'],
        ]);
    }

    protected function accessibleProjects($user)
    {
        $query = $user->isSuperAdmin() || $user->isManager()
            ? Project::query()
            : $user->projects();

        return $query
            ->with(['client:id,name', 'clients:id,name', 'manager:id,name,email', 'admin:id,name,email'])
            ->get();
    }

    protected function assertClientBelongsToProject(Project $project, ?int $clientId): void
    {
        if (! $clientId) {
            return;
        }

        $projectClientIds = collect([$project->client?->id])
            ->merge($project->clients->pluck('id'))
            ->filter()
            ->unique();

        if (! $projectClientIds->contains($clientId)) {
            throw ValidationException::withMessages([
                'client_id' => 'Selected client is not linked to the chosen project.',
            ]);
        }
    }
}