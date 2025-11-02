<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityController extends Controller
{
    /**
     * Get activities for a specific subject
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'subject_type' => 'sometimes|string',
            'subject_id' => 'required_with:subject_type|integer',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        // Start with a base query
        $query = Activity::with('causer');

        // Filter by subject if provided
        if ($request->has('subject_type') && $request->has('subject_id')) {
            $subjectType = $request->input('subject_type');
            $subjectId = $request->input('subject_id');

            $query->where('subject_type', $subjectType)
                ->where('subject_id', $subjectId);
        }

        // Apply limit if provided, otherwise default to 50
        $limit = $request->input('limit', 50);

        // Get the activities ordered by most recent first
        $activities = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json($activities);
    }
}
