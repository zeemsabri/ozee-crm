<?php

namespace App\Services;

use App\Traits\GoogleApiAuthTrait;
use Google\Service\Drive;
use Illuminate\Support\Facades\Log;

class GoogleDriveService
{
    use GoogleApiAuthTrait;

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
    public function uploadFile(string $filePath, string $fileName, string $parentFolderId, $field = 'webContentLink'): array
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

            $permission = new Drive\Permission();
            $permission->setType('anyone');
            $permission->setRole('reader');

            $this->driveService->permissions->create(
                $uploadedFile->id, // The ID of the file you just uploaded
                $permission, // The permission object you created
                ['fields' => 'id'] // Request only the ID of the created permission (optional, but good practice)
            );

            Log::info('File uploaded to Google Drive', [
                'file_name' => $fileName,
                'file_id' => $uploadedFile->id,
                'parent_folder_id' => $parentFolderId,
            ]);

            return [
                'id'    =>  $uploadedFile->id,
                'thumbnail' =>  $this->getThumbnailLink($uploadedFile->id),
                'path'  => $this->getWebContentLink($uploadedFile->id, $field)
            ];

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
     * Copy a file to a new destination in Google Drive.
     *
     * @param string $sourceFileId The ID of the file to copy.
     * @param string $destinationFolderId The ID of the folder to copy the file to.
     * @param string $newFileName The new name for the copied file.
     * @return array An array containing the new file's ID and web link.
     * @throws \Exception
     */
    public function copyFile(string $sourceFileId, string $destinationFolderId, string $newFileName): array
    {
        try {
            $file = new Drive\DriveFile();
            $file->setName($newFileName);
            $file->setParents([$destinationFolderId]);

            $copiedFile = $this->driveService->files->copy($sourceFileId, $file, [
                'fields' => 'id, webViewLink', // Request ID and webViewLink for the new file
            ]);

            Log::info('File copied in Google Drive', [
                'source_file_id' => $sourceFileId,
                'copied_file_id' => $copiedFile->id,
                'destination_folder_id' => $destinationFolderId,
                'new_file_name' => $newFileName,
            ]);

            return [
                'id' => $copiedFile->id,
                'link' => $copiedFile->getWebViewLink(),
            ];
        } catch (\Exception $e) {
            Log::error('Error copying file in Google Drive: ' . $e->getMessage(), [
                'source_file_id' => $sourceFileId,
                'destination_folder_id' => $destinationFolderId,
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
//        try {
            $this->driveService->files->delete($fileId);
            Log::info('Google Drive file/folder deleted', ['file_id' => $fileId]);
//        } catch (\Exception $e) {
//            Log::error('Error deleting Google Drive file/folder: ' . $e->getMessage(), [
//                'file_id' => $fileId,
//                'error' => $e->getTraceAsString(),
//            ]);
//            throw $e;
//        }
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

    /**
     * Add a permission to a file or folder in Google Drive.
     *
     * @param string $fileId The ID of the file or folder.
     * @param string $emailAddress The email address of the user or group to add.
     * @param string $role The role for the user (e.g., 'reader', 'writer', 'commenter', 'owner').
     * @param string $type The type of the grantee (e.g., 'user', 'group', 'domain', 'anyone').
     * @return Drive\Permission The created permission resource.
     * @throws \Exception
     */
    public function addPermission(string $fileId, string $emailAddress, string $role = 'writer', string $type = 'user'): Drive\Permission
    {
        try {
            $permission = new Drive\Permission();
            $permission->setType($type);
            $permission->setRole($role);
            $permission->setEmailAddress($emailAddress);
            // Optionally, you can set 'sendNotificationEmail' to false if you don't want to send an email to the user.
            // $optParams = ['sendNotificationEmail' => false];

            $newPermission = $this->driveService->permissions->create($fileId, $permission);

            Log::info('Google Drive permission added', [
                'file_id' => $fileId,
                'email_address' => $emailAddress,
                'role' => $role,
                'permission_id' => $newPermission->id,
            ]);

            return $newPermission;
        } catch (\Google\Service\Exception $e) {
            // Check for common errors, e.g., 'duplicate' if user already has permission
            if ($e->getCode() == 409 && str_contains($e->getMessage(), 'duplicate')) {
                Log::warning('Permission already exists for ' . $emailAddress . ' on ' . $fileId);
                // Optionally, you could try to fetch existing permission or return a specific status
                throw new \Exception("Permission already exists for {$emailAddress} on file/folder {$fileId}.", 0, $e);
            }
            Log::error('Error adding Google Drive permission: ' . $e->getMessage(), [
                'file_id' => $fileId,
                'email_address' => $emailAddress,
                'role' => $role,
                'error' => $e->getTraceAsString(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error adding Google Drive permission: ' . $e->getMessage(), [
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
     * @param string $fileId The ID of the file or folder.
     * @param string $emailAddress The email address of the user or group to remove.
     * @return void
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
                Log::info('Google Drive permission removed', [
                    'file_id' => $fileId,
                    'email_address' => $emailAddress,
                    'permission_id' => $permissionIdToRemove,
                ]);
            } else {
                Log::warning('Google Drive permission not found for removal', [
                    'file_id' => $fileId,
                    'email_address' => $emailAddress,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error removing Google Drive permission: ' . $e->getMessage(), [
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
            Log::info('Fetched webContentLink for file', ['file_id' => $fileId]);
            return $file->{$field};
        } catch (\Exception $e) {
            Log::error('Error fetching webContentLink: ' . $e->getMessage(), [
                'file_id' => $fileId,
                'error' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Fetch the thumbnail link for a file.
     *
     * @param string $fileId
     * @return string|null Thumbnail link or null if not available
     */
    public function getThumbnailLink(string $fileId): ?string
    {
        try {
            $file = $this->driveService->files->get($fileId, ['fields' => 'thumbnailLink']);
            Log::info('Fetched thumbnail link for file', ['file_id' => $fileId, 'thumbnail_link' => $file->getThumbnailLink()]);
            return $file->getThumbnailLink();
        } catch (\Exception $e) {
            Log::error('Error fetching thumbnail link: ' . $e->getMessage(), [
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

            if(!ISSET($id)) {
                $id = $this->createFolder($folderName, $parentFolderId);
            }

            return $id;

        } catch (\Exception $e) {
            \Log::error('Error finding or creating subfolder: ' . $e->getMessage(), [
                'parent_id' => $parentFolderId,
                'folder_name' => $folderName,
            ]);
            throw $e;
        }
    }
}
