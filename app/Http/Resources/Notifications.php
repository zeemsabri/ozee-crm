<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Notifications extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * {
         * "title": "Test 2 0Hu",
         * "project_name": "Demo Project",
         * "message": "You have been assigned a new task: Test 2",
         * "project_id": null,
         * "description": null,
         * "task_type": null,
         * "priority": "low",
         * "task_id": 84,
         * "task_name": "Test 2",
         * "due_date": "2025-08-04",
         * "url": "http://localhost:8000/project/2/task/84",
         * "id": "36a352ce-2a7b-4a29-af35-57e4856afdfc",
         * "type": "App\\Notifications\\TaskAssigned"
         * }
         */
        $data = $this->data ?? [];

        return [
            'title' => $data['title'] ?? null,
            'project_name' => $data['project_name'] ?? null,
            'message' => $data['message'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'description' => $data['description'] ?? null,
            'task_type' => $data['task_type'] ?? null,
            'priority' => $data['priority'] ?? null,
            'task_id' => $data['task_id'] ?? null,
            'task_name' => $data['task_name'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'url' => $data['url'] ?? null,
            'view_id' => $data['view_id'] ?? null,
            'type' => $this->type,
            'isRead' => (bool) $this->read_at,
        ];
    }
}
