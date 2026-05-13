<?php

namespace App\Services;

use App\Traits\GoogleApiAuthTrait;
use Google\Service\Drive;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleDriveService
{
    use GoogleApiAuthTrait;

    /**
     * Create a folder in Google Drive.
     *
     * @return string Folder ID
     */
    public function createFolder(string $folderName, ?string $parentFolderId = null): string
    {
        try {
            $folder = new Drive\DriveFile;
            $folder->setName($folderName);
            $folder->setMimeType('application/vnd.google-apps.folder');
            if ($parentFolderId) {
                $folder->setParents([$parentFolderId]);
            }

            $createdFolder = $this->driveService->files->create($folder, [
                'fields' => 'id',
            ]);

            return $createdFolder->id;
        } catch (\Exception $e) {
            Log::error('Error creating Google Drive folder: '.$e->getMessage(), [
                'folder_name' => $folderName,
                'error' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Upload a file to Google Drive.
     *
     * @param  string  $filePath  Local path to the file
     * @param  string  $fileName  Name of the file in Google Drive
     * @param  string  $parentFolderId  Folder ID to upload to
     * @return string File ID
     */
    public function uploadFile(string $filePath, string $fileName, string $parentFolderId, $field = 'webContentLink'): array
    {
        try {
            $file = new Drive\DriveFile;
            $file->setName($fileName);
            $file->setParents([$parentFolderId]);

            $content = file_get_contents($filePath);
            $uploadedFile = $this->driveService->files->create($file, [
                'data' => $content,
                'mimeType' => mime_content_type($filePath),
                'uploadType' => 'multipart',
                'fields' => 'id',
            ]);

            $permission = new Drive\Permission;
            $permission->setType('anyone');
            $permission->setRole('reader');

            $this->driveService->permissions->create(
                $uploadedFile->id, // The ID of the file you just uploaded
                $permission, // The permission object you created
                ['fields' => 'id'] // Request only the ID of the created permission (optional, but good practice)
            );

            return [
                'id' => $uploadedFile->id,
                'thumbnail' => $this->getThumbnailLink($uploadedFile->id),
                'path' => $this->getWebContentLink($uploadedFile->id, $field),
            ];

        } catch (\Exception $e) {
            Log::error('Error uploading file to Google Drive: '.$e->getMessage(), [
                'file_name' => $fileName,
                'parent_folder_id' => $parentFolderId,
                'error' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Create a new Google-native document in a Drive folder.
     *
     * @return array{id:string,link:?string,mime_type:string,name:string}
     */
    public function createDocument(
        string $name,
        string $parentFolderId,
        string $mimeType = 'application/vnd.google-apps.document'
    ): array {
        try {
            $file = new Drive\DriveFile;
            $file->setName($name);
            $file->setMimeType($mimeType);
            $file->setParents([$parentFolderId]);

            $createdFile = $this->driveService->files->create($file, [
                'fields' => 'id, webViewLink, mimeType, name',
                'supportsAllDrives' => true,
            ]);

            $permission = new Drive\Permission;
            $permission->setType('anyone');
            $permission->setRole('reader');

            $this->driveService->permissions->create(
                $createdFile->id,
                $permission,
                [
                    'fields' => 'id',
                    'supportsAllDrives' => true,
                ]
            );

            return [
                'id' => $createdFile->id,
                'link' => $createdFile->getWebViewLink(),
                'mime_type' => $createdFile->getMimeType(),
                'name' => $createdFile->getName(),
            ];
        } catch (\Exception $e) {
            Log::error('Error creating Google Drive document: ' . $e->getMessage(), [
                'name' => $name,
                'parent_folder_id' => $parentFolderId,
                'mime_type' => $mimeType,
                'error' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Copy a file to a new destination in Google Drive.
     *
     * @param  string  $sourceFileId  The ID of the file to copy.
     * @param  string  $destinationFolderId  The ID of the folder to copy the file to.
     * @param  string  $newFileName  The new name for the copied file.
     * @return array An array containing the new file's ID and web link.
     *
     * @throws \Exception
     */
    public function copyFile(string $sourceFileId, string $destinationFolderId, string $newFileName): array
    {
        try {
            $file = new Drive\DriveFile;
            $file->setName($newFileName);
            $file->setParents([$destinationFolderId]);

            $copiedFile = $this->driveService->files->copy($sourceFileId, $file, [
                'fields' => 'id, webViewLink', // Request ID and webViewLink for the new file
                'supportsAllDrives' => true,      // Add this for robustness with Shared Drives
            ]);

            return [
                'id' => $copiedFile->id,
                'link' => $copiedFile->getWebViewLink(),
            ];
        } catch (\Exception $e) {
            Log::error('Error copying file in Google Drive: '.$e->getMessage(), [
                'source_file_id' => $sourceFileId,
                'destination_folder_id' => $destinationFolderId,
                'error' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * [DEBUG] Lists files and folders visible to the service account in its root.
     */
    public function listRootFilesAndFolders(?string $fileName = null, $mimeType = 'application/vnd.google-apps.document'): array
    {
        try {
            $foundFiles = [];
            $queryParts = ['trashed=false'];

            // If a specific file name is provided, add it to the search query.
            if ($fileName) {
                $escapedFileName = addslashes($fileName);
                $queryParts[] = "name = '{$escapedFileName}'";
            }

            // If a specific mimeType is provided, add it to the search query.
            if ($mimeType) {
                $escapedMimeType = addslashes($mimeType);
                $queryParts[] = "mimeType = '{$escapedMimeType}'";
            }

            $optParams = [
                'q' => implode(' and ', $queryParts),
                'pageSize' => 100,
                'fields' => 'files(id, name, mimeType, shared, capabilities, parents)',
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ];

            $results = $this->driveService->files->listFiles($optParams);

            foreach ($results->getFiles() as $file) {
                $foundFiles[] = [
                    'name' => $file->getName(),
                    'id' => $file->getId(),
                    'mimeType' => $file->getMimeType(),
                    'shared' => $file->getShared(),
                    'canCopy' => $file->getCapabilities() ? $file->getCapabilities()->getCanCopy() : null,
                    'parents' => $file->getParents(),
                ];
            }

            return $foundFiles;
        } catch (\Exception $e) {
            Log::error('Error listing/finding files: '.$e->getMessage(), [
                'error' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete a file or folder in Google Drive.
     */
    public function deleteFile(string $fileId): void
    {
        $this->driveService->files->delete($fileId);
    }

    /**
     * Update a folder's name in Google Drive.
     */
    public function updateFolderName(string $folderId, string $newName): void
    {
        try {
            $file = new Drive\DriveFile;
            $file->setName($newName);
            $this->driveService->files->update($folderId, $file);
        } catch (\Exception $e) {
            Log::error('Failed to update Google Drive folder name: '.$e->getMessage(), [
                'folder_id' => $folderId,
                'new_name' => $newName,
                'error' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Add a permission to a file or folder in Google Drive.
     *
     * @param  string  $fileId  The ID of the file or folder.
     * @param  string  $emailAddress  The email address of the user or group to add.
     * @param  string  $role  The role for the user (e.g., 'reader', 'writer', 'commenter', 'owner').
     * @param  string  $type  The type of the grantee (e.g., 'user', 'group', 'domain', 'anyone').
     * @return Drive\Permission The created permission resource.
     *
     * @throws \Exception
     */
    public function addPermission(string $fileId, string $emailAddress, string $role = 'writer', string $type = 'user'): Drive\Permission
    {
        try {
            $permission = new Drive\Permission;
            $permission->setType($type);
            $permission->setRole($role);
            $permission->setEmailAddress($emailAddress);
            // Optionally, you can set 'sendNotificationEmail' to false if you don't want to send an email to the user.
            // $optParams = ['sendNotificationEmail' => false];

            $newPermission = $this->driveService->permissions->create($fileId, $permission);

            return $newPermission;
        } catch (\Google\Service\Exception $e) {
            // Check for common errors, e.g., 'duplicate' if user already has permission
            if ($e->getCode() == 409 && str_contains($e->getMessage(), 'duplicate')) {
                Log::warning('Permission already exists for '.$emailAddress.' on '.$fileId);
                // Optionally, you could try to fetch existing permission or return a specific status
                throw new \Exception("Permission already exists for {$emailAddress} on file/folder {$fileId}.", 0, $e);
            }
            Log::error('Error adding Google Drive permission: '.$e->getMessage(), [
                'file_id' => $fileId,
                'email_address' => $emailAddress,
                'role' => $role,
                'error' => $e->getTraceAsString(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error adding Google Drive permission: '.$e->getMessage(), [
                'file_id' => $fileId,
                'email_address' => $emailAddress,
                'role' => $role,
                'error' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Remove a permission from a file or folder in Google Drive.
     * To get the permissionId, you might need to list permissions first.
     * For user-specific permissions added by email, you often have to query.
     * However, it's safer to ensure you store the permissionId when you add it,
     * or query for it when needed. For simplicity, we'll try to find it first.
     *
     * @param  string  $fileId  The ID of the file or folder.
     * @param  string  $emailAddress  The email address of the user or group to remove.
     *
     * @throws \Exception
     */
    public function removePermission(string $fileId, string $emailAddress): void
    {
        try {
            // Find the permission ID for the given email address on the file/folder
            $permissions = $this->driveService->permissions->listPermissions($fileId, ['fields' => 'permissions(id,emailAddress)']);
            $permissionIdToRemove = null;

            foreach ($permissions->getPermissions() as $permission) {
                if ($permission->getEmailAddress() === $emailAddress) {
                    $permissionIdToRemove = $permission->getId();
                    break;
                }
            }

            if ($permissionIdToRemove) {
                $this->driveService->permissions->delete($fileId, $permissionIdToRemove);
            } else {
                Log::warning('Google Drive permission not found for removal', [
                    'file_id' => $fileId,
                    'email_address' => $emailAddress,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error removing Google Drive permission: '.$e->getMessage(), [
                'file_id' => $fileId,
                'email_address' => $emailAddress,
                'error' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function getWebContentLink(string $fileId, $field = 'webContentLink'): ?string
    {
        try {
            $file = $this->driveService->files->get($fileId, ['fields' => $field]);
            return $file->{$field};
        } catch (\Exception $e) {
            Log::error('Error fetching webContentLink: '.$e->getMessage(), [
                'file_id' => $fileId,
                'error' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Return metadata for a Drive file id.
     */
    public function getFileMetadata(string $fileId): array
    {
        try {
            $file = $this->driveService->files->get($fileId, [
                'fields' => 'id, name, mimeType, webViewLink, thumbnailLink, size, modifiedTime, parents',
                'supportsAllDrives' => true,
            ]);

            return [
                'id' => $file->getId(),
                'name' => $file->getName(),
                'mime_type' => $file->getMimeType(),
                'web_view_link' => $file->getWebViewLink(),
                'thumbnail' => $file->getThumbnailLink(),
                'size' => $file->getSize(),
                'modified_time' => $file->getModifiedTime(),
                'parents' => $file->getParents(),
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching file metadata from Google Drive: ' . $e->getMessage(), [
                'file_id' => $fileId,
                'error' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * List files/folders directly inside a parent folder.
     */
    public function listFolderItems(string $folderId, int $pageSize = 100): array
    {
        try {
            $safeFolderId = addslashes($folderId);
            $results = $this->driveService->files->listFiles([
                'q' => "'{$safeFolderId}' in parents and trashed=false",
                'pageSize' => max(1, min($pageSize, 200)),
                'orderBy' => 'folder,name',
                'fields' => 'files(id, name, mimeType, webViewLink, thumbnailLink, size, modifiedTime, parents)',
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ]);

            return collect($results->getFiles())->map(function ($file) {
                $mime = (string) $file->getMimeType();
                return [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'mime_type' => $mime,
                    'is_folder' => $mime === 'application/vnd.google-apps.folder',
                    'web_view_link' => $file->getWebViewLink(),
                    'thumbnail' => $file->getThumbnailLink(),
                    'size' => $file->getSize(),
                    'modified_time' => $file->getModifiedTime(),
                    'parents' => $file->getParents(),
                ];
            })->values()->all();
        } catch (\Exception $e) {
            Log::error('Error listing Google Drive folder items: ' . $e->getMessage(), [
                'folder_id' => $folderId,
                'error' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Extract a Drive file ID from a full URL or return the incoming raw ID.
     */
    public function resolveFileId(string $raw): string
    {
        $value = trim($raw);

        if ($value === '') {
            throw new \InvalidArgumentException('Google Drive file ID or URL is required.');
        }

        if (!Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        if (preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $value, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/', $value, $matches) === 1) {
            return $matches[1];
        }

        throw new \InvalidArgumentException('Unable to parse Google Drive file ID from URL.');
    }

    /**
     * Fetch the thumbnail link for a file.
     *
     * @return string|null Thumbnail link or null if not available
     */
    public function getThumbnailLink(string $fileId): ?string
    {
        try {
            $file = $this->driveService->files->get($fileId, ['fields' => 'thumbnailLink']);
            return $file->getThumbnailLink();
        } catch (\Exception $e) {
            Log::error('Error fetching thumbnail link: '.$e->getMessage(), [
                'file_id' => $fileId,
                'error' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Find a subfolder by name under a parent folder; if not exists, create it.
     * Returns the subfolder ID.
     */
    public function findOrCreateSubfolder(string $parentFolderId, string $folderName): string
    {
        try {
            //            $query = sprintf("name='%s' and mimeType='application/vnd.google-apps.folder' and '%s' in parents and trashed=false", addslashes($folderName), $parentFolderId);
            //            $results = $this->driveService->files->listFiles([
            //                'q' => $query,
            //                'fields' => 'files(id,name)'
            //            ]);

            $query = "name='{$folderName}' and mimeType='application/vnd.google-apps.folder' and '{$parentFolderId}' in parents and trashed=false";

            // Search for existing folders that match the query.
            $results = $this->driveService->files->listFiles([
                'q' => $query,
                'spaces' => 'drive',
                'fields' => 'files(id, name)',
            ]);

            // Check if any folders were found.
            if (count($results->getFiles()) > 0) {
                // A matching folder was found, so return the first one's ID.
                $existingFolder = $results->getFiles()[0];
                $id = $existingFolder->getId();

            }

            if (! isset($id)) {
                $id = $this->createFolder($folderName, $parentFolderId);
            }

            return $id;

        } catch (\Exception $e) {
            \Log::error('Error finding or creating subfolder: '.$e->getMessage(), [
                'parent_id' => $parentFolderId,
                'folder_name' => $folderName,
            ]);
            throw $e;
        }
    }

    /**
     * Get the raw content of a file from Google Drive.
     */
    public function getFileContent(string $fileId): string
    {
        try {
            $response = $this->driveService->files->get($fileId, ['alt' => 'media']);
            return (string) $response->getBody();
        } catch (\Exception $e) {
            Log::error('Error fetching file content from Google Drive: ' . $e->getMessage(), [
                'file_id' => $fileId,
                'error' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
