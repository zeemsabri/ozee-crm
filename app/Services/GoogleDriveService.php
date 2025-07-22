<?php

namespace App\Services;

use App\Traits\GoogleApiAuthTrait;
use Google\Service\Drive;
use Illuminate\Support\Facades\Log;

class GoogleDriveService
{
    use GoogleApiAuthTrait;

    protected $driveService;

    public function __construct()
    {
        $this->initializeGoogleClient();
        $this->driveService = new Drive($this->getGoogleClient());
    }

    /**
     * Create a folder in Google Drive.
     *
     * @param string $folderName
     * @param string|null $parentFolderId
     * @return string Folder ID
     */
    public function createFolder(string $folderName, ?string $parentFolderId = null): string
    {
        try {
            $folder = new Drive\DriveFile();
            $folder->setName($folderName);
            $folder->setMimeType('application/vnd.google-apps.folder');
            if ($parentFolderId) {
                $folder->setParents([$parentFolderId]);
            }

            $createdFolder = $this->driveService->files->create($folder, [
                'fields' => 'id',
            ]);

            Log::info('Google Drive folder created', [
                'folder_name' => $folderName,
                'folder_id' => $createdFolder->id,
            ]);

            return $createdFolder->id;
        } catch (\Exception $e) {
            Log::error('Error creating Google Drive folder: ' . $e->getMessage(), [
                'folder_name' => $folderName,
                'error' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Upload a file to Google Drive.
     *
     * @param string $filePath Local path to the file
     * @param string $fileName Name of the file in Google Drive
     * @param string $parentFolderId Folder ID to upload to
     * @return string File ID
     */
    public function uploadFile(string $filePath, string $fileName, string $parentFolderId): string
    {
        try {
            $file = new Drive\DriveFile();
            $file->setName($fileName);
            $file->setParents([$parentFolderId]);

            $content = file_get_contents($filePath);
            $uploadedFile = $this->driveService->files->create($file, [
                'data' => $content,
                'mimeType' => mime_content_type($filePath),
                'uploadType' => 'multipart',
                'fields' => 'id',
            ]);

            Log::info('File uploaded to Google Drive', [
                'file_name' => $fileName,
                'file_id' => $uploadedFile->id,
                'parent_folder_id' => $parentFolderId,
            ]);

            return $uploadedFile->id;
        } catch (\Exception $e) {
            Log::error('Error uploading file to Google Drive: ' . $e->getMessage(), [
                'file_name' => $fileName,
                'parent_folder_id' => $parentFolderId,
                'error' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete a file or folder in Google Drive.
     *
     * @param string $fileId
     * @return void
     */
    public function deleteFile(string $fileId): void
    {
        try {
            $this->driveService->files->delete($fileId);
            Log::info('Google Drive file/folder deleted', ['file_id' => $fileId]);
        } catch (\Exception $e) {
            Log::error('Error deleting Google Drive file/folder: ' . $e->getMessage(), [
                'file_id' => $fileId,
                'error' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Update a folder's name in Google Drive.
     *
     * @param string $folderId
     * @param string $newName
     * @return void
     */
    public function updateFolderName(string $folderId, string $newName): void
    {
        try {
            $file = new Drive\DriveFile();
            $file->setName($newName);
            $this->driveService->files->update($folderId, $file);
            Log::info('Google Drive folder name updated', [
                'folder_id' => $folderId,
                'new_name' => $newName,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update Google Drive folder name: ' . $e->getMessage(), [
                'folder_id' => $folderId,
                'new_name' => $newName,
                'error' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
