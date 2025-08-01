<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlaceholderDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PlaceholderDefinitionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $placeholderDefinitions = PlaceholderDefinition::all();
        return response()->json($placeholderDefinitions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:placeholder_definitions,name',
            'description' => 'nullable|string',
            'source_model' => 'nullable|string|max:255',
            'source_attribute' => 'nullable|string|max:255',
            'is_dynamic' => 'boolean',
            'is_repeatable' =>  'boolean'
        ]);

        $placeholder = PlaceholderDefinition::create($request->all());

        return response()->json($placeholder, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(PlaceholderDefinition $placeholderDefinition)
    {
        return response()->json($placeholderDefinition);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PlaceholderDefinition $placeholderDefinition)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:placeholder_definitions,name,' . $placeholderDefinition->id,
            'description' => 'nullable|string',
            'source_model' => 'nullable|string|max:255',
            'source_attribute' => 'nullable|string|max:255',
            'is_dynamic' => 'boolean',
        ]);

        $placeholderDefinition->update($request->all());

        return response()->json($placeholderDefinition);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PlaceholderDefinition $placeholderDefinition)
    {
        $placeholderDefinition->delete();

        return response()->json(null, 204);
    }

    /**
     * Get a list of application models and their columns.
     * This will be used to populate dropdowns in the frontend.
     */
    public function getModelsAndColumns()
    {
        $models = [];
        // Get all files in the App/Models directory
        $modelFiles = File::files(app_path('Models'));

        foreach ($modelFiles as $modelFile) {
            $modelName = $modelFile->getFilenameWithoutExtension();
            $className = 'App\\Models\\' . $modelName;

            // Check if the class is a subclass of Eloquent\Model
            if (class_exists($className) && is_subclass_of($className, 'Illuminate\Database\Eloquent\Model')) {
                // Get table name from model
                $modelInstance = new $className();
                $tableName = $modelInstance->getTable();

                // Get columns from the table
                $columns = DB::getSchemaBuilder()->getColumnListing($tableName);

                $models[] = [
                    'name' => $modelName,
                    'full_class' => $className,
                    'columns' => $columns,
                ];
            }
        }
        return response()->json($models);
    }
}
