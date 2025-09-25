<?php

namespace App\Http\Controllers;

use App\Enums\EmailStatus;
use App\Enums\EmailType;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Email;
use App\Models\File;
use App\Models\Lead;
use App\Models\Project;
use App\Services\EmailAiAnalysisService;
use App\Services\EmailProcessingService;
use App\Services\GmailService;
use App\Services\LeadReplyHandlerService;
use App\Services\WorkflowEngineService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use App\Http\Controllers\Api\Concerns\HandlesImageUploads;

class EmailReceiveController extends Controller
{
    use HandlesImageUploads;

    protected GmailService $gmailService;
    protected EmailAiAnalysisService $emailAiAnalysisService;

    protected EmailProcessingService $emailProcessingService;
    protected LeadReplyHandlerService $leadReplyHandlerService;
    private $workflowEngineService;

    public function __construct(
        GmailService $gmailService,
        EmailAiAnalysisService $emailAiAnalysisService,
        EmailProcessingService $emailProcessingService,
        LeadReplyHandlerService $leadReplyHandlerService,
        WorkflowEngineService $workflowEngineService
    )
    {
        $this->gmailService = $gmailService;
        $this->emailAiAnalysisService = $emailAiAnalysisService;
        $this->emailProcessingService = $emailProcessingService;
        $this->leadReplyHandlerService = $leadReplyHandlerService;
        $this->workflowEngineService = $workflowEngineService;
    }

    protected function extractEmailAddress(string $formattedEmail): string
    {
        if (preg_match('/<([^>]+)>/', $formattedEmail, $matches)) {
            return trim($matches[1]);
        }
        return trim($formattedEmail);
    }

    public function receiveEmails()
    {
        try {
            $lastReceivedEmail = Email::where('type', 'received')
                ->orderByDesc('sent_at')
                ->first();

            $query = 'is:inbox';
            if ($lastReceivedEmail) {
                $afterDate = Carbon::parse($lastReceivedEmail->sent_at)
                    ->subMinutes(5)
                    ->unix();
                $query .= " after:{$afterDate}";
            } else {
                $query .= ' newer_than:30d';
            }

            $messageIds = $this->gmailService->listMessages(50, $query);

            if (empty($messageIds)) {
                return response()->json(['message' => 'No new messages found in the inbox since last fetch.']);
            }

            $receivedEmailSummaries = [];
            $authorizedGmailAccount = $this->gmailService->getAuthorizedEmail();

            foreach ($messageIds as $messageId) {
                $emailDetails = $this->gmailService->getMessage($messageId);

                $date = Carbon::parse($emailDetails['date'])->setTimezone('UTC');
                $emailDetails['date'] = $date;

                if ($lastReceivedEmail && Carbon::parse($emailDetails['date'])->lte(Carbon::parse($lastReceivedEmail->sent_at))) {
                    continue;
                }

                if (Email::where('message_id', $emailDetails['id'])->exists()) {
                    continue;
                }

                $fromEmail = $this->extractEmailAddress($emailDetails['from']);
                $client = Client::whereRaw('LOWER(email) = ?', [strtolower($fromEmail)])->first();
                $lead = Lead::whereRaw('LOWER(email) = ?', [strtolower($fromEmail)])->first();

                if (!$client && !$lead) {
                    continue;
                }

                $processed = false;
                if ($client) {
                    // Try to find a project with this client
                    $project = Project::whereHas('clients', function($q) use($client) {
                        $q->where('id', $client->id);
                    })->first();

                    if ($project) {
                        $result = $this->handleClientEmailWithProject($client, $project, $emailDetails, $authorizedGmailAccount);
                        $processed = true;
                    } else {
                        // Accept client email without project using null project_id
                        $result = $this->handleEmailWithoutProjectOrLead($client, null, $emailDetails, $authorizedGmailAccount);
                        $processed = true;
                    }
                } elseif ($lead) {
                    // Lead-based conversation without a project (or future: with default Leads project)
                    $result = $this->handleEmailWithoutProjectOrLead(null, $lead, $emailDetails, $authorizedGmailAccount);
                    $processed = true;
                }

                if (!empty($result)) {
                    [$conversation, $email] = $result;

                    if ($conversation->last_activity_at === null || Carbon::parse($emailDetails['date'])->gt($conversation->last_activity_at)) {
                        $conversation->update(['last_activity_at' => Carbon::parse($emailDetails['date'])]);
                    }

                    $receivedEmailSummaries[] = [
                        'id' => $email->id,
                        'gmail_message_id' => $emailDetails['id'],
                        'from' => $emailDetails['from'],
                        'subject' => $emailDetails['subject'],
                        'date' => $emailDetails['date'],
                        'status'    =>  $emailDetails['status'] ?? null,
                    ];

                }
            }


            return response()->json([
                'message' => 'Successfully fetched and stored new emails.',
                'summary_of_emails' => $receivedEmailSummaries,
                'count_fetched' => count($receivedEmailSummaries),
                'note' => 'Emails stored with status pending_approval_received. Check storage/logs/laravel.log for details.'
            ]);



        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to receive emails: ' . $e->getMessage()], 500);
        }
    }

