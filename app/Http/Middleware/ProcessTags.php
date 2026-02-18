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
    public function handle(Request $request, Closure $next, ...$fields): Response
    {
        // If no fields are specified, default to 'tags' and 'tag_ids'
        $targetFields = ! empty($fields) ? $fields : ['tags', 'tag_ids'];

        foreach ($targetFields as $field) {
            if ($request->has($field) && is_array($request->input($field))) {
                $incomingIdentifiers = $request->input($field);
                $processedIds = [];

                foreach ($incomingIdentifiers as $identifier) {
                    if (is_string($identifier) && str_starts_with($identifier, 'new_')) {
                        // This is a new tag identified by the frontend (e.g., 'new_my-new-tag_timestamp')
                        $parts = explode('_', $identifier);
                        // Remove 'new' prefix
                        array_shift($parts);
                        // Remove timestamp suffix if it exists (numeric)
                        if (count($parts) > 1 && is_numeric(end($parts))) {
                            array_pop($parts);
                        }
                        // Rejoin remaining parts
                        $tagName = implode('_', $parts);

                        if (! empty($tagName)) {
                            // Find or create the tag based on its name
                            $tag = Tag::firstOrCreate(
                                ['name' => $tagName],
                                ['slug' => Str::slug($tagName)]
                            );
                            $processedIds[] = $tag->id;
                        }
                    } elseif (is_numeric($identifier)) {
                        // This is an existing tag ID, ensure it's an integer
                        $processedIds[] = (int) $identifier;
                    }
                }

                // Overwrite the original input with processed IDs
                $request->merge([$field => array_unique($processedIds)]);
            }
        }

        return $next($request);
    }
}
