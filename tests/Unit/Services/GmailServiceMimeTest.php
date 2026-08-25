<?php

namespace Tests\Unit\Services;

use App\Services\GmailService;
use Tests\TestCase;

class GmailServiceMimeTest extends TestCase
{
    public function test_mixed_body_with_attachments_and_plain_html(): void
    {
        $gmailService = new GmailService();
        $html = '<p>Hello world</p>';
        $attachments = [
            [
                'filename' => 'document.pdf',
                'mime_type' => 'application/pdf',
                'bytes' => '%PDF-1.4 test document content',
            ],
            [
                'filename' => 'photo.jpg',
                'mime_type' => 'image/jpeg',
                'bytes' => 'fake-jpeg-binary-data',
            ],
        ];

        $mime = $gmailService->mixedBody($html, [], $attachments);

        $this->assertStringContainsString('Content-Type: multipart/mixed; boundary="', $mime);
        $this->assertStringContainsString('Content-Type: text/html; charset=utf-8', $mime);
        $this->assertStringContainsString(chunk_split(base64_encode($html)), $mime);

        $this->assertStringContainsString('Content-Type: application/pdf; name="document.pdf"', $mime);
        $this->assertStringContainsString('Content-Disposition: attachment; filename="document.pdf"', $mime);
        $this->assertStringContainsString(chunk_split(base64_encode('%PDF-1.4 test document content')), $mime);

        $this->assertStringContainsString('Content-Type: image/jpeg; name="photo.jpg"', $mime);
        $this->assertStringContainsString('Content-Disposition: attachment; filename="photo.jpg"', $mime);
        $this->assertStringContainsString(chunk_split(base64_encode('fake-jpeg-binary-data')), $mime);
    }

    public function test_mixed_body_with_both_inline_images_and_attachments(): void
    {
        $gmailService = new GmailService();
        $html = '<p>Hello <img src="cid:img_123" /></p>';
        $inlineImages = [
            'img_123' => [
                'filename' => 'inline.png',
                'mime_type' => 'image/png',
                'bytes' => 'png-data',
            ],
        ];
        $attachments = [
            [
                'filename' => 'contract.pdf',
                'mime_type' => 'application/pdf',
                'bytes' => 'pdf-data',
            ],
        ];

        $mime = $gmailService->mixedBody($html, $inlineImages, $attachments);

        $this->assertStringContainsString('Content-Type: multipart/mixed; boundary="', $mime);
        $this->assertStringContainsString('Content-Type: multipart/related; boundary="', $mime);
        $this->assertStringContainsString('Content-ID: <img_123>', $mime);
        $this->assertStringContainsString('Content-Disposition: inline; filename="inline.png"', $mime);
        $this->assertStringContainsString('Content-Disposition: attachment; filename="contract.pdf"', $mime);
    }
}
