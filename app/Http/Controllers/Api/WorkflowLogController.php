<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use Illuminate\Http\Request;

class WorkflowLogController extends Controller
{
    /**
     * List execution logs for a workflow (paginated).
     */
    public function index(Request $request, Workflow $workflow)
    {
        $perPage = (int) $request->get('per_page', 50);
        $perPage = max(1, min($perPage, 200));

        $query = $workflow->logs()->with('step')
            ->orderByDesc('id');

        // Optional filters
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($stepId = $request->get('step_id')) {
            $query->where('step_id', (int) $stepId);
        }

        return response()->json($query->paginate($perPage));
    }
}
