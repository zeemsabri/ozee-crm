<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash; // For hashing passwords
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth; // For Auth::user()


class UserController extends Controller
{
    public function __construct()
    {
        // Policy authorization for User management
        // This links to App\Policies\UserPolicy that we defined
//        $this->authorizeResource(User::class, 'user');
    }

    /**
     * Display a listing of the users.
     * Accessible by: Super Admin, Manager, Employee (view all); Contractor (view only self)
     */
    public function index()
    {
        $user = Auth::user();

        // The UserPolicy's `viewAny` method will authorize access to this endpoint.
        // However, the actual data returned needs to be filtered based on role.
        if ($user->isSuperAdmin() || $user->isManager() || $user->isEmployee()) {
            // Admins, Managers, and Employees can view all users.
            // Eager load projects and roles so the frontend can display assigned projects and roles.
            $users = User::with(['projects'])->orderBy('name')->get();
        } elseif ($user->isContractor()) {
            // Contractors can only view their own profile.
            // We wrap it in a collection for consistent return type with other roles.
            $users = collect([$user->load(['projects'])]);
        } else {
            // Should not happen if authenticated and role is set, but as a fallback.
            $users = collect();
        }

        return response()->json($users);
    }

    /**
     * Store a newly created user in storage.
     * Accessible by: Super Admin (can assign any role), Manager (can create employee/contractor)
     */
    public function store(Request $request)
    {
        // Authorization is handled by the UserPolicy's `create` method.
        $this->authorize('create', User::class);

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email',
                'password' => 'required|string|min:8|confirmed', // 'confirmed' means password_confirmation must match
                'role' => 'required|in:super-admin,manager,employee,contractor',
            ]);

            // Enforce additional role restrictions based on the current user's role.
            // This is a safety check beyond the policy.
            $currentUser = Auth::user();
            if (!$currentUser->isSuperAdmin()) {
                // If the current user is not a Super Admin, they cannot create Super Admin or Manager accounts.
                if ($validated['role'] === 'super-admin' || $validated['role'] === 'manager') {
                    throw ValidationException::withMessages(['role' => 'Only Super Admins can create Super Admin or Manager accounts.']);
                }
            }

            // Find the role by slug
            $role = \App\Models\Role::where('slug', $validated['role'])->first();
            if (!$role) {
                throw ValidationException::withMessages(['role' => 'Invalid role specified.']);
            }

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']), // Hash the password securely
                'role_id' => $role->id, // Use role_id instead of role
            ]);

            Log::info('User created', ['user_id' => $user->id, 'user_email' => $user->email, 'created_by' => Auth::id()]);
            return response()->json($user->load('role'), 201); // 201 Created status with role
        } catch (ValidationException $e) {
            // Return validation errors
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422); // 422 Unprocessable Entity
        } catch (\Exception $e) {
            // Catch any other unexpected errors
            Log::error('Error creating user: ' . $e->getMessage(), ['request' => $request->all(), 'error' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to create user', 'error' => $e->getMessage()], 500); // 500 Internal Server Error
        }
    }

    /**
     * Display the specified user.
     * Accessible by: Super Admin, Manager, Employee (any); Contractor (self only)
     */
    public function show(User $user)
    {
        // Authorization is handled by the UserPolicy's `view` method.
        $this->authorize('view', $user);
        return response()->json($user->load(['projects', 'role'])); // Load projects and role when showing a single user
    }

    /**
     * Update the specified user in storage.
     * Accessible by: Super Admin (any user/any role); Manager (employee/contractor role); User (self profile only)
     */
    public function update(Request $request, User $user)
    {
        // Authorization is handled by the UserPolicy's `update` method.
        $this->authorize('update', $user);

        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id, // Unique check, excluding current user's email
                'password' => 'nullable|string|min:8|confirmed', // Password is optional; 'confirmed' requires password_confirmation field
                'role' => 'sometimes|required|in:super-admin,manager,employee,contractor', // Role can be updated
            ]);

            $currentUser = Auth::user();

            // Additional server-side validation for role changes, especially for non-Super Admins.
            if ($request->has('role')) {
                // Find the role by slug
                $newRole = \App\Models\Role::where('slug', $validated['role'])->first();
                if (!$newRole) {
                    throw ValidationException::withMessages(['role' => 'Invalid role specified.']);
                }

                // Check if the role is actually changing
                if ($newRole->id !== $user->role_id) {
                    if (!$currentUser->isSuperAdmin()) {
                        // Non-Super Admins cannot elevate any user's role to Super Admin or Manager.
                        if ($validated['role'] === 'super-admin' || $validated['role'] === 'manager') {
                            throw ValidationException::withMessages(['role' => 'Only Super Admins can assign Super Admin or Manager roles.']);
                        }
                        // Non-Super Admins cannot demote or update existing Super Admin or Manager accounts.
                        if ($user->isSuperAdmin() || $user->isManager()) {
                            throw ValidationException::withMessages(['role' => 'Only Super Admins can update Super Admin or Manager accounts.']);
                        }
                    }
                }
            }

            // Prevent a user from elevating their OWN role if they are not a Super Admin.
            if ($currentUser->id === $user->id && $request->has('role') && !$currentUser->isSuperAdmin() && ($validated['role'] === 'super-admin' || $validated['role'] === 'manager')) {
                throw ValidationException::withMessages(['role' => 'You cannot elevate your own role to Super Admin or Manager.']);
            }

            // Prepare data for update
            $userData = $request->only(['name', 'email']);
            if (isset($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']); // Hash new password if provided
            }

            // If role is being updated, set the role_id
            if ($request->has('role')) {
                $newRole = \App\Models\Role::where('slug', $validated['role'])->first();
                if ($newRole) {
                    $userData['role_id'] = $newRole->id;
                }
            }

            $user->update($userData);

            Log::info('User updated', ['user_id' => $user->id, 'updated_by' => Auth::id()]);
            return response()->json($user->load('role'));
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating user: ' . $e->getMessage(), ['user_id' => $user->id, 'request' => $request->all(), 'error' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to update user', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified user from storage.
     * Accessible by: Super Admin only
     */
    public function destroy(User $user)
    {
        // Authorization is handled by the UserPolicy's `delete` method.
        $this->authorize('delete', $user);

        // Prevent a user from deleting their own account.
        if (Auth::id() === $user->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 403); // 403 Forbidden
        }

        try {
            $user->delete();
            Log::info('User deleted', ['user_id' => $user->id, 'deleted_by' => Auth::id()]);
            return response()->json(null, 204); // 204 No Content
        } catch (\Exception $e) {
            Log::error('Error deleting user: ' . $e->getMessage(), ['user_id' => $user->id, 'error' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to delete user', 'error' => $e->getMessage()], 500);
        }
    }

}
