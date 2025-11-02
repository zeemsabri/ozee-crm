<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CategorySet;
use App\Models\CategorySetBinding;
use Illuminate\Http\Request;

class CategorySetController extends Controller
{
    public function index()
    {
        $sets = CategorySet::withCount('categories')->with('bindings')->orderBy('name')->get();

        return response()->json($sets);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'allowed_models' => 'array', // array of FQCN strings
            'allowed_models.*' => 'string',
        ]);

        $set = new CategorySet(['name' => $validated['name']]);
        // slug will be generated in model creating event
        $set->save();

        // Sync bindings
        $models = collect($validated['allowed_models'] ?? [])->filter()->unique();
        foreach ($models as $modelType) {
            CategorySetBinding::firstOrCreate([
                'category_set_id' => $set->id,
                'model_type' => $modelType,
            ]);
        }

        return response()->json($set->load('bindings'), 201);
    }

    public function update(Request $request, CategorySet $categorySet)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'allowed_models' => 'array',
            'allowed_models.*' => 'string',
        ]);

        if (isset($validated['name'])) {
            $categorySet->name = $validated['name'];
            // do not auto-change slug on update to avoid breaking URLs; optional behavior
            $categorySet->save();
        }

        if ($request->has('allowed_models')) {
            $models = collect($validated['allowed_models'] ?? [])->filter()->unique();
            // Sync bindings: delete old, insert new
            CategorySetBinding::where('category_set_id', $categorySet->id)->delete();
            foreach ($models as $modelType) {
                CategorySetBinding::create([
                    'category_set_id' => $categorySet->id,
                    'model_type' => $modelType,
                ]);
            }
        }

        return response()->json($categorySet->load('bindings'));
    }

    public function destroy(CategorySet $categorySet)
    {
        $categorySet->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
