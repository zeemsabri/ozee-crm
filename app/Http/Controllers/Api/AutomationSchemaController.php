<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema; // Ensure this is imported

class AutomationSchemaController extends Controller
{
    /**
     * Provide the data schema required by the automation builder UI.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSchema(Request $request)
    {
        // NOTE: This assumes you have a way to discover your application's models.
        // If your existing logic for this is elsewhere, please adapt it here.
        $discoveredModels = [
            \App\Models\Task::class,
            \App\Models\Project::class,
            \App\Models\Email::class,
            // ... add all other relevant models for automation
        ];

        $allModelEvents = $this->getModelEvents();
        $modelsData = [];

        foreach ($discoveredModels as $modelClass) {
            if (!class_exists($modelClass)) continue;

            $modelInstance = new $modelClass();
            $modelName = class_basename($modelInstance);

            // Presuming existing logic for columns and relationships
            $columns = Schema::getColumnListing($modelInstance->getTable());
            $relationships = $this->discoverRelationships($modelInstance); // Placeholder for your relationship logic

            $modelsData[] = [
                'name' => $modelName,
                'full_class' => $modelClass,
                'columns' => $columns,
                'relationships' => $relationships,
                'events' => $allModelEvents[$modelName] ?? [],
            ];
        }

        // You may want to sort the models alphabetically by name
        usort($modelsData, fn($a, $b) => strcmp($a['name'], $b['name']));

        return response()->json($modelsData);
    }

    /**
     * Defines the available trigger events for each model.
     *
     * @return array
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
     * Placeholder for your existing relationship discovery logic.
     *
     * @param $modelInstance
     * @return array
     */
    private function discoverRelationships($modelInstance): array
    {
        // This is a placeholder. Please integrate your actual logic for
        // discovering relationships (belongsTo, hasMany, etc.) here.
        return [];
    }
}
