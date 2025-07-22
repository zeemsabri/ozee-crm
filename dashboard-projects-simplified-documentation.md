# Dashboard Projects Simplified Implementation

## Overview

This document describes the implementation of a simplified projects API for the Dashboard component. The changes ensure that only the necessary information is returned from the API and displayed in the UI, making the page more efficient and reducing the amount of data transferred between the server and client.

## Background

Previously, the dashboard's Projects section was fetching data from the `/api/projects` endpoint, which was returning too much information. The endpoint was loading projects with several related models:
- clients
- users (with pivot role_id)
- transactions
- notes (which were also being decrypted)

However, the Dashboard component only needs the project's id, name, and status for display. All the other data being loaded and returned was unnecessary for the dashboard view, which was causing performance issues and excessive data transfer.

## Implementation Details

### 1. Created a New API Endpoint

Added a new method `getProjectsSimplified` to the `ProjectController` that returns only the required fields:

```php
/**
 * Get simplified projects data for dashboard
 * Returns only id, name, and status fields
 * 
 * @return \Illuminate\Http\JsonResponse
 */
public function getProjectsSimplified()
{
    $user = Auth::user();

    if ($user->isSuperAdmin() || $user->isManager()) {
        $projects = Project::select('id', 'name', 'status')->get();
    } else {
        $projects = $user->projects()->select('projects.id', 'projects.name', 'projects.status')->get();
    }

    return response()->json($projects);
}
```

This method:
- Follows the same authorization logic as the original `index` method
- Uses `select()` to only retrieve the necessary fields (id, name, status)
- Does not load any related models

### 2. Added a Route for the New Endpoint

Added a new route in `routes/api.php`:

```php
Route::get('projects-simplified', [ProjectController::class, 'getProjectsSimplified']); // New route with limited information for dashboard
```

The original endpoint is kept for backward compatibility and other parts of the application that might need the full project data.

### 3. Updated the Dashboard Component

Modified the `fetchProjects` function in `Dashboard.vue` to use the new simplified endpoint:

```javascript
// Fetch projects
const fetchProjects = async () => {
    loading.value = true;
    error.value = '';
    try {
        const response = await axios.get('/api/projects-simplified');
        projects.value = response.data;
    } catch (err) {
        error.value = 'Failed to load projects';
        console.error('Error fetching projects:', err);
    } finally {
        loading.value = false;
    }
};
```

## Testing

A test script (`test-dashboard-projects-simplified.php`) has been created to verify that the changes work correctly. The script:

1. Tests the original `/api/projects` endpoint and examines its response structure
2. Tests the new `/api/projects-simplified` endpoint and examines its response structure
3. Checks that only the required fields (id, name, status) are present in the simplified response
4. Compares the data size between the original and simplified responses to quantify the reduction

To run the test script, you need to run it within the Laravel application context. You can do this using Laravel Tinker:

```bash
# Option 1: Run in Laravel Tinker
php artisan tinker
# Then paste the relevant parts of the test script

# Option 2: Create an Artisan command
php artisan make:command TestDashboardProjectsSimplified
# Then copy the test logic into the handle() method of the command
# Then run:
php artisan test:dashboard-projects-simplified
```

Note: The test script as provided needs to be run within the Laravel application context to have access to the database connection.

## Impact

These changes:

1. Significantly reduce the amount of data transferred between the server and client
2. Improve the performance of the dashboard page by loading only the necessary data
3. Reduce server load by eliminating unnecessary database queries for related models
4. Follow the established pattern in the codebase for simplified endpoints

### Data Size Reduction

The test script quantifies the data size reduction by comparing the JSON response size of the original and simplified endpoints. Depending on the number of projects and their related data, the reduction can be substantial (often 90% or more), especially for projects with many clients, users, transactions, and notes.

## Related Files

- `app/Http/Controllers/Api/ProjectController.php` - Added the `getProjectsSimplified` method
- `routes/api.php` - Added a new route for the simplified endpoint
- `resources/js/Pages/Dashboard.vue` - Updated to use the simplified endpoint
- `test-dashboard-projects-simplified.php` - Test script to verify the changes
