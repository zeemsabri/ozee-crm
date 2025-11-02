<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $roles = Role::with('permissions')->get();

        return view('admin.roles.index', compact('roles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $permissions = Permission::orderBy('category')->get()->groupBy('category');

        return view('admin.roles.create', compact('permissions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles',
            'description' => 'nullable|string',
            'type' => 'required|string|in:application,client,project',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        DB::beginTransaction();
        try {
            $role = Role::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
                'type' => $request->type,
            ]);

            if ($request->has('permissions')) {
                $role->permissions()->attach($request->permissions);
            }

            DB::commit();

            return redirect()->route('admin.roles.index')
                ->with('success', 'Role created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Error creating role: '.$e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Role $role)
    {
        $role->load('permissions');

        // Get users with this role as their application role
        $applicationUsers = User::where('role_id', $role->id)->get();

        // Get users with this role as their project role
        $projectUsers = [];
        if ($role->type === 'project') {
            $projectUsers = DB::table('users')
                ->join('project_user', 'users.id', '=', 'project_user.user_id')
                ->join('projects', 'project_user.project_id', '=', 'projects.id')
                ->where('project_user.role_id', $role->id)
                ->select('users.*', 'projects.name as project_name', 'projects.id as project_id')
                ->get();
        }

        return view('admin.roles.show', compact('role', 'applicationUsers', 'projectUsers'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Role $role)
    {
        $role->load('permissions');
        $permissions = Permission::orderBy('category')->get()->groupBy('category');
        $rolePermissions = $role->permissions->pluck('id')->toArray();

        // Get users with this role as their application role
        $applicationUsers = User::where('role_id', $role->id)->get();

        // Get users with this role as their project role
        $projectUsers = [];
        if ($role->type === 'project') {
            $projectUsers = DB::table('users')
                ->join('project_user', 'users.id', '=', 'project_user.user_id')
                ->join('projects', 'project_user.project_id', '=', 'projects.id')
                ->where('project_user.role_id', $role->id)
                ->select('users.*', 'projects.name as project_name', 'projects.id as project_id')
                ->get();
        }

        return view('admin.roles.edit', compact('role', 'permissions', 'rolePermissions', 'applicationUsers', 'projectUsers'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Role $role)
    {

        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'description' => 'nullable|string',
            'type' => 'required|string|in:application,client,project',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        DB::beginTransaction();
        try {
            $role->update([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
                'type' => $request->type,
            ]);

            if ($request->has('permissions')) {
                $role->permissions()->sync($request->permissions);
            } else {
                $role->permissions()->detach();
            }

            DB::commit();

            // Check if this is an Inertia/AJAX request
            if ($request->wantsJson() || $request->header('X-Inertia')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Role updated successfully.',
                    'role' => $role->load('permissions'),
                ], 200); // Explicitly return 200 OK status code
            }

            // For regular form submissions, redirect as before
            return redirect()->route('admin.roles.index')
                ->with('success', 'Role updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            // Check if this is an Inertia/AJAX request
            if ($request->wantsJson() || $request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error updating role: '.$e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'Error updating role: '.$e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
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
            return back()->with('error', 'Error deleting role: '.$e->getMessage());
        }
    }

    /**
     * Manage permissions for a role.
     */
    public function managePermissions(Role $role)
    {
        $role->load('permissions');
        $permissions = Permission::orderBy('category')->get()->groupBy('category');
        $rolePermissions = $role->permissions->pluck('id')->toArray();

        // Get users with this role as their application role
        $applicationUsers = User::where('role_id', $role->id)->get();

        // Get users with this role as their project role
        $projectUsers = [];
        if ($role->type === 'project') {
            $projectUsers = DB::table('users')
                ->join('project_user', 'users.id', '=', 'project_user.user_id')
                ->join('projects', 'project_user.project_id', '=', 'projects.id')
                ->where('project_user.role_id', $role->id)
                ->select('users.*', 'projects.name as project_name', 'projects.id as project_id')
                ->get();
        }

        return view('admin.roles.permissions', compact('role', 'permissions', 'rolePermissions', 'applicationUsers', 'projectUsers'));
    }

    /**
     * Update permissions for a role.
     */
    public function updatePermissions(Request $request, Role $role)
    {
        $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        DB::beginTransaction();
        try {
            if ($request->has('permissions')) {
                $role->permissions()->sync($request->permissions);
            } else {
                $role->permissions()->detach();
            }

            DB::commit();

            return redirect()->route('admin.roles.show', $role)
                ->with('success', 'Role permissions updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Error updating role permissions: '.$e->getMessage());
        }
    }

    /**
     * Revoke a role from a user
     */
    public function revokeUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
            'role_type' => 'required|in:application,project',
            'project_id' => 'required_if:role_type,project|exists:projects,id',
        ]);

        $userId = $request->user_id;
        $roleId = $request->role_id;
        $roleType = $request->role_type;

        DB::beginTransaction();
        try {
            if ($roleType === 'application') {
                // Revoke application role
                User::where('id', $userId)
                    ->where('role_id', $roleId)
                    ->update(['role_id' => null]);
            } else {
                // Revoke project role
                DB::table('project_user')
                    ->where('user_id', $userId)
                    ->where('project_id', $request->project_id)
                    ->where('role_id', $roleId)
                    ->update(['role_id' => null]);
            }

            DB::commit();

            if ($request->wantsJson() || $request->header('X-Inertia')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Role revoked successfully.',
                ]);
            }

            return back()->with('success', 'Role revoked successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson() || $request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error revoking role: '.$e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'Error revoking role: '.$e->getMessage());
        }
    }
}
