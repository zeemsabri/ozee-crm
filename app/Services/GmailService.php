<?php

namespace App\Services;

use App\Traits\GoogleApiAuthTrait;
use Exception;
use Google\Service\Gmail\Message;
use Google\Service\Gmail\MessagePart;
use Illuminate\Support\Facades\Storage;

class GmailService
{
    use GoogleApiAuthTrait;

    /**
     * Move a Gmail message to Trash by message ID.
     * Requires Gmail MODIFY scope.
     *
     * @return bool true on success
     *
     * @throws Exception on API failure
     */
    public function trashMessage(string $messageId): bool
    {
        try {
            $this->gmailService->users_messages->trash('me', $messageId);

            return true;
        } catch (Exception $e) {
            throw new Exception('Failed to trash Gmail message: '.$e->getMessage());
        }
    }

    /**
     * Sends an email via Gmail API.
     *
     * @param  string  $to  Recipient email address
     * @param  string  $subject  Email subject
     * @param  string  $body  Email body (HTML or plain text)
     * @return string Message ID of the sent email
     *
     * @throws Exception If email sending fails.
     */
    public function sendEmail(string $to, string $subject, string $body): string
    {
        return $this->sendMessage($to, $subject, $body)['id'];
    }

    /**
     * Send an email, optionally as part of an existing thread.
     *
     * Split out of sendEmail() so replies can carry the two headers Gmail actually threads
     * on. sendEmail() is unchanged for every existing caller — it just delegates and
     * returns the Gmail API id as before.
     *
     * @param  array<string,string>  $headers  extra RFC headers, e.g. In-Reply-To / References
     * @param  string|null  $messageId  the Message-ID header to stamp on this message. Pass
     *                                  one when you need to know it afterwards: Gmail's
     *                                  send response returns its own API id, NOT the header
     *                                  it generated, so a value we set is the only value we
     *                                  can reliably record for later replies to thread onto.
     * @param  array<string,array{filename:string,mime_type:string,bytes:string}>  $inlineImages
     *                                  keyed by Content-ID. When present the message is
     *                                  built as multipart/related and the HTML may
     *                                  reference each part as `cid:<key>`.
     * @return array{id:string,threadId:?string,messageId:?string}
     */
    public function sendMessage(
        string $to,
        string $subject,
        string $body,
        array $headers = [],
        ?string $messageId = null,
        array $inlineImages = []
    ): array {
        // Construct the raw email message in RFC 2822 format.
        $rawMessage = "To: $to\r\n";
        $rawMessage .= 'From: '.$this->getAuthorizedEmail()."\r\n";
        $rawMessage .= 'Subject: =?utf-8?B?'.base64_encode($subject)."?=\r\n";

        if ($messageId) {
            $rawMessage .= 'Message-ID: '.$this->sanitiseHeader($messageId)."\r\n";
        }

        foreach ($headers as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $rawMessage .= $this->sanitiseHeader((string) $name).': '
                .$this->sanitiseHeader((string) $value)."\r\n";
        }

        $rawMessage .= "MIME-Version: 1.0\r\n";
        $rawMessage .= $inlineImages
            ? $this->relatedBody($body, $inlineImages)
            : "Content-type: text/html; charset=utf-8\r\n"
                ."Content-Transfer-Encoding: base64\r\n"
                ."\r\n".chunk_split(base64_encode($body));

        $message = new Message;
        $message->setRaw(strtr(base64_encode($rawMessage), ['+' => '-', '/' => '_']));

        try {
            $sentMessage = $this->gmailService->users_messages->send('me', $message);

            return [
                'id' => $sentMessage->getId(),
                'threadId' => $sentMessage->getThreadId(),
                'messageId' => $messageId,
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to send email: '.$e->getMessage());
        }
    }

    /**
     * A `multipart/related` body: the HTML, then one part per inline image.
     *
     * `related` rather than `mixed` is the distinction that matters — it tells the client
     * these parts are *referenced by* the HTML rather than being attachments to list at
     * the bottom, so a CID image renders in place and does not also show up as a paperclip.
     *
     * Embedding rather than hot-linking is deliberate: the image then lives in the
     * recipient's mailbox permanently, renders offline, and is not suppressed by the
     * remote-image blocking that Outlook and Apple Mail apply by default. It is also what
     * makes it safe for our own stored copy to expire (see config('inbox.blocks')).
     *
     * @param  array<string,array{filename:string,mime_type:string,bytes:string}>  $images
     */
    private function relatedBody(string $html, array $images): string
    {
        // Must not appear in any part's content. Random, so it cannot collide with body text.
        $boundary = 'ozee_'.bin2hex(random_bytes(12));

        $out = "Content-Type: multipart/related; boundary=\"{$boundary}\"\r\n\r\n";

        $out .= "--{$boundary}\r\n";
        $out .= "Content-Type: text/html; charset=utf-8\r\n";
        $out .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $out .= chunk_split(base64_encode($html));

        foreach ($images as $cid => $image) {
            $safeCid = $this->sanitiseHeader((string) $cid);
            $filename = $this->sanitiseHeader($image['filename'] ?? 'image');
            $mime = $this->sanitiseHeader($image['mime_type'] ?? 'application/octet-stream');

            $out .= "--{$boundary}\r\n";
            $out .= "Content-Type: {$mime}\r\n";
            $out .= "Content-Transfer-Encoding: base64\r\n";
            // Angle brackets are required here and must NOT appear in the `cid:` URL that
            // references it — <foo> in the header, cid:foo in the HTML.
            $out .= "Content-ID: <{$safeCid}>\r\n";
            $out .= "Content-Disposition: inline; filename=\"{$filename}\"\r\n\r\n";
            $out .= chunk_split(base64_encode($image['bytes']));
        }

        return $out."--{$boundary}--\r\n";
    }

    /**
     * Strip CR/LF from a header value.
     *
     * Header values here are built from stored data, and a newline inside one would let
     * that data inject arbitrary extra headers into the message — Bcc being the obvious
     * one. Cheap to prevent, so prevent it unconditionally rather than trusting callers.
     */
    private function sanitiseHeader(string $value): string
    {
        return trim(str_replace(["\r", "\n"], '', $value));
    }

    /**
     * The messages in one Gmail thread, headers only.
     *
     * `metadata` format with an explicit header list: the bodies of a long thread are
     * megabytes we would immediately discard, and this is only ever asked "which message
     * in here is ours".
     *
     * @return array<int,array{id:string,messageIdHeader:?string,from:string,subject:string,date:?string,internalDate:?int}>
     */
    public function getThread(string $threadId): array
    {
        try {
            $thread = $this->gmailService->users_threads->get('me', $threadId, [
                'format' => 'metadata',
                'metadataHeaders' => ['Message-ID', 'From', 'Subject', 'Date'],
            ]);
        } catch (Exception $e) {
            throw new Exception('Failed to load thread: '.$e->getMessage());
        }

        $out = [];

        foreach ($thread->getMessages() ?? [] as $message) {
            $headers = [];

            foreach ($message->getPayload()?->getHeaders() ?? [] as $header) {
                $headers[strtolower($header->getName())] = $header->getValue();
            }

            $out[] = [
                'id' => $message->getId(),
                // The RFC 5322 header, not the API id — see getMessage()'s note.
                'messageIdHeader' => $headers['message-id'] ?? null,
                'from' => $headers['from'] ?? '',
                'subject' => $headers['subject'] ?? '',
                'date' => $headers['date'] ?? null,
                // Milliseconds since epoch, set by Gmail on receipt. More reliable for
                // ordering than the Date header, which the sender writes.
                'internalDate' => $message->getInternalDate() ? (int) $message->getInternalDate() : null,
            ];
        }

        return $out;
    }

    /**
     * Lists email message IDs from the authenticated user's mailbox.
     *
     * @param  int  $maxResults  Maximum number of messages to retrieve.
     * @param  string  $query  Gmail search query string (e.g., 'is:inbox', 'from:someone@example.com').
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
            throw new Exception('Failed to list messages: '.$e->getMessage());
        }
    }

    /**
     * Retrieves the full content of a specific email message, including attachments and inline images.
     *
     * @param  string  $messageId  The ID of the message to retrieve.
     * @return array Decoded email data, including body content and attachments.
     *
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
                // The RFC 5322 Message-ID header — distinct from 'id' above, which is
                // Gmail's own API handle. This is the value In-Reply-To/References must
                // carry for a reply to thread; the API id matches nothing.
                'messageIdHeader' => $parsedHeaders['message-id'] ?? null,
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

            $processParts = function ($parts) use (&$emailData, $messageId, &$processParts) {
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
                            'is_inline' => ! is_null($contentId),
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
            throw new Exception('Failed to retrieve message: '.$e->getMessage());
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
