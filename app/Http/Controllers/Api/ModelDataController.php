<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
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
        try {
            $className = 'App\\Models\\' . Str::studly($shortModelName);

            if (!class_exists($className)) {
                return response()->json(['message' => 'Model not found.'], 404);
            }

            $model = new $className();
            $query = $model->newQuery();

            // Check if the model should be filtered by project_id
            $projectScopedModels = ['Project', 'ShareableResource']; // Add other project-scoped models here
            if ($project && !in_array(Str::studly($shortModelName), $projectScopedModels)) {
                    $query->where('project_id', $project->id);
            }

            if($shortModelName === 'Project') {
                $query->where('id', $project->id);
            }

            $data = $query->get();

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to fetch model data: ' . $e->getMessage()], 500);
        }
    }
}
