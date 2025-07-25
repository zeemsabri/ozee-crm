<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BonusConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BonusConfigurationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            // Get the authenticated user
            $user = Auth::user();

            // Retrieve all bonus configurations for the user
            $bonusConfigurations = BonusConfiguration::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($bonusConfigurations);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve bonus configurations: ' . $e->getMessage()], 500);
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
                'type' => 'required|in:bonus,penalty',
                'amountType' => 'required|in:percentage,fixed,all_related_bonus',
                'value' => 'required_unless:amountType,all_related_bonus|numeric|min:0',
                'appliesTo' => 'required|in:task,milestone,standup,late_task,late_milestone,standup_missed',
                'targetBonusTypeForRevocation' => 'required_if:amountType,all_related_bonus|nullable|string|max:255',
                'isActive' => 'boolean',
                'uuid' => 'required|string|unique:bonus_configurations,uuid',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Get the authenticated user
            $user = Auth::user();

            // Create a new bonus configuration
            $bonusConfiguration = new BonusConfiguration($request->all());
            $bonusConfiguration->user_id = $user->id;
            $bonusConfiguration->save();

            return response()->json($bonusConfiguration, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create bonus configuration: ' . $e->getMessage()], 500);
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

            // Find the bonus configuration by ID and user ID
            $bonusConfiguration = BonusConfiguration::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$bonusConfiguration) {
                return response()->json(['error' => 'Bonus configuration not found'], 404);
            }

            return response()->json($bonusConfiguration);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve bonus configuration: ' . $e->getMessage()], 500);
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
                'type' => 'required|in:bonus,penalty',
                'amountType' => 'required|in:percentage,fixed,all_related_bonus',
                'value' => 'required_unless:amountType,all_related_bonus|numeric|min:0',
                'appliesTo' => 'required|in:task,milestone,standup,late_task,late_milestone,standup_missed',
                'targetBonusTypeForRevocation' => 'required_if:amountType,all_related_bonus|nullable|string|max:255',
                'isActive' => 'boolean',
                'uuid' => 'required|string|unique:bonus_configurations,uuid,' . $id,
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Get the authenticated user
            $user = Auth::user();

            // Find the bonus configuration by ID and user ID
            $bonusConfiguration = BonusConfiguration::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$bonusConfiguration) {
                return response()->json(['error' => 'Bonus configuration not found'], 404);
            }

            // Update the bonus configuration
            $bonusConfiguration->update($request->all());

            return response()->json($bonusConfiguration);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update bonus configuration: ' . $e->getMessage()], 500);
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

            // Find the bonus configuration by ID and user ID
            $bonusConfiguration = BonusConfiguration::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$bonusConfiguration) {
                return response()->json(['error' => 'Bonus configuration not found'], 404);
            }

            // Delete the bonus configuration
            $bonusConfiguration->delete();

            return response()->json(['message' => 'Bonus configuration deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete bonus configuration: ' . $e->getMessage()], 500);
        }
    }
}
