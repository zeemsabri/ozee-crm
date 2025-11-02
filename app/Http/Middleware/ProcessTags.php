<?php

namespace App\Http\Middleware;

use App\Models\Tag; // Make sure your Tag model is imported
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str; // For Str::slug
use Symfony\Component\HttpFoundation\Response;

class ProcessTags
{
    /**
     * Handle an incoming request.
     *
     * This middleware pre-processes the 'tags' input from incoming requests.
     * If the 'tags' input is an array (which can contain existing tag IDs or new tag names with 'new_' prefix),
     * it converts all identifiers into actual tag IDs, creating new tags if necessary.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if the request has a 'tags' input and if it's an array
        if ($request->has('tags') && is_array($request->input('tags'))) {
            $incomingTagIdentifiers = $request->input('tags');
            $processedTagIds = [];

            foreach ($incomingTagIdentifiers as $tagIdentifier) {
                if (is_string($tagIdentifier) && str_starts_with($tagIdentifier, 'new_')) {
                    // This is a new tag identified by the frontend (e.g., 'new_my-new-tag_timestamp')
                    // Extract the actual tag name by removing 'new_' prefix and the timestamp suffix
                    $parts = explode('_', $tagIdentifier);
                    // Remove 'new' prefix
                    array_shift($parts);
                    // Remove timestamp suffix
                    array_pop($parts);
                    // Rejoin remaining parts in case the tag name itself contained underscores
                    $tagName = implode('_', $parts);

                    if (! empty($tagName)) {
                        // Find or create the tag based on its name
                        $tag = Tag::firstOrCreate(
                            ['name' => $tagName],
                            ['slug' => Str::slug($tagName)] // Generate slug from name
                        );
                        $processedTagIds[] = $tag->id;
                    }
                } elseif (is_numeric($tagIdentifier)) {
                    // This is an existing tag ID, ensure it's an integer
                    $processedTagIds[] = (int) $tagIdentifier;
                }
                // Optional: You might want to log or handle invalid tagIdentifier types here
            }

            // Overwrite the original 'tags' input with an array of processed tag IDs
            // Use array_unique to prevent duplicate IDs if the same tag was added multiple times
            $request->merge(['tags' => array_unique($processedTagIds)]);
        }

        return $next($request);
    }
}
