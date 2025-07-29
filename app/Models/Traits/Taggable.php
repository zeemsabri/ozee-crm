<?php

namespace App\Models\Traits;

use App\Models\Tag;
use Illuminate\Support\Str; // While Str::slug won't be used for existing tags, it's good practice to keep if other methods in trait might need it.

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
     * This method now expects an array of tag IDs (integers).
     * The processing of new tags from names to IDs should be handled
     * by a middleware (e.g., ProcessTags) before this method is called.
     *
     * @param array $tagIds - An array of integer tag IDs to sync.
     * @return void
     */
    public function syncTags(array $tagIds)
    {
        // Directly sync the tags with the model using the provided array of IDs.
        // The middleware has already ensured these are valid integer IDs.
        $this->tags()->sync($tagIds);
    }
}
