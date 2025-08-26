# Project Clients Endpoint Documentation

## Issue Description

For users except super admin, the API endpoint `http://localhost:8000/api/clients` was returning all clients regardless of permissions. We needed a new API endpoint in the ProjectSectionController to fill dropdowns in the project client and user tab that would:

1. Return all clients if the user has the `manage_project_clients` permission
2. Return all clients in the current project if the user has the `view_project_clients` permission
3. Return no clients if the user doesn't have either permission

This is similar to what was previously implemented for users with the `/api/projects/{project}/users` endpoint.

## Solution

### 1. New Endpoint in ProjectSectionController

Added a new method `getProjectClients` to the ProjectSectionController that returns clients based on the authenticated user's permissions:

```php
/**
 * Get clients for a project based on permissions
 *
 * @param Project $project
 * @return \Illuminate\Http\JsonResponse
 */
public function getProjectClients(Project $project)
{
    $user = Auth::user();

    // Check if user has permission to view the project
    if (!$this->canViewProject($user, $project)) {
        return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
    }

    // Check if user has manage_project_clients permission (either globally or for this project)
    $hasManageProjectClients = false;
    
    // Check project-specific permission first
    $projectUser = $project->users()->where('users.id', $user->id)->first();
    if ($projectUser && isset($projectUser->pivot->role_id)) {
        $projectRole = \App\Models\Role::with('permissions')->find($projectUser->pivot->role_id);
        if ($projectRole && $projectRole->permissions->contains('slug', 'manage_project_clients')) {
            $hasManageProjectClients = true;
        }
    }
    
    // If not found in project role, check global permission
    if (!$hasManageProjectClients) {
        $hasManageProjectClients = $user->hasPermission('manage_project_clients');
    }
    
    // Check if user has view_project_clients permission (either globally or for this project)
    $hasViewProjectClients = false;
    
    // Check project-specific permission first
    if ($projectUser && isset($projectUser->pivot->role_id)) {
        $projectRole = \App\Models\Role::with('permissions')->find($projectUser->pivot->role_id);
        if ($projectRole && $projectRole->permissions->contains('slug', 'view_project_clients')) {
            $hasViewProjectClients = true;
        }
    }
    
    // If not found in project role, check global permission
    if (!$hasViewProjectClients) {
        $hasViewProjectClients = $user->hasPermission('view_project_clients');
    }
    
    // Return clients based on permissions
    if ($user->isSuperAdmin() || $hasManageProjectClients) {
        // Super admins and users with manage_project_clients permission can see all clients
        $clients = \App\Models\Client::with(['projects'])->orderBy('name')->get();
        return response()->json($clients);
    } elseif ($hasViewProjectClients) {
        // Users with view_project_clients permission can see all clients in the current project
        $clients = $project->clients;
        return response()->json($clients);
    } else {
        // Other users can't see any clients
        return response()->json([]);
    }
}
```

### 2. New Route in API Routes

Added a new route to access the endpoint:

```php
Route::get('projects/{project}/clients', [ProjectSectionController::class, 'getProjectClients']);
```

### 3. Updated ProjectForm.vue

Updated the `fetchClients` function in ProjectForm.vue to use the new endpoint when a project ID is available:

```javascript
// Fetch clients from the database
const fetchClients = async () => {
    try {
        // If we have a project ID, use the project-specific endpoint
        if (projectId.value) {
            const response = await window.axios.get(`/api/projects/${projectId.value}/clients`);
            clients.value = response.data;
        } else {
            // Fall back to the global endpoint if no project ID is available (e.g., when creating a new project)
            const response = await window.axios.get('/api/clients');
            clients.value = response.data.data || response.data;
        }
    } catch (error) {
        console.error('Error fetching clients:', error);
        generalError.value = 'Failed to load clients.';
    }
};
```

### 4. Updated Watch on projectId

Updated the watch on projectId to also call `fetchClients` when the project ID changes:

