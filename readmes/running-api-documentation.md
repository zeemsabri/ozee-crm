# Running API Documentation

This is a living API document. Add or update endpoint sections here as implementation evolves.

## How to Use This File

1. Add new endpoints under the relevant module section.
2. Keep request/response samples aligned with controller behavior.
3. Update the `Last updated` date whenever you modify a section.
4. If an endpoint has role/permission constraints, document them in `Authorization`.

## Global Conventions

- Base URL: `/api`
- Auth: `Authorization: Bearer {sanctum_token}`
- Content type: `application/json` (except multipart upload endpoints)
- Date format: ISO-8601 unless noted

---

## Task and Project Task Endpoints

- Last updated: 2026-05-09
- Scope: Internal endpoints only (`auth:sanctum`)

### 1) Get Tasks For a Project

- Route: `GET /api/projects/{project}/tasks`
- Controller: `App\Http\Controllers\Api\ProjectReadController@getTasks`
- Middleware: `auth:sanctum`

#### Authorization

- Requires project view access (`$this->authorize('view', $project)`).

#### Path Parameters

- `project` (integer, required): Project ID.

#### Response (200)

Returns project milestone tasks ordered by `due_date ASC` with relations:

- `assignedTo`
- `taskType`
- `milestone`
- `tags`
- `subtasks`

---

### 2) Task Resource Endpoints (`apiResource tasks`)

Routes from `Route::apiResource('tasks', TaskController::class)->middleware(['process.tags'])`:

- `GET /api/tasks` -> `index`
- `POST /api/tasks` -> `store`
- `GET /api/tasks/{task}` -> `show`
- `PUT/PATCH /api/tasks/{task}` -> `update`
- `DELETE /api/tasks/{task}` -> `destroy`

#### 2.1) `GET /api/tasks` (index)

Supported query filters:

- `milestone_id`
- `status`
- `assigned_to_user_id`
- `project_id`
- `project_ids` (comma-separated)
- `statuses` (comma-separated)
- `updated_since`
- `completed_on`
- `completed_since`
- `completed_until`
- `due_until`
- `search`
- `per_page` (returns paginator when provided)

Response:

- `200` tasks list or paginated response.

#### 2.2) `POST /api/tasks` (store)

Required fields:

- `name`
- `status`
- `task_type_id`
- `milestone_id`

Optional fields include:

- `description`, `assigned_to_user_id`, `due_date`, `needs_approval`, `requires_qa`, `effort`, `tags`, `schedule`

Response:

- `201` created task.

#### 2.3) `GET /api/tasks/{task}` (show)

Response:

- `200` task with relations: `assignedTo`, `taskType`, `milestone.project`, `tags`, `subtasks`, `notes`.

#### 2.4) `PUT/PATCH /api/tasks/{task}` (update)

Behavior highlights:

- Supports partial updates.
- Rejects updates to `priority` or `assigned_to_user_id` when status is `Done`.

Response:

- `200` updated task.

Common errors:

- `422` invalid payload or restricted update on completed task.

#### 2.5) `DELETE /api/tasks/{task}` (destroy)

Response:

```json
{
  "message": "Task deleted successfully"
}
```

---

### 3) Create Tasks in Bulk (Contract-Based)

- Route: `POST /api/tasks/bulk`
- Controller: `App\Http\Controllers\Api\TaskController@bulk`
- Middleware: `auth:sanctum`

Request body:

```json
{
  "tasks": [
    {
      "name": "Prepare final copy",
      "description": "Optional",
      "dueDate": "2026-05-15",
      "priority": "Medium",
      "contract_id": 10
    }
  ]
}
```

Notes:

- `dueDate` is required and must be today or later.
- `priority` accepted values: `Low`, `Medium`, `High`.
- `contract_id` must exist in `project_expendables` and map to a milestone expendable.

Response:

- `201` with `tasks` array.

---

### 4) Create Tasks in Bulk (Workspace)

- Route: `POST /api/tasks/bulk-workspace`
- Controller: `App\Http\Controllers\Api\TaskController@bulkWorkspace`
- Middleware: `auth:sanctum`

Request body:

```json
{
  "tasks": [
    {
      "name": "Draft onboarding email",
      "project_id": 7,
      "description": "Optional",
      "due_date": "2026-05-12",
      "priority": "medium",
      "assigned_to_user_id": 8,
      "milestone_id": 41
    }
  ]
}
```

