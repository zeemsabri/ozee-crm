<?php

namespace App\Http\Controllers;

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
        $user = User::first();
        $lead = Lead::latest()->with('campaign')->first();

        $email = Email::latest()->first();
        $job = new ProcessDraftEmailJob($email);
        $job->handle(new EmailProcessingService($this->aiAnalysisService, $this->gmailService, $this->magicLinkService));
//        $email->status = 'pending_approval';
//        $email->save();

//        if($request->notify === 'approval') {
//            $user->notify(new EmailApprovalRequired($email));
//        }
//
////        $task = Task::first();
//        if($request->notify === 'approved') {
//            $user->notify(new EmailApproved($email));
//        }

        return 'done';
    }
}
