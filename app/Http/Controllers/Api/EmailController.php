<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HasProjectPermissions;
use App\Http\Controllers\Controller;
use App\Mail\ClientEmail;
use App\Models\Email;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\Client;
use App\Services\GmailService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class EmailController extends Controller
{
    protected GmailService $gmailService;
    use HasProjectPermissions;

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
                'custom_greeting_name' => 'string|nullable',
                'greeting_name' =>  'string|nullable',
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
            if (!$user->projects->contains($project->id)) {
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

            $greeting = $validated['custom_greeting_name'] ?? $validated['greeting_name'] ?: 'Hi there';

            // Create the email record
            $email = Email::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id, // The user who drafted/submitted the email
                'to' => json_encode($clientEmails), // Store recipient emails as JSON array
                'subject' => $validated['subject'],
                'body' => $greeting . '<br/>' . $validated['body'],
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
     * Store a newly created email using a template, saving only the template and dynamic data.
     */
    public function storeTemplatedEmail(Request $request)
    {
        $user = Auth::user();

        try {
            $validated = $request->validate([
                'project_id' => 'required|exists:projects,id',
                'client_ids' => 'required|array|min:1',
                'client_ids.*' => 'required|exists:clients,id',
                'subject' => 'required|string|max:255',
                'template_id' => 'required|exists:email_templates,id',
                'template_data' => 'nullable|array',
            ]);

            $clientIds = $validated['client_ids'];

            $project = Project::with('clients')->find($validated['project_id']);
            $projectClientIds = $project->clients->pluck('id')->toArray();
            foreach ($clientIds as $clientId) {
                if (!in_array($clientId, $projectClientIds)) {
                    throw ValidationException::withMessages([
                        'client_ids' => "Client ID {$clientId} is not assigned to this project.",
                    ]);
                }
            }

            if (!$user->projects->contains($project->id)) {
                return response()->json(['message' => 'Unauthorized: You are not assigned to this project.'], 403);
            }

            $conversation = Conversation::firstOrCreate(
                [
                    'project_id' => $validated['project_id'],
                ],
                [
                    'subject' => $validated['subject'],
                    'contractor_id' => $user->id,
                    'client_id' => $clientIds[0],
                    'last_activity_at' => now(),
                ]
            );

            if ($conversation->wasRecentlyCreated && empty($conversation->subject)) {
                $conversation->subject = $validated['subject'];
                $conversation->save();
            }

            $clientEmails = Client::whereIn('id', $clientIds)
                ->pluck('email')
                ->toArray();

            $email = Email::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'to' => json_encode($clientEmails),
                'subject' => $validated['subject'],
                'template_id' => $validated['template_id'],
                'template_data' => json_encode($validated['template_data'] ?? []),
                'status' => 'pending_approval',
            ]);

            $conversation->update(['last_activity_at' => now()]);

            Log::info('Templated email created/submitted for approval', ['email_id' => $email->id, 'status' => $email->status, 'user_id' => $user->id]);
            return response()->json($email->load('conversation'), 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating/submitting templated email: ' . $e->getMessage(), ['request' => $request->all(), 'error' => $e->getTraceAsString()]);
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
     *
     * @deprecated Use editAndApprove() method instead
     */
    public function approve(Email $email)
    {
        throw new Exception('This method is deprecated. Use editAndApprove instead.');
    }

    /**
     * Edit and approve an email in one step.
     * Accessible by: Super Admin, Manager
     */
    public function editAndApprove(Request $request, Email $email)
    {
        $approver = Auth::user();

        if (!$approver) {
            return response()->json(['error' => 'No authenticated user found for approval.'], 401);
        }

        if (!in_array($email->status, ['pending_approval', 'pending_approval_received'])) {
            return response()->json(['message' => 'Email is not in pending approval status.'], 400);
        }

        try {
            $validated = $request->validate([
                'subject' => 'sometimes|required|string|max:255',
                'body' => 'sometimes|required|string',
            ]);

            // Handle templated emails
            if ($email->template_id) {
                // Get the client for the conversation
                $recipientClient = $email->conversation->client;
                if (!$recipientClient) {
                    throw new Exception('Recipient client not found for email ID: ' . $email->id);
                }

                // Get the template and dynamic data
                $template = EmailTemplate::with('placeholders')->findOrFail($email->template_id);
                $templateData = json_decode($email->template_data, true) ?? [];

                // Render the subject and body using the template
                $subject = $this->populateAllPlaceholders(
                    $template->subject,
                    $template,
                    $templateData,
                    $recipientClient,
                    $email->conversation->project,
                    true
                );
                $renderedBody = $this->populateAllPlaceholders(
                    $template->body_html,
                    $template,
                    $templateData,
                    $recipientClient,
                    $email->conversation->project,
                    true
                );
            } else {
                // Handle regular emails
                $subject = $validated['subject'] ?? $email->subject;
                $renderedBody = $validated['body'] ?? $email->body;
            }

            $email->update($validated);

            $sender = $email->sender;
            $senderDetails = [
                'name' => $sender->name ?? 'Unknown Sender',
                'role' => $this->getProjectRoleName($sender, $email->conversation->project) ?? 'Staff',
            ];

            $companyDetails = [
                'phone' => '+61 456 639 389',
                'website' => 'ozeeweb.com.au',
                'logo_url' => asset('logo.png'),
                'brand_primary_color' => '#1a73e8',
                'brand_secondary_color' => '#fbbc05',
                'text_color_primary' => '#1a202c',
                'text_color_secondary' => '#4a5568',
                'border_color' => '#e5e7eb',
                'background_color' => '#f9fafb',
            ];

            $recipientClient = $email->conversation->client;
            if (!$recipientClient) {
                throw new Exception('Recipient client not found for email ID: ' . $email->id);
            }
            $clientEmailAddress = $recipientClient->email;
            $clientName = $recipientClient->name ?? 'Valued Client';

            $mailablePayload = [
                'subject' => $subject,
                'body' => $renderedBody,
                'greeting_type' => $request->input('greeting_type', 'full_name'),
                'custom_greeting_name' => $request->input('custom_greeting_name', ''),
                'clientName' => $clientName,
            ];

            $mailable = new ClientEmail($mailablePayload, $senderDetails, $companyDetails);
            $finalRenderedBody = $mailable->render();

            $gmailMessageId = $this->gmailService->sendEmail(
                $clientEmailAddress,
                $subject,
                $finalRenderedBody
            );

            $email->update([
                'status' => 'sent',
                'approved_by' => $approver->id,
                'sent_at' => now(),
                'message_id' => $gmailMessageId,
            ]);

            Log::info('Email edited and approved', [
                'email_id' => $email->id,
                'gmail_message_id' => $gmailMessageId,
                'approved_by' => $approver->id,
            ]);

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
            return response()->json(['message' => 'Failed to edit and approve received email: ' . e->getMessage()], 500);
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
            'conversation',
            'conversation.project',
            'conversation.client',
            'sender'
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
            'conversation.project:id,name',
            'conversation.client:id,name',
            'sender:id,name'
        ])
            ->whereIn('status', ['pending_approval', 'pending_approval_received'])
            ->orderBy('created_at', 'asc')
            ->get();

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
                'body' => $email->body,
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

        if ($user->isContractor() && !$user->projects->contains($project->id)) {
            return response()->json(['message' => 'Unauthorized to view emails for this project.'], 403);
        }

        $conversations = $project->conversations;
        $conversationIds = $conversations->pluck('id')->toArray();

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
     *
     * @param int $projectId The ID of the project
     * @param Request $request The request object which may contain:
     * - type: Filter by email type
     * - start_date: Filter by start date
     * - end_date: Filter by end date
     * - search: Search term for subject or body
     * - limit: Optional parameter to limit the number of emails returned (default: all)
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectEmailsSimplified($projectId, Request $request)
    {
        $user = Auth::user();
        $role = $user->getRoleForProject($projectId);

        $project = Project::findOrFail($projectId);

        if ($user->isContractor() && !$user->projects->contains($project->id)) {
            return response()->json(['message' => 'Unauthorized to view emails for this project.'], 403);
        }

        $conversations = $project->conversations;
        $conversationIds = $conversations->pluck('id')->toArray();

        $query = Email::with(['sender:id,name'])
//            ->whereIn('status', ['approved', 'pending_approval', 'sent'])
            ->whereIn('conversation_id', $conversationIds);

        if ($request->has('type') && !empty($request->type)) {
            $query->where('type', $request->type);
        }

        if ($request->has('start_date') && !empty($request->start_date)) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date') && !empty($request->end_date)) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('subject', 'like', "%{$searchTerm}%")
                  ->orWhere('body', 'like', "%{$searchTerm}%");
            });
        }

        $query->orderBy('created_at', 'desc');

        if ($request->has('limit') && is_numeric($request->limit)) {
            $query->limit($request->limit);
        }

        $emails = $query->get();

        if(!$user->hasPermission('approve_emails')) {
            foreach ($emails as $email) {
                if(in_array($email->status, ['pending_approval_received']))
                {
                    $email->body = 'Please ask project admin to approve this email';
                }
            }
        }

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
                'body' => $email->body,
                'rejection_reason' => $email->rejection_reason,
                'approver' => $email->approver ? [
                    'id' => $email->approver->id,
                    'name' => $email->approver->name
                ] : null,
                'sent_at' => $email->sent_at,
                'template_id' => $email->template_id,
                'template_data' => $email->template_data ? json_decode($email->template_data, true) : null,
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

    /**
     * Preview an email exactly how the recipient will see it.
     * Accessible by: Super Admin, Manager (or whoever needs to preview emails)
     */
    public function previewEmail(Email $email)
    {
        try {
            // Check if the email is based on a template
            if ($email->template_id) {
                // Get the template and dynamic data
                $template = EmailTemplate::with('placeholders')->findOrFail($email->template_id);
                $templateData = json_decode($email->template_data, true) ?? [];

                // Get the recipient client
                $recipientClient = $email->conversation->client;
                if (!$recipientClient) {
                    throw new Exception('Recipient client not found for email ID: ' . $email->id);
                }

                // Render the body from the template and dynamic data
                $renderedBody = $this->populateAllPlaceholders(
                    $template->body_html,
                    $template,
                    $templateData,
                    $recipientClient,
                    $email->conversation->project,
                    false // It's a preview, not a final send
                );
                $subject = $this->populateAllPlaceholders(
                    $template->subject,
                    $template,
                    $templateData,
                    $recipientClient,
                    $email->conversation->project,
                    false
                );
            } else {
                // Use the stored body for non-templated emails
                $renderedBody = $email->body;
                $subject = $email->subject;
            }

            $sender = $email->sender;
            $senderDetails = [
                'name' => $sender->name ?? 'Original Sender',
                'role' => $this->getProjectRoleName($sender, $email->conversation->project) ?? 'Staff',
            ];

            $companyDetails = [
                'phone' => '+61 456 639 389',
                'website' => 'ozeeweb.com.au',
                'logo_url' => asset('logo.png'),
                'brand_primary_color' => '#1a73e8',
                'brand_secondary_color' => '#fbbc05',
                'text_color_primary' => '#1a202c',
                'text_color_secondary' => '#4a5568',
                'border_color' => '#e5e7eb',
                'background_color' => '#f9fafb',
            ];

            $recipientClient = $email->conversation->client;
            $clientName = $recipientClient->name ?? 'Valued Client';

            $mailablePayload = [
                'subject' => $subject,
                'body' => $renderedBody,
                'greeting_type' => 'full_name',
                'custom_greeting_name' => '',
                'clientName' => $clientName,
            ];

            $mailable = new ClientEmail($mailablePayload, $senderDetails, $companyDetails);
            $fullHtml = $mailable->render();

            return response($fullHtml)->header('Content-Type', 'text/html');

        } catch (Exception $e) {
            Log::error('Error previewing email: ' . $e->getMessage(), [
                'email_id' => $email->id,
                'error' => $e->getTraceAsString(),
            ]);
            return response('Error generating email preview: ' . $e->getMessage(), 500);
        }
    }
}
