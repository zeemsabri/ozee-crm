# Running API Documentation

This is a living API document. Add or update endpoint sections here as implementation evolves.

## How to Use This File

1. Add new endpoints under the relevant module section.
2. Keep request/response samples aligned with controller behavior.
3. Update the `Last updated` date whenever you modify a section.
4. If an endpoint has role/permission constraints, document them in `Authorization`.

## Global Conventions

- Base URL: `/api`
- Auth (internal app): `Authorization: Bearer {sanctum_token}`
- Auth (extension/external): `auth.apikey` protected endpoints under `/api/activity/*`
- Content type: `application/json` (except multipart upload endpoints)
- Date format: ISO-8601 unless noted

---

## Task Endpoints (Initial Set)

### 1) Get Tasks For a Project

- Last updated: 2026-05-09
- Route: `GET /api/projects/{project}/tasks`
- Controller: `App\Http\Controllers\Api\ProjectReadController@getTasks`
- Middleware: `auth:sanctum`

#### Authorization

- Requires project view access (`$this->authorize('view', $project)`).

#### Path Parameters

- `project` (integer, required): Project ID.

#### Response (200)

Returns a list of tasks for milestone IDs belonging to the project, ordered by `due_date ASC`, with relations:

- `assignedTo`
- `taskType`
- `milestone`
- `tags`
- `subtasks`

```json
[
  {
    "id": 123,
    "name": "Implement API docs",
    "status": "To Do",
    "due_date": "2026-05-12",
    "assigned_to_user_id": 8,
    "milestone_id": 41,
    "task_type_id": 2,
    "assigned_to": {
      "id": 8,
      "name": "John Doe"
    },
    "task_type": {
      "id": 2,
      "name": "Development"
    },
    "milestone": {
      "id": 41,
      "name": "Phase 1"
    },
    "tags": [],
    "subtasks": []
  }
]
```

#### Common Errors

- `403`: Unauthorized to view this project.

---

### 2) Get Task Details

- Last updated: 2026-05-09
- Route: `GET /api/tasks/{task}`
- Controller: `App\Http\Controllers\Api\TaskController@show`
- Middleware: `auth:sanctum`

#### Authorization

- Inherits route-level authenticated access.

#### Path Parameters

- `task` (integer, required): Task ID.

#### Response (200)

Returns a single task with relations:

- `assignedTo`
- `taskType`
- `milestone.project`
- `tags`
- `subtasks`
- `notes` (selected fields only)

`notes` include fields:

- `id`
- `content`
- `noteable_type`
- `noteable_id`
- `created_at`
- `creator_type`
- `creator_id`

```json
{
  "id": 123,
  "name": "Implement API docs",
  "status": "In Progress",
  "assigned_to": {
    "id": 8,
    "name": "John Doe"
  },
  "milestone": {
    "id": 41,
    "name": "Phase 1",
    "project": {
      "id": 7,
      "name": "Email Approval App"
    }
  },
  "tags": [],
  "subtasks": [],
  "notes": [
    {
      "id": 33,
      "content": "Initial implementation done",
      "noteable_type": "App\\Models\\Task",
      "noteable_id": 123,
      "created_at": "2026-05-09T08:30:00.000000Z",
      "creator_type": "App\\Models\\User",
      "creator_id": 8
    }
  ]
}
```

---

### 3) Get Task Notes

- Last updated: 2026-05-09
- Route: `GET /api/activity/tasks/{task}/notes`
- Controller: `App\Http\Controllers\Api\ExternalApiController@getTaskNotes`
- Middleware: `auth.apikey`

#### Authorization

- Super admin/manager or project membership for the task's project.

#### Path Parameters

- `task` (integer, required): Task ID.

#### Response (200)

Returns notes ordered by `created_at DESC` with `creator` loaded.

```json
[
  {
    "id": 33,
    "content": "Initial implementation done",
    "creator": {
      "id": 8,
      "name": "John Doe"
    },
    "created_at": "2026-05-09T08:30:00.000000Z"
  }
]
```

#### Common Errors

- `403`: Unauthorized.

---

### 4) Add Task Note

