<?php

namespace App\Models\Traits;

use App\Models\Tag;
use Illuminate\Support\Str;

trait Taggable
{
    /**
     * Get all tags for this model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /**
     * Sync tags for this model.
     *
     * @param array|string $tags
     * @return void
     */
    public function syncTags($tags)
    {
        // If tags is a string, convert it to an array
        if (is_string($tags)) {
            $tags = array_map('trim', explode(',', $tags));
        }

        // Array to store tag IDs
        $tagIds = [];

        // Process each tag
        foreach ($tags as $tagName) {
            if (!empty($tagName)) {
                // Find or create the tag
                $tag = Tag::firstOrCreate(
                    ['name' => $tagName],
                    ['slug' => Str::slug($tagName)]
                );

                // Add the tag ID to our array
                $tagIds[] = $tag->id;
            }
        }

        // Sync the tags with the model
        $this->tags()->sync($tagIds);
    }
}
