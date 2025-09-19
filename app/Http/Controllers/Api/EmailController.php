<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HasProjectPermissions;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\HandlesTemplatedEmails;
use App\Http\Controllers\Api\Concerns\HandlesEmailCreation;
use App\Jobs\ProcessDraftEmailJob;
use App\Mail\ClientEmail;
use App\Models\Email;
use App\Models\EmailTemplate;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\Client;
use App\Models\Role;
use App\Services\GmailService;
use App\Services\MagicLinkService;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class EmailController extends Controller
{
    protected GmailService $gmailService;
    protected MagicLinkService $magicLinkService;

    use HasProjectPermissions, HandlesTemplatedEmails, HandlesEmailCreation;

    public function __construct(GmailService $gmailService, MagicLinkService $magicLinkService)
    {
        $this->gmailService = $gmailService;
        $this->magicLinkService = $magicLinkService;
    }

    /**
     * Delete an email locally and/or from Gmail.
     * Requires 'delete_emails' permission.
     */
    public function destroy(Request $request, Email $email)
    {

        $this->authorize('delete', $email);

        $validated = $request->validate([
            'delete_gmail' => 'sometimes|boolean',
            'delete_local' => 'sometimes|boolean',
        ]);

        $deleteGmail = (bool)($validated['delete_gmail'] ?? false);
        $deleteLocal = (bool)($validated['delete_local'] ?? true); // default to local delete if not specified

        $errors = [];

        // Attempt to trash on Gmail if requested and message_id exists
        if ($deleteGmail) {
            $gmailId = $email->message_id;
            if ($gmailId) {
                try {
                    $this->gmailService->trashMessage($gmailId);
                } catch (\Throwable $e) {
                    \Log::error('Failed to trash Gmail message for email', ['email_id' => $email->id, 'error' => $e->getMessage()]);
                    $errors[] = 'Failed to delete Gmail copy: ' . $e->getMessage();
                }
            } else {
                $errors[] = 'No Gmail message ID associated with this email.';
            }
        }

        // Soft delete locally if requested
        if ($deleteLocal) {
            try {
                $email->delete();
            } catch (\Throwable $e) {
                \Log::error('Failed to delete local email', ['email_id' => $email->id, 'error' => $e->getMessage()]);
                $errors[] = 'Failed to delete local copy: ' . $e->getMessage();
            }
        }

        $status = empty($errors) ? 200 : 207; // 207 Multi-Status when partial failures
        return response()->json([
            'message' => empty($errors) ? 'Email deletion completed.' : 'Email deletion completed with some errors.',
            'errors' => $errors,
        ], $status);
    }

    /**
     * Display a listing of emails (conversations) relevant to the authenticated user.
     * Accessible by: Super Admin, Manager (all); Contractor (their assigned projects/conversations)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $emailsQuery = Email::visibleTo($user)->with(['conversation.project', 'conversation.conversable', 'sender', 'approver']);

        if ($user->isContractor()) {
            // Contractors only see emails from conversations on their assigned projects
            $assignedProjectIds = $user->projects->pluck('id');
            $emailsQuery->whereHas('conversation', function ($query) use ($assignedProjectIds, $user) {
                $query->whereIn('project_id', $assignedProjectIds)
                    ->where('contractor_id', $user->id); // Optionally, only conversations where they are the primary contractor
            });
        }

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
                'project_id' => 'nullable|exists:projects,id|required_without:lead_ids',
                // Recipient sets
                'client_ids' => 'array',
                'client_ids.*.id' => 'required_with:client_ids|exists:clients,id',
                'lead_ids' => 'array',
                'lead_ids.*.id' => 'required_with:lead_ids|exists:leads,id',
                // Content
                'subject' => 'required|string|max:255',
                'body' => 'required_without:template_id|string|nullable',
                'template_id' => 'nullable|exists:email_templates,id',
                'template_data' => 'nullable|array',
                'custom_greeting_name' => 'string|nullable',
                'greeting_name' =>  'string|nullable',
                'status' => 'sometimes|in:draft,pending_approval',
            ]);

            if (array_key_exists('status', $validated)) {
                app(\App\Services\ValueSetValidator::class)->validate('Email','status',$validated['status']);
            }

            // Decide which handler to use
            if (!empty($validated['template_id'])) {
                $email = $this->handleTemplatedEmail($user, $validated);
            } elseif (!empty($validated['lead_ids'])) {
                // project_id may be null by design for leads
                $email = $this->handleLeadEmail($user, $validated);
            } else {
                // Custom email to existing clients requires project_id
                if (empty($validated['project_id'])) {
                    throw ValidationException::withMessages(['project_id' => 'Project is required for client emails.']);
                }
                $email = $this->handleCustomClientEmail($user, $validated);
            }

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
     * Store a newly created email using a template, saving only the template and template data.
     */
    public function storeTemplatedEmail(Request $request)
    {
        $user = Auth::user();

//        try {
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
                    'subject' => $validated['subject'],
                ],
                [
                    'contractor_id' => $user->id,
                    'conversable_type' => Client::class,
                    'conversable_id' => $clientIds[0],
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
                'to' => $clientEmails,
                'subject' => $validated['subject'],
                'template_id' => $validated['template_id'],
                'template_data' => json_encode($validated['template_data'] ?? []),
                'status' => Email::STATUS_DRAFT,
                'type' => \App\Enums\EmailType::Sent, // Set type to sent for outgoing emails
            ]);

