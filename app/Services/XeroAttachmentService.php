<?php

namespace App\Services;

use App\Models\FileAttachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class XeroAttachmentService
{
    private const ATTACHMENTS_URL_TEMPLATE = 'https://api.xero.com/api.xro/2.0/%s/%s/Attachments/%s';

    public function __construct(
        private readonly XeroTokenService $xeroTokenService,
        private readonly GoogleDriveService $googleDriveService
    ) {}

    /**
     * Upload all attachments for a model to Xero.
     * 
     * @param Model $model The model (Bill or Invoice)
     * @param string $xeroId The ID of the record in Xero
     * @param string $endpoint The Xero endpoint (e.g., 'Invoices')
     */
    public function uploadAttachments(Model $model, string $xeroId, string $endpoint = 'Invoices'): void
    {
        $attachments = $model->files;

        foreach ($attachments as $attachment) {
            try {
                $this->uploadToXero($attachment, $xeroId, $endpoint);
            } catch (\Exception $e) {
                Log::error("Failed to upload attachment {$attachment->id} to Xero: " . $e->getMessage());
                // Continue with other attachments
            }
        }
    }

    /**
     * Upload one attachment to Xero.
     */
    public function uploadAttachment(FileAttachment $attachment, string $xeroId, string $endpoint = 'Invoices'): void
    {
        $this->uploadToXero($attachment, $xeroId, $endpoint);
    }

    private function uploadToXero(FileAttachment $attachment, string $xeroId, string $endpoint): void
    {
        $content = $this->getFileContent($attachment);
        $credentials = $this->xeroTokenService->getRuntimeCredentials();

        $url = sprintf(
            self::ATTACHMENTS_URL_TEMPLATE,
            $endpoint,
            $xeroId,
            rawurlencode($attachment->filename)
        );

        Http::withToken($credentials['access_token'])
            ->withHeaders([
                'Xero-tenant-id' => $credentials['tenant_id'],
                'Content-Type' => $attachment->mime_type ?? 'application/octet-stream',
                'Accept' => 'application/json',
            ])
            ->withBody($content, $attachment->mime_type ?? 'application/octet-stream')
            ->post($url)
            ->throw();
    }

    private function getFileContent(FileAttachment $attachment): string
    {
        if ($attachment->google_drive_file_id) {
            return $this->googleDriveService->getFileContent($attachment->google_drive_file_id);
        }

        if ($attachment->path) {
            // Check if it's a full URL or a relative path on GCS
            if (filter_var($attachment->path, FILTER_VALIDATE_URL)) {
                return Http::get($attachment->path)->throw()->body();
            }
            
            return Storage::disk('gcs')->get($attachment->path);
        }

        throw new RuntimeException("Attachment {$attachment->id} has no file path or Google Drive ID.");
    }
}
