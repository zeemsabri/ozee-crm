# Role System Fix: Removing References to Non-existent `user_role` Table

## Issue Description

The application was encountering an error when trying to store or update roles:

```
SQLSTATE[42S02]: Base table or view not found: 1146 Table 'ozee-crm.user_role' doesn't exist
```

This error occurred because the code was trying to use a many-to-many relationship between users and roles through a `user_role` pivot table, but this table doesn't exist in the database. Instead, the application uses a direct relationship where users have a `role_id` column that references the roles table.

## Changes Made

### 1. Updated Role Model

Changed the `users()` relationship in the Role model from a many-to-many relationship to a one-to-many relationship:

```php
// Before
public function users()
{
    return $this->belongsToMany(User::class, 'user_role');
}

// After
public function users()
{
    return $this->hasMany(User::class);
}
```

### 2. Updated RoleController

Modified the `destroy()` method in the RoleController to update users with the role being deleted to have no role, instead of trying to detach them from a many-to-many relationship:

```php
// Before
public function destroy(Role $role)
{
    try {
        // Detach all permissions and users before deleting
        $role->permissions()->detach();
        $role->users()->detach();
        $role->delete();

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role deleted successfully.');
    } catch (\Exception $e) {
        return back()->with('error', 'Error deleting role: ' . $e->getMessage());
    }
}

// After
public function destroy(Role $role)
{
    try {
        // Detach all permissions before deleting
        $role->permissions()->detach();
        
        // Update users with this role to have no role (set role_id to null)
        User::where('role_id', $role->id)->update(['role_id' => null]);
        
        $role->delete();

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role deleted successfully.');
    } catch (\Exception $e) {
        return back()->with('error', 'Error deleting role: ' . $e->getMessage());
    }
}
```

Also added the missing `use App\Models\User;` statement to the RoleController.php file.

### 3. Updated User Model

Modified the `assignRole()` and `removeRole()` methods in the User model to update the user's role_id directly instead of using a many-to-many relationship:

```php
// Before
public function assignRole($role)
{
    if (is_numeric($role)) {
        $role = Role::findOrFail($role);
    }

    $this->roles()->syncWithoutDetaching([$role->id]);
}

public function removeRole($role)
{
    if (is_numeric($role)) {
        $role = Role::findOrFail($role);
    }

    $this->roles()->detach($role);
}

// After
public function assignRole($role)
{
    if (is_numeric($role)) {
        $role = Role::findOrFail($role);
    }

    $this->role_id = $role->id;
    $this->save();
}

public function removeRole($role)
{
    if (is_numeric($role)) {
        $role = Role::findOrFail($role);
    }

    if ($this->role_id == $role->id) {
        $this->role_id = null;
        $this->save();
    }
}
```

### 4. Updated User Model's Permission Methods

Modified the `hasAnyPermission()` and `getAllPermissions()` methods in the User model to only check permissions through the user's primary role, removing code that was trying to check through a many-to-many relationship:

```php
// Before
public function hasAnyPermission(array $permissionSlugs)
{
    // First check if the user has a direct role_id relationship
    if ($this->role_id && $this->role) {
        foreach ($permissionSlugs as $permissionSlug) {
            if ($this->role->hasPermission($permissionSlug)) {
                return true;
            }
        }
    }

    // For backward compatibility, also check the many-to-many relationship
    foreach ($this->roles as $role) {
        foreach ($permissionSlugs as $permissionSlug) {
            if ($role->hasPermission($permissionSlug)) {
                return true;
            }
        }
    }

    return false;
}

public function getAllPermissions()
{
    $permissions = collect();

    // First check if the user has a direct role_id relationship
    if ($this->role_id && $this->role) {
        $permissions = $permissions->merge($this->role->permissions);
    }

    // For backward compatibility, also check the many-to-many relationship
    foreach ($this->roles as $role) {
        $permissions = $permissions->merge($role->permissions);
    }

    return $permissions->unique('id');
}

// After
public function hasAnyPermission(array $permissionSlugs)
{
    // Check if the user has a direct role_id relationship
    if ($this->role_id && $this->role) {
        foreach ($permissionSlugs as $permissionSlug) {
            if ($this->role->hasPermission($permissionSlug)) {
                return true;
            }
        }
    }

    return false;
}

public function getAllPermissions()
{
    $permissions = collect();

    // Check if the user has a direct role_id relationship
    if ($this->role_id && $this->role) {
        $permissions = $permissions->merge($this->role->permissions);
    }

    return $permissions->unique('id');
}
```

## Testing

A test script was created to verify that the role API changes work correctly. The script tests:

1. Fetching roles from the API endpoint
2. Creating a new role
3. Assigning the role to a user
4. Deleting the role

All tests passed successfully, confirming that the issue with the non-existent `user_role` table has been resolved.

## Conclusion

The application was using a mix of direct relationships (through the `role_id` column on the users table) and many-to-many relationships (through a non-existent `user_role` pivot table) for managing user roles. This inconsistency was causing errors when trying to store or update roles.

By updating the code to consistently use the direct relationship approach, we've resolved the issue and ensured that the role management functionality works correctly.
