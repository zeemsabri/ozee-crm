<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateLeadFollowUpJob;
use App\Jobs\ProcessDraftEmailJob;
use App\Models\Email;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Models\Project;
use App\Notifications\EmailApprovalRequired;
use App\Notifications\EmailApproved;
use App\Notifications\TaskAssigned;
use App\Services\EmailAiAnalysisService;
use App\Services\EmailProcessingService;
use App\Services\GmailService;
use App\Services\MagicLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TestController extends Controller
{
    public function __construct(protected EmailAiAnalysisService $aiAnalysisService,
                                protected GmailService $gmailService,
                                protected MagicLinkService $magicLinkService)
    {
    }

    /**
     * Test the User model's project role functionality
     */
    public function testUserProjectRole(Request $request)
    {



        $user = User::create(['name' => 'John', 'email' => 'john@example.com']);

        // Using save() on a new or existing model instance
        $user = User::find(1);
        $user->name = 'Jane';
        $user->save();

        return $user;
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

    public function playGourd(Request $request)
    {

        $marker = $request->input('marker');
        $source = $request->input('source');

        // --- Step 1: Prepare and validate the marker ---
        $cleanMarker = trim($marker);

        // If the marker is empty after trimming, we can't search for it.
        if ($cleanMarker === '') {
            return $source;
        }

        // --- Step 2: Find the first occurrence of the marker in the text ---
        // We use a case-insensitive search (stripos) for better reliability.
        $markerPosition = stripos($source, $cleanMarker);

        // --- Step 3: Handle the case where the marker is not found ---
        if ($markerPosition === false) {
            // If the marker doesn't exist in the source text, return the original text.
            return $source;
        }

        // --- Step 4: Calculate the length of the text to keep ---
        // This includes all text from the beginning up to the very end of the marker.
        $lengthToKeep = $markerPosition + strlen($cleanMarker);

        // --- Step 5: Extract the desired part of the string and clean it up ---
        $substring = substr($source, 0, $lengthToKeep);

        // Finally, trim the result to remove any unwanted leading/trailing whitespace.
        return trim($substring);
    }
}
