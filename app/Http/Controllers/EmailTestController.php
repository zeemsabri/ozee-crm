<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Conversation;
use App\Models\Email;
use App\Models\Project;
use App\Services\GmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailTestController extends Controller
{
    protected GmailService $gmailService;

    // The GmailService will be automatically injected by Laravel's service container.
    public function __construct(GmailService $gmailService)
    {
        $this->gmailService = $gmailService;
    }

    /**
     * Extract email address from a formatted email string.
     *
     * @param string $formattedEmail Formatted email like "Name" <email@example.com>
     * @return string The extracted email address
     */
    protected function extractEmailAddress(string $formattedEmail): string
    {
        // Check if the email contains angle brackets (indicating a formatted email)
        if (preg_match('/<([^>]+)>/', $formattedEmail, $matches)) {
            return trim($matches[1]);
        }

        // If no angle brackets found, return the original string (assuming it's just an email)
        return trim($formattedEmail);
    }

    /**
     * Sends a test email using the GmailService.
     * Access this via your browser: http://localhost:8000/send-test-email
     * CHANGE 'YOUR_TEST_RECIPIENT_EMAIL@example.com' to a real email you can check.
     * Ensure the 'From' email in the sent mail matches the authorized Google Workspace account.
     */
    public function sendTestEmail()
    {
        // IMPORTANT: Change this to a real email address you can check!
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

    /**
     * Fetches the latest emails from the inbox, stores them in the database, and logs details.
     * Access via: http://localhost:8000/receive-test-emails
     */
    public function receiveTestEmails()
    {
        try {
            // Fetch the latest 5 messages from the inbox
            $messageIds = $this->gmailService->listMessages(5, 'is:inbox');

            if (empty($messageIds)) {
                return response()->json(['message' => 'No messages found in the inbox.']);
            }

            $receivedEmailSummaries = [];
            foreach ($messageIds as $messageId) {
                $emailDetails = $this->gmailService->getMessage($messageId);

                // Extract email address from the formatted 'from' field
                $fromEmail = $this->extractEmailAddress($emailDetails['from']);

                // Find the client by email address (case-insensitive)
                $client = Client::whereRaw('LOWER(email) = ?', [strtolower($fromEmail)])->first();
                if (!$client) {
                    Log::warning('No client found for email: ' . $fromEmail . ' (original: ' . $emailDetails['from'] . ')');
                    continue; // Skip if no matching client
                }

                // Find or create a conversation
                $conversation = Conversation::where('client_id', $client->id)
                    ->whereHas('emails', function ($query) use ($emailDetails) {
                        // Match by subject or thread (simplified for MVP)
                        $query->where('subject', 'like', '%' . $emailDetails['subject'] . '%');
                    })
                    ->first();

                if (!$conversation) {
                    // Find a project associated with this client
                    $project = Project::where('client_id', $client->id)->first();
                    if (!$project) {
                        Log::warning('No project found for client: ' . $client->id);
                        continue; // Skip if no project
                    }

                    $conversation = Conversation::create([
                        'subject' => $emailDetails['subject'],
                        'project_id' => $project->id,
                        'client_id' => $client->id,
                        'contractor_id' => $project->users()->where('users.role', 'contractor')->first()->id ?? null,
                        'last_activity_at' => now(),
                    ]);
                }

                // Create email record
                $email = Email::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => null, // No internal sender for received emails
                    'to' => json_encode([$this->gmailService->getAuthorizedEmail()]),
                    'subject' => $emailDetails['subject'],
                    'body' => $emailDetails['body'],
                    'status' => 'pending_approval_received',
                    'message_id' => $emailDetails['id'],
                    'sent_at' => $emailDetails['date'],
                ]);

                $conversation->update(['last_activity_at' => now()]);

                $receivedEmailSummaries[] = [
                    'id' => $email->id,
                    'gmail_message_id' => $emailDetails['id'],
                    'from' => $emailDetails['from'],
                    'subject' => $emailDetails['subject'],
                    'date' => $emailDetails['date'],
                ];

                Log::info('Received Email Stored:', $emailDetails);
            }

            return response()->json([
                'message' => 'Successfully fetched and stored emails.',
                'summary_of_emails' => $receivedEmailSummaries,
                'note' => 'Emails stored with status pending_approval_received. Check storage/logs/laravel.log for details.'
            ]);

        } catch (\Exception $e) {
            Log::error('Error receiving test emails:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to receive emails: ' . $e->getMessage()], 500);
        }
    }
}
