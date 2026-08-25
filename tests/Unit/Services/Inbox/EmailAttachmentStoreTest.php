<?php

namespace Tests\Unit\Services\Inbox;

use App\Models\Email;
use App\Models\FileAttachment;
use App\Services\Inbox\EmailAttachmentStore;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmailAttachmentStoreTest extends TestCase
{
    public function test_bytes_returns_contents_from_storage(): void
    {
        Storage::fake('gcs');
        Storage::disk('gcs')->put('email-attachments/test.pdf', '%PDF-1.4 test bytes');

        $file = new FileAttachment();
        $file->path = 'email-attachments/test.pdf';

        $store = new EmailAttachmentStore();
        $this->assertEquals('%PDF-1.4 test bytes', $store->bytes($file));
    }

    public function test_bytes_returns_null_on_missing_file(): void
    {
        Storage::fake('gcs');

        $file = new FileAttachment();
        $file->path = 'email-attachments/non-existent.pdf';

        $store = new EmailAttachmentStore();
        $this->assertNull($store->bytes($file));
    }
}
