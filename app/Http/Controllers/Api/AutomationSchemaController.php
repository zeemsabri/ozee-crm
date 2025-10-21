<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema; // Ensure this is imported
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

class AutomationSchemaController extends Controller
{
    /**
     * Provide the data schema required by the automation builder UI.
     * Adds per-column type hints and allowed enum values using config/value_sets.php.
     */
    public function getSchema(Request $request)
    {
        // Seed with commonly used base models; we will auto-include related models discovered via reflection.
        $seedModels = [
            \App\Models\Task::class,
            \App\Models\Project::class,
            \App\Models\Email::class,
            \App\Models\Category::class,
            \App\Models\CategorySet::class,
            Campaign::class,
            Lead::class,
//            User::class
        ];

        $allModelEvents = $this->getModelEvents();

        // Helper to build one model schema array
        $buildModel = function (string $modelClass) use ($allModelEvents, $request) {

            if (!class_exists($modelClass)) return null;
            try {
                $instance = new $modelClass();
                $modelName = class_basename($instance);
                $columns = Schema::getColumnListing($instance->getTable());
                $relationships = $this->discoverRelationships($instance);
                $required = [];
                $defaults = [];

                if (is_subclass_of($modelClass, \App\Contracts\CreatableViaWorkflow::class)) {
                    $ctx = $request->input('context', []);
                    try { $required = $modelClass::requiredOnCreate(); } catch (\Throwable $e) {}
                    try { $defaults = $modelClass::defaultsOnCreate($ctx); } catch (\Throwable $e) {}
                }
                // Optional, model-provided field metadata for friendly labels/descriptions/UI hints
                $fieldMeta = [];
                try {
                    if (method_exists($modelClass, 'fieldMetaForWorkflow')) {
                        $fieldMeta = $modelClass::fieldMetaForWorkflow();
                    }
                } catch (\Throwable $e) { /* no-op */ }

                // Build quick lookup of MorphTo relations to enable *_type dropdowns
                $morphRelations = [];
                foreach (($relationships ?? []) as $rel) {
                    if (($rel['type'] ?? null) === 'MorphTo' && !empty($rel['name'])) {
                        $morphRelations[$rel['name']] = true;
                    }
                }

                $columnsMeta = array_map(function ($col) use ($instance, $modelName, $required, $fieldMeta, $morphRelations) {
                    $type = $this->guessColumnType($instance, $col);
                    $allowed = $this->getAllowedValues($modelName, $col);

                    // Friendly label/description/ui from model meta
                    $label = $this->prettifyLabel($col);
                    $description = null;
                    $ui = null;
                    if (!empty($fieldMeta[$col])) {
                        $label = $fieldMeta[$col]['label'] ?? $label;
                        $description = $fieldMeta[$col]['description'] ?? null;
                        $ui = $fieldMeta[$col]['ui'] ?? null;
                    }

                    // Detect morph-type columns (e.g., referencable_type for relation 'referencable')
                    if (str_ends_with($col, '_type')) {
                        $base = substr($col, 0, -5);
                        if (isset($morphRelations[$base])) {
                            $morphMap = Relation::morphMap() ?: [];
                            $options = [];
                            if (!empty($morphMap)) {
                                foreach ($morphMap as $alias => $class) {
                                    $options[] = [ 'value' => $alias, 'label' => class_basename($class) ];
                                }
                            } else {
                                // Fallback to seed models exposed in schema
                                $known = [\App\Models\Task::class, \App\Models\Project::class, \App\Models\Email::class, Client::class, Lead::class, User::class, User::class];
                                foreach ($known as $class) {
                                    if (class_exists($class)) {
                                        $options[] = [ 'value' => $class, 'label' => class_basename($class) ];
                                    }
                                }
                            }
                            if (!empty($options)) {
                                $allowed = $options;
                                $type = 'enum';
                                $ui = $ui ?: 'morph_type';
                                if (!$description) {
                                    $description = 'Select the type of item (e.g., Task, Project, Email).';
                                }
                            }
                        }
                    }

                    return [
                        'name' => $col,
                        'label' => $label,
                        'type' => $type,
                        'allowed_values' => $allowed,
                        'is_required' => in_array($col, $required, true),
                        'description' => $description,
                        'ui' => $ui,
                    ];
                }, $columns);
                return [
                    'name' => $modelName,
                    'full_class' => $modelClass,
                    'columns' => $columnsMeta,
                    'relationships' => $relationships,
                    'events' => $allModelEvents[$modelName] ?? [],
                    'required_on_create' => array_values(array_unique($required)),
                    'defaults_on_create' => $defaults,
                ];
            } catch (\Throwable $e) {
                return null;
            }
        };

        // BFS over relationships to include related models
        $queue = [];
        $seenClasses = [];
        foreach ($seedModels as $m) { if (class_exists($m)) { $queue[] = $m; $seenClasses[$m] = true; } }
        $modelsByName = [];
        $iterations = 0; $max = 99; // safety guard
        while (!empty($queue) && $iterations < $max) {
            $iterations++;
            $class = array_shift($queue);
            $schema = $buildModel($class);
            if (!$schema) continue;
            $modelsByName[$schema['name']] = $schema;
            // Enqueue related model classes for discovery
            foreach (($schema['relationships'] ?? []) as $rel) {
                $relatedClass = $rel['full_class'] ?? null;
                if (is_string($relatedClass) && class_exists($relatedClass) && !isset($seenClasses[$relatedClass])) {
                    $seenClasses[$relatedClass] = true;
                    $queue[] = $relatedClass;
                }
            }
        }

        // Sort models alphabetically by name for UI consistency
        $modelsData = array_values($modelsByName);
        usort($modelsData, fn($a, $b) => strcmp($a['name'], $b['name']));

        // Also expose campaigns (id/name) for AI context selector, when present on the frontend
        $campaigns = [];
        if (class_exists(Campaign::class)) {
            $campaigns = Campaign::query()->select('id', 'name')->orderBy('name')->get();
        }

        return response()->json([
            'models' => $modelsData,
            'campaigns' => $campaigns,
            'transforms' => $this->getTransformOptions(),
            'morph_map' => $this->getMorphMapForUi(),
        ]);
    }