- Last updated: 2026-05-09
- Route (internal): `POST /api/tasks/{task}/notes`
- Controller (internal): `App\Http\Controllers\Api\TaskController@addNote`
- Middleware (internal): `auth:sanctum`

- Route (extension/external): `POST /api/activity/tasks/{task}/notes`
- Controller (extension/external): `App\Http\Controllers\Api\ExternalApiController@addTaskNote`
- Middleware (extension/external): `auth.apikey`

#### Request Body

```json
{
  "note": "Need final QA review before deploy"
}
```

#### Response (200)

```json
{
  "message": "Note added successfully",
  "result": {
    "id": 34,
    "content": "Need final QA review before deploy"
  }
}
```

#### Common Errors

- `422`: Missing/invalid `note`.
- `403`: Unauthorized (external route).

---

### 5) Get Task Files

- Last updated: 2026-05-09
- Route: `GET /api/files?model_type=Task&model_id={taskId}`
- Controller: `App\Http\Controllers\Api\FileAttachmentController@index`
- Middleware: `auth:sanctum`

#### Query Parameters

- `model_type` (string, required): `Task` or full class (e.g. `App\\Models\\Task`)
- `model_id` (integer, required): Task ID

#### Response (200)

Returns latest-first file attachments for the task. File model appends signed URLs:

- `path_url`
- `thumbnail_url`

```json
[
  {
    "id": 12,
    "fileable_type": "App\\Models\\Task",
    "fileable_id": 123,
    "filename": "spec.pdf",
    "mime_type": "application/pdf",
    "file_size": 249120,
    "path": "uploads/tasks/spec.pdf",
    "thumbnail": null,
    "path_url": "https://...",
    "thumbnail_url": null,
    "created_at": "2026-05-09T09:10:00.000000Z"
  }
]
```

#### Common Errors

- `422`: Missing `model_type` or `model_id`.
- `404`: Model not found.
- `403`: Unauthorized.

---

### 6) Upload Task Files

- Last updated: 2026-05-09
- Route: `POST /api/files`
- Controller: `App\Http\Controllers\Api\FileAttachmentController@store`
- Middleware: `auth:sanctum`
- Content-Type: `multipart/form-data`

#### Form Data

- `model_type` (string, required): `Task` or `App\\Models\\Task`
- `model_id` (integer, required): Task ID
- `files[]` (file[], required): Max 20MB each

#### Response (200)

```json
{
  "message": "Files uploaded successfully",
  "files": []
}
```

Note: Current implementation returns an empty `files` array even after successful upload. The success message confirms upload; entries are persisted via the task `files()` relation.

#### Common Errors

- `422`: Validation failed.
- `400`: Project Google Drive folder not configured.
- `404`: Model not found.
- `403`: Unauthorized.

---

### 7) Delete Task File

- Last updated: 2026-05-09
- Route: `DELETE /api/files/{file}`
- Controller: `App\Http\Controllers\Api\FileAttachmentController@destroy`
- Middleware: `auth:sanctum`

#### Path Parameters

- `file` (integer, required): File attachment ID.

#### Response (200)

```json
{
  "message": "File deleted successfully"
}
```

#### Common Errors

- `403`: Unauthorized.

---

### 8) Task Status Transitions (Internal API)

- Last updated: 2026-05-09
- Base controller: `App\Http\Controllers\Api\TaskController`
- Middleware: `auth:sanctum`

This API exposes one endpoint per transition:

- `POST /api/tasks/{task}/start`
- `POST /api/tasks/{task}/pause`
- `POST /api/tasks/{task}/resume`
- `PATCH /api/tasks/{task}/complete`
- `POST /api/tasks/{task}/block`
- `POST /api/tasks/{task}/unblock`
- `POST /api/tasks/{task}/archive`
- `POST /api/tasks/{task}/revise`

All endpoints return the updated task with relationships:

- `assignedTo`
- `taskType`
- `milestone.project`
- `tags`
- `subtasks`

#### 8.1) Start Task

- Route: `POST /api/tasks/{task}/start`
- Controller method: `start`

Behavior:

- Sets status to `In Progress`.

Response (200): Updated task object.

#### 8.2) Pause Task

- Route: `POST /api/tasks/{task}/pause`
- Controller method: `pause`

Behavior:

- Allowed only when current status is `In Progress`.
- Sets status to `Paused`.

