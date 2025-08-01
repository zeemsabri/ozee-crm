<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Project;
use Illuminate\Http\Request;

class TestController extends Controller
{
    /**
     * Test the User model's project role functionality
     */
    public function testUserProjectRole(Request $request)
    {
        // Get a project ID from the request or use a default
        $projectId = $request->input('project_id');

        if (!$projectId) {
            return response()->json(['error' => 'Please provide a project_id parameter'], 400);
        }

        // Example 1: Using the scope to get users with their roles for a specific project
        $usersWithRoles = User::withProjectRole($projectId)->get();

        // Example 2: Get a specific user's role for a project
        $user = User::find($request->input('user_id', 1)); // Default to user ID 1 if not provided
        $userRole = $user ? $user->getRoleForProject($projectId) : null;

        return response()->json([
            'users_with_roles' => $usersWithRoles,
            'specific_user_role' => [
                'user_id' => $user ? $user->id : null,
                'project_id' => $projectId,
                'role' => $userRole
            ]
        ]);
    }
}