//            ProcessDraftEmailJob::dispatch($email);

            $conversation->update(['last_activity_at' => now()]);

            Log::info('Templated email created/submitted for approval', ['email_id' => $email->id, 'status' => $email->status, 'user_id' => $user->id]);
            return response()->json($email->load('conversation'), 201);
//        } catch (ValidationException $e) {
//            return response()->json([
//                'message' => 'Validation failed',
//                'errors' => $e->errors(),
//            ], 422);
//        } catch (\Exception $e) {
//            Log::error('Error creating/submitting templated email: ' . $e->getMessage(), ['request' => $request->all(), 'error' => $e->getTraceAsString()]);
//            return response()->json(['message' => 'Failed to process email', 'error' => $e->getMessage()], 500);
//        }
    }


    /**
     * Display the specified email.
     * Accessible by: Super Admin, Manager (any); Employee (any); Contractor (if on associated project)
     * This method now returns a JSON response with the rendered subject and body HTML.
     */
    public function show(Email $email)
    {

        $user = Auth::user();
        if ($email->is_private && !$user->hasPermission('view_private_emails')) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        if ($user->isContractor() && !$user->projects->contains($email->conversation->project_id)) {
            return response()->json(['message' => 'Unauthorized to view this email.'], 403);
        }

//        try {
        // Use the trait method to render the full email preview as a JSON response
        return $this->renderFullEmailPreviewResponse($email);
//        } catch (Exception $e) {
//            Log::error('Error showing email: ' . $e->getMessage(), ['email_id' => $email->id, 'error' => $e->getTraceAsString()]);
//            return response()->json(['message' => 'Error generating email view: ' . $e->getMessage()], 500);
//        }
    }

    /**
     * Update the specified email (e.g., from draft, or by admin).
     * Accessible by: Contractor (their own drafts), Super Admin, Manager (any email)
     */
    public function update(Request $request, Email $email)
    {

        try {

            $validated = $request->validate([
                'subject' => 'sometimes|required|string|max:255',
                'body' => 'sometimes|required|string',
                'status' => 'sometimes|required|in:draft,pending_approval', // Can transition back to draft or to pending
                'rejection_reason' => 'nullable|string', // Admin might clear this on re-submit
            ]);

            $this->authorize('resubmit', $email);

            $email->update(array_merge($validated, ['rejection_reason' => null])); // Clear reason on re-submit

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

        $this->authorize('editAndApprove', $email);

        if (!$approver) {
            return response()->json(['error' => 'No authenticated user found for approval.'], 401);
        }

        $statusEnum = $email->status instanceof \App\Enums\EmailStatus ? $email->status : \App\Enums\EmailStatus::tryFrom((string)$email->status);
        if (!in_array($statusEnum, [\App\Enums\EmailStatus::PendingApproval, \App\Enums\EmailStatus::PendingApprovalReceived], true)) {
            return response()->json(['message' => 'Email is not in pending approval status.'], 400);
        }

        try {
            $validated = $request->validate([
                'subject' => 'sometimes|required|string|max:255',
                'body' => 'sometimes|required|string',
                'composition_type' => 'sometimes|string|in:custom,template',
                'template_id' => 'nullable|exists:email_templates,id',
                'template_data' => 'nullable|array',
            ]);

            // Determine if we're dealing with a template-based email or a regular HTML email
            $isTemplateEmail = ($request->input('composition_type') === 'template' || $email->template_id);
            $senderDetails = $this->getSenderDetails($email);

            if ($isTemplateEmail) {
                // For template-based emails
                $templateId = $validated['template_id'] ?? $email->template_id;
                $templateData = $validated['template_data'] ?? json_decode($email->template_data, true) ?? [];

                // Update the email with template data
                $email->update([
                    'subject' => $validated['subject'] ?? $email->subject,
                    'template_id' => $templateId,
                    'template_data' => json_encode($templateData),
                ]);

                // Render the email content using the template
                $renderedContent = $this->renderEmailContent($email, true);
                $subject = $renderedContent['subject'];
                $renderedBody = $renderedContent['body'];
                $template = $email->email_template ?: 'email_template';

            } else {
                // For regular HTML emails
                $renderedBody = $validated['body'] ?? $email->body;
                $subject = $validated['subject'] ?? $email->subject;


                $email->update($validated);
                $template = $email->email_template ?: 'email_template';
            }

            $data = $this->getData($subject, $renderedBody, $senderDetails, $email, true);

            // If the body already contains a full HTML document (e.g., from preview/editor), do not wrap it again
            $isFullHtmlDoc = is_string($renderedBody) && (str_contains($renderedBody, '<html') || str_contains($renderedBody, '<!DOCTYPE'));
            $finalRenderedBody = $isFullHtmlDoc ? $renderedBody : $this->renderHtmlTemplate($data, $template);

            // Resolve recipient(s): if a client is associated, use it; otherwise fall back to Email.to
            $recipient = $email->conversation->conversable;
            $recipients = [];
            if ($recipient && !empty($recipient->email)) {
                $recipients = [$recipient->email];
            } else {
                $recipients = is_array($email->to) ? $email->to : (empty($email->to) ? [] : [$email->to]);
            }

            if ($statusEnum === \App\Enums\EmailStatus::PendingApproval && !empty($recipients)) {
                // Send to first recipient for now (extend to multiple later if required)
                $this->gmailService->sendEmail(
                    $recipients[0],
                    $subject,
                    $finalRenderedBody
                );
            }

            app(\App\Services\ValueSetValidator::class)->validate('Email','status', \App\Enums\EmailStatus::Sent);

            $email->update([
                'status' => \App\Enums\EmailStatus::Sent,
                'approved_by' => $approver->id,
                'sent_at' => now()
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

        $statusEnum = $email->status instanceof \App\Enums\EmailStatus ? $email->status : \App\Enums\EmailStatus::tryFrom((string)$email->status);
        if ($statusEnum !== \App\Enums\EmailStatus::PendingApprovalReceived) {
            return response()->json(['message' => 'Email is not in pending approval received status.'], 400);
        }

        try {
            app(\App\Services\ValueSetValidator::class)->validate('Email','status', \App\Enums\EmailStatus::Received);
            $email->update([
                'status' => \App\Enums\EmailStatus::Received,
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

        $statusEnum = $email->status instanceof \App\Enums\EmailStatus ? $email->status : \App\Enums\EmailStatus::tryFrom((string)$email->status);
        if ($statusEnum !== \App\Enums\EmailStatus::PendingApprovalReceived) {
            return response()->json(['message' => 'Email is not in pending approval received status.'], 400);
        }

        try {
            $validated = $request->validate([
                'subject' => 'sometimes|required|string|max:255',
                'body' => 'sometimes|required|string',
            ]);

            $email->update($validated);
            app(\App\Services\ValueSetValidator::class)->validate('Email','status', \App\Enums\EmailStatus::Received);
            $email->update([
                'status' => \App\Enums\EmailStatus::Received,
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

        $statusEnum = $email->status instanceof \App\Enums\EmailStatus ? $email->status : \App\Enums\EmailStatus::tryFrom((string)$email->status);
        if ($statusEnum !== \App\Enums\EmailStatus::PendingApprovalReceived) {
            return response()->json(['message' => 'Email is not in pending approval received status.'], 400);
        }

        try {
            $validated = $request->validate([
                'rejection_reason' => 'required|string|min:10',
            ]);

            app(\App\Services\ValueSetValidator::class)->validate('Email','status', \App\Enums\EmailStatus::Rejected);
            $email->update([
                'status' => \App\Enums\EmailStatus::Rejected,
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

       $this->authorize('reject', $email);

        try {
            $validated = $request->validate([
                'rejection_reason' => 'required|string|min:10',
            ]);

            app(\App\Services\ValueSetValidator::class)->validate('Email','status', \App\Enums\EmailStatus::Rejected);
            $email->update([
                'status' => \App\Enums\EmailStatus::Rejected,
                'rejection_reason' => $validated['rejection_reason'],
                'approved_by' => $user->id,
            ]);

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

        $pendingEmails = Email::visibleTo($user)->with([
            'conversation',
            'conversation.project',
            'conversation.conversable',
            'sender'
        ])
            ->whereIn('status', [ \App\Enums\EmailStatus::PendingApproval->value, \App\Enums\EmailStatus::PendingApprovalReceived->value])
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

        $pendingEmails = Email::visibleTo($user)->with([
            'conversation.project:id,name',
            'conversation.conversable',
            'sender:id,name'
        ])
            ->whereIn('status', [\App\Enums\EmailStatus::PendingApproval->value, \App\Enums\EmailStatus::PendingApprovalReceived->value])
            ->orderBy('created_at', 'asc')
            ->get();

        $simplifiedEmails = $pendingEmails->map(function ($email) {
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
                'project_id' => $email->conversation->project_id ?? null,
                'client_id' => $email->conversation->conversable_id ?? null,
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
            ? Email::visibleTo(Auth::user())->where('sender_id', Auth::id())->where('status', '=', \App\Enums\EmailStatus::Rejected->value)
            : Email::visibleTo(Auth::user())->where('status', \App\Enums\EmailStatus::Rejected->value);
        return $query->with(['conversation.project', 'conversation.conversable', 'sender'])->get();
    }

    /**
     * Display rejected emails with limited information.
     */
    public function rejectedSimplified()
    {
        $query = Auth::user()->isContractor()
            ? Email::where('sender_id', Auth::id())->where('status', '=', \App\Enums\EmailStatus::Rejected->value)
            : Email::where('status', \App\Enums\EmailStatus::Rejected->value);

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

        $statusEnum = $email->status instanceof \App\Enums\EmailStatus ? $email->status : \App\Enums\EmailStatus::tryFrom((string)$email->status);
        if ($statusEnum !== \App\Enums\EmailStatus::Rejected) {
            return response()->json(['message' => 'Only rejected emails can be resubmitted.'], 422);
        }

        app(\App\Services\ValueSetValidator::class)->validate('Email','status', \App\Enums\EmailStatus::PendingApproval);
        $email->update([
            'status' => \App\Enums\EmailStatus::PendingApproval,
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

        $emails = Email::visibleTo($user)->with(['conversation.project', 'conversation.conversable', 'sender', 'approver'])
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
        $project = Project::findOrFail($projectId);

        if ($user->isContractor() && !$user->projects->contains($project->id)) {
            return response()->json(['message' => 'Unauthorized to view emails for this project.'], 403);
        }

        $conversations = $project->conversations;
        $conversationIds = $conversations->pluck('id')->toArray();

        // Eager load the conversation with client and project IDs for the frontend
        $query = Email::visibleTo($user)->with([
            'sender:id,name',
            'conversation:id,conversable_id,project_id',
            'conversation.conversable'
        ])->whereIn('conversation_id', $conversationIds);

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

        $simplifiedEmails = $emails->map(function ($email) use ($user) {
            $body = $email->body;

            if (!$user->hasPermission('approve_received_emails') && in_array($email->status, ['pending_approval_received'])) {
                $body = 'Please ask project admin to approve this email';
            }

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
                'body' => $body,
                'rejection_reason' => $email->rejection_reason,
                'approver' => $email->approver ? [
                    'id' => $email->approver->id,
                    'name' => $email->approver->name
                ] : null,
                'sent_at' => $email->sent_at,
                'template_id' => $email->template_id,
                'template_data' => $email->template_data ? json_decode($email->template_data, true) : null,
                'project_id' => $email->conversation->project_id ?? null,
                'client_id' => $email->conversation->conversable_id ?? null,
            ];
        });

        return response()->json($simplifiedEmails);
    }

    /**
     * Renders a full email (subject and body) for final sending.
     *
     * @param Email $email
     * @param array $validated
     * @return array
     * @throws Exception
     */
    private function renderEmail(Email $email, array $validated)
    {
        // Handle templated emails
        if ($email->template_id) {
            $recipientClient = $email->conversation->client;
            if (!$recipientClient) {
                throw new Exception('Recipient client not found for email ID: ' . $email->id);
            }

            $template = EmailTemplate::with('placeholders')->findOrFail($email->template_id);
            $templateData = json_decode($email->template_data, true) ?? [];

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

        return [$subject, $renderedBody];
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
     * Accessible by:
     * - For 'sent' emails: Email creator, users with approve_emails permission globally or for the project
     * - For 'received' emails: Users with approve_received_emails permission globally or for the project
     */
    public function reviewEmail(Email $email)
    {
        $user = Auth::user();

        // Check for null user and email upfront
        if (!Auth::check() || !$email) {
            return response()->json(['message' => 'Unauthorized or invalid email.'], 403);
        }

        // Or even better, use the authorize() method which handles the 403 response for you
        try {
            $this->authorize('approveOrView', $email);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => 'Unauthorized to view this email.'], 403);
        }

        try {
            // Use the trait method to render the email content
            $renderedContent = $this->renderEmailContent($email, false);
            $subject = $renderedContent['subject'];
            $body = $renderedContent['body'];

            // Get the sender details
            $senderDetails = $this->getSenderDetails($email);

            // Combine all data into a single array for the view
            $data = $this->getData($subject, $body, $senderDetails);

            // Return the view with the complete data
            return View('emails.email_template', $data);

        } catch (Exception $e) {
            Log::error('Error previewing email: ' . $e->getMessage(), [
                'email_id' => $email->id,
                'error' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Error generating email preview: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Preview an email exactly how the recipient will see it.
     * Accessible by: Super Admin, Manager (or whoever needs to preview emails)
     */
    public function previewEmail(Email $email)
    {
        try {
            // Use the trait method to render the full email preview as a JSON response
            return $this->renderFullEmailPreviewResponse($email);
        } catch (Exception $e) {
            Log::error('Error previewing email: ' . $e->getMessage(), [
                'email_id' => $email->id,
                'error' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Error generating email preview: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get email content for editing in EmailActionModal.
     * Returns the rendered content using renderEmailContent from HandlesTemplatedEmail.
     * Accessible by: Super Admin, Manager, Contractor (if on associated project)
     *
     * @param Email $email
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEmailContent(Email $email)
    {

        $user = Auth::user();

        $this->authorize('approveOrView', $email);

        try {
            // Use the renderEmailContent method from HandlesTemplatedEmails trait
            $renderedContent = $this->renderEmailContent($email, false);
            $subject = $renderedContent['subject'];
            $body = $renderedContent['body'];

            // Build full HTML using the saved blade template for a proper editor preview
            $senderDetails = $this->getSenderDetails($email);
            $data = $this->getData($subject, $body, $senderDetails, $email, false);
            $template = $email->email_template ?: \App\Models\Email::TEMPLATE_DEFAULT;
            $fullHtml = $this->renderHtmlTemplate($data, $template);

            return response()->json([
                'subject' => $subject,
                'body_html' => $fullHtml,
                'template_id' => $email->template_id,
                'template_data' => $email->template_data ? json_decode($email->template_data, true) : null,
                'email_template' => $email->email_template,
                'client_id' => $email->conversation->conversable_id,
                'type'  =>  $email->type,
                'status'    =>  $email->status
            ]);
        } catch (Exception $e) {
            Log::error('Error getting email content for editing: ' . $e->getMessage(), [
                'email_id' => $email->id,
                'error' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Error getting email content: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Create multiple tasks under the email's conversation project support milestone.
     * Expects payload: { tasks: [ { name, description?, dueDate, priority? } ] }
     */
    public function bulkTasksFromEmail(Request $request, Email $email)
    {
        $user = Auth::user();

        // Ensure user can view/approve this email which implies project visibility
        try {
            $this->authorize('approveOrView', $email);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'tasks' => 'required|array|min:1',
            'tasks.*.name' => 'required|string|max:255',
            'tasks.*.description' => 'nullable|string',
            'tasks.*.dueDate' => 'required|date|after_or_equal:today',
            'tasks.*.priority' => 'nullable|string|in:Low,Medium,High',
                        'tasks.*.assigned_to_user_id' => 'nullable|integer|exists:users,id',
        ]);

        // Resolve project via email's conversation
        $conversation = $email->conversation()->with('project')->first();
        if (!$conversation || !$conversation->project) {
            return response()->json(['message' => 'Email is not associated with a project conversation.'], 422);
        }

        $project = $conversation->project;
        // Use supportMilestone() to get or create support milestone
        $milestone = $project->supportMilestone();

        // Default Task Type
        $defaultTaskType = \App\Models\TaskType::firstOrCreate(['name' => 'New']);

        $created = [];
        foreach ($validated['tasks'] as $item) {
            $taskData = [
                'name' => $item['name'],
                'description' => $item['description'] ?? null,
                'due_date' => $item['dueDate'],
                'priority' => $item['priority'] ?? 'Medium',
                'status' => \App\Enums\TaskStatus::ToDo->value,
                'task_type_id' => $defaultTaskType->id,
                'milestone_id' => $milestone->id,
                                'assigned_to_user_id' => $item['assigned_to_user_id'] ?? null,
            ];

            // Soft-validate task status using the value dictionary (non-enforcing)
            app(\App\Services\ValueSetValidator::class)->validate('Task','status', \App\Enums\TaskStatus::ToDo);

            $task = \App\Models\Task::create($taskData);
            $task->load(['assignedTo', 'taskType', 'milestone']);
            $created[] = $task;
        }

        return response()->json(['tasks' => $created, 'milestone_id' => $milestone->id, 'project_id' => $project->id], 201);
    }


    /**
     * Toggle or set privacy flag on an email.
     * Authorized for users who can delete emails (delete_emails permission).
     */
    public function togglePrivacy(Request $request, Email $email)
    {
        // Use delete permission as the control for privacy per requirement
        $this->authorize('delete', $email);

        $validated = $request->validate([
            'is_private' => 'sometimes|boolean',
        ]);

        if (array_key_exists('is_private', $validated)) {
            $email->is_private = (bool) $validated['is_private'];
        } else {
            $email->is_private = !((bool) $email->is_private);
        }

        $email->save();

        return response()->json($email->fresh()->load(['sender', 'conversation.project', 'approver']));
    }

}
