<?php

namespace App\Services\StepHandlers;

use App\Models\ExecutionLog;
use App\Models\WorkflowStep;
use App\Services\WorkflowEngineService;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class SyncRelationshipStepHandler implements StepHandlerContract
{
    public function __construct(
        protected WorkflowEngineService $engine
    ) {}

    public function handle(array $context, WorkflowStep $step, ExecutionLog|null $execLog = null): array
    {
        $config = $step->step_config ?? [];
        
        $targetModel = $config['target_model'] ?? null;
        $recordId = $config['record_id'] ?? null;
        $relationship = $config['relationship'] ?? null;
        $syncMode = $config['sync_mode'] ?? 'sync';
        $relatedIds = $config['related_ids'] ?? null;

        if (!$targetModel || !$recordId || !$relationship) {
            throw new \RuntimeException('SYNC_RELATIONSHIP requires target_model, record_id, and relationship');
        }

        // Resolve template values
        $resolvedRecordId = $this->engine->getTemplatedValue($recordId, $context);
        $resolvedRelatedIds = $this->engine->getTemplatedValue($relatedIds, $context);

        // Parse related IDs if they're a string
        if (is_string($resolvedRelatedIds)) {
            $resolvedRelatedIds = array_map('trim', explode(',', $resolvedRelatedIds));
            $resolvedRelatedIds = array_filter($resolvedRelatedIds, fn($id) => !empty($id));
            $resolvedRelatedIds = array_map('intval', $resolvedRelatedIds);
        } elseif (!is_array($resolvedRelatedIds)) {
            $resolvedRelatedIds = [];
        }

        // Find the model class
        $modelClass = $this->resolveModelClass($targetModel);
        if (!$modelClass) {
            throw new \RuntimeException("Model class not found for: {$targetModel}");
        }

        // Find the record
        $record = $modelClass::find($resolvedRecordId);
        if (!$record) {
            throw new \RuntimeException("Record not found: {$targetModel}#{$resolvedRecordId}");
        }

        // Check if relationship exists
        if (!method_exists($record, $relationship)) {
            throw new \RuntimeException("Relationship '{$relationship}' not found on {$targetModel}");
        }

        // Get the relationship instance
        $relationshipInstance = $record->{$relationship}();
        $relationshipType = class_basename($relationshipInstance);

        // Only allow many-to-many relationships
        if (!in_array($relationshipType, ['BelongsToMany', 'MorphToMany'])) {
            throw new \RuntimeException("Relationship '{$relationship}' must be BelongsToMany or MorphToMany, got: {$relationshipType}");
        }

        $result = [];

        try {
            switch ($syncMode) {
                case 'sync':
                    $record->{$relationship}()->sync($resolvedRelatedIds);
                    $result = [
                        'action' => 'sync',
                        'relationship' => $relationship,
                        'record_id' => $resolvedRecordId,
                        'synced_ids' => $resolvedRelatedIds,
                        'count' => count($resolvedRelatedIds)
                    ];
                    break;
                    
                case 'attach':
                    $record->{$relationship}()->syncWithoutDetaching($resolvedRelatedIds);
                    $result = [
                        'action' => 'attach',
                        'relationship' => $relationship,
                        'record_id' => $resolvedRecordId,
                        'attached_ids' => $resolvedRelatedIds,
                        'count' => count($resolvedRelatedIds)
                    ];
                    break;
                    
                case 'detach':
                    $record->{$relationship}()->detach($resolvedRelatedIds);
                    $result = [
                        'action' => 'detach',
                        'relationship' => $relationship,
                        'record_id' => $resolvedRecordId,
                        'detached_ids' => $resolvedRelatedIds,
                        'count' => count($resolvedRelatedIds)
                    ];
                    break;
                    
                default:
                    throw new \RuntimeException("Unknown sync mode: {$syncMode}");
            }

            Log::info('SyncRelationshipStepHandler.success', [
                'step_id' => $step->id,
                'target_model' => $targetModel,
                'record_id' => $resolvedRecordId,
                'relationship' => $relationship,
                'sync_mode' => $syncMode,
                'related_ids' => $resolvedRelatedIds,
            ]);

        } catch (\Exception $e) {
            Log::error('SyncRelationshipStepHandler.error', [
                'step_id' => $step->id,
                'error' => $e->getMessage(),
                'target_model' => $targetModel,
                'record_id' => $resolvedRecordId,
                'relationship' => $relationship,
            ]);
            throw $e;
        }

        return [
            'parsed' => $result,
            'logs' => [
                'sync_mode' => $syncMode,
                'relationship' => $relationship,
                'count' => count($resolvedRelatedIds)
            ]
        ];
    }

    protected function resolveModelClass(string $modelName): ?string
    {
        // Try common namespaces
        $namespaces = [
            "App\\Models\\{$modelName}",
            $modelName, // In case full class name is provided
        ];

        foreach ($namespaces as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }

        return null;
    }
}