<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserWorkspaceController extends Controller
{
    /**
     * Return the authenticated user's workspace data (checklist and notes).
     */
    public function workspace(Request $request)
    {
        $user = Auth::user();
        $checklist = is_array($user->checklist) ? $user->checklist : [];
        $notes = $user->notes;
        // Normalize notes to a simple string for the frontend textarea
        $notesText = '';
        if (is_array($notes)) {
            $notesText = (string)($notes['text'] ?? '');
        } elseif (is_string($notes)) {
            $notesText = $notes; // In case stored as string
        }

        return response()->json([
            'checklist' => $checklist,
            'notes' => $notesText,
        ]);
    }

    /**
     * Update the authenticated user's checklist.
     * Expected payload: { items: [{ name: string, completed: bool }, ...] }
     */
    public function updateChecklist(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.name' => 'required|string|max:255',
            'items.*.completed' => 'required|boolean',
        ]);

        $user->checklist = array_values($validated['items']);
        $user->save();

        return response()->json([
            'checklist' => $user->checklist,
            'message' => 'Checklist updated successfully',
        ]);
    }

    /**
     * Update the authenticated user's notes.
     * Expected payload: { text: string }
     */
    public function updateNotes(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'text' => 'nullable|string',
        ]);

        $user->notes = ['text' => (string)($validated['text'] ?? '')];
        $user->save();

        return response()->json([
            'notes' => $user->notes,
            'message' => 'Notes updated successfully',
        ]);
    }
}