Notes:

- `project_id` is required per item.
- If `milestone_id` is omitted, the project support milestone is used.

Response:

- `201` with `tasks` array.

---

### 5) Quick Task Create

- Route: `POST /api/tasks/quick`
- Controller: `App\Http\Controllers\Api\TaskController@quickStore`
- Middleware: `auth:sanctum`

Request body:

```json
{
  "name": "Quick follow-up",
  "status": "To Do",
  "project_id": 7
}
```

Response:

- `201` created task.

---

### 6) Add Note to Task

- Route: `POST /api/tasks/{task}/notes`
- Controller: `App\Http\Controllers\Api\TaskController@addNote`
- Middleware: `auth:sanctum`

Request body:

```json
{
  "note": "Need final QA review before deploy"
}
```

Response:

```json
{
  "message": "Note added successfully",
  "result": {
    "id": 34,
    "content": "Need final QA review before deploy"
  }
}
```

---

### 7) Task Status Transition Endpoints

- `PATCH /api/tasks/{task}/complete` -> `markAsCompleted`
- `POST /api/tasks/{task}/start` -> `start`
- `POST /api/tasks/{task}/pause` -> `pause`
- `POST /api/tasks/{task}/resume` -> `resume`
- `POST /api/tasks/{task}/block` -> `block`
- `POST /api/tasks/{task}/unblock` -> `unblock`
- `POST /api/tasks/{task}/archive` -> `archive`
- `POST /api/tasks/{task}/revise` -> `revise`

Behavior summary:

- `complete` requires status `In Progress`.
- `pause` requires status `In Progress`.
- `resume` requires status `Paused`.
- `block` requires body field `reason`.
- `unblock` requires status `Blocked`.
- `revise` requires status `Done`.

Block request body example:

```json
{
  "reason": "Waiting for client assets"
}
```

---

### 8) Task Files Endpoints

#### 8.1) List Task Files

- Route: `GET /api/files?model_type=Task&model_id={taskId}`
- Controller: `App\Http\Controllers\Api\FileAttachmentController@index`
- Middleware: `auth:sanctum`

Query parameters:

- `model_type` (required): `Task` or `App\\Models\\Task`
- `model_id` (required): Task ID

Response:

- `200` latest-first file list.

#### 8.2) Upload Task Files

- Route: `POST /api/files`
- Controller: `App\Http\Controllers\Api\FileAttachmentController@store`
- Middleware: `auth:sanctum`
- Content-Type: `multipart/form-data`

Form data:

- `model_type` (required): `Task` or `App\\Models\\Task`
- `model_id` (required): Task ID
- `files[]` (required): max 20MB each

Response:

```json
{
  "message": "Files uploaded successfully",
  "files": []
}
```

#### 8.3) Delete Task File

- Route: `DELETE /api/files/{file}`
- Controller: `App\Http\Controllers\Api\FileAttachmentController@destroy`
- Middleware: `auth:sanctum`

Response:

```json
{
  "message": "File deleted successfully"
}
```

---

### 9) Get Activities (Model Activity Log)

- Route: `GET /api/activities`
- Controller: `App\Http\Controllers\Api\ActivityController@index`
- Middleware: `auth:sanctum`

Use this endpoint to pull recent activity logs globally or for a specific model instance such as a task.

Query parameters:

- `subject_type` (optional): Fully-qualified model class, for example `App\\Models\\Task`
- `subject_id` (required when `subject_type` is provided): Model record ID
- `limit` (optional): min `1`, max `100`, default `50`

Task example:

`GET /api/activities?subject_type=App\\Models\\Task&subject_id=123&limit=25`

---

## Endpoint Template (Copy/Paste)

````md
### X) Endpoint Title

- Last updated: YYYY-MM-DD
- Route: `METHOD /api/...`
- Controller: `Namespace\\Controller@method`
- Middleware: `auth:sanctum`

#### Authorization

- Describe role/project permission checks.

#### Path Parameters

- `param` (type, required/optional): Description.

#### Query Parameters

- `param` (type, required/optional): Description.

#### Request Body

```json
{}
```

#### Response (200)

```json
{}
```

#### Common Errors

- `4xx/5xx`: Description.
````