```javascript
// Watch for changes to project ID and fetch project-specific permissions, users, and clients
watch(projectId, (newProjectId, oldProjectId) => {
    if (newProjectId && newProjectId !== oldProjectId) {
        // Fetch project-specific permissions
        fetchProjectPermissions(newProjectId)
            .then(permissions => {
                // Success - no logging needed
            })
            .catch(error => {
                // Error handled by the permissions utility
            });

        // Fetch users based on the new project ID and permissions
        fetchUsers();
        
        // Fetch clients based on the new project ID and permissions
        fetchClients();
    }
});
```

## How It Works

The endpoint checks the user's permissions and returns clients based on those permissions:

1. **Super Admin or User with `manage_project_clients` permission**:
   - Returns all clients in the system
   - Uses `Client::with(['projects'])->orderBy('name')->get()`

2. **User with `view_project_clients` permission**:
   - Returns only the clients in the current project
   - Uses `$project->clients`

3. **User without either permission**:
   - Returns an empty array
   - Uses `response()->json([])`

## Permission Checking

The endpoint checks for permissions in both the user's project-specific role and their global role:

1. **Project-Specific Permissions**:
   - Gets the user's role in the project
   - Checks if the role has the required permission

2. **Global Permissions**:
   - If the user doesn't have the permission in their project role, checks their global permissions
   - Uses `$user->hasPermission('permission_slug')`

## Testing

A test script has been created to verify that the endpoint works correctly with different user roles and permissions:

```php
// Test cases
$testCases = [
    [
        'name' => 'Super Admin can see all clients',
        'endpoint' => "/projects/{$projectId}/clients",
        'method' => 'GET',
        'token' => $superAdminToken,
        'expectedStatus' => 200,
        'expectedCount' => 'all', // Should return all clients
    ],
    [
        'name' => 'User with manage_project_clients permission can see all clients',
        'endpoint' => "/projects/{$projectId}/clients",
        'method' => 'GET',
        'token' => $managerToken, // Assuming manager has manage_project_clients permission
        'expectedStatus' => 200,
        'expectedCount' => 'all', // Should return all clients
    ],
    [
        'name' => 'User with view_project_clients permission can see only project clients',
        'endpoint' => "/projects/{$projectId}/clients",
        'method' => 'GET',
        'token' => $employeeToken, // Assuming employee has view_project_clients permission
        'expectedStatus' => 200,
        'expectedCount' => 'project', // Should return only project clients
    ],
    [
        'name' => 'User without permissions cannot see any clients',
        'endpoint' => "/projects/{$projectId}/clients",
        'method' => 'GET',
        'token' => $contractorToken, // Assuming contractor has no permissions
        'expectedStatus' => 200,
        'expectedCount' => 'none', // Should return empty array
    ],
];
```

## Manual Testing

1. Log in to the application with different user roles:
   - Super Admin
   - User with manage_project_clients permission
   - User with view_project_clients permission
   - User without either permission

2. Navigate to a project form and go to the Clients and Users tab.

3. Check the dropdown for clients and verify that:
   - Super Admin and users with manage_project_clients permission see all clients
   - Users with view_project_clients permission see only clients in the current project
   - Users without either permission see no clients

4. You can also use the browser's developer tools to check the API response:
   - Open the Network tab
   - Look for the request to /api/projects/{id}/clients
   - Check the response to ensure it contains the expected clients

## Benefits

1. **Improved Security**: Users only see the clients they have permission to see
2. **Better User Experience**: Dropdowns only show relevant clients
3. **Reduced Data Transfer**: Only necessary data is sent to the client
4. **Consistent Permissions**: Uses the same permission-based logic as other parts of the application

## Future Improvements

1. **Caching**: Implement caching of client data to reduce API calls
2. **Pagination**: Add pagination for large client lists
3. **Search**: Add search functionality to filter clients
4. **Sorting**: Add sorting options for clients
