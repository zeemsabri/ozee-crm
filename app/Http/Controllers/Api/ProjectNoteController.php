<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProjectNoteController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $noteableId = $request->input('noteable_id');
        $noteableType = $request->input('noteable_type');

        $query = ProjectNote::query();
        if ($noteableId && $noteableType) {
            $query->where('noteable_id', $noteableId)
                ->where('noteable_type', $noteableType);
        }
        $query->with('user:id,name');
        $query->orderBy('created_at', 'desc');
        return $query->get();
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

//        try {
            $validated = $request->validate([
                'body' => 'required|string',
                'noteable_id' => 'required|integer',
                'noteable_type' => 'required|string',
                'type' => 'nullable|string',
                'parent_id' => 'nullable|exists:project_notes,id',
            ]);

            $note = new ProjectNote();
            $note->content = $validated['body'];
            $note->noteable_id = $validated['noteable_id'];
            $note->noteable_type = $validated['noteable_type'];
            $note->type = ProjectNote::COMMENT;
            $note->parent_id = $validated['parent_id'] ?? null;
            $note->user_id = $user->id;
            $note->save();

            return response()->json($note->load('user:id,name'), 201);
//        } catch (ValidationException $e) {
//            return response()->json([
//                'message' => 'Validation failed',
//                'errors' => $e->errors(),
//            ], 422);
//        } catch (\Throwable $e) {
//            Log::error('Failed to create project note: '.$e->getMessage());
//            return response()->json(['message' => 'Failed to create note'], 500);
//        }
    }
}