Common errors:

- `422`: `Only tasks in progress can be paused`.

#### 8.3) Resume Task

- Route: `POST /api/tasks/{task}/resume`
- Controller method: `resume`

Behavior:

- Allowed only when current status is `Paused`.
- Sets status to `In Progress`.

Common errors:

- `422`: `Only paused tasks can be resumed`.

#### 8.4) Complete Task

- Route: `PATCH /api/tasks/{task}/complete`
- Controller method: `markAsCompleted`

Behavior:

- Allowed only when current status is `In Progress`.
- Sets status to `Done` via `markAsCompleted(...)`.
- Updates today DailyTask status for the authenticated user to `completed`.

Common errors:

- `422`: `Task must be started before it can be completed`.

#### 8.5) Block Task

- Route: `POST /api/tasks/{task}/block`
- Controller method: `block`

Request Body:

```json
{
  "reason": "Waiting for client assets"
}
```

Behavior:

- Stores current status in `previous_status`.
- Sets status to `Blocked`.
- Saves `block_reason`.

Common errors:

- `422`: Missing/invalid `reason`.

#### 8.6) Unblock Task

- Route: `POST /api/tasks/{task}/unblock`
- Controller method: `unblock`

Behavior:

- Allowed only when current status is `Blocked`.
- Restores status to `previous_status` if set, otherwise `To Do`.
- Clears `block_reason` and `previous_status`.

Common errors:

- `422`: `Only blocked tasks can be unblocked`.

#### 8.7) Archive Task

- Route: `POST /api/tasks/{task}/archive`
- Controller method: `archive`

Behavior:

- Sets status to `Archived` (via model `archive()` helper).

Response (200): Updated task object.

#### 8.8) Revise Task

- Route: `POST /api/tasks/{task}/revise`
- Controller method: `revise`

Behavior:

- Allowed only when current status is `Done`.
- Sets status back to `To Do`.
- Sets today's DailyTask status back to `pending` for the authenticated user.

Common errors:

- `422`: `Only completed tasks can be revised`.

---

### 9) Task Status Transitions (Activity/Extension API)

- Last updated: 2026-05-09
- Route: `POST /api/activity/tasks/{task}/status`
- Controller: `App\Http\Controllers\Api\ExternalApiController@updateTaskStatus`
- Middleware: `auth.apikey`

#### Authorization

- Super admin/manager or project membership for the task's project.

#### Request Body

```json
{
  "status": "start",
  "reason": "Required only when status is block"
}
```

Fields:

- `status` (required): One of `start`, `pause`, `resume`, `complete`, `stop`, `block`, `unblock`, `revise`, `archive`
- `reason` (required when `status=block`): Max 255 chars

#### Transition Rules

- `start`: Allowed from `To Do` or `Paused`; target `In Progress`.
- `pause`: Allowed only from `In Progress`; target `Paused`.
- `resume`: Allowed from `Paused` or `Blocked`; target `In Progress`.
- `complete` / `stop`: Allowed only from `In Progress`; target `Done`.
- `block`: Not allowed from `Done` or `Archived`; target `Blocked`; stores `previous_status` and `block_reason`.
- `unblock`: Allowed only from `Blocked`; restores `previous_status` or defaults to `To Do`; clears block fields.
- `revise`: Allowed only from `Done`; target `To Do`.
- `archive`: Moves task to `Archived`.

#### Response (200)

```json
{
  "message": "Task status successfully updated to In Progress",
  "task": {
    "id": 123,
    "status": "In Progress"
  }
}
```

Response includes `task` with relations:

- `assignedTo`
- `taskType`
- `milestone.project`
- `tags`
- `subtasks`

#### Common Errors

- `422`: Invalid transition for current state.
- `422`: Missing `reason` for `block`.
- `422`: Task has no milestone/project (`Task can't be progressed because it doesn't belong to any milesone`).
- `403`: Unauthorized.

---

## Endpoint Template (Copy/Paste)

````md
### X) Endpoint Title

- Last updated: YYYY-MM-DD
- Route: `METHOD /api/...`
- Controller: `Namespace\\Controller@method`
- Middleware: `auth:sanctum | auth.apikey | auth.magiclink`

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
