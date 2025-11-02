<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Email;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\UserInteraction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InboxController extends Controller
{
    /**
     * Get all new (unread) emails for the authenticated user.
     *
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
        $emails = Email::visibleTo($user)->whereHas('conversation', function ($query) use ($projectIds) {
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
                    'total' => 0,
                ],
            ]);
        }

        // Get pagination parameters
        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);

        // Normalize filters to accept both camelCase and snake_case from frontend
        $type = $request->input('type');
        $status = $request->input('status');
        $statuses = $request->input('statuses'); // optional array
        $projectId = $request->input('project_id') ?? $request->input('projectId');
        $senderId = $request->input('sender_id') ?? $request->input('senderId');
        $startDate = $request->input('start_date') ?? $request->input('startDate');
        $endDate = $request->input('end_date') ?? $request->input('endDate');
        $search = $request->input('search');
        $categoryIds = $request->input('category_ids', []);
        // is_read is optional; when provided, we filter based on user interactions
        $isRead = $request->has('is_read') ? filter_var($request->input('is_read'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) : null;

        // Apply base visibility filter and accessible projects/leads
        $query = Email::visibleTo($user)->whereHas('conversation', function ($query) use ($projectIds, $user) {
            $query->whereIn('project_id', $projectIds);

            if ($user->hasPermission('contact_lead')) {
                $query->orWhereNull('project_id');
            }
        });

        // Apply filters based on normalized parameters
        if (! empty($type)) {
            if ($type === 'new') {
                // Limit to statuses considered as "inbox new" and unread for the user
                $query->whereIn('status', ['pending_approval', 'pending_approval_received', 'received', 'sent', 'draft'])
                    ->whereNotExists(function ($subQuery) use ($user) {
                        $subQuery->select(DB::raw(1))
                            ->from('user_interactions')
                            ->whereColumn('user_interactions.interactable_id', 'emails.id')
                            ->where('user_interactions.interactable_type', 'App\\Models\\Email')
                            ->where('user_interactions.user_id', $user->id)
                            ->where('user_interactions.interaction_type', 'read');
                    });
            } elseif ($type === 'waiting-approval') {
                $query->whereIn('status', ['pending_approval', 'pending_approval_received']);
            } elseif ($type !== 'all') {
                $query->where('type', $type);
            }
        }

        // If explicit statuses array provided, it takes precedence
        if (is_array($statuses) && ! empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        // Apply single status (overrides type-driven implications for status)
        if (! empty($status)) {
            $query->where('status', $status);
        }

        if (! empty($projectId)) {
            $query->whereHas('conversation', function ($q) use ($projectId) {
                $q->where('project_id', $projectId);
            });
        }

        if (! empty($senderId)) {
            $query->where('sender_id', $senderId);
        }

        // Date range filtering with inclusive end-of-day handling
        if (! empty($startDate) || ! empty($endDate)) {
            $start = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::minValue();
            $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::maxValue();
            $query->whereBetween('created_at', [$start, $end]);
        }

        // Optional is_read filter (independent of type)
        if ($isRead !== null) {
            if ($isRead) {
                $query->whereExists(function ($subQuery) use ($user) {
                    $subQuery->select(DB::raw(1))
                        ->from('user_interactions')
                        ->whereColumn('user_interactions.interactable_id', 'emails.id')
                        ->where('user_interactions.interactable_type', 'App\\Models\\Email')
                        ->where('user_interactions.user_id', $user->id)
                        ->where('user_interactions.interaction_type', 'read');
                });
            } else {
                $query->whereNotExists(function ($subQuery) use ($user) {
                    $subQuery->select(DB::raw(1))
                        ->from('user_interactions')
                        ->whereColumn('user_interactions.interactable_id', 'emails.id')
                        ->where('user_interactions.interactable_type', 'App\\Models\\Email')
                        ->where('user_interactions.user_id', $user->id)
                        ->where('user_interactions.interaction_type', 'read');
                });
            }
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        // Category filtering
        if (! empty($categoryIds) && is_array($categoryIds)) {
            if (count($categoryIds) === 1) {
                // Single category - simple filter
                $query->whereHas('categories', function ($q) use ($categoryIds) {
                    $q->where('categories.id', $categoryIds[0]);
                });
            } else {
                // Multiple categories - additive (emails must belong to ALL selected categories)
                foreach ($categoryIds as $categoryId) {
                    $query->whereHas('categories', function ($q) use ($categoryId) {
                        $q->where('categories.id', $categoryId);
                    });
                }
            }
        }

        $emails = $query->with(['sender', 'conversation.project', 'approver'])
            ->orderBy('created_at', 'desc')
            ->select('emails.*') // Ensure all columns are selected
            ->paginate($perPage, ['*'], 'page', $page);

        // Add a flag to indicate if each email has been read and if the user can approve it
        $emails->getCollection()->each(function (Email $email) use ($user) {
            $email->is_read = $email->isReadBy($user->id);
        });

        return response()->json($emails);
    }

    /**
     * Get email counts for the authenticated user's inbox.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function counts(Request $request)
    {
        $user = Auth::user();

        // Get all projects the user has access to
        $projectIds = $this->getAccessibleProjectIds($user);

        if (empty($projectIds)) {
            return response()->json([
                'waiting-approval' => 0,
                'received' => 0,
            ]);
        }

        $waitingApprovalCount = Email::visibleTo($user)->whereHas('conversation', function ($query) use ($projectIds) {
            $query->whereIn('project_id', $projectIds);
        })
            ->whereIn('status', ['pending_approval', 'pending_approval_received'])
            ->count();

        $receivedCount = Email::visibleTo($user)->whereHas('conversation', function ($query) use ($projectIds) {
            $query->whereIn('project_id', $projectIds);
        })
            ->where('type', 'received')
            ->count();

        // You can add more counts here as needed

        return response()->json([
            'waiting-approval' => $waitingApprovalCount,
            'received' => $receivedCount,
        ]);
    }

    /**
     * Get category statistics for the authenticated user's inbox.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function categoryStats(Request $request)
    {
        $user = Auth::user();

        // Check if user has view_all_emails permission
        if (! $user->hasPermission('view_all_emails')) {
            return response()->json([
                'categories' => [],
            ]);
        }

        // Get all projects the user has access to
        $projectIds = $this->getAccessibleProjectIds($user);

        if (empty($projectIds)) {
            return response()->json([
                'categories' => [],
            ]);
        }

        // Get current filters to apply them to category counts
        $type = $request->input('type');
        $status = $request->input('status');
        $startDate = $request->input('start_date') ?? $request->input('startDate');
        $endDate = $request->input('end_date') ?? $request->input('endDate');
        $search = $request->input('search');
        $projectId = $request->input('project_id') ?? $request->input('projectId');
        $senderId = $request->input('sender_id') ?? $request->input('senderId');

        // Get categories that are available for Email model
        $categories = Email::availableCategories();

        $categoryStats = [];

        foreach ($categories as $category) {
            // Base query for emails in accessible projects with this category
            $query = Email::visibleTo($user)
                ->whereHas('conversation', function ($query) use ($projectIds, $user) {
                    $query->whereIn('project_id', $projectIds);
                    if ($user->hasPermission('contact_lead')) {
                        $query->orWhereNull('project_id');
                    }
                })
                ->whereHas('categories', function ($q) use ($category) {
                    $q->where('categories.id', $category->id);
                });

            // Apply the same filters as the main email list
            $this->applyFilters($query, $user, $type, $status, $startDate, $endDate, $search, $projectId, $senderId);

            $totalCount = $query->count();

            // Count unread emails in this category
            $unreadQuery = clone $query;
            $unreadQuery->whereNotExists(function ($subQuery) use ($user) {
                $subQuery->select(DB::raw(1))
                    ->from('user_interactions')
                    ->whereColumn('user_interactions.interactable_id', 'emails.id')
                    ->where('user_interactions.interactable_type', 'App\\Models\\Email')
                    ->where('user_interactions.user_id', $user->id)
                    ->where('user_interactions.interaction_type', 'read');
            });

            $unreadCount = $unreadQuery->count();

            if ($totalCount > 0) {
                $categoryStats[] = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug ?? strtolower(str_replace(' ', '-', $category->name)),
                    'total_count' => $totalCount,
                    'unread_count' => $unreadCount,
                    'has_unread' => $unreadCount > 0,
                ];
            }
        }

        // Sort categories: those with unread emails first, then alphabetically
        usort($categoryStats, function ($a, $b) {
            if ($a['has_unread'] && ! $b['has_unread']) {
                return -1;
            }
            if (! $a['has_unread'] && $b['has_unread']) {
                return 1;
            }

            return strcmp($a['name'], $b['name']);
        });

        return response()->json([
            'categories' => $categoryStats,
        ]);
    }

    /**
     * Apply filters to email query (extracted for reusability)
     */
    private function applyFilters($query, $user, $type, $status, $startDate, $endDate, $search, $projectId, $senderId)
    {
        // Apply type filter
        if (! empty($type)) {
            if ($type === 'new') {
                $query->whereIn('status', ['pending_approval', 'pending_approval_received', 'received', 'sent', 'draft'])
                    ->whereNotExists(function ($subQuery) use ($user) {
                        $subQuery->select(DB::raw(1))
                            ->from('user_interactions')
                            ->whereColumn('user_interactions.interactable_id', 'emails.id')
                            ->where('user_interactions.interactable_type', 'App\\Models\\Email')
                            ->where('user_interactions.user_id', $user->id)
                            ->where('user_interactions.interaction_type', 'read');
                    });
            } elseif ($type === 'waiting-approval') {
                $query->whereIn('status', ['pending_approval', 'pending_approval_received']);
            } elseif ($type !== 'all') {
                $query->where('type', $type);
            }
        }

        // Apply status filter
        if (! empty($status)) {
            $query->where('status', $status);
        }

        // Apply project filter
        if (! empty($projectId)) {
            $query->whereHas('conversation', function ($q) use ($projectId) {
                $q->where('project_id', $projectId);
            });
        }

        // Apply sender filter
        if (! empty($senderId)) {
            $query->where('sender_id', $senderId);
        }

        // Apply date range filter
        if (! empty($startDate) || ! empty($endDate)) {
            $start = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::minValue();
            $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::maxValue();
            $query->whereBetween('created_at', [$start, $end]);
        }

        // Apply search filter
        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }
    }

    /**
     * Get all emails waiting for approval.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function waitingApproval(Request $request)
    {
        $user = Auth::user();

        // Assume this method correctly returns an array of project IDs where the
        // authenticated user has 'view_emails' permission.
        $accessibleProjectIds = $this->getAccessibleProjectIds($user);

        // Fetch outgoing emails (sent by the user) that need approval
        $outgoingEmails = Email::visibleTo($user)->where('sender_type', 'App\\Models\\User')
            ->where('status', 'pending_approval')
            ->with(['sender', 'conversation' => function ($q) use ($accessibleProjectIds) {
                $q->whereIn('project_id', $accessibleProjectIds);
            }, 'conversation.project'])
            ->select('emails.*') // Ensure all columns including read_at are selected
            ->orderBy('created_at', 'desc')
            ->get();

        // Fetch incoming emails (in accessible projects) that need approval
        $incomingEmails = Email::visibleTo($user)->whereHas('conversation', function ($query) {
            // It's disabled until we need it
            //            $query->whereIn('project_id', $accessibleProjectIds);
        })
            ->where('status', 'pending_approval_received')
            ->with(['sender', 'conversation', 'conversation.project'])
            ->select('emails.*') // Ensure all columns including read_at are selected
            ->orderBy('created_at', 'desc')
            ->get();

        // Loop through the outgoing emails to apply content redaction based on permissions
        $redactedOutgoingEmails = $outgoingEmails->map(function ($email) use ($user) {

            $email->can_approve = false;
            $isAuthorized = false;
            // Check if the user has permission to approve received emails in appplicaiton role

            if ($email->conversation && $user->hasProjectPermission($email->conversation->project_id, 'approve_emails')) {
                $email->can_approve = true;
                $isAuthorized = true;
            }

            // If not authorized, redact the email content
            if (! $isAuthorized) {
                $email->can_approve = false;
            }

            // Outgoing emails are authorized for the sender
            return $email;
        });

        // Loop through the incoming emails to apply content redaction based on permissions
        $redactedIncomingEmails = $incomingEmails->map(function ($email) use ($user) {
            $isAuthorized = false;

            $email->can_approve = false;

            // Check if the user has permission to approve received emails in appplicaiton role

            if ($user->hasPermission('approve_received_emails')) {
                $email->can_approve = true;
                $isAuthorized = true;
            }

            // If not authorized, redact the email content
            if (! $isAuthorized) {
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead(Request $request, Email $email)
    {
        $user = Auth::user();

        // Check if the user has permission to view this email
        if (! $email->isViewableByNonManagers() && ! $user->can('view', $email)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Create a read interaction if it doesn't exist
        $interaction = UserInteraction::firstOrCreate([
            'user_id' => $user->id,
            'interactable_id' => $email->id,
            'interactable_type' => 'App\\Models\\Email',
            'interaction_type' => 'read',
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Get all project IDs that the user has access to and has permission to view emails.
     *
     * @param  \App\Models\User  $user
     * @return array
     */
    private function getAccessibleProjectIds($user)
    {

        if ($user->hasPermission('view_all_emails')) {
            return Project::select('id')->get()->pluck('id')->toArray();
        }

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
