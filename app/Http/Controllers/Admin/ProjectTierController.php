<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectTier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectTierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('Admin/ProjectTiers/Index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'point_multiplier' => 'required|numeric|min:0',
            'min_profit_margin_percentage' => 'required|numeric|min:0|max:100',
            'max_profit_margin_percentage' => 'required|numeric|min:0|max:100|gte:min_profit_margin_percentage',
            'min_client_amount_pkr' => 'required|numeric|min:0',
            'max_client_amount_pkr' => 'required|numeric|min:0|gte:min_client_amount_pkr',
        ]);

        $projectTier = ProjectTier::create($validated);

        return redirect()->route('admin.project-tiers.index')
            ->with('success', 'Project tier created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProjectTier $projectTier)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'point_multiplier' => 'required|numeric|min:0',
            'min_profit_margin_percentage' => 'required|numeric|min:0|max:100',
            'max_profit_margin_percentage' => 'required|numeric|min:0|max:100|gte:min_profit_margin_percentage',
            'min_client_amount_pkr' => 'required|numeric|min:0',
            'max_client_amount_pkr' => 'required|numeric|min:0|gte:min_client_amount_pkr',
        ]);

        $projectTier->update($validated);

        return redirect()->route('admin.project-tiers.index')
            ->with('success', 'Project tier updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProjectTier $projectTier)
    {
        // Update any projects using this tier to have null tier_id
        $projectTier->projects()->update(['project_tier_id' => null]);

        // Delete the tier
        $projectTier->delete();

        return redirect()->route('admin.project-tiers.index')
            ->with('success', 'Project tier deleted successfully.');
    }
}
