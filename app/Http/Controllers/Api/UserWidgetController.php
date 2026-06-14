<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserNote;
use App\Models\UserMetadataKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserWidgetController extends Controller
{
    public function getMetadata(User $user)
    {
        return response()->json([
            'metadata' => $user->metadata ?? [],
            'keys' => UserMetadataKey::all()
        ]);
    }

    public function updateMetadata(Request $request, User $user)
    {
        if (!Auth::user()->hasPermission('edit_users')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'metadata' => 'required|array'
        ]);

        $user->update(['metadata' => $validated['metadata']]);

        return response()->json([
            'message' => 'Metadata updated successfully',
            'metadata' => $user->metadata
        ]);
    }

    public function getNotes(User $user)
    {
        $notes = $user->userNotes()->with('author:id,name')->latest()->get();
        return response()->json($notes);
    }

    public function addNote(Request $request, User $user)
    {
        $validated = $request->validate([
            'content' => 'required|string'
        ]);

        $note = $user->userNotes()->create([
            'author_id' => Auth::id(),
            'content' => $validated['content']
        ]);

        return response()->json($note->load('author:id,name'));
    }

    public function getKeys()
    {
        return response()->json(UserMetadataKey::all());
    }

    public function addKey(Request $request)
    {
        // Only users with edit_users permission can add keys
        if (!Auth::user()->hasPermission('edit_users')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'key' => 'required|string|unique:user_metadata_keys,key',
            'label' => 'required|string',
            'type' => 'required|string|in:text,date,number'
        ]);

        $key = UserMetadataKey::create($validated);

        return response()->json($key);
    }
}
