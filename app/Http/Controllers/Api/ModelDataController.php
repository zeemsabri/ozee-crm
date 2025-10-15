<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModelDataController extends Controller
{
    /**
     * Get a list of items for a specific model, optionally filtered by project.
     *
     * @param string $shortModelName
     * @param Project|null $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Project $project, string $shortModelName)
    {
//        try {
            $className = 'App\\Models\\' . Str::studly($shortModelName);

            if (!class_exists($className)) {
                return response()->json(['message' => 'Model not found.'], 404);
            }

            $model = new $className();
            $query = $model->newQuery();

            // Check if the model should be filtered by project_id
            $projectScopedModels = ['Project', 'ShareableResource', 'Task']; // Add other project-scoped models here
            if ($project && !in_array(Str::studly($shortModelName), $projectScopedModels)) {
                    $query->where('project_id', $project->id);
            }

            if($shortModelName === 'Task') {
                $query->whereHas('milestone', function ($q) use ($project) {
                    $q->where('project_id', $project->id);
                });
            }

            if($shortModelName === 'Project') {
                $query->where('id', $project->id);
            }

            $data = $query->get();

            return response()->json($data);
//        } catch (\Exception $e) {
//            return response()->json(['message' => 'Failed to fetch model data: ' . $e->getMessage()], 500);
//        }
    }

    /**
     * Get a list of items for a specific model without project context.
     * Used by email templates to populate dropdowns and multi-selects.
     *
     * @param string $modelName The fully qualified model name or short name
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSourceModelData(string $modelName)
    {
        try {
            // Handle both fully qualified model names and short names
            if (strpos($modelName, '\\') === false) {
                $className = 'App\\Models\\' . Str::studly($modelName);
            } else {
                $className = $modelName;
            }

            if (!class_exists($className)) {
                return response()->json(['message' => 'Model not found: ' . $className], 404);
            }

            $model = new $className();
            $query = $model->newQuery();

            // Limit the number of records to prevent performance issues
            $data = $query->limit(100)->get();

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to fetch source model data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Return available Eloquent model class names under App\\Models.
     * Used by admin UI for binding Category Sets to model types.
     */
    public function availableModels()
    {
        $modelsPath = app_path('Models');
        $classes = [];
        if (File::exists($modelsPath)) {
            $files = File::allFiles($modelsPath);
            foreach ($files as $file) {
                $relativePath = trim(str_replace($modelsPath, '', $file->getPathname()), DIRECTORY_SEPARATOR);
                if (str_contains($relativePath, 'Traits'.DIRECTORY_SEPARATOR)) {
                    continue; // skip traits
                }
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $classBase = str_replace(['/', '.php'], ['\\', ''], $relativePath);
                $fqcn = 'App\\Models\\' . $classBase;
                if (!class_exists($fqcn)) {
                    continue;
                }
                if (is_a($fqcn, EloquentModel::class, true)) {
                    $classes[] = $fqcn;
                }
            }
        }

        // Build label/value pairs
        $options = collect($classes)
            ->unique()
            ->sort()
            ->map(fn($fqcn) => [
                'value' => $fqcn,
                'label' => class_basename($fqcn),
            ])->values();

        return response()->json($options);
    }
}
