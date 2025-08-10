<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Email;
use App\Models\Project;
use App\Models\UserInteraction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InboxController extends Controller
{
    /**
     * Get all new (unread) emails for the authenticated user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function newEmails(Request $request)
    {
        $user = Auth::user();

        // Get all projects the user has access to
        $projectIds = $this->getAccessibleProjectIds($user);

        if (empty($projectIds)) {
            return response()->json([]);
        }

        // Get all emails from these projects
        $emails = Email::whereHas('conversation', function ($query) use ($projectIds) {
            $query->whereIn('project_id', $projectIds);
        })
        ->where(function ($query) {
            // Include emails that are approved or sent
            $query->whereIn('status', ['approved', 'sent', 'received']);
        })
        ->whereNotExists(function ($query) use ($user) {
            // Exclude emails that have been read by the user
            $query->select(DB::raw(1))
                ->from('user_interactions')
                ->whereColumn('user_interactions.interactable_id', 'emails.id')
                ->where('user_interactions.interactable_type', 'App\\Models\\Email')
                ->where('user_interactions.user_id', $user->id)
                ->where('user_interactions.interaction_type', 'read');
        })
        ->with(['sender', 'conversation.project', 'approver'])
        ->select('emails.*') // Ensure all columns including read_at are selected
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json($emails);
    }

    /**
     * Get all emails for the authenticated user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function allEmails(Request $request)
    {
        $user = Auth::user();

        // Get all projects the user has access to
        $projectIds = $this->getAccessibleProjectIds($user);

        if (empty($projectIds)) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 15,
                    'total' => 0
                ]
            ]);
        }

        // Get pagination parameters
        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);

        // Apply filters if provided
        $query = Email::whereHas('conversation', function ($query) use ($projectIds) {
            $query->whereIn('project_id', $projectIds);
        })
        ->where(function ($query) {
            // Include emails that are approved or sent
            $query->whereIn('status', ['approved', 'sent', 'received']);
        });

        // Apply search filter if provided
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->where('subject', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        // Apply type filter if provided
        if ($request->has('type') && !empty($request->type)) {
            $query->where('type', $request->type);
        }

        // Apply status filter if provided
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        // Apply date filters if provided
        if ($request->has('start_date') && !empty($request->start_date)) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date') && !empty($request->end_date)) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Apply client filter if provided
        if ($request->has('client_id') && !empty($request->client_id)) {
            $query->whereHas('conversation.project.clients', function ($query) use ($request) {
                $query->where('clients.id', $request->client_id);
            });
        }

        // Apply project filter if provided
        if ($request->has('project_id') && !empty($request->project_id)) {
            $query->whereHas('conversation', function ($query) use ($request) {
                $query->where('project_id', $request->project_id);
            });
        }

        // Apply sender filter if provided
        if ($request->has('sender_id') && !empty($request->sender_id)) {
            $query->where(function ($query) use ($request) {
                $query->where('sender_id', $request->sender_id)
                      ->where('sender_type', 'App\\Models\\User');
            });
        }

        $emails = $query->with(['sender', 'conversation.project', 'approver'])
            ->orderBy('created_at', 'desc')
            ->select('emails.*') // Ensure all columns including read_at are selected
            ->paginate($perPage, ['*'], 'page', $page);

        // Add a flag to indicate if each email has been read and include read_at timestamp
        $emails->getCollection()->each(function ($email) use ($user) {
            $email->is_read = $email->isReadBy($user->id);
        });

        return response()->json($emails);
    }

    /**
     * Get all emails waiting for approval.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function waitingApproval(Request $request)
    {
        $user = Auth::user();

        // Assume this method correctly returns an array of project IDs where the
        // authenticated user has 'view_emails' permission.
        $accessibleProjectIds = $this->getAccessibleProjectIds($user);

        // Fetch outgoing emails (sent by the user) that need approval
        $outgoingEmails = Email::where('sender_id', $user->id)
            ->where('sender_type', 'App\\Models\\User')
            ->where('status', 'pending_approval')
            ->with(['sender', 'conversation', 'conversation.project'])
            ->select('emails.*') // Ensure all columns including read_at are selected
            ->orderBy('created_at', 'desc')
            ->get();

        // Fetch incoming emails (in accessible projects) that need approval
        $incomingEmails = Email::whereHas('conversation', function ($query) use ($accessibleProjectIds) {
            $query->whereIn('project_id', $accessibleProjectIds);
        })
            ->where('status', 'pending_approval_received')
            ->with(['sender', 'conversation', 'conversation.project'])
            ->select('emails.*') // Ensure all columns including read_at are selected
            ->orderBy('created_at', 'desc')
            ->get();

        // Loop through the outgoing emails to apply content redaction based on permissions
        $redactedOutgoingEmails = $outgoingEmails->map(function ($email) use ($user) {
            // Outgoing emails are authorized for the sender
            return $email;
        });

        // Loop through the incoming emails to apply content redaction based on permissions
        $redactedIncomingEmails = $incomingEmails->map(function ($email) use ($user) {
            $isAuthorized = false;

            $email->can_approve = false;

            // Check if the user has permission to approve received emails
            if ($user->hasProjectPermission($email->conversation?->project->id, 'approve_received_emails')) {
                $email->can_approve = true;
                $isAuthorized = true;
            }

            // If not authorized, redact the email content
            if (!$isAuthorized) {
                $email->can_approve = false;
                $email->body = 'This email is waiting for approval. Please contact the project administrator or manager for assistance.';
            }

            return $email;
        });

        return response()->json([
            'outgoing' => $redactedOutgoingEmails,
            'incoming' => $redactedIncomingEmails,
        ]);
    }

    /**
     * Mark an email as read.
     *
     * @param Request $request
     * @param Email $email
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead(Request $request, Email $email)
    {
        $user = Auth::user();

        // Check if the user has permission to view this email
        if (!$email->isViewableByNonManagers() && !$user->can('view', $email)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Create a read interaction if it doesn't exist
        $interaction = UserInteraction::firstOrCreate([
            'user_id' => $user->id,
            'interactable_id' => $email->id,
            'interactable_type' => 'App\\Models\\Email',
            'interaction_type' => 'read'
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Get all project IDs that the user has access to and has permission to view emails.
     *
     * @param \App\Models\User $user
     * @return array
     */
    private function getAccessibleProjectIds($user)
    {
        // Get all projects where the user has the view_emails permission
        return Project::whereHas('users', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })
        ->whereExists(function ($query) use ($user) {
            $query->select(DB::raw(1))
                ->from('project_user')
                ->join('role_permission', 'project_user.role_id', '=', 'role_permission.role_id')
                ->join('permissions', 'role_permission.permission_id', '=', 'permissions.id')
                ->whereColumn('project_user.project_id', 'projects.id')
                ->where('project_user.user_id', $user->id)
                ->where('permissions.slug', 'view_emails');
        })
        ->pluck('id')
        ->toArray();
    }
}
