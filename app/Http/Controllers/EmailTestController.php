<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Conversation;
use App\Models\Email;
use App\Models\File;
use App\Models\Project;
use App\Services\GmailService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use App\Http\Controllers\Api\Concerns\HandlesImageUploads;

class EmailTestController extends Controller
{
    use HandlesImageUploads;

    protected GmailService $gmailService;

    public function __construct(GmailService $gmailService)
    {
        $this->gmailService = $gmailService;
    }

    protected function extractEmailAddress(string $formattedEmail): string
    {
        if (preg_match('/<([^>]+)>/', $formattedEmail, $matches)) {
            return trim($matches[1]);
        }
        return trim($formattedEmail);
    }

    public function sendTestEmail()
    {
        $to = 'zeemsabri@gmail.com';
        $subject = 'Laravel Gmail MVP Test Email - ' . now()->format('Y-m-d H:i:s');
        $body = '
            <h1>Hello from your Laravel Gmail API MVP!</h1>
            <p>This is a test email sent using the Gmail API.</p>
            <p>Sent from: <strong>' . $this->gmailService->getAuthorizedEmail() . '</strong></p>
            <p>Time: ' . now()->format('Y-m-d H:i:s') . '</p>
            <hr>
            <p>You can reply to this email to test the receiving functionality.</p>
        ';

        try {
            $messageId = $this->gmailService->sendEmail($to, $subject, $body);
            return response()->json([
                'message' => 'Test email sent successfully!',
                'gmail_message_id' => $messageId,
                'sent_from' => $this->gmailService->getAuthorizedEmail(),
                'sent_to'   =>  $to
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending test email:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to send test email: ' . $e->getMessage()], 500);
        }
    }

    public function receiveTestEmails()
    {
//        try {
            $lastReceivedEmail = Email::where('type', 'received')
                ->orderByDesc('sent_at')
                ->first();

            Log::info('Last received email:', ['email_id' => $lastReceivedEmail->id ?? 'none', 'sent_at' => $lastReceivedEmail->sent_at ?? 'none']);

            $query = 'is:inbox has:attachment';
            if ($lastReceivedEmail) {
                $afterDate = Carbon::parse($lastReceivedEmail->sent_at)
                    ->subMinutes(5)
                    ->unix();
                $query .= " after:{$afterDate}";
                Log::info('Fetching emails with query:', ['query' => $query]);
            } else {
                Log::info('No previous received emails found. Fetching initial batch (e.g., last 50).');
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
                    Log::info("Skipping already processed email with ID: {$messageId} and Date: {$emailDetails['date']}");
                    continue;
                }

                if (Email::where('message_id', $emailDetails['id'])->exists()) {
                    Log::info('Email with message_id ' . $emailDetails['id'] . ' already exists in the database. Skipping.');
                    continue;
                }

                $fromEmail = $this->extractEmailAddress($emailDetails['from']);
                $client = Client::whereRaw('LOWER(email) = ?', [strtolower($fromEmail)])->first();
                if (!$client) {
                    Log::warning('No client found for email: ' . $fromEmail . ' (original: ' . $emailDetails['from'] . ')');
                    continue;
                }

                $conversation = Conversation::where('client_id', $client->id)
                    ->where('subject', $emailDetails['subject'])
                    ->first();

                if (!$conversation) {
                    $project = Project::whereHas('clients', function($q) use($client) {
                        $q->where('id', $client->id);
                    })->first();

                    if (!$project) {
                        Log::warning('No project found for client: ' . $client->id . ' for email subject: ' . $emailDetails['subject']);
                        continue;
                    }

                    $conversation = Conversation::create([
                        'subject' => $emailDetails['subject'],
                        'project_id' => $project->id,
                        'client_id' => $client->id,
                        'last_activity_at' => Carbon::parse($emailDetails['date']),
                    ]);
                    Log::info('New Conversation Created:', ['conversation_id' => $conversation->id, 'subject' => $emailDetails['subject']]);
                }

                $email = Email::create([
                    'conversation_id' => $conversation->id,
                    'sender_type'   =>  Client::class,
                    'sender_id' => $client->id,
                    'to' => json_encode([$authorizedGmailAccount]),
                    'subject' => $emailDetails['subject'],
                    'body' => $emailDetails['body']['html'] ?: $emailDetails['body']['plain'],
                    'type'  =>  'received',
                    'status' => 'pending_approval_received',
                    'message_id' => $emailDetails['id'],
                    'sent_at' => Carbon::parse($emailDetails['date']),
                ]);

                $uploadedFiles = [];
                // Process each attachment from the Gmail API response
                foreach ($emailDetails['attachments'] as $attachment) {
                    $tempPath = tempnam(sys_get_temp_dir(), 'email_attachment_');
                    file_put_contents($tempPath, $attachment['data']);

                    $uploadedFile = new UploadedFile(
                        $tempPath,
                        $attachment['filename'],
                        $attachment['mimeType'],
                        null,
                        true
                    );

                    $uploadedFiles[] = $uploadedFile;
                }

                $paths = $this->uploadFilesToGcsWithThumbnails($uploadedFiles);

                foreach ($paths as $uploadedFile) {
                    $email->files()->create($uploadedFile);
                }

                if ($conversation->last_activity_at === null || Carbon::parse($emailDetails['date'])->gt($conversation->last_activity_at)) {
                    $conversation->update(['last_activity_at' => Carbon::parse($emailDetails['date'])]);
                }

                $receivedEmailSummaries[] = [
                    'id' => $email->id,
                    'gmail_message_id' => $emailDetails['id'],
                    'from' => $emailDetails['from'],
                    'subject' => $emailDetails['subject'],
                    'date' => $emailDetails['date'],
                ];

                Log::info('Received Email Stored:', ['email_id' => $email->id, 'gmail_id' => $emailDetails['id'], 'subject' => $emailDetails['subject']]);
            }

            dd('all done without error');

            return response()->json([
                'message' => 'Successfully fetched and stored new emails.',
                'summary_of_emails' => $receivedEmailSummaries,
                'count_fetched' => count($receivedEmailSummaries),
                'note' => 'Emails stored with status pending_approval_received. Check storage/logs/laravel.log for details.'
            ]);



//        } catch (\Exception $e) {
//            Log::error('Error receiving emails:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
//            return response()->json(['message' => 'Failed to receive emails: ' . $e->getMessage()], 500);
//        }
    }
}