    /**
     * Defines the available trigger events for each model.
     */
    private function getModelEvents(): array
    {
        return [
            'Task' => [
                ['value' => 'created', 'label' => 'is created'],
                ['value' => 'updated', 'label' => 'is updated'],
                ['value' => 'completed', 'label' => 'is completed'],
                ['value' => 'status_changed', 'label' => 'status is changed'],
                ['value' => 'assigned', 'label' => 'is assigned to someone'],
            ],
            'Project' => [
                ['value' => 'created', 'label' => 'is created'],
                ['value' => 'completed', 'label' => 'is completed'],
                ['value' => 'archived', 'label' => 'is archived'],
            ],
            'Email' => [
                ['value' => 'received', 'label' => 'is received'],
                ['value' => 'created', 'label' => 'is created'],
                ['value' => 'updated', 'label' => 'is updated'],
            ],
            'Lead' => [
                ['value' => 'created', 'label' => 'is created'],
                ['value' => 'updated', 'label' => 'is updated'],
            ],
            'User'  =>  [
                ['value' => 'received', 'label' => 'is received'],
                ['value' => 'created', 'label' => 'is created'],
                ['value' => 'updated', 'label' => 'is updated'],
            ]
        ];
    }

    /**
     * Best-effort type inference without requiring doctrine/dbal.
     * Uses model casts and common naming conventions.
     */
    private function guessColumnType($modelInstance, string $column): string
    {
        $casts = method_exists($modelInstance, 'getCasts') ? $modelInstance->getCasts() : [];
        $cast = $casts[$column] ?? null;
        $castStr = is_string($cast) ? strtolower($cast) : '';
        if (str_contains($castStr, 'bool')) return 'True/False';
        if (str_contains($castStr, 'int') || str_contains($castStr, 'decimal') || str_contains($castStr, 'float')) return 'Number';
        if ($castStr === 'array' || $castStr === 'json' || $castStr === 'collection') return 'Array';
        if (str_contains($castStr, 'datetime')) return 'DateTime';
        if ($castStr === 'date') return 'Date';

        // Heuristics by column name
        if ($column === 'id' || str_ends_with($column, '_id')) return 'Number';
        if (str_starts_with($column, 'is_')) return 'True/False';
        if (str_ends_with($column, '_at')) return 'DateTime';
        if (str_ends_with($column, '_date')) return 'Date';

        return 'Text';
    }

    /**
     * Pull allowed values for specific fields from config/value_sets.php.
     * Supports PHP backed enums.
     * Returns list of [value,label] pairs or null.
     */
    private function getAllowedValues(string $modelName, string $field): ?array
    {

        $def = config("value_sets.models.$modelName.$field");
        if (!$def || !is_array($def)) return null;

        $source = $def['source'] ?? null;

        // 1) PHP backed enum
        if ($source === 'php_enum' && isset($def['enum']) && is_string($def['enum']) && class_exists($def['enum'])) {
            $enumClass = $def['enum'];
            if ((function_exists('enum_exists') && enum_exists($enumClass)) || \PHP_VERSION_ID >= 80100) {
                $options = [];
                foreach ($enumClass::cases() as $case) {
                    $value = property_exists($case, 'value') ? $case->value : $case->name;
                    $label = ucwords(str_replace(['_', '-'], ' ', (string) $case->name));
                    $options[] = ['value' => $value, 'label' => $label];
                }
                return $options;
            }
        }

        // 2) Eloquent model source (e.g., TaskType)
        if ($source === 'model' && isset($def['class']) && class_exists($def['class'])) {
            $class = $def['class'];
            $valueCol = $def['value_column'] ?? 'id';
            $labelCol = $def['label_column'] ?? 'name';
            $activeCol = $def['active_column'] ?? null;
            try {
                $query = $class::query()->select([$valueCol, $labelCol]);
                if ($activeCol) {
                    $query->where($activeCol, true);
                }
                $rows = $query->orderBy($labelCol)->get();
                return $rows->map(fn($r) => [
                    'value' => (string) $r->{$valueCol},
                    'label' => (string) $r->{$labelCol},
                ])->all();
            } catch (\Throwable $e) {
                return null;
            }
        }

        // 3) DB table source
        if ($source === 'db' && isset($def['table'])) {
            $table = $def['table'];
            $valueCol = $def['value_column'] ?? 'id';
            $labelCol = $def['label_column'] ?? 'name';
            $activeCol = $def['active_column'] ?? null;
            try {
                $query = DB::table($table)->select([$valueCol, $labelCol]);
                if ($activeCol) {
                    $query->where($activeCol, true);
                }
                $rows = $query->orderBy($labelCol)->get();
                return $rows->map(fn($r) => [
                    'value' => (string) $r->{$valueCol},
                    'label' => (string) $r->{$labelCol},
                ])->all();
            } catch (\Throwable $e) {
                return null;
            }
        }

        // Future: support other sources (config/model_const)
        return null;
    }

