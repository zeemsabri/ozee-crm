<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema; // Ensure this is imported

class AutomationSchemaController extends Controller
{
    /**
     * Provide the data schema required by the automation builder UI.
     * Adds per-column type hints and allowed enum values using config/value_sets.php.
     */
    public function getSchema(Request $request)
    {
        // NOTE: This assumes you have a way to discover your application's models.
        $discoveredModels = [
            \App\Models\Task::class,
            \App\Models\Project::class,
            \App\Models\Email::class,
            Campaign::class,
            Lead::class,
        ];

        $allModelEvents = $this->getModelEvents();
        $modelsData = [];

        foreach ($discoveredModels as $modelClass) {
            if (!class_exists($modelClass)) continue;

            $modelInstance = new $modelClass();
            $modelName = class_basename($modelInstance);

            // Base column list from database table
            $columns = Schema::getColumnListing($modelInstance->getTable());
            $relationships = $this->discoverRelationships($modelInstance); // Placeholder for your relationship logic

            // Enrich columns with type and enum/allowed values
            $columnsMeta = array_map(function ($col) use ($modelInstance, $modelName) {
                $type = $this->guessColumnType($modelInstance, $col);
                $allowed = $this->getAllowedValues($modelName, $col);
                return [
                    'name' => $col,
                    'label' => $this->prettifyLabel($col),
                    'type' => $type,
                    'allowed_values' => $allowed,
                ];
            }, $columns);

            $modelsData[] = [
                'name' => $modelName,
                'full_class' => $modelClass,
                'columns' => $columnsMeta,
                'relationships' => $relationships,
                'events' => $allModelEvents[$modelName] ?? [],
            ];
        }

        // Sort models alphabetically by name for UI consistency
        usort($modelsData, fn($a, $b) => strcmp($a['name'], $b['name']));

        // Also expose campaigns (id/name) for AI context selector, when present on the frontend
        $campaigns = [];
        if (class_exists(Campaign::class)) {
            $campaigns = Campaign::query()->select('id', 'name')->orderBy('name')->get();
        }

        // Return in an object shape to allow future extensions (store already supports array/object)
        return response()->json([
            'models' => $modelsData,
            'campaigns' => $campaigns,
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
        if ($source === 'php_enum' && isset($def['enum']) && is_string($def['enum']) && class_exists($def['enum'])) {
            $enumClass = $def['enum'];
            if (function_exists('enum_exists') ? enum_exists($enumClass) : \PHP_VERSION_ID >= 80100) {
                $options = [];
                foreach ($enumClass::cases() as $case) {
                    $value = property_exists($case, 'value') ? $case->value : $case->name;
                    $label = ucwords(str_replace(['_', '-'], ' ', (string) $case->name));
                    $options[] = ['value' => $value, 'label' => $label];
                }
                return $options;
            }
        }
        // Future: support other sources (config/db/model_const) as needed
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
        // This is a placeholder. Please integrate your actual logic for
        // discovering relationships (belongsTo, hasMany, etc.) here.
        return [];
    }
}
