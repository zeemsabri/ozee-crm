<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Conversation;
use App\Models\Email;
use App\Models\Project;
use App\Services\GmailService;
use Carbon\Carbon;
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
    /**
     * Fetches and stores new emails from Gmail since the last received email.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function receiveTestEmails()
    {
        try {
            // 1. Get the date of the last received email from your database
            $lastReceivedEmail = Email::where('type', 'received')
                ->orderByDesc('sent_at')
                ->first();

            Log::info('Last received email:', ['email_id' => $lastReceivedEmail->id ?? 'none', 'sent_at' => $lastReceivedEmail->sent_at ?? 'none']);;

            $query = 'is:inbox';
            if ($lastReceivedEmail) {
                // Convert the sent_at timestamp to a Gmail-compatible date format (e.g., Y/m/d H:i:s or Y/m/d)
                // Gmail's 'after' operator usually expects YYYY/MM/DD.
                // To be safe and avoid missing emails due to time differences, fetch from a bit before the last email.
                // Let's go back 1 minute to account for potential slight discrepancies or network delays.
                $afterDate = Carbon::parse($lastReceivedEmail->sent_at)
                    ->subMinutes(5) // Added more buffer
                    ->unix();
                $query .= " after:{$afterDate}";
                Log::info('Fetching emails with query:', ['query' => $query]);
            } else {
                // If no previous emails, fetch a reasonable number (e.g., last 50) to start.
                // You might want to adjust this initial fetch limit based on expected volume.
                Log::info('No previous received emails found. Fetching initial batch (e.g., last 50).');
                $query .= ' newer_than:30d'; // Fetch emails from the last 30 days if no last email found
            }

            // 2. Fetch messages from Gmail using the generated query
            // You might need to adjust your GmailService's listMessages to accept a query string directly
            // and potentially a limit if you want to cap the number of emails fetched in one go (e.g., 200)
            // if you expect a very large backlog, to avoid hitting memory limits or API rate limits.
            $messageIds = $this->gmailService->listMessages(50, $query); // Pass null for limit to fetch all matching

            if (empty($messageIds)) {
                return response()->json(['message' => 'No new messages found in the inbox since last fetch.']);
            }

            $receivedEmailSummaries = [];
            $authorizedGmailAccount = $this->gmailService->getAuthorizedEmail(); // Get the email address of the authenticated Gmail account

            // Sort message IDs by date to process older emails first, if Gmail's API doesn't guarantee order.
            // This is important for "last received email" logic to work correctly when storing.
            // Gmail API usually returns messages in reverse chronological order (newest first),
            // but it's good practice to ensure. If you fetch by 'after' date, it should be fine.
            // If you get full message details first, you can sort them by 'date' before processing.

            foreach ($messageIds as $messageId) {
                $emailDetails = $this->gmailService->getMessage($messageId);

                $date = Carbon::parse($emailDetails['date'])->setTimezone('UTC');

                $emailDetails['date'] = $date;
                // IMPORTANT: Check if the email is *actually* newer than the last processed email.
                // Gmail's 'after' query is based on the internal date, but your `sent_at` might be slightly different
                // or you might have fetched an email from the same minute. Avoid re-processing.
                if ($lastReceivedEmail && Carbon::parse($emailDetails['date'])->lte(Carbon::parse($lastReceivedEmail->sent_at))) {
                    Log::info("Skipping already processed email with ID: {$messageId} and Date: {$emailDetails['date']}");
                    continue;
                }

                // Check if this email (based on message_id) already exists in your database
                // This is crucial to prevent duplicate entries if the script runs multiple times or if
                // an email somehow gets included in a subsequent 'after' query due to date precision issues.
                if (Email::where('message_id', $emailDetails['id'])->exists()) {
                    Log::info('Email with message_id ' . $emailDetails['id'] . ' already exists in the database. Skipping.');
                    continue;
                }

                // Extract email address from the formatted 'from' field
                $fromEmail = $this->extractEmailAddress($emailDetails['from']);

                // Find the client by email address (case-insensitive)
                $client = Client::whereRaw('LOWER(email) = ?', [strtolower($fromEmail)])->first();
                if (!$client) {
                    Log::warning('No client found for email: ' . $fromEmail . ' (original: ' . $emailDetails['from'] . ')');
                    // Optionally, you could store these as "unassigned" or "pending" emails for manual review
                    continue; // Skip if no matching client
                }

                // Find or create a conversation
                // Improved conversation finding: Try to match by subject AND client_id.
                // For existing conversations, consider using Gmail's `threadId` if available in `emailDetails`.
                // This is a more robust way to group related emails than just subject.
                $conversation = Conversation::where('client_id', $client->id)
                    ->where('subject', $emailDetails['subject']) // Exact subject match is often too strict, use `like` if you expect variations
                    ->first();

                // If `threadId` is available in $emailDetails, prioritize it for conversation matching
                // You would need to add a `gmail_thread_id` column to your `conversations` table.
                /*
                if (isset($emailDetails['threadId'])) {
                    $conversation = Conversation::where('client_id', $client->id)
                                                ->where('gmail_thread_id', $emailDetails['threadId'])
                                                ->first();
                }
                */

                if (!$conversation) {
                    // If no conversation found by exact subject and client, try a broader search or create new.
                    // You might want to add more sophisticated logic here, e.g., if subject is "Re: Original Subject",
                    // try to find "Original Subject".

                    // Find a project associated with this client
                    $project = Project::whereHas('clients', function($q) use($client) {
                        $q->where('id', $client->id);
                    })->first(); // Consider if a client can have multiple projects and how to pick

                    if (!$project) {
                        Log::warning('No project found for client: ' . $client->id . ' for email subject: ' . $emailDetails['subject']);
                        continue; // Skip if no project
                    }

                    $conversation = Conversation::create([
                        'subject' => $emailDetails['subject'],
                        'project_id' => $project->id,
                        'client_id' => $client->id,
                        'last_activity_at' => Carbon::parse($emailDetails['date']),
                    ]);
                    Log::info('New Conversation Created:', ['conversation_id' => $conversation->id, 'subject' => $emailDetails['subject']]);
                }

                // Create email record
                $email = Email::create([
                    'conversation_id' => $conversation->id,
                    'sender_type'   =>  Client::class,
                    'sender_id' => $client->id, // No internal sender for received emails (sender is the external client)
                    'to' => json_encode([$authorizedGmailAccount]), // The recipient of the email is your authorized Gmail account
                    'subject' => $emailDetails['subject'],
                    'body' => $emailDetails['body'],
                    'type'  =>  'received',
                    'status' => 'pending_approval_received',
                    'message_id' => $emailDetails['id'], // Gmail's unique message ID
                    'sent_at' => Carbon::parse($emailDetails['date']), // Use Carbon to ensure correct datetime object
                ]);

                // Update conversation's last activity to the most recent email in that conversation
                // This ensures `last_activity_at` always reflects the latest email in the conversation
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

            return response()->json([
                'message' => 'Successfully fetched and stored new emails.',
                'summary_of_emails' => $receivedEmailSummaries,
                'count_fetched' => count($receivedEmailSummaries),
                'note' => 'Emails stored with status pending_approval_received. Check storage/logs/laravel.log for details.'
            ]);

        } catch (\Exception $e) {
            Log::error('Error receiving emails:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to receive emails: ' . $e->getMessage()], 500);
        }
    }


}