    /**
     * Convert a database column name into a human-friendly label.
     */
    private function prettifyLabel(string $column): string
    {
        $lower = strtolower($column);
        // Common special cases
        if ($lower === 'id') return 'ID';
        if ($lower === 'created_at') return 'Created At';
        if ($lower === 'updated_at') return 'Updated At';
        if ($lower === 'deleted_at') return 'Deleted At';

        // Strip trailing _id to show the related entity name
        if (str_ends_with($lower, '_id')) {
            $lower = substr($lower, 0, -3);
        }

        // Replace underscores/dashes with spaces and Title Case
        $spaced = str_replace(['_', '-'], ' ', $lower);
        // Handle boolean style prefixes like is_*, has_*
        $spaced = preg_replace_callback('/\b(is|has|was|can)\b\s+/i', function ($m) {
            return ucfirst(strtolower($m[1])) . ' ';
        }, $spaced);

        // Title case
        $label = ucwords($spaced);

        // Small touch: URL/ID capitalization if present as words
        $label = preg_replace('/\bId\b/', 'ID', $label);
        $label = preg_replace('/\bUrl\b/', 'URL', $label);

        return $label;
    }

    /**
     * Placeholder for your existing relationship discovery logic.
     */
    private function discoverRelationships($modelInstance): array
    {
        $relationships = [];
        try {
            $ref = new \ReflectionClass($modelInstance);
            $className = get_class($modelInstance);
            foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                // Only consider methods declared on the concrete model class, with no parameters
                if ($method->class !== $className) continue;
                if ($method->isStatic()) continue;
                if ($method->getNumberOfParameters() > 0) continue;
                $name = $method->getName();
                // Skip common non-relationship accessors/mutators/scopes
                if (str_starts_with($name, 'get') || str_starts_with($name, 'set') || str_starts_with($name, 'scope')) continue;
                if (in_array($name, ['newQuery', 'newModelQuery', 'newEloquentBuilder', 'newCollection', 'getTable'], true)) continue;
                try {
                    $result = $modelInstance->{$name}();
                    if ($result instanceof Relation) {
                        $relatedClass = get_class($result->getRelated());
                        $relationships[] = [
                            'name' => $name,
                            'type' => class_basename($result),
                            'model' => class_basename($relatedClass),
                            'full_class' => $relatedClass,
                        ];
                    }
                } catch (\Throwable $e) {
                    // Ignore methods that are not relations or throw errors on invocation
                    continue;
                }
            }
        } catch (\Throwable $e) {
            // If reflection fails, just return empty
        }
        // Sort by name for consistency
        usort($relationships, fn($a, $b) => strcmp($a['name'], $b['name']));
        return $relationships;
    }
    private function getTransformOptions(): array
    {
        return [
            [ 'value' => 'remove_after_marker', 'label' => 'Remove content after a marker' ],
            [ 'value' => 'find_and_replace', 'label' => 'Find and replace text' ],
        ];
    }

    private function getMorphMapForUi(): array
    {
        $map = Relation::morphMap() ?: [];
        $out = [];
        if (!empty($map)) {
            foreach ($map as $alias => $class) {
                $out[] = [
                    'alias' => $alias,
                    'class' => $class,
                    'label' => class_basename($class),
                ];
            }
            return $out;
        }
        // Fallback when no global morph map is configured: suggest common models present in the app
        $fallbacks = [
            \App\Models\Client::class,
            \App\Models\Lead::class,
            \App\Models\Project::class,
            \App\Models\Task::class,
            \App\Models\Email::class,
            \App\Models\User::class,
        ];
        foreach ($fallbacks as $class) {
            if (class_exists($class)) {
                $alias = strtolower(class_basename($class));
                $out[] = [
                    'alias' => $alias,
                    'class' => $class,
                    'label' => class_basename($class),
                ];
            }
        }
        return $out;
    }
}
