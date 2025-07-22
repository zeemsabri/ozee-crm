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
        return view('admin.permissions.create', compact('categories'));
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
        ]);

        try {
            Permission::create([
                'name' => $request->name,
                'slug' => $request->slug ?? Str::slug($request->name, '_'),
                'description' => $request->description,
                'category' => $request->category,
            ]);

            return redirect()->route('admin.permissions.index')
                ->with('success', 'Permission created successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error creating permission: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Permission $permission)
    {
        $permission->load('roles');
        return view('admin.permissions.show', compact('permission'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Permission $permission)
    {
        $categories = Permission::select('category')->distinct()->pluck('category');
        return view('admin.permissions.edit', compact('permission', 'categories'));
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
        ]);

        try {
            $permission->update([
                'name' => $request->name,
                'slug' => $request->slug ?? Str::slug($request->name, '_'),
                'description' => $request->description,
                'category' => $request->category,
            ]);

            return redirect()->route('admin.permissions.index')
                ->with('success', 'Permission updated successfully.');
        } catch (\Exception $e) {
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
        return view('admin.permissions.bulk-create', compact('categories'));
    }

    /**
     * Store bulk permissions
     */
    public function bulkStore(Request $request)
    {
        $request->validate([
            'category' => 'required|string|max:255',
            'permissions' => 'required|string',
        ]);

        $permissionNames = explode("\n", str_replace("\r", "", $request->permissions));
        $permissionNames = array_filter($permissionNames, 'trim');

        DB::beginTransaction();
        try {
            foreach ($permissionNames as $name) {
                $name = trim($name);
                if (!empty($name)) {
                    Permission::create([
                        'name' => $name,
                        'slug' => Str::slug($name, '_'),
                        'category' => $request->category,
                    ]);
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
}
