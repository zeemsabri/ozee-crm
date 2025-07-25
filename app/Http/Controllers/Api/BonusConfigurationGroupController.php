<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BonusConfiguration;
use App\Models\BonusConfigurationGroup;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BonusConfigurationGroupController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            // Get the authenticated user
            $user = Auth::user();

            // Retrieve all bonus configuration groups for the user with their configurations
            $bonusConfigurationGroups = BonusConfigurationGroup::where('user_id', $user->id)
                ->with('bonusConfigurations')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($bonusConfigurationGroups);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve bonus configuration groups: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'is_active' => 'boolean',
                'configurations' => 'array',
                'configurations.*' => 'exists:bonus_configurations,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Get the authenticated user
            $user = Auth::user();

            // Create a new bonus configuration group
            $bonusConfigurationGroup = new BonusConfigurationGroup();
            $bonusConfigurationGroup->name = $request->name;
            $bonusConfigurationGroup->description = $request->description;
            $bonusConfigurationGroup->is_active = $request->has('is_active') ? $request->is_active : true;
            $bonusConfigurationGroup->user_id = $user->id;
            $bonusConfigurationGroup->save();

            // Attach configurations if provided
            if ($request->has('configurations') && is_array($request->configurations)) {
                $sortOrder = 0;
                foreach ($request->configurations as $configId) {
                    $bonusConfigurationGroup->bonusConfigurations()->attach($configId, [
                        'sort_order' => $sortOrder++
                    ]);
                }
            }

            // Load the configurations relationship
            $bonusConfigurationGroup->load('bonusConfigurations');

            return response()->json($bonusConfigurationGroup, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create bonus configuration group: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            // Get the authenticated user
            $user = Auth::user();

            // Find the bonus configuration group by ID and user ID
            $bonusConfigurationGroup = BonusConfigurationGroup::where('id', $id)
                ->where('user_id', $user->id)
                ->with('bonusConfigurations')
                ->first();

            if (!$bonusConfigurationGroup) {
                return response()->json(['error' => 'Bonus configuration group not found'], 404);
            }

            return response()->json($bonusConfigurationGroup);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve bonus configuration group: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'is_active' => 'boolean',
                'configurations' => 'array',
                'configurations.*' => 'exists:bonus_configurations,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Get the authenticated user
            $user = Auth::user();

            // Find the bonus configuration group by ID and user ID
            $bonusConfigurationGroup = BonusConfigurationGroup::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$bonusConfigurationGroup) {
                return response()->json(['error' => 'Bonus configuration group not found'], 404);
            }

            // Update the bonus configuration group
            $bonusConfigurationGroup->name = $request->name;
            $bonusConfigurationGroup->description = $request->description;
            $bonusConfigurationGroup->is_active = $request->has('is_active') ? $request->is_active : $bonusConfigurationGroup->is_active;
            $bonusConfigurationGroup->save();

            // Update configurations if provided
            if ($request->has('configurations')) {
                // Detach all existing configurations
                $bonusConfigurationGroup->bonusConfigurations()->detach();

                // Attach new configurations
                $sortOrder = 0;
                foreach ($request->configurations as $configId) {
                    $bonusConfigurationGroup->bonusConfigurations()->attach($configId, [
                        'sort_order' => $sortOrder++
                    ]);
                }
            }

            // Load the configurations relationship
            $bonusConfigurationGroup->load('bonusConfigurations');

            return response()->json($bonusConfigurationGroup);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update bonus configuration group: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            // Get the authenticated user
            $user = Auth::user();

            // Find the bonus configuration group by ID and user ID
            $bonusConfigurationGroup = BonusConfigurationGroup::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$bonusConfigurationGroup) {
                return response()->json(['error' => 'Bonus configuration group not found'], 404);
            }

            // Delete the bonus configuration group
            // The relationships will be automatically detached due to the cascade delete in the migration
            $bonusConfigurationGroup->delete();

            return response()->json(['message' => 'Bonus configuration group deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete bonus configuration group: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Attach a bonus configuration group to a project.
     */
    public function attachToProject(Request $request, string $projectId)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'group_id' => 'required|exists:bonus_configuration_groups,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Get the authenticated user
            $user = Auth::user();

            // Find the project
            $project = Project::findOrFail($projectId);

            // Check if the user has permission to manage the project
            // This is a simplified check - in a real application, you would use a more robust authorization system
            if (!$user->can('manage_projects')) {
                return response()->json(['error' => 'You do not have permission to manage this project'], 403);
            }

            // Find the bonus configuration group
            $bonusConfigurationGroup = BonusConfigurationGroup::where('id', $request->group_id)
                ->where('user_id', $user->id)
                ->first();

            if (!$bonusConfigurationGroup) {
                return response()->json(['error' => 'Bonus configuration group not found'], 404);
            }

            // Check if the group is already attached to the project
            if ($project->bonusConfigurationGroups()->where('group_id', $bonusConfigurationGroup->id)->exists()) {
                return response()->json(['message' => 'Bonus configuration group is already attached to this project']);
            }

            // Attach the group to the project
            $project->bonusConfigurationGroups()->attach($bonusConfigurationGroup->id);

            return response()->json(['message' => 'Bonus configuration group attached to project successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to attach bonus configuration group to project: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Detach a bonus configuration group from a project.
     */
    public function detachFromProject(Request $request, string $projectId)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'group_id' => 'required|exists:bonus_configuration_groups,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Get the authenticated user
            $user = Auth::user();

            // Find the project
            $project = Project::findOrFail($projectId);

            // Check if the user has permission to manage the project
            if (!$user->can('manage_projects')) {
                return response()->json(['error' => 'You do not have permission to manage this project'], 403);
            }

            // Detach the group from the project
            $project->bonusConfigurationGroups()->detach($request->group_id);

            return response()->json(['message' => 'Bonus configuration group detached from project successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to detach bonus configuration group from project: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Duplicate a bonus configuration group.
     */
    public function duplicate(string $id)
    {
        try {
            // Get the authenticated user
            $user = Auth::user();

            // Find the bonus configuration group by ID and user ID
            $bonusConfigurationGroup = BonusConfigurationGroup::where('id', $id)
                ->where('user_id', $user->id)
                ->with('bonusConfigurations')
                ->first();

            if (!$bonusConfigurationGroup) {
                return response()->json(['error' => 'Bonus configuration group not found'], 404);
            }

            // Duplicate the group using the model's duplicate method
            $newGroup = $bonusConfigurationGroup->duplicate();

            // Load the configurations relationship
            $newGroup->load('bonusConfigurations');

            return response()->json($newGroup);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to duplicate bonus configuration group: ' . $e->getMessage()], 500);
        }
    }
}
