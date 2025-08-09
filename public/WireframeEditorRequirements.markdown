# Requirements Document for Wireframe Editor with Versioning and Component Management

## Objective

Develop a robust wireframe editor within a Laravel and Vue.js application, featuring a versioning system for wireframes, dynamic component and icon management, and integration with an existing `RightSidebar.vue` base component. The editor converts `wireframe.html` to `Wireframe.vue`, integrates with `Project/Show.vue`, and uses Spatie’s Activity Log to track changes. Users can save draft and published versions, create new versions of the same wireframe, manage components and icons in the database, and upload new components to extend the editor dynamically. The system ensures scalability, error handling, and a seamless user experience.

## Requirements

### 1. Versioning System

- **Overview**: Implement a versioning system to manage draft and published versions of wireframes.
- **Details**:
  - Each wireframe has a single `name` and `project_id`, with multiple versions stored separately.
  - Save action creates or updates a draft version; Publish action marks the latest draft as published.
  - New changes after publishing create a new draft version.
  - Users can create new versions of the same wireframe (incrementing `version_number`, e.g., v1, v2) without creating a new wireframe record.
  - Users can view and revert to previous versions.
- **Constraints**:
  - Draft versions are editable; published versions are read-only.
  - Ensure unique `version_number` per wireframe.

### 2. Component and Icon Management

- **Overview**: Store wireframe components and icons in the database, allowing users to upload new components to extend the editor.
- **Details**:
  - Components (e.g., HeroSection, Button) are stored with a `name`, `type`, `definition` (JSON for rendering logic and default props), and optional `icon_id`.
  - Icons are stored with a `name` and `svg_content` (SVG data as text).
  - Users can upload new components via a form, providing `name`, `type`, `definition` (JSON), and an optional SVG icon file.
  - Wireframe state references components by `component_id` in a JSON array (e.g., `{ component_id, position, size, props }`).
  - Components are dynamically loaded into the editor’s component palette.
- **Constraints**:
  - Validate `definition` as JSON and `svg_content` as valid SVG.
  - Limit icon file size (e.g., 2MB) and sanitize SVG to remove malicious code.
  - Ensure component names are unique.

### 3. Wireframe.vue Component

- **Overview**: Convert `wireframe.html` to a Vue.js component (`Wireframe.vue`) for the wireframe editor.
- **Details**:
  - Preserve drag-and-drop, resizing, and component palette functionality from `wireframe.html`.
  - Remove Import/Export buttons; add Save (saves draft version), Publish (publishes latest draft), and New Version buttons.
  - Include a form in the component palette to upload new components (`name`, `type`, `definition`, `icon`).
  - Load components dynamically from the database into the palette.
  - Save wireframe state as JSON referencing `component_id`.
  - Integrate with `RightSidebar.vue` to display wireframes, versions, and logs.
  - Provide a Close button to return to the project view.
- **Constraints**:
  - Use Vue 3 Composition API.
  - Handle API errors with user-friendly messages (e.g., toast notifications).
  - Cache state locally for recovery after network failures.

### 4. Project/Show.vue Integration

- **Overview**: Update the project show page to integrate the wireframe editor.
- **Details**:
  - Remove the "Send Magic Link" button.
  - Add a “Wireframe” button to hide all project content and display `Wireframe.vue`.
  - Ensure seamless toggling between project and wireframe views.
- **Constraints**:
  - Maintain existing project content visibility when not in wireframe mode.
  - Use Vue Router for navigation (e.g., `/projects/:id/wireframe`) if feasible.

### 5. RightSidebar.vue Integration

- **Overview**: Use the existing `RightSidebar.vue` base component to load content for wireframe versions and activity logs.
- **Details**:
  - Display a list of wireframes for the current project, with collapsible version lists (e.g., “Wireframe A: v1 (Published), v2 (Draft)”).
  - Show activity logs for the selected wireframe using Spatie’s Activity Log.
  - Include buttons to select a version, create a new version, or delete a wireframe.
  - Load content dynamically within `RightSidebar.vue` (e.g., using slots or dynamic components).
- **Constraints**:
  - Respect the existing `RightSidebar.vue` structure (e.g., collapsible behavior, styling).
  - Highlight the current version and display status badges (e.g., green for published).

### 6. Backend Structure

- **Models and Migrations**:
  - **Wireframe**:
    - Fields: `id`, `project_id` (foreign key to `projects.id`, cascade on delete), `name` (string, unique per project), `created_at`, `updated_at`.
    - Relationships: Has many `WireframeVersion`.
  - **WireframeVersion**:
    - Fields: `id`, `wireframe_id` (foreign key, cascade on delete), `version_number` (integer), `data` (JSON, e.g., `[{ component_id, position, size, props }]`), `status` (enum: `draft`, `published`), `created_at`, `updated_at`.
    - Relationships: Belongs to `Wireframe`.
  - **Component**:
    - Fields: `id`, `name` (string, unique), `type` (string), `definition` (JSON, e.g., `{ default: { size, props }, render }`), `icon_id` (foreign key to `icons.id`, nullable), `created_at`, `updated_at`.
    - Relationships: Belongs to `Icon`.
  - **Icon**:
    - Fields: `id`, `name` (string, unique), `svg_content` (text), `created_at`, `updated_at`.
  - Migration Example:

    ```php
    Schema::create('wireframes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('project_id')->constrained()->onDelete('cascade');
        $table->string('name')->unique();
        $table->timestamps();
    });
    
    Schema::create('wireframe_versions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('wireframe_id')->constrained()->onDelete('cascade');
        $table->unsignedInteger('version_number');
        $table->json('data');
        $table->enum('status', ['draft', 'published'])->default('draft');
        $table->timestamps();
        $table->unique(['wireframe_id', 'version_number']);
    });
    
    Schema::create('icons', function (Blueprint $table) {
        $table->id();
        $table->string('name')->unique();
        $table->text('svg_content');
        $table->timestamps();
    });
    
    Schema::create('components', function (Blueprint $table) {
        $table->id();
        $table->string('name')->unique();
        $table->string('type');
        $table->json('definition');
        $table->foreignId('icon_id')->nullable()->constrained()->onDelete('set null');
        $table->timestamps();
    });
    ```
