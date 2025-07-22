<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Role::query()->with('permissions');

        // Filter by type if provided
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $roles = $query->get();

        return response()->json($roles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles',
            'description' => 'nullable|string',
            'type' => 'required|string|in:application,client,project',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        DB::beginTransaction();
        try {
            $role = Role::create([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'description' => $validated['description'] ?? null,
                'type' => $validated['type'],
            ]);

            if ($request->has('permissions')) {
                $role->permissions()->attach($validated['permissions']);
            }

            DB::commit();

            // Check if this is an Inertia request
            if ($request->header('X-Inertia')) {
                // Return a redirect response for Inertia requests
                return redirect()->route('admin.roles.index')
                    ->with('success', 'Role created successfully.');
            }

            // Return JSON response for API requests
            return response()->json([
                'success' => true,
                'message' => 'Role created successfully.',
                'role' => $role->load('permissions')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            // Check if this is an Inertia request
            if ($request->header('X-Inertia')) {
                // Return a redirect back with error for Inertia requests
                return back()->withErrors(['error' => 'Error creating role: ' . $e->getMessage()]);
            }

            // Return JSON response for API requests
            return response()->json([
                'success' => false,
                'message' => 'Error creating role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Role $role)
    {
        $role->load(['permissions', 'users']);

        return response()->json($role);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'description' => 'nullable|string',
            'type' => 'required|string|in:application,client,project',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        DB::beginTransaction();
        try {
            $role->update([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'description' => $validated['description'] ?? null,
                'type' => $validated['type'],
            ]);

            if ($request->has('permissions')) {
                $role->permissions()->sync($validated['permissions']);
            } else {
                $role->permissions()->detach();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully.',
                'role' => $role->load('permissions')
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error updating role: ' . $e->getMessage()
            ], 500);
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

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update permissions for a role.
     */
    public function updatePermissions(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        DB::beginTransaction();
        try {
            if ($request->has('permissions')) {
                $role->permissions()->sync($validated['permissions']);
            } else {
                $role->permissions()->detach();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Role permissions updated successfully.',
                'role' => $role->load('permissions')
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error updating role permissions: ' . $e->getMessage()
            ], 500);
        }
    }
}
