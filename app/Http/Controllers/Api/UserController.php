<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash; // For hashing passwords
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException; // For Auth::user()

class UserController extends Controller
{
    public function __construct()
    {
        // Policy authorization for User management
        // This links to App\Policies\UserPolicy that we defined
        //        $this->authorizeResource(User::class, 'user');
    }

    /**
     * Get emails related to a specific user (as sender or approver).
     */
    public function emails(User $user, Request $request)
    {
        $authUser = Auth::user();
        // Reuse the same permission gate used for Users pages routing (manage users area)
        if (! $authUser || ! $authUser->hasPermission('create_users')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $perPage = (int) ($request->input('per_page', 15));
        $page = (int) ($request->input('page', 1));

        $query = \App\Models\Email::with(['sender', 'approver', 'conversation.project'])
            ->where(function ($q) use ($user) {
                $q->where(function ($qq) use ($user) {
                    $qq->where('sender_type', \App\Models\User::class)
                        ->where('sender_id', $user->id);
                })
                    ->orWhere('approved_by', $user->id);
            })
            ->orderByDesc('created_at');

        // Optional filters
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($qq) use ($search) {
                $qq->where('subject', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        $emails = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json($emails);
    }

    /**
     * Display a listing of the users.
     * Accessible by: Super Admin, Manager, Employee (view all); Contractor (view only self)
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Build base query with eager loads
        $query = User::with(['projects']);

        // Apply soft delete scopes based on query params
        // ?with_trashed=1 -> include both active and archived
        // ?only_trashed=1 -> only archived
        if ($request->boolean('only_trashed')) {
            $query->onlyTrashed();
        } elseif ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        // Filter data based on role
        if ($user->hasPermission('view_users')) {
            $users = $query->orderBy('name')->get();
        } else {
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
            // Normalize role slug to match DB (underscores -> hyphens, lowercase)
            if ($request->has('role')) {
                $normalizedRole = strtolower(str_replace('_', '-', $request->input('role')));
                $request->merge(['role' => $normalizedRole]);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email',
                'password' => 'required|string|min:8|confirmed', // 'confirmed' means password_confirmation must match
                'role' => 'required|exists:roles,slug',
                'timezone' => 'nullable|string|max:255',
                'user_type' => 'required|string|in:employee,contractor,admin',
            ]);

            // Enforce additional role restrictions based on the current user's role.
            // This is a safety check beyond the policy.
            $currentUser = Auth::user();
            if (! $currentUser->isSuperAdmin()) {
                // If the current user is not a Super Admin, they cannot create Super Admin or Manager accounts.
                if ($validated['role'] === 'super-admin' || $validated['role'] === 'manager') {
                    throw ValidationException::withMessages(['role' => 'Only Super Admins can create Super Admin or Manager accounts.']);
                }
            }

            // Find the role by slug
            $role = \App\Models\Role::where('slug', $validated['role'])->first();
            if (! $role) {
                throw ValidationException::withMessages(['role' => 'Invalid role specified.']);
            }

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']), // Hash the password securely
                'role_id' => $role->id, // Use role_id instead of role
                'timezone' => $request->input('timezone'),
                'user_type' => $request->input('user_type'),
            ]);

            return response()->json($user->load('role'), 201); // 201 Created status with role
        } catch (ValidationException $e) {
            // Return validation errors
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422); // 422 Unprocessable Entity
        } catch (\Exception $e) {
            // Catch any other unexpected errors
            Log::error('Error creating user: '.$e->getMessage(), ['request' => $request->all(), 'error' => $e->getTraceAsString()]);

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
            // Normalize role slug to match DB (underscores -> hyphens, lowercase)
            if ($request->has('role')) {
                $normalizedRole = strtolower(str_replace('_', '-', $request->input('role')));
                $request->merge(['role' => $normalizedRole]);
            }

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|string|email|max:255|unique:users,email,'.$user->id, // Unique check, excluding current user's email
                'password' => 'nullable|string|min:8|confirmed', // Password is optional; 'confirmed' requires password_confirmation field
                'role' => 'sometimes|required|exists:roles,slug', // Role can be updated
                'timezone' => 'nullable|string|max:255',
                'user_type' => 'required|string|in:employee,contractor,admin',
            ]);

            $currentUser = Auth::user();

            // Additional server-side validation for role changes, especially for non-Super Admins.
            if ($request->has('role')) {
                // Find the role by slug
                $newRole = \App\Models\Role::where('slug', $validated['role'])->first();
                if (! $newRole) {
                    throw ValidationException::withMessages(['role' => 'Invalid role specified.']);
                }

                // Check if the role is actually changing
                if ($newRole->id !== $user->role_id) {
                    if (! $currentUser->isSuperAdmin()) {
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
            if ($currentUser->id === $user->id && $request->has('role') && ! $currentUser->isSuperAdmin() && ($validated['role'] === 'super-admin' || $validated['role'] === 'manager')) {
                throw ValidationException::withMessages(['role' => 'You cannot elevate your own role to Super Admin or Manager.']);
            }

            // Prepare data for update
            $userData = $request->only(['name', 'email', 'timezone', 'user_type']);
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

            return response()->json($user->load('role'));

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating user: '.$e->getMessage(), ['user_id' => $user->id, 'request' => $request->all(), 'error' => $e->getTraceAsString()]);

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

            return response()->json(null, 204); // 204 No Content
        } catch (\Exception $e) {
            Log::error('Error deleting user: '.$e->getMessage(), ['user_id' => $user->id, 'error' => $e->getTraceAsString()]);

            return response()->json(['message' => 'Failed to delete user', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Restore a soft-deleted user (unarchive)
     */
    public function restore($id)
    {
        // We need to include trashed to find the user
        $user = User::withTrashed()->findOrFail($id);

        // Authorization: reuse delete permission for restore
        $this->authorize('restore', $user);

        try {
            if ($user->trashed()) {
                $user->restore();
            }

            return response()->json($user->fresh('role'));
        } catch (\Exception $e) {
            Log::error('Error restoring user: '.$e->getMessage(), ['user_id' => $user->id, 'error' => $e->getTraceAsString()]);

            return response()->json(['message' => 'Failed to restore user', 'error' => $e->getMessage()], 500);
        }
    }
}
