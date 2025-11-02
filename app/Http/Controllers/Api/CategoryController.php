<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategorySet;
use App\Models\CategorySetBinding;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(CategorySet $categorySet)
    {
        $categories = $categorySet->categories()->orderBy('name')->get();

        return response()->json($categories);
    }

    /**
     * Store a category. If new_set_name is provided, create the set (and optional bindings) first.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_set_id' => ['nullable', 'integer', Rule::exists('category_sets', 'id')],
            'new_set_name' => ['nullable', 'string', 'max:255'],
            'allowed_models' => ['array'], // used only when creating new set
            'allowed_models.*' => ['string'],
        ]);

        if (empty($validated['category_set_id']) && empty($validated['new_set_name'])) {
            return response()->json(['message' => 'Either category_set_id or new_set_name is required.'], 422);
        }

        // Create set if requested
        $set = null;
        if (! empty($validated['new_set_name'])) {
            $set = new CategorySet(['name' => $validated['new_set_name']]);
            $set->save();

            $models = collect($validated['allowed_models'] ?? [])->filter()->unique();
            foreach ($models as $modelType) {
                CategorySetBinding::firstOrCreate([
                    'category_set_id' => $set->id,
                    'model_type' => $modelType,
                ]);
            }
        } else {
            $set = CategorySet::findOrFail($validated['category_set_id']);
        }

        // Create category
        $category = Category::create([
            'name' => $validated['name'],
            'category_set_id' => $set->id,
        ]);

        return response()->json([
            'category' => $category,
            'set' => $set->fresh('bindings'),
        ], 201);
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category_set_id' => ['sometimes', 'required', 'integer', Rule::exists('category_sets', 'id')],
        ]);

        if (isset($validated['name'])) {
            $category->name = $validated['name'];
        }
        if (isset($validated['category_set_id'])) {
            $category->category_set_id = $validated['category_set_id'];
        }
        $category->save();

        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