    private function handleClientEmailWithProject(Client $client, Project $project, array $emailDetails, string $authorizedGmailAccount): array
    {
        // Find conversation by subject + client conversable + project
        $conversation = Conversation::where('subject', $emailDetails['subject'])
            ->where('project_id', $project->id)
            ->where('conversable_type', Client::class)
            ->where('conversable_id', $client->id)
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'subject' => $emailDetails['subject'],
                'project_id' => $project->id,
                'conversable_type' => Client::class,
                'conversable_id' => $client->id,
                'last_activity_at' => Carbon::parse($emailDetails['date']),
            ]);
        }

        $body = $emailDetails['body']['plain'] ?: $emailDetails['body']['html'];

        $email = Email::create([
            'conversation_id' => $conversation->id,
            'sender_type'   =>  Client::class,
            'sender_id' => $client->id,
            'to' => [$authorizedGmailAccount],
            'subject' => $emailDetails['subject'],
//            'body' => $this->cleanEmailBody($body),
            'body' => $body,
            'template_data' =>  strip_tags($body),
            'type'  =>  'received',
            'status' => EmailStatus::Draft,
            'message_id' => $emailDetails['id'],
            'sent_at' => Carbon::parse($emailDetails['date']),
        ]);

        $this->attachEmailAttachments($email, $emailDetails['attachments'] ?? []);

        // ** TRIGGER AI ANALYSIS **
//        $content = $this->emailAiAnalysisService->analyzeAndSummarize($email);
//        $context = [
//            'email' => $email,
//            'client' => $client,
//            'project' => $project ?? null
//        ];
//        $this->workflowEngineService->trigger('email.received', $context);

//        if($content) {
//            $this->emailProcessingService->createContextForEmail($email, $content);
//        }


        return [$conversation, $email];
    }

    private function cleanEmailBody($html)
    {
        $sourceWithNewlines = preg_replace('/<br\s*\/?>/i', "\n", $html);

        $sourceWithNewlines = preg_replace('/(<\/p>|<\/div>|<\/li>|<\/h[1-6]>)/i', "\n\n", $sourceWithNewlines);

        $stripped = strip_tags($sourceWithNewlines);

        // 4. Decode HTML entities and trim whitespace.
        $decoded = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($decoded);

    }

    private function handleEmailWithoutProjectOrLead(?Client $client, ?Lead $lead, array $emailDetails, string $authorizedGmailAccount): array
    {
        // Determine conversable
        $conversableType = $client ? Client::class : Lead::class;
        $conversableId = $client ? $client->id : ($lead ? $lead->id : null);

        // If still none, bail (shouldn't happen because caller checks)
//        if (!$conversableId) {
//            return [];
//        }

        $conversation = Conversation::where('subject', $emailDetails['subject'])
            ->whereNull('project_id')
            ->where('conversable_type', $conversableType)
            ->where('conversable_id', $conversableId)
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'subject' => $emailDetails['subject'],
                'project_id' => null, // explicit null project
                'conversable_type' => $conversableType,
                'conversable_id' => $conversableId,
                'last_activity_at' => Carbon::parse($emailDetails['date']),
            ]);
        }

        $email = Email::create([
            'conversation_id' => $conversation->id,
            'sender_type'   =>  $conversableType,
            'sender_id' => $conversableId,
            'to' => [$authorizedGmailAccount],
            'subject' => $emailDetails['subject'],
            'body' => $emailDetails['body']['plain'] ?: $emailDetails['body']['html'],
            'type'  =>  EmailType::Received,
            'status' => EmailStatus::Received,
            'is_private'    =>   true,
            'message_id' => $emailDetails['id'],
            'sent_at' => Carbon::parse($emailDetails['date']),
        ]);

        $this->attachEmailAttachments($email, $emailDetails['attachments'] ?? []);

        $this->leadReplyHandlerService->handleIncomingReply($lead, $email);

//        if($content) {
//            $this->emailProcessingService->createContextForEmail($email, $content);
//        }

        return [$conversation, $email];
    }

    private function attachEmailAttachments(Email $email, array $attachments): void
    {
        if (empty($attachments)) return;

        $uploadedFiles = [];
        foreach ($attachments as $attachment) {
            $tempPath = tempnam(sys_get_temp_dir(), 'email_attachment_');
            file_put_contents($tempPath, $attachment['data']);

            $uploadedFile = new UploadedFile(
                $tempPath,
                $attachment['filename'],
                $attachment['mimeType'] ?? null,
                null,
                true
            );

            $uploadedFiles[] = $uploadedFile;
        }

        $paths = $this->uploadFilesToGcsWithThumbnails($uploadedFiles);
        foreach ($paths as $uploadedFile) {
            $email->files()->create($uploadedFile);
        }
    }

}
