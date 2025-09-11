<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    public function index()
    {
        return response()->json(Workflow::with('steps')->orderBy('id', 'desc')->paginate(50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_event' => 'required|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $workflow = Workflow::create($data);
        return response()->json($workflow->load('steps'), 201);
    }

    public function show(Workflow $workflow)
    {
        return response()->json($workflow->load('steps'));
    }

    public function update(Request $request, Workflow $workflow)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'trigger_event' => 'sometimes|required|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $workflow->update($data);
        return response()->json($workflow->load('steps'));
    }

    public function destroy(Workflow $workflow)
    {
        $workflow->delete();
        return response()->noContent();
    }
}
