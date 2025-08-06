<?php

namespace App\Services;

use App\Traits\GoogleApiAuthTrait;
use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class GmailService
{

    use GoogleApiAuthTrait;

    /**
     * Sends an email via Gmail API.
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body Email body (HTML or plain text)
     * @return string Message ID of the sent email
     * @throws Exception If email sending fails.
     */
    public function sendEmail(string $to, string $subject, string $body): string
    {
        $service = new Gmail($this->client);

        // Construct the raw email message in RFC 2822 format.
        // This format is required by the Gmail API for sending raw messages.
        $rawMessage = "To: $to\r\n";
        $rawMessage .= "From: " . $this->getAuthorizedEmail() . "\r\n";
        $rawMessage .= "Subject: =?utf-8?B?" . base64_encode($subject) . "?=\r\n"; // Properly encode subject for UTF-8
        $rawMessage .= "MIME-Version: 1.0\r\n";
        $rawMessage .= "Content-type: text/html; charset=utf-8\r\n"; // Assume HTML content for now
        $rawMessage .= "Content-Transfer-Encoding: base64\r\n";
        $rawMessage .= "\r\n" . chunk_split(base64_encode($body)); // Base64 encode the body and chunk it

        $message = new Message();
        // The raw message must be URL-safe base64 encoded.
        $message->setRaw(strtr(base64_encode($rawMessage), ['+' => '-', '/' => '_']));

        try {
            // 'me' refers to the authenticated user's mailbox (the one linked via OAuth)
            $sentMessage = $service->users_messages->send('me', $message);
            Log::info('Email sent successfully via Gmail API', ['to' => $to, 'subject' => $subject, 'message_id' => $sentMessage->getId()]);
            return $sentMessage->getId();
        } catch (Exception $e) {
            Log::error('Failed to send email via Gmail API: ' . $e->getMessage(), ['to' => $to, 'subject' => $subject, 'error' => $e->getTraceAsString()]);
            throw new Exception('Failed to send email: ' . $e->getMessage());
        }
    }

    /**
     * Lists email message IDs from the authenticated user's mailbox.
     *
     * @param int $maxResults Maximum number of messages to retrieve.
     * @param string $query Gmail search query string (e.g., 'is:inbox', 'from:someone@example.com').
     * @return array An array of message IDs.
     */
    public function listMessages(int $maxResults = 10, string $query = 'is:inbox'): array
    {
        $service = new Gmail($this->client);
        $messageIds = [];
        try {
            // 'me' refers to the authenticated user's mailbox
            $response = $service->users_messages->listUsersMessages('me', [
                'maxResults' => $maxResults,
                'q' => $query,
            ]);

            if ($response->getMessages()) {
                foreach ($response->getMessages() as $message) {
                    $messageIds[] = $message->getId();
                }
            }
            Log::info('Listed Gmail messages', ['count' => count($messageIds), 'query' => $query]);
            return $messageIds;
        } catch (Exception $e) {
            Log::error('Failed to list Gmail messages: ' . $e->getMessage(), ['query' => $query, 'exception' => $e]);
            throw new Exception('Failed to list messages: ' . $e->getMessage());
        }
    }

    /**
     * Retrieves the full content of a specific email message.
     *
     * @param string $messageId The ID of the message to retrieve.
     * @return array Decoded email parts (from, to, subject, body, headers).
     * @throws Exception If message retrieval or parsing fails.
     */
    public function getMessage(string $messageId): array
    {
        $service = new Gmail($this->client);
        try {
            // 'format' => 'full' fetches all headers and body parts.
            $message = $service->users_messages->get('me', $messageId, ['format' => 'full']);

            $payload = $message->getPayload();
            $headers = $payload->getHeaders();
            $body = '';

            // Extract headers into an associative array for easier access
            $parsedHeaders = [];
            foreach ($headers as $header) {
                $parsedHeaders[strtolower($header->getName())] = $header->getValue();
            }

            // Function to decode base64url data
            $decodeBase64Url = function($data) {
                return base64_decode(strtr($data, ['-' => '+', '_' => '/']));
            };

            // Find the message body (prioritize HTML over plain text)
            if ($payload->getParts()) {
                foreach ($payload->getParts() as $part) {
                    // Look for the main HTML or Plain text part first
                    if ($part->getMimeType() == 'text/html' || $part->getMimeType() == 'text/plain') {
                        $body = $decodeBase64Url($part->getBody()->getData());
                        // If HTML is found, we can stop here for simple cases
                        if ($part->getMimeType() == 'text/html') break;
                    }
                }
            } else {
                // If there are no parts (simple message), body is directly in payload
                $body = $decodeBase64Url($payload->getBody()->getData());
            }

            Log::info('Fetched Gmail message details', ['message_id' => $messageId, 'subject' => $parsedHeaders['subject'] ?? 'N/A']);

            return [
                'id' => $message->getId(),
                'threadId' => $message->getThreadId(),
                'from' => $parsedHeaders['from'] ?? 'N/A',
                'to' => $parsedHeaders['to'] ?? 'N/A',
                'subject' => $parsedHeaders['subject'] ?? 'N/A',
                'date' => $parsedHeaders['date'] ?? 'N/A',
                'inReplyTo' => $parsedHeaders['in-reply-to'] ?? null, // Crucial for threading
                'references' => $parsedHeaders['references'] ?? null, // Crucial for threading
                'body' => $body,
                'headers' => $parsedHeaders, // Return all parsed headers for full context
            ];

        } catch (Exception $e) {
            Log::error('Failed to retrieve Gmail message: ' . $e->getMessage(), ['message_id' => $messageId, 'exception' => $e]);
            throw new Exception('Failed to retrieve message: ' . $e->getMessage());
        }
    }
}
