<?php

namespace App\Models\Traits;

use App\Models\Category;
use App\Models\CategorySet;
use Illuminate\Support\Collection;

trait HasCategories
{
    public function categories()
    {
        return $this->morphToMany(Category::class, 'categorizable', 'categorizables')
            ->withTimestamps();
    }

    /**
     * Sync categories on the model. Accepts array of category IDs.
     */
    public function syncCategories(array $categoryIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $categoryIds)));
        $this->categories()->sync($ids);
    }

    /**
     * Attach category IDs without detaching existing ones.
     */
    public function attachCategories(array $categoryIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $categoryIds)));
        $this->categories()->syncWithoutDetaching($ids);
    }

    /**
     * Detach category IDs.
     */
    public function detachCategories(array $categoryIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $categoryIds)));
        $this->categories()->detach($ids);
    }

    /**
     * Return Category Sets that are allowed for the current model class (including global sets with no bindings).
     */
    public static function availableCategorySets(): Collection
    {
        $modelClass = static::class;

        return CategorySet::query()
            ->where(function ($q) use ($modelClass) {
                $q->whereDoesntHave('bindings')
                    ->orWhereHas('bindings', function ($b) use ($modelClass) {
                        $b->where('model_type', $modelClass);
                    });
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Return Categories allowed for the current model class, optionally filtered by set slug.
     * Includes categories from global sets (no bindings).
     */
    public static function availableCategories(?string $setSlug = null): Collection
    {
        $modelClass = static::class;

        $query = Category::with('set')
            ->where(function ($q) use ($modelClass) {
                $q->whereHas('set.bindings', function ($b) use ($modelClass) {
                    $b->where('model_type', $modelClass);
                })
                    ->orWhereHas('set', function ($s) {
                        $s->doesntHave('bindings');
                    });
            });

        if ($setSlug) {
            $query->whereHas('set', function ($q) use ($setSlug) {
                $q->where('slug', $setSlug);
            });
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Simple options array: [{ value: id, label: name }] for all allowed categories (optionally by set slug).
     */
    public static function availableCategoryOptions(?string $setSlug = null, ?string $category = null): array
    {
        return static::availableCategories($setSlug)
            ->map(fn (Category $c) => ['value' => $c->id, 'label' => $c->name])
            ->when($category, function ($query) use ($category) {
                $query->whereRaw('LOWER(name) = ?', [strtolower($category)]);
            })
            ->values()
            ->all();
    }

    /**
     * Grouped options by set slug: [{ set: {id, name, slug}, options: [{value,label}, ...] }, ...]
     */
    public static function availableCategoryOptionsGrouped(): array
    {
        $categories = static::availableCategories()->load('set');

        return $categories
            ->groupBy(fn (Category $c) => $c->set?->slug ?: 'global')
            ->map(function ($group) {
                /** @var Category $first */
                $first = $group->first();
                $set = $first?->set;

                return [
                    'set' => $set ? [
                        'id' => $set->id,
                        'name' => $set->name,
                        'slug' => $set->slug,
                    ] : [
                        'id' => null,
                        'name' => 'Global',
                        'slug' => 'global',
                    ],
                    'options' => $group->map(fn (Category $c) => ['value' => $c->id, 'label' => $c->name])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
