<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    /**
     * Search for tags based on a query string.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        $query = $request->input('query');

        // If no query is provided, return an empty array
        if (empty($query)) {
            return response()->json([]);
        }

        // Search for tags where name is like the query
        $tags = Tag::where('name', 'LIKE', "%{$query}%")
            ->limit(10)
            ->get(['id', 'name']);

        return response()->json($tags);
    }
}
