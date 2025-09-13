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
            'is_repeatable' =>  'boolean',
            'is_link'   =>  'boolean',
            'is_selectable' =>  'boolean'
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
            'is_repeatable' =>  'boolean',
            'is_link'   =>  'boolean',
            'is_selectable' =>  'boolean'
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
        $schema = DB::getSchemaBuilder();
        // Get all files in the App/Models directory
        $modelFiles = File::files(app_path('Models'));

        // First pass: collect base info (class, table, columns)
        $registry = [];
        foreach ($modelFiles as $modelFile) {
            $modelName = $modelFile->getFilenameWithoutExtension();
            $className = 'App\\Models\\' . $modelName;
            if (class_exists($className) && is_subclass_of($className, 'Illuminate\Database\Eloquent\Model')) {
                $instance = new $className();
                $table = $instance->getTable();
                $columns = $schema->getColumnListing($table);
                $registry[$modelName] = [
                    'name' => $modelName,
                    'full_class' => $className,
                    'table' => $table,
                    'columns' => $columns,
                ];
            }
        }

        // Second pass: infer relationships (belongsTo via *_id, hasMany via reverse FK discovery)
        foreach ($registry as $modelName => $info) {
            $relationships = [];
            // belongsTo: scan own columns for *_id
            foreach ($info['columns'] as $col) {
                if (str_ends_with($col, '_id')) {
                    $relatedBase = Str::studly(Str::beforeLast($col, '_id'));
                    if (isset($registry[$relatedBase])) {
                        $relationships[] = [
                            'name' => Str::camel($relatedBase),
                            'type' => 'belongsTo',
                            'model' => $relatedBase,
                            'full_class' => $registry[$relatedBase]['full_class'],
                            'foreign_key' => $col,
                            'columns' => $registry[$relatedBase]['columns'],
                        ];
                    } else {
                        $candidate = 'App\\Models\\' . $relatedBase;
                        if (class_exists($candidate)) {
                            // Load columns if possible
                            $inst = new $candidate();
                            $relTable = $inst->getTable();
                            $relCols = $schema->hasTable($relTable) ? $schema->getColumnListing($relTable) : [];
                            $relationships[] = [
                                'name' => Str::camel($relatedBase),
                                'type' => 'belongsTo',
                                'model' => $relatedBase,
                                'full_class' => $candidate,
                                'foreign_key' => $col,
                                'columns' => $relCols,
                            ];
                        }
                    }
                }
            }

            // hasMany: other models that have currentModel_id
            $currentFk = Str::snake(Str::singular($modelName)) . '_id';
            foreach ($registry as $otherName => $otherInfo) {
                if ($otherName === $modelName) continue;
                if (in_array($currentFk, $otherInfo['columns'], true)) {
                    $relationships[] = [
                        'name' => Str::camel(Str::pluralStudly($otherName)),
                        'type' => 'hasMany',
                        'model' => $otherName,
                        'full_class' => $otherInfo['full_class'],
                        'foreign_key' => $currentFk,
                        'columns' => $otherInfo['columns'],
                    ];
                }
            }

            // Attach field metadata for enum/value sets if available
            $fieldMeta = [];
            try {
                $valueSets = app(\App\Services\ValueDictionaryRegistry::class)->all();
                if (isset($valueSets[$info['name']]['fields'])) {
                    foreach ($valueSets[$info['name']]['fields'] as $fieldName => $def) {
                        $fieldMeta[$fieldName] = array_merge(['enum' => true], $def);
                    }
                }
            } catch (\Throwable $e) {
                // Silently ignore to keep endpoint resilient if registry fails
            }

            $models[] = [
                'name' => $info['name'],
                'full_class' => $info['full_class'],
                'columns' => $info['columns'],
                'relationships' => $relationships,
                'field_meta' => $fieldMeta,
            ];
        }

        return response()->json($models);
    }
}