- **Spatie Activity Log**:
  - Use `LogsActivity` trait on `Wireframe`, `WireframeVersion`, and `Component` models.
  - Log `fillable` attributes and custom events (e.g., “Component {name} uploaded”, “Wireframe {name} v{version_number} published”).
  - Include user IDs and detailed changes (e.g., “Added Button component”).
- **API Endpoints**:
  - **WireframeController**:
    - `GET /api/projects/{projectId}/wireframes`: List wireframes with latest version’s `status`.
    - `GET /api/projects/{projectId}/wireframes/{id}?version_number=X`: Get wireframe with specific version.
    - `POST /api/projects/{projectId}/wireframes`: Create wireframe with version 1 (draft).
    - `PUT /api/projects/{projectId}/wireframes/{id}`: Update latest draft or create new draft.
    - `POST /api/projects/{projectId}/wireframes/{id}/publish`: Publish latest draft.
    - `POST /api/projects/{projectId}/wireframes/{id}/versions`: Create new draft version.
    - `DELETE /api/projects/{projectId}/wireframes/{id}`: Delete wireframe and versions.
    - `GET /api/projects/{projectId}/wireframes/{id}/logs`: Fetch activity logs.
  - **ComponentController**:
    - `GET /api/components`: List components with icons.
    - `POST /api/components`: Upload component with optional icon.
    - `DELETE /api/components/{id}`: Delete component.
  - Routes in `routes/api.php`:

    ```php
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::prefix('projects/{projectId}/wireframes')->group(function () {
            Route::get('/', [WireframeController::class, 'index']);
            Route::get('{id}', [WireframeController::class, 'show']);
            Route::post('/', [WireframeController::class, 'store']);
            Route::put('{id}', [WireframeController::class, 'update']);
            Route::post('{id}/publish', [WireframeController::class, 'publish']);
            Route::post('{id}/versions', [WireframeController::class, 'newVersion']);
            Route::delete('{id}', [WireframeController::class, 'destroy']);
            Route::get('{id}/logs', [WireframeController::class, 'logs']);
        });
        Route::prefix('components')->group(function () {
            Route::get('/', [ComponentController::class, 'index']);
            Route::post('/', [ComponentController::class, 'store']);
            Route::delete('{id}', [ComponentController::class, 'destroy']);
        });
    });
    ```

### 7. Robustness and Scenarios

- **Error Handling**:
  - **Frontend**: Display API errors (e.g., `422` for invalid JSON/SVG, `413` for large files) via toast notifications.
  - **Backend**: Validate `name` uniqueness, `definition` as JSON, `svg_content` as SVG. Return detailed error messages.
  - **Scenario**: Invalid SVG upload returns `422` with “Invalid SVG format”.
- **Concurrency**:
  - **Scenario**: Multiple users editing the same wireframe version or component.
  - **Solution**: Add `version` column to `wireframe_versions` and `components` for optimistic locking. Prompt users to resolve conflicts.
- **Large Data**:
  - **Scenario**: Large `data` or `definition` JSON.
  - **Solution**: Compress with `gzcompress` or store in a separate `chunks` table.
- **File Uploads**:
  - **Scenario**: User uploads large or malicious SVG.
  - **Solution**: Limit file size (e.g., 2MB) and sanitize SVG with `DOMPurify` or equivalent.
- **Permission Checks**:
  - **Scenario**: Unauthorized user accessing wireframes or uploading components.
  - **Solution**: Restrict endpoints to project owners/members. Return `403 Forbidden`.
- **Version Management**:
  - **Scenario**: User tries to publish without a draft.
  - **Solution**: Require a draft version before publishing.
- **Dynamic Components**:
  - **Scenario**: Invalid `definition` breaks the editor.
  - **Solution**: Validate JSON schema (e.g., `{ default: { size, props }, render }`) before saving.
- **Scalability**:
  - **Scenario**: Many components, versions, or logs slow down the UI.
  - **Solution**: Paginate API responses. Cache components in `localStorage`.

### 8. Suggestions for Improvement

- **Frontend**:
  - Use Pinia for centralized state management of wireframes and components.
  - Add a component preview in the upload form using dynamic Vue components.
  - Implement autosave for drafts with debounced API calls.
  - Support drag-and-drop for SVG uploads.
- **Backend**:
  - Store version diffs to reduce `data` size.
  - Use soft deletes for `Wireframe`, `WireframeVersion`, and `Component`.
  - Add a `ComponentVersion` model for versioned component definitions.
- **Activity Log**:
  - Log detailed changes (e.g., “Added Button at x:100, y:100”).
  - Allow filtering logs by type in `RightSidebar.vue`.
- **User Experience**:
  - Auto-generate version labels (e.g., “v1”, “v2”) and component names (e.g., “CustomButton_1”).
  - Warn users when overwriting drafts or uploading duplicate component names.
  - Display component thumbnails using `svg_content` in the palette.
- **Performance**:
  - Cache `/api/components` responses in the frontend.
  - Use WebSockets for real-time updates in collaborative settings.
  - Optimize queries with indexes on `wireframe_id`, `version_number`, `component_id`.

### 