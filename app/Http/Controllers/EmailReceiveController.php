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
                ->withTrashed()
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

//                if ($lastReceivedEmail && Carbon::parse($emailDetails['date'])->lte(Carbon::parse($lastReceivedEmail->sent_at))) {
//                    Log::info('Skipping email with date ' . $emailDetails['date'] . ' ' . $messageId . ' because it is older than the last received email.');
//                    continue;
//                }

                if (Email::where('message_id', $emailDetails['id'])->exists()) {
                    Log::info('Skipping email with message ID ' . $emailDetails['id'] . ' because it has already been received.');
                    continue;
                }

                $fromEmail = $this->extractEmailAddress($emailDetails['from']);
                $client = Client::whereRaw('LOWER(email) = ?', [strtolower($fromEmail)])->first();
                $lead = Lead::whereRaw('LOWER(email) = ?', [strtolower($fromEmail)])->first();

//                if (!$client && !$lead) {
//                    continue;
//                }


                $processed = false;
                $result = [];
                if ($client) {
                    // Try to find a project with this client
                    $project = Project::whereHas('clients', function($q) use($client) {
                        $q->where('id', $client->id);
                    })->first();

                    if ($project) {
                        $result = $this->handleClientEmailWithProject($client, $project, $emailDetails, $authorizedGmailAccount);
                        $processed = true;
                    }
                    else {
                        // Accept client email without project using null project_id
                        $result = $this->handleEmailWithoutProjectOrLead($client, null, $emailDetails, $authorizedGmailAccount);
                        $processed = true;
                    }


                }
                elseif ($lead) {
                    // Lead-based conversation without a project (or future: with default Leads project)
                    $result = $this->handleEmailWithoutProjectOrLead(null, $lead, $emailDetails, $authorizedGmailAccount);
                    $processed = true;


                }
                else {
                    Log::info('Skipping email with from ' . $emailDetails['from'] . ' because it is not a client or lead.');;
                    $result = $this->handleUnknownEmail($emailDetails, $fromEmail, $authorizedGmailAccount);

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
            Log::error($e->getMessage());
            return response()->json(['message' => 'Failed to receive emails: ' . $e->getMessage()], 500);
        }
    }

    private function handleUnknownEmail(array $emailDetails, string $from, string $authorizedGmailAccount): array
    {

        Log::info('Received unknown email from ' . $emailDetails['from']);

//        $client =   Client::createOrFirst(
//            ['email'  =>  $from ?? null],
//            ['name' => $emailDetails['name'] ?? $from ?? null]
//        );

//        $options = Client::availableCategoryOptions('other');
//        $client->syncCategories(collect($options)->pluck('value')->toArray());

        $conversation = Conversation::createOrFirst(
            ['subject' => $emailDetails['subject']],
            [
                'project_id' => null,
                'last_activity_at' => now()
            ]
        );

        $email = $this->createEmail(
            conversation: $conversation,
            emailDetails: $emailDetails,
            authorizedGmailAccount: $authorizedGmailAccount,
            body: $emailDetails['body']['plain'] ?: $emailDetails['body']['html'],
            status: EmailStatus::Unknown);

        return [$conversation, $email];


    }

    private function handleClientEmailWithProject(Client $client, Project $project, array $emailDetails, string $authorizedGmailAccount): array
    {

        $conversation = $this->createConversation($emailDetails, $client, $project);

        $body = $emailDetails['body']['plain'] ?: $emailDetails['body']['html'];

        $email = Email::create([
            'conversation_id' => $conversation->id,
            'sender_type'   =>  Client::class,
            'sender_id' => $client->id,
            'to' => [$authorizedGmailAccount],
            'subject' => $emailDetails['subject'],
            'body' => $this->cleanEmailBody($body),
            'template_data' =>  strip_tags($body),
            'type'  =>  'received',
            'status' => EmailStatus::Draft,
            'message_id' => $emailDetails['id'],
            'sent_at' => Carbon::parse($emailDetails['date']),
        ]);

        $this->attachEmailAttachments($email, $emailDetails['attachments'] ?? []);

        return [$conversation, $email];
    }

    private function createConversation($emailDetails, Client|Lead $conversable, Project|null $project = null)
    {
        // Find conversation by subject + client conversable + project
        $conversation = Conversation::where('subject', $emailDetails['subject'])
            ->where('project_id', $project?->id)
            ->where('conversable_type', get_class($conversable))
            ->where('conversable_id', $conversable->id)
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'subject' => $emailDetails['subject'],
                'project_id' => $project?->id,
                'conversable_type' => get_class($conversable),
                'conversable_id' => $conversable->id,
                'last_activity_at' => Carbon::parse($emailDetails['date']),
            ]);
        }
        return $conversation;
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
        if($client) {
            $conversableClass = new Client();
            $conversable = $client;
            $conversableId = $conversable ? $conversable->id : null;
        }
        else {
            $conversableClass = new Lead();
            $conversable = $lead;
            $conversableId = $lead ? $lead->id : null;
        }

        $conversableClass->id = $conversableId;

        $conversableType = get_class($conversable);

        $conversation = $this->createConversation($emailDetails, $conversableClass);

        $body = $emailDetails['body']['plain'] ?: $emailDetails['body']['html'];

        $email = $this->createEmail(
            conversation: $conversation,
            emailDetails: $emailDetails,
            authorizedGmailAccount: $authorizedGmailAccount,
            body: $body,
            conversableType: $conversableType,
            conversableId: $conversableId);

        $this->attachEmailAttachments($email, $emailDetails['attachments'] ?? []);

//        $this->leadReplyHandlerService->handleIncomingReply($lead, $email);

//        if($content) {
//            $this->emailProcessingService->createContextForEmail($email, $content);
//        }

        return [$conversation, $email];
    }

    private function createEmail(
        Conversation $conversation,
        array $emailDetails, string $authorizedGmailAccount, string $body,
        EmailStatus $status = null,
        string $conversableType = null, int $conversableId = null,
    )
    {
        $body = $this->cleanEmailBody($body);
        $rawData = [
            'from'  =>  $emailDetails['from'] ?? null,
            'to'    =>  $emailDetails['to'] ?? null,
            'subject'   =>  $emailDetails['subject'] ?? null,
            'body'  =>  $body,
        ];
        $email = Email::create([
            'conversation_id' => $conversation?->id,
            'sender_type'   =>  $conversableType ?? null,
            'sender_id' => $conversableId ?? null,
            'to' => [$authorizedGmailAccount],
            'subject' => $emailDetails['subject'],
            'body' => $body,
            'type'  =>  EmailType::Received,
            'status' => $status ?? EmailStatus::Draft,
            'template_data' =>  json_encode($rawData),
            'is_private'    =>   true,
            'message_id' => $emailDetails['id'],
            'sent_at' => Carbon::parse($emailDetails['date']),
        ]);

        return $email;
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
