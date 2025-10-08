<?php

namespace App\Services;

use App\Traits\GoogleApiAuthTrait;
use Google\Client;
use Google\Service\Gmail\Message;
use Google\Service\Gmail\MessagePart;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class GmailService
{

    use GoogleApiAuthTrait;

    /**
     * Move a Gmail message to Trash by message ID.
     * Requires Gmail MODIFY scope.
     *
     * @param string $messageId
     * @return bool true on success
     * @throws Exception on API failure
     */
    public function trashMessage(string $messageId): bool
    {
        try {
            $this->gmailService->users_messages->trash('me', $messageId);
            return true;
        } catch (Exception $e) {
            throw new Exception('Failed to trash Gmail message: ' . $e->getMessage());
        }
    }

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
        // Construct the raw email message in RFC 2822 format.
        $rawMessage = "To: $to\r\n";
        $rawMessage .= "From: " . $this->getAuthorizedEmail() . "\r\n";
        $rawMessage .= "Subject: =?utf-8?B?" . base64_encode($subject) . "?=\r\n";
        $rawMessage .= "MIME-Version: 1.0\r\n";
        $rawMessage .= "Content-type: text/html; charset=utf-8\r\n";
        $rawMessage .= "Content-Transfer-Encoding: base64\r\n";
        $rawMessage .= "\r\n" . chunk_split(base64_encode($body));

        $message = new Message();
        $message->setRaw(strtr(base64_encode($rawMessage), ['+' => '-', '/' => '_']));

        try {
            $sentMessage = $this->gmailService->users_messages->send('me', $message);
            return $sentMessage->getId();
        } catch (Exception $e) {
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
        $messageIds = [];
        try {
            $response = $this->gmailService->users_messages->listUsersMessages('me', [
                'maxResults' => $maxResults,
                'q' => $query,
            ]);

            if ($response->getMessages()) {
                foreach ($response->getMessages() as $message) {
                    $messageIds[] = $message->getId();
                }
            }
            return $messageIds;
        } catch (Exception $e) {
            throw new Exception('Failed to list messages: ' . $e->getMessage());
        }
    }

    /**
     * Retrieves the full content of a specific email message, including attachments and inline images.
     *
     * @param string $messageId The ID of the message to retrieve.
     * @return array Decoded email data, including body content and attachments.
     * @throws Exception If message retrieval or parsing fails.
     */
    public function getMessage(string $messageId): array
    {
        try {
            $message = $this->gmailService->users_messages->get('me', $messageId, ['format' => 'full']);
            $payload = $message->getPayload();

            $parsedHeaders = [];
            foreach ($payload->getHeaders() as $header) {
                $parsedHeaders[strtolower($header->getName())] = $header->getValue();
            }

            $emailData = [
                'id' => $message->getId(),
                'threadId' => $message->getThreadId(),
                'from' => $parsedHeaders['from'] ?? 'N/A',
                'to' => $parsedHeaders['to'] ?? 'N/A',
                'subject' => $parsedHeaders['subject'] ?? 'N/A',
                'date' => $parsedHeaders['date'] ?? 'N/A',
                'inReplyTo' => $parsedHeaders['in-reply-to'] ?? null,
                'references' => $parsedHeaders['references'] ?? null,
                'body' => [
                    'plain' => '',
                    'html' => '',
                ],
                'attachments' => [],
            ];

            $processParts = function($parts) use (&$emailData, $messageId, &$processParts) {
                foreach ($parts as $part) {
                    $mimeType = $part->getMimeType();
                    $bodyData = $part->getBody()->getData();
                    $filename = $part->getFilename();

                    $contentId = $this->getPartHeader($part, 'Content-ID');

                    if ($mimeType == 'text/plain' && $bodyData) {
                        $emailData['body']['plain'] = $this->decodeBase64Url($bodyData);
                    } elseif ($mimeType == 'text/html' && $bodyData) {
                        $emailData['body']['html'] = $this->decodeBase64Url($bodyData);
                    } elseif ($filename) {
                        $attachmentId = $part->getBody()->getAttachmentId();
                        $attachmentData = $this->gmailService->users_messages_attachments->get('me', $messageId, $attachmentId);

                        // Return the raw data for the controller to handle storage
                        $emailData['attachments'][] = [
                            'filename' => $filename,
                            'mimeType' => $mimeType,
                            'size' => $attachmentData->getSize(),
                            'data' => $this->decodeBase64Url($attachmentData->getData()),
                            'is_inline' => !is_null($contentId),
                            'content_id' => trim($contentId, '<>'),
                        ];
                    } elseif (str_starts_with($mimeType, 'image/')) {
                        $attachmentId = $part->getBody()->getAttachmentId();
                        $attachmentData = $this->gmailService->users_messages_attachments->get('me', $messageId, $attachmentId);

                        $emailData['attachments'][] = [
                            'filename' => $filename ?? 'inline_image',
                            'mimeType' => $mimeType,
                            'size' => $attachmentData->getSize(),
                            'data' => $this->decodeBase64Url($attachmentData->getData()),
                            'is_inline' => true,
                            'content_id' => trim($contentId, '<>'),
                        ];
                    }

                    if ($part->getParts()) {
                        $processParts($part->getParts());
                    }
                }
            };

            if ($payload->getParts()) {
                $processParts($payload->getParts());
            } else {
                $emailData['body']['html'] = $this->decodeBase64Url($payload->getBody()->getData());
            }

            return $emailData;

        } catch (Exception $e) {
            throw new Exception('Failed to retrieve message: ' . $e->getMessage());
        }
    }

    private function decodeBase64Url(string $data): string
    {
        return base64_decode(strtr($data, ['-' => '+', '_' => '/']));
    }

    private function getPartHeader(MessagePart $part, string $headerName): ?string
    {
        foreach ($part->getHeaders() as $header) {
            if (strtolower($header->getName()) === strtolower($headerName)) {
                return $header->getValue();
            }
        }
        return null;
    }
}
