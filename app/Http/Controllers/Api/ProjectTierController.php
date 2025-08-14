<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectTier;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProjectTierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return ProjectTier::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'point_multiplier' => 'required|numeric|min:0',
                'min_profit_margin_percentage' => 'required|numeric|min:0|max:100',
                'max_profit_margin_percentage' => 'required|numeric|min:0|max:100|gte:min_profit_margin_percentage',
                'min_client_amount_pkr' => 'required|numeric|min:0',
                'max_client_amount_pkr' => 'required|numeric|min:0|gte:min_client_amount_pkr',
            ]);

            $projectTier = ProjectTier::create($validated);

            return response()->json($projectTier, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create project tier',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ProjectTier $projectTier)
    {
        return $projectTier;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProjectTier $projectTier)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'point_multiplier' => 'required|numeric|min:0',
                'min_profit_margin_percentage' => 'required|numeric|min:0|max:100',
                'max_profit_margin_percentage' => 'required|numeric|min:0|max:100|gte:min_profit_margin_percentage',
                'min_client_amount_pkr' => 'required|numeric|min:0',
                'max_client_amount_pkr' => 'required|numeric|min:0|gte:min_client_amount_pkr',
            ]);

            $projectTier->update($validated);

            return response()->json($projectTier);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update project tier',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProjectTier $projectTier)
    {
        try {
            // Update any projects using this tier to have null tier_id
            $projectTier->projects()->update(['project_tier_id' => null]);

            // Delete the tier
            $projectTier->delete();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete project tier',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
