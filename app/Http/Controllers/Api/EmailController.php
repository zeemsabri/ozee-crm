<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Email;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\Client;
use App\Services\GmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class EmailController extends Controller
{
    protected GmailService $gmailService;

    public function __construct(GmailService $gmailService)
    {
        // We'll use manual authorization in methods, as `authorizeResource` is a bit limited for custom actions like approve/reject
        $this->gmailService = $gmailService;
    }

    /**
     * Display a listing of emails (conversations) relevant to the authenticated user.
     * Accessible by: Super Admin, Manager (all); Contractor (their assigned projects/conversations)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $emailsQuery = Email::with(['conversation.project.client', 'sender', 'approver']);

        if ($user->isContractor()) {
            // Contractors only see emails from conversations on their assigned projects
            $assignedProjectIds = $user->projects->pluck('id');
            $emailsQuery->whereHas('conversation', function ($query) use ($assignedProjectIds, $user) {
                $query->whereIn('project_id', $assignedProjectIds)
                    ->where('contractor_id', $user->id); // Optionally, only conversations where they are the primary contractor
            });
        }
        // Super Admin, Manager, Employee (if allowed to view emails) see all relevant emails,
        // The Policy will handle what is displayed based on permissions.
        // For now, let's assume Super Admin/Manager see all, Employees see none (or read-only filtered).

        $emails = $emailsQuery->orderBy('created_at', 'desc')->get(); // Or paginate

        return response()->json($emails);
    }


    /**
     * Store a newly created email (as a draft or pending approval).
     * Accessible by: Contractor (to create draft/pending), Super Admin, Manager (to create directly if needed)
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        try {
            $validated = $request->validate([
                'project_id' => 'required|exists:projects,id',
                'client_ids' => 'required|array|min:1', // Ensure at least one client is selected
                'client_ids.*.id' => 'required|exists:clients,id', // Validate each client ID
                'subject' => 'required|string|max:255',
                'body' => 'required|string',
                'status' => 'sometimes|in:draft,pending_approval', // Default to pending if submitted
            ]);

            // Extract client IDs from the client_ids array of objects
            $clientIds = array_map(function ($client) {
                return $client['id'];
            }, $validated['client_ids']);

            // Verify project-client relationship for each client
            $project = Project::with('clients')->find($validated['project_id']);
            $projectClientIds = $project->clients->pluck('id')->toArray();
            foreach ($clientIds as $clientId) {
                if (!in_array($clientId, $projectClientIds)) {
                    throw ValidationException::withMessages([
                        'client_ids' => "Client ID {$clientId} is not assigned to this project.",
                    ]);
                }
            }

            // Verify if contractor is assigned to this project
            if ($user->isContractor() && !$user->projects->contains($project->id)) {
                return response()->json(['message' => 'Unauthorized: You are not assigned to this project.'], 403);
            }

            // Create or update a conversation for this project-client(s) combination
            $conversation = Conversation::firstOrCreate(
                [
                    'project_id' => $validated['project_id'],
                    // For multiple clients, use project_id as the primary identifier
                ],
                [
                    'subject' => $validated['subject'], // Use email subject as conversation subject if new
                    'contractor_id' => $user->id,
                    'client_id' => $clientIds[0],
                    'last_activity_at' => now(),
                ]
            );

            // If a new conversation was created and its subject is generic, update it
            if ($conversation->wasRecentlyCreated && empty($conversation->subject)) {
                $conversation->subject = $validated['subject'];
                $conversation->save();
            }

            // Retrieve client emails server-side
            $clientEmails = Client::whereIn('id', $clientIds)
                ->pluck('email')
                ->toArray();

            // Create the email record
            $email = Email::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id, // The user who drafted/submitted the email
                'to' => json_encode($clientEmails), // Store recipient emails as JSON array
                'subject' => $validated['subject'],
                'body' => $validated['body'],
                'status' => $validated['status'] ?? 'pending_approval', // Default to pending
            ]);

            // Attach clients to the conversation (if not already attached)
            //$conversation->clients()->syncWithoutDetaching($clientIds);

            // Update last activity for conversation
            $conversation->update(['last_activity_at' => now()]);

            Log::info('Email created/submitted', ['email_id' => $email->id, 'status' => $email->status, 'user_id' => $user->id]);
            return response()->json($email->load('conversation'), 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating/submitting email: ' . $e->getMessage(), ['request' => $request->all(), 'error' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to process email', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified email.
     * Accessible by: Super Admin, Manager (any); Employee (any); Contractor (if on associated project)
     */
    public function show(Email $email)
    {
        $user = Auth::user();
        // Policy check for viewing a specific email
        // Implement policy logic later (e.g., if contractor, check if on project)
        // For now, let's simply return it, assuming index filtering handles most cases.
        if ($user->isContractor() && !$user->projects->contains($email->conversation->project_id)) {
            return response()->json(['message' => 'Unauthorized to view this email.'], 403);
        }

        return response()->json($email->load(['conversation.project.client', 'sender', 'approver']));
    }

    /**
     * Update the specified email (e.g., from draft, or by admin).
     * Accessible by: Contractor (their own drafts), Super Admin, Manager (any email)
     */
    public function update(Request $request, Email $email)
    {
        $user = Auth::user();

        // Only Super Admins and Managers can update any email
        // Contractors can only update their own drafts or pending emails that were rejected
        if (!$user->isSuperAdmin() && !$user->isManager()) {
            if ($user->isContractor() && $email->sender_id === $user->id && in_array($email->status, ['draft', 'rejected'])) {
                // Allow contractor to update their own draft or rejected emails
            } else {
                return response()->json(['message' => 'Unauthorized to update this email.'], 403);
            }
        }

        try {
            $validated = $request->validate([
                'subject' => 'sometimes|required|string|max:255',
                'body' => 'sometimes|required|string',
                'status' => 'sometimes|required|in:draft,pending_approval', // Can transition back to draft or to pending
                'rejection_reason' => 'nullable|string', // Admin might clear this on re-submit
            ]);

            // If a contractor is resubmitting, ensure status is changing to pending_approval
            if ($user->isContractor() && $email->sender_id === $user->id && $email->status === 'rejected' && ($validated['status'] ?? null) === 'pending_approval') {
                $email->update(array_merge($validated, ['rejection_reason' => null])); // Clear reason on re-submit
                Log::info('Contractor re-submitted email for approval', ['email_id' => $email->id, 'user_id' => $user->id]);
            } else if ($user->isSuperAdmin() || $user->isManager()) {
                $email->update($validated);
                Log::info('Email updated by admin/manager', ['email_id' => $email->id, 'user_id' => $user->id]);
            } else {
                return response()->json(['message' => 'Unauthorized to perform this update for this email status.'], 403);
            }

            $email->load('conversation'); // Reload relationships for response
            return response()->json($email);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating email: ' . $e->getMessage(), ['email_id' => $email->id, 'request' => $request->all(), 'error' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to update email', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Approve an email and send it.
     * Accessible by: Super Admin, Manager
     */
    public function approve(Email $email)
    {
        $user = Auth::user();

        if (!$user->isSuperAdmin() && !$user->isManager()) {
            return response()->json(['message' => 'Unauthorized to approve emails.'], 403);
        }


        if (!in_array($email->status, ['pending_approval', 'pending_approval_received'])  ) {
            return response()->json(['message' => 'Email is not in pending approval status.'], 400);
        }

        try {
            // Get client's email from the linked conversation/client
            $recipientClientEmail = $email->to;

            // Convert JSON-encoded array to comma-separated string
            if (is_string($recipientClientEmail) && $this->isJson($recipientClientEmail)) {
                $emailArray = json_decode($recipientClientEmail, true);
                $recipientClientEmail = implode(',', $emailArray);
            }

            // Send email using GmailService
            $gmailMessageId = $this->gmailService->sendEmail(
                $recipientClientEmail,
                $email->subject,
                $email->body
            );

            // Update email status and record who approved it and when it was sent
            $email->update([
                'status' => 'sent',
                'approved_by' => $user->id,
                'sent_at' => now(),
                'message_id' => $gmailMessageId, // Store the Gmail message ID
            ]);

            Log::info('Email approved and sent', ['email_id' => $email->id, 'gmail_message_id' => $gmailMessageId, 'approved_by' => $user->id]);
            return response()->json(['message' => 'Email approved and sent successfully!', 'email' => $email->load('approver')], 200);
        } catch (\Exception $e) {
            Log::error('Error approving/sending email: ' . $e->getMessage(), ['email_id' => $email->id, 'error' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to approve and send email: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Edit and approve an email in one step.
     * Accessible by: Super Admin, Manager
     */
    public function editAndApprove(Request $request, Email $email)
    {

        $user = Auth::user();

        if (!in_array($email->status, ['pending_approval', 'pending_approval_received'])  ) {
            return response()->json(['message' => 'Email is not in pending approval status.'], 400);
        }

        try {
            $validated = $request->validate([
                'project_id' => 'sometimes|required|exists:projects,id',
                'client_id' => 'sometimes|required|exists:clients,id',
                'subject' => 'sometimes|required|string|max:255',
                'body' => 'sometimes|required|string',
            ]);

            if (isset($validated['project_id'], $validated['client_id'])) {

                $project = Project::findOrFail($validated['project_id']);

                if ($project->client_id !== (int)$validated['client_id']) {
                    throw ValidationException::withMessages(['client_id' => 'The selected client is not assigned to this project.']);
                }

                $conversation = Conversation::firstOrCreate(
                    [
                        'client_id' => $validated['client_id'],
                    ],
                    [
                        'project_id' => $validated['project_id'],
                        'subject' => $validated['subject'] ?? $email->subject,
                        'contractor_id' => $email->conversation->contractor_id,
                        'last_activity_at' => now(),
                    ]
                );
                $validated['conversation_id'] = $conversation->id;
                $validated['to'] = json_encode([Client::find($validated['client_id'])->email]);
            }

            $email->update($validated);
            $recipientClientEmail = $email->conversation->client->email;

            if($email->type === 'sent') {
                $gmailMessageId = $this->gmailService->sendEmail(
                    $recipientClientEmail,
                    $email->subject,
                    $email->body
                );

                $email->update([
                    'status' => 'sent',
                    'approved_by' => $user->id,
                    'sent_at' => now(),
                    'message_id' => $gmailMessageId,
                ]);

                Log::info('Email edited and approved', [
                    'email_id' => $email->id,
                    'gmail_message_id' => $gmailMessageId,
                    'approved_by' => $user->id,
                ]);
            }





            return response()->json(['message' => 'Email updated and approved successfully!', 'email' => $email->load('approver')]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error editing and approving email: ' . $e->getMessage(), [
                'email_id' => $email->id,
                'error' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to edit and approve email: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Approve a received email for viewing.
     */
    public function approveReceived(Email $email)
    {
        $user = Auth::user();

        if (!$user->isSuperAdmin() && !$user->isManager()) {
            return response()->json(['message' => 'Unauthorized to approve received emails.'], 403);
        }

        if ($email->status !== 'pending_approval_received') {
            return response()->json(['message' => 'Email is not in pending approval received status.'], 400);
        }

        try {
            $email->update([
                'status' => 'received',
                'approved_by' => $user->id,
            ]);

            Log::info('Received email approved', ['email_id' => $email->id, 'approved_by' => $user->id]);
            return response()->json(['message' => 'Received email approved successfully!', 'email' => $email->load('approver')], 200);
        } catch (\Exception $e) {
            Log::error('Error approving received email: ' . $e->getMessage(), ['email_id' => $email->id, 'error' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to approve received email: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Edit and approve a received email in one step.
     */
    public function editAndApproveReceived(Request $request, Email $email)
    {
        $user = Auth::user();

        if (!$user->isSuperAdmin() && !$user->isManager()) {
            return response()->json(['message' => 'Unauthorized to edit and approve received emails.'], 403);
        }

        if ($email->status !== 'pending_approval_received') {
            return response()->json(['message' => 'Email is not in pending approval received status.'], 400);
        }

        try {
            $validated = $request->validate([
                'subject' => 'sometimes|required|string|max:255',
                'body' => 'sometimes|required|string',
            ]);

            $email->update($validated);
            $email->update([
                'status' => 'received',
                'approved_by' => $user->id,
            ]);

            Log::info('Received email edited and approved', [
                'email_id' => $email->id,
                'approved_by' => $user->id,
            ]);
            return response()->json(['message' => 'Received email updated and approved successfully!', 'email' => $email->load('approver')]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error editing and approving received email: ' . $e->getMessage(), [
                'email_id' => $email->id,
                'error' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to edit and approve received email: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reject a received email.
     */
    public function rejectReceived(Request $request, Email $email)
    {
        $user = Auth::user();

        if (!$user->isSuperAdmin() && !$user->isManager()) {
            return response()->json(['message' => 'Unauthorized to reject received emails.'], 403);
        }

        if ($email->status !== 'pending_approval_received') {
            return response()->json(['message' => 'Email is not in pending approval received status.'], 400);
        }

        try {
            $validated = $request->validate([
                'rejection_reason' => 'required|string|min:10',
            ]);

            $email->update([
                'status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
                'approved_by' => $user->id,
            ]);

            Log::info('Received email rejected', ['email_id' => $email->id, 'rejection_reason' => $validated['rejection_reason'], 'rejected_by' => $user->id]);
            return response()->json(['message' => 'Received email rejected successfully!', 'email' => $email->load('approver')], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error rejecting received email: ' . $e->getMessage(), ['email_id' => $email->id, 'error' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to reject received email: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reject an email.
     * Accessible by: Super Admin, Manager
     */
    public function reject(Request $request, Email $email)
    {
        $user = Auth::user();

        if (!$user->isSuperAdmin() && !$user->isManager()) {
            return response()->json(['message' => 'Unauthorized to reject emails.'], 403);
        }

        if ($email->status !== 'pending_approval') {
            return response()->json(['message' => 'Email is not in pending approval status.'], 400);
        }

        try {
            $validated = $request->validate([
                'rejection_reason' => 'required|string|min:10',
            ]);

            $email->update([
                'status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
                'approved_by' => $user->id, // Record who rejected it
            ]);

            Log::info('Email rejected', ['email_id' => $email->id, 'rejection_reason' => $validated['rejection_reason'], 'rejected_by' => $user->id]);
            return response()->json(['message' => 'Email rejected successfully!', 'email' => $email->load('approver')], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error rejecting email: ' . $e->getMessage(), ['email_id' => $email->id, 'error' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to reject email: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display a listing of emails pending approval.
     * Accessible by: Super Admin, Manager
     */
    public function pendingApproval()
    {

        $user = Auth::user();

        if (!$user->isSuperAdmin() && !$user->isManager()) {
            return response()->json(['message' => 'Unauthorized to view pending approvals.'], 403);
        }

        $pendingEmails = Email::with([
            'conversation',                  // Load the conversation
            'conversation.project',          // Load the project nested inside conversation
            'conversation.client',           // Load the client nested inside conversation
            'sender'                         // Load the sender user
        ])
            ->whereIn('status', [ 'pending_approval', 'pending_approval_received'])
            ->orderBy('created_at', 'asc')
            ->get(); // Or paginate

        return response()->json($pendingEmails);
    }

    /**
     * Display a listing of emails pending approval with limited information.
     * Only returns Project Name, Client Name, Subject, Sender, Submitted On
     * Accessible by: Super Admin, Manager
     */
    public function pendingApprovalSimplified()
    {
        $user = Auth::user();

        if (!$user->isSuperAdmin() && !$user->isManager()) {
            return response()->json(['message' => 'Unauthorized to view pending approvals.'], 403);
        }

        $pendingEmails = Email::with([
            'conversation.project:id,name',  // Load only project id and name
            'conversation.client:id,name',   // Load only client id and name
            'sender:id,name'                 // Load only sender id and name
        ])
            ->whereIn('status', ['pending_approval', 'pending_approval_received'])
            ->orderBy('created_at', 'asc')
            ->get();

        // Transform the data to include only the required fields
        $simplifiedEmails = $pendingEmails->map(function ($email) {
            return [
                'id' => $email->id,
                'project' => $email->conversation->project ? [
                    'id' => $email->conversation->project->id,
                    'name' => $email->conversation->project->name
                ] : null,
                'client' => $email->conversation->client ? [
                    'id' => $email->conversation->client->id,
                    'name' => $email->conversation->client->name
                ] : null,
                'subject' => $email->subject,
                'sender' => $email->sender ? [
                    'id' => $email->sender->id,
                    'name' => $email->sender->name
                ] : null,
                'created_at' => $email->created_at,
                'body' => $email->body, // Include body for the modal
            ];
        });

        return response()->json($simplifiedEmails);
    }

    /**
     * Display rejected emails with all details (legacy endpoint).
     */
    public function rejected()
    {
        $query = Auth::user()->isContractor()
            ? Email::where('sender_id', Auth::id())->where('status', '=', 'rejected')
            : Email::where('status', 'rejected');
        return $query->with(['conversation.project', 'conversation.client', 'sender'])->get();
    }

    /**
     * Display rejected emails with limited information.
     * Only returns subject, body, rejection_reason, created_at
     */
    public function rejectedSimplified()
    {
        $query = Auth::user()->isContractor()
            ? Email::where('sender_id', Auth::id())->where('status', '=', 'rejected')
            : Email::where('status', 'rejected');

        $emails = $query->get();

        // Transform the data to include only the required fields
        $simplifiedEmails = $emails->map(function ($email) {
            return [
                'id' => $email->id,
                'subject' => $email->subject,
                'body' => $email->body,
                'rejection_reason' => $email->rejection_reason,
                'created_at' => $email->created_at,
            ];
        });

        return response()->json($simplifiedEmails);
    }

    public function resubmit(Request $request, Email $email)
    {

        if ($email->status !== 'rejected') {
            return response()->json(['message' => 'Only rejected emails can be resubmitted.'], 422);
        }

        $email->update([
            'status' => 'pending_approval',
            'rejection_reason' => null,
            'approved_by' => null,
            'sent_at' => null,
        ]);

        return response()->json(['message' => 'Email resubmitted for approval successfully.']);
    }

    /**
     * Get all emails for a specific project.
     * Accessible by: Super Admin, Manager (all); Contractor (if assigned to project)
     */
    public function getProjectEmails($projectId)
    {
        $user = Auth::user();
        $role = $user->getRoleForProject($projectId);

        $project = Project::findOrFail($projectId);

        // Check if user has access to this project
        if ($user->isContractor() && !$user->projects->contains($project->id)) {
            return response()->json(['message' => 'Unauthorized to view emails for this project.'], 403);
        }

        // Get all conversations for this project
        $conversations = $project->conversations;
        $conversationIds = $conversations->pluck('id')->toArray();

        // Get all emails for these conversations
        $emails = Email::with(['conversation.project.client', 'sender', 'approver'])
            ->whereIn('status', ['approved', 'pending_approval', 'sent']);

        $emails = $emails->whereIn('conversation_id', $conversationIds)
        ->orderBy('created_at', 'desc')
        ->get();


        return response()->json($emails);
    }

    /**
     * Get emails for a specific project with simplified information.
     * Only returns Subject, From, Date, Status
     * Accessible by: Super Admin, Manager (all); Contractor (if assigned to project)
     */
    public function getProjectEmailsSimplified($projectId, Request $request)
    {
        $user = Auth::user();
        $role = $user->getRoleForProject($projectId);

        $project = Project::findOrFail($projectId);

        // Check if user has access to this project
        if ($user->isContractor() && !$user->projects->contains($project->id)) {
            return response()->json(['message' => 'Unauthorized to view emails for this project.'], 403);
        }

        // Get all conversations for this project
        $conversations = $project->conversations;
        $conversationIds = $conversations->pluck('id')->toArray();

        // Get all emails for these conversations
        $query = Email::with(['sender:id,name'])
            ->whereIn('status', ['approved', 'pending_approval', 'sent'])
            ->whereIn('conversation_id', $conversationIds);

        // Apply type filter if provided
        if ($request->has('type') && !empty($request->type)) {
            $query->where('type', $request->type);
        }

        // Apply date range filters if provided
        if ($request->has('start_date') && !empty($request->start_date)) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date') && !empty($request->end_date)) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Apply search filter if provided
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('subject', 'like', "%{$searchTerm}%")
                  ->orWhere('body', 'like', "%{$searchTerm}%");
            });
        }

        // Get the filtered emails
        $emails = $query->orderBy('created_at', 'desc')->get();

        // Transform the data to include only the required fields
        $simplifiedEmails = $emails->map(function ($email) {
            return [
                'id' => $email->id,
                'subject' => $email->subject,
                'sender' => $email->sender ? [
                    'id' => $email->sender->id,
                    'name' => $email->sender->name
                ] : null,
                'created_at' => $email->created_at,
                'status' => $email->status,
                'type'  => $email->type,
                // Include body for the modal view
                'body' => $email->body,
                // Include additional fields needed for the modal view
                'rejection_reason' => $email->rejection_reason,
                'approver' => $email->approver ? [
                    'id' => $email->approver->id,
                    'name' => $email->approver->name
                ] : null,
                'sent_at' => $email->sent_at
            ];
        });

        return response()->json($simplifiedEmails);
    }

    /**
     * Check if a string is a valid JSON
     *
     * @param string $string
     * @return bool
     */
    private function isJson($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
}
