<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HasProjectPermissions;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectExpendable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectExpendableController extends Controller
{
    use HasProjectPermissions;

    public function index(Project $project)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project) || !$this->canViewProjectExpendable($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view expendables.'], 403);
        }

        $expendables = ProjectExpendable::where('project_id', $project->id)
            ->latest()
            ->get();

        return response()->json($expendables);
    }

    public function store(Request $request, Project $project)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project) || !$this->canManageProjectExpendable($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to create expendables.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',
            'status' => 'nullable|string|max:50',
            'expandable_id' => 'nullable|integer',
            'expandable_type' => 'nullable|string|max:255',
        ]);

        $expendable = $project->expendable()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'currency' => strtoupper($validated['currency']),
            'amount' => $validated['amount'],
            'balance' => $validated['amount'],
            'status' => $validated['status'] ?? 'active'
        ]);

        return response()->json($expendable, 201);
    }

    public function destroy(Project $project, ProjectExpendable $expendable)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project) || !$this->canManageProjectExpendable($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to delete expendables.'], 403);
        }

        if ($expendable->project_id !== $project->id) {
            return response()->json(['message' => 'Expendable does not belong to this project.'], 400);
        }

        $expendable->delete();
        return response()->json(['success' => true]);
    }
}
