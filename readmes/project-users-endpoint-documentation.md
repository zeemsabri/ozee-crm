# Project Users Endpoint Documentation

## Issue Description

For users except super admin, the API endpoint `http://localhost:8000/api/users` was only returning the authenticated user's own data. We needed a new API endpoint in the ProjectSectionController to fill dropdowns in the project client and user tab that would:

1. Return all users if the user has the `manage_project_users` permission
2. Return all users in the current project if the user has the `view_project_users` permission

## Solution

### 1. New Endpoint in ProjectSectionController

Added a new method `getProjectUsers` to the ProjectSectionController that returns users based on the authenticated user's permissions:

```php
/**
 * Get users for a project based on permissions
 *
 * @param Project $project
 * @return \Illuminate\Http\JsonResponse
 */
public function getProjectUsers(Project $project)
{
    $user = Auth::user();

    // Check if user has permission to view the project
    if (!$this->canViewProject($user, $project)) {
        return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
    }

    // Check if user has manage_project_users permission (either globally or for this project)
    $hasManageProjectUsers = false;
    
    // Check project-specific permission first
    $projectUser = $project->users()->where('users.id', $user->id)->first();
    if ($projectUser && isset($projectUser->pivot->role_id)) {
        $projectRole = \App\Models\Role::with('permissions')->find($projectUser->pivot->role_id);
        if ($projectRole && $projectRole->permissions->contains('slug', 'manage_project_users')) {
            $hasManageProjectUsers = true;
        }
    }
    
    // If not found in project role, check global permission
    if (!$hasManageProjectUsers) {
        $hasManageProjectUsers = $user->hasPermission('manage_project_users');
    }
    
    // Check if user has view_project_users permission (either globally or for this project)
    $hasViewProjectUsers = false;
    
    // Check project-specific permission first
    if ($projectUser && isset($projectUser->pivot->role_id)) {
        $projectRole = \App\Models\Role::with('permissions')->find($projectUser->pivot->role_id);
        if ($projectRole && $projectRole->permissions->contains('slug', 'view_project_users')) {
            $hasViewProjectUsers = true;
        }
    }
    
    // If not found in project role, check global permission
    if (!$hasViewProjectUsers) {
        $hasViewProjectUsers = $user->hasPermission('view_project_users');
    }
    
    // Return users based on permissions
    if ($user->isSuperAdmin() || $hasManageProjectUsers) {
        // Super admins and users with manage_project_users permission can see all users
        $users = \App\Models\User::with(['projects'])->orderBy('name')->get();
        return response()->json($users);
    } elseif ($hasViewProjectUsers) {
        // Users with view_project_users permission can see all users in the current project
        $users = $project->users;
        return response()->json($users);
    } else {
        // Other users can only see themselves
        return response()->json(collect([$user]));
    }
}
```

### 2. New Route in API Routes

Added a new route to access the endpoint:

```php
Route::get('projects/{project}/users', [ProjectSectionController::class, 'getProjectUsers']);
```

## How It Works

The endpoint checks the user's permissions and returns users based on those permissions:

1. **Super Admin or User with `manage_project_users` permission**:
   - Returns all users in the system
   - Uses `User::with(['projects'])->orderBy('name')->get()`

2. **User with `view_project_users` permission**:
   - Returns only the users in the current project
   - Uses `$project->users`

3. **User without either permission**:
   - Returns only the authenticated user
   - Uses `collect([$user])`

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
        'name' => 'Super Admin can see all users',
        'endpoint' => "/projects/{$projectId}/users",
        'method' => 'GET',
        'token' => $superAdminToken,
        'expectedStatus' => 200,
        'expectedCount' => 'all', // Should return all users
    ],
    [
        'name' => 'User with manage_project_users permission can see all users',
        'endpoint' => "/projects/{$projectId}/users",
        'method' => 'GET',
        'token' => $managerToken, // Assuming manager has manage_project_users permission
        'expectedStatus' => 200,
        'expectedCount' => 'all', // Should return all users
    ],
    [
        'name' => 'User with view_project_users permission can see only project users',
        'endpoint' => "/projects/{$projectId}/users",
        'method' => 'GET',
        'token' => $employeeToken, // Assuming employee has view_project_users permission
        'expectedStatus' => 200,
        'expectedCount' => 'project', // Should return only project users
    ],
    [
        'name' => 'User without permissions can see only themselves',
        'endpoint' => "/projects/{$projectId}/users",
        'method' => 'GET',
        'token' => $contractorToken, // Assuming contractor has no permissions
        'expectedStatus' => 200,
        'expectedCount' => 'self', // Should return only the authenticated user
    ],
];
```

## Manual Testing

1. Log in to the application with different user roles:
   - Super Admin
   - User with manage_project_users permission
   - User with view_project_users permission
   - User without either permission

2. Navigate to a project form and go to the Clients and Users tab.

3. Check the dropdown for users and verify that:
   - Super Admin and users with manage_project_users permission see all users
   - Users with view_project_users permission see only users in the current project
   - Users without either permission see only themselves

4. You can also use the browser's developer tools to check the API response:
   - Open the Network tab
   - Look for the request to /api/projects/{id}/users
   - Check the response to ensure it contains the expected users

## Frontend Integration

To use this endpoint in the frontend, update the code that fetches users for the dropdown in the project client and user tab to use this new endpoint instead of the `/api/users` endpoint.

Example:
```javascript
// Before
const fetchUsers = async () => {
  const response = await axios.get('/api/users');
  users.value = response.data;
};

// After
const fetchUsers = async (projectId) => {
  const response = await axios.get(`/api/projects/${projectId}/users`);
  users.value = response.data;
};
```

## Benefits

1. **Improved Security**: Users only see the users they have permission to see
2. **Better User Experience**: Dropdowns only show relevant users
3. **Reduced Data Transfer**: Only necessary data is sent to the client
4. **Simplified Frontend Logic**: Frontend doesn't need to filter users based on permissions
