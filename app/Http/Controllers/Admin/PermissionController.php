<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $permissions = Permission::orderBy('category')->get()->groupBy('category');
        return view('admin.permissions.index', compact('permissions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Get unique categories for dropdown
        $categories = Permission::select('category')->distinct()->pluck('category');
        // Get all roles for selection
        $roles = \App\Models\Role::all();
        return view('admin.permissions.create', compact('categories', 'roles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:permissions',
            'slug' => 'nullable|string|max:255|unique:permissions',
            'description' => 'nullable|string',
            'category' => 'required|string|max:255',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,id',
        ]);

        try {
            DB::beginTransaction();

            $permission = Permission::create([
                'name' => $request->name,
                'slug' => $request->slug ?? Str::slug($request->name, '_'),
                'description' => $request->description,
                'category' => $request->category,
            ]);

            // Assign permission to selected roles
            if ($request->has('roles')) {
                foreach ($request->roles as $roleId) {
                    $role = \App\Models\Role::findOrFail($roleId);
                    $role->assignPermission($permission);
                }
            }

            DB::commit();

            return redirect()->route('admin.permissions.index')
                ->with('success', 'Permission created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error creating permission: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Permission $permission)
    {
        $permission->load('roles');

        // Get all roles that have this permission
        $roles = $permission->roles;

        // Get users with application roles that have this permission
        $applicationUsers = \App\Models\User::whereIn('role_id', $roles->where('type', 'application')->pluck('id'))->get();

        // Get users with project roles that have this permission
        $projectUsers = [];
        $projectRoleIds = $roles->where('type', 'project')->pluck('id')->toArray();

        if (!empty($projectRoleIds)) {
            $projectUsers = DB::table('users')
                ->join('project_user', 'users.id', '=', 'project_user.user_id')
                ->join('projects', 'project_user.project_id', '=', 'projects.id')
                ->whereIn('project_user.role_id', $projectRoleIds)
                ->select('users.*', 'projects.name as project_name', 'projects.id as project_id')
                ->get();
        }

        return view('admin.permissions.show', compact('permission', 'applicationUsers', 'projectUsers'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Permission $permission)
    {
        $categories = Permission::select('category')->distinct()->pluck('category');
        $permission->load('roles');

        // Get all roles for selection
        $roles = \App\Models\Role::all();
        $permissionRoles = $permission->roles->pluck('id')->toArray();

        // Get all roles that have this permission
        $rolesWithPermission = $permission->roles;

        // Get users with application roles that have this permission
        $applicationUsers = \App\Models\User::whereIn('role_id', $rolesWithPermission->where('type', 'application')->pluck('id'))->get();

        // Get users with project roles that have this permission
        $projectUsers = [];
        $projectRoleIds = $rolesWithPermission->where('type', 'project')->pluck('id')->toArray();

        if (!empty($projectRoleIds)) {
            $projectUsers = DB::table('users')
                ->join('project_user', 'users.id', '=', 'project_user.user_id')
                ->join('projects', 'project_user.project_id', '=', 'projects.id')
                ->whereIn('project_user.role_id', $projectRoleIds)
                ->select('users.*', 'projects.name as project_name', 'projects.id as project_id')
                ->get();
        }

        return view('admin.permissions.edit', compact('permission', 'categories', 'roles', 'permissionRoles', 'applicationUsers', 'projectUsers'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Permission $permission)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions')->ignore($permission->id)],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('permissions')->ignore($permission->id)],
            'description' => 'nullable|string',
            'category' => 'required|string|max:255',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,id',
        ]);

        DB::beginTransaction();
        try {
            $permission->update([
                'name' => $request->name,
                'slug' => $request->slug ?? Str::slug($request->name, '_'),
                'description' => $request->description,
                'category' => $request->category,
            ]);

            // Sync roles for this permission
            if ($request->has('roles')) {
                // Get current roles
                $currentRoles = $permission->roles->pluck('id')->toArray();

                // Roles to add
                $rolesToAdd = array_diff($request->roles, $currentRoles);
                foreach ($rolesToAdd as $roleId) {
                    $role = \App\Models\Role::findOrFail($roleId);
                    $role->assignPermission($permission);
                }

                // Roles to remove
                $rolesToRemove = array_diff($currentRoles, $request->roles);
                foreach ($rolesToRemove as $roleId) {
                    $role = \App\Models\Role::findOrFail($roleId);
                    $role->removePermission($permission);
                }
            } else {
                // Remove all roles if none selected
                $permission->roles()->detach();
            }

            DB::commit();
            return redirect()->route('admin.permissions.index')
                ->with('success', 'Permission updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error updating permission: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Permission $permission)
    {
        try {
            // Detach all roles before deleting
            $permission->roles()->detach();
            $permission->delete();

            return redirect()->route('admin.permissions.index')
                ->with('success', 'Permission deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error deleting permission: ' . $e->getMessage());
        }
    }

    /**
     * Get permissions by category (for AJAX requests)
     */
    public function getByCategory(Request $request)
    {
        $category = $request->category;
        $permissions = Permission::where('category', $category)->get();
        return response()->json($permissions);
    }

    /**
     * Bulk create permissions
     */
    public function bulkCreate()
    {
        $categories = Permission::select('category')->distinct()->pluck('category');
        // Get all roles for selection
        $roles = \App\Models\Role::all();
        return view('admin.permissions.bulk-create', compact('categories', 'roles'));
    }

    /**
     * Store bulk permissions
     */
    public function bulkStore(Request $request)
    {
        $request->validate([
            'category' => 'required|string|max:255',
            'permissions' => 'required|string',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,id',
        ]);

        $permissionNames = explode("\n", str_replace("\r", "", $request->permissions));
        $permissionNames = array_filter($permissionNames, 'trim');

        DB::beginTransaction();
        try {
            $createdPermissions = [];

            foreach ($permissionNames as $name) {
                $name = trim($name);
                if (!empty($name)) {
                    $permission = Permission::create([
                        'name' => $name,
                        'slug' => Str::slug($name, '_'),
                        'category' => $request->category,
                    ]);

                    $createdPermissions[] = $permission;
                }
            }

            // Assign permissions to selected roles
            if ($request->has('roles') && !empty($createdPermissions)) {
                foreach ($request->roles as $roleId) {
                    $role = \App\Models\Role::findOrFail($roleId);
                    foreach ($createdPermissions as $permission) {
                        $role->assignPermission($permission);
                    }
                }
            }

            DB::commit();
            return redirect()->route('admin.permissions.index')
                ->with('success', 'Permissions created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error creating permissions: ' . $e->getMessage());
        }
    }

    /**
     * Revoke a permission from a user by removing the role that grants it
     */
    public function revokeUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'permission_id' => 'required|exists:permissions,id',
            'role_type' => 'required|in:application,project',
            'project_id' => 'required_if:role_type,project|exists:projects,id',
        ]);

        $userId = $request->user_id;
        $permissionId = $request->permission_id;
        $roleType = $request->role_type;

        DB::beginTransaction();
        try {
            // Get the permission
            $permission = Permission::findOrFail($permissionId);

            if ($roleType === 'application') {
                // Get the user's application role
                $user = \App\Models\User::findOrFail($userId);
                $role = \App\Models\Role::findOrFail($user->role_id);

                // Remove the permission from the role
                $role->removePermission($permission);
            } else {
                // Get the user's project role
                $projectUser = DB::table('project_user')
                    ->where('user_id', $userId)
                    ->where('project_id', $request->project_id)
                    ->first();

                if ($projectUser && $projectUser->role_id) {
                    $role = \App\Models\Role::findOrFail($projectUser->role_id);

                    // Remove the permission from the role
                    $role->removePermission($permission);
                }
            }

            DB::commit();

            if ($request->wantsJson() || $request->header('X-Inertia')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Permission revoked successfully.'
                ]);
            }

            return back()->with('success', 'Permission revoked successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson() || $request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error revoking permission: ' . $e->getMessage()
                ], 500);
            }

            return back()->with('error', 'Error revoking permission: ' . $e->getMessage());
        }
    }
}
