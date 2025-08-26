# Project Logo Upload Feature Documentation

## Overview
This document describes the implementation of a new API endpoint for uploading project logos. The feature allows users to upload a logo image for a project, which is stored in the application's storage and optionally in Google Drive.

## Implementation Details

### API Endpoint
A new API endpoint was added to handle project logo uploads:

```
POST /api/projects/{project}/logo
```

This endpoint accepts a file upload with the name "logo" and updates the project's logo information.

### Route Definition
The route was added to the `routes/api.php` file:

```php
Route::post('projects/{project}/logo', [ProjectActionController::class, 'uploadLogo']);
```

### Controller Method
A new method `uploadLogo` was implemented in the `ProjectActionController` class:

```php
/**
 * Upload a logo for a project.
 *
 * @param Request $request
 * @param Project $project
 * @return \Illuminate\Http\JsonResponse
 */
public function uploadLogo(Request $request, Project $project)
{
    $user = Auth::user();
    if (!$this->canManageProjects($user, $project)) {
        return response()->json(['message' => 'Unauthorized. You do not have permission to update this project.'], 403);
    }

    try {
        $validationRules = [
            'logo' => 'required|image|max:2048', // Max 2MB
        ];
        
        $validated = $request->validate($validationRules);

        if ($request->hasFile('logo')) {
            // Delete existing logo if it exists
            if ($project->logo) {
                Storage::disk('public')->delete($project->logo);
            }
            
            // Store the new logo
            $localPath = $request->file('logo')->store('logos', 'public');
            $project->logo = $localPath;

            // Upload to Google Drive if folder ID exists
            if ($project->google_drive_folder_id) {
                try {
                    $fullLocalPath = Storage::disk('public')->path($localPath);
                    $originalFilename = $request->file('logo')->getClientOriginalName();
                    $response = $this->googleDriveService->uploadFile($fullLocalPath, 'logo_' . $originalFilename, $project->google_drive_folder_id);
                    $project->logo_google_drive_file_id = $response['id'] ?? null;
                } catch (\Exception $e) {
                    Log::error('Failed to upload logo to Google Drive: ' . $e->getMessage(), ['project_id' => $project->id]);
                }
            }

            $project->save();

            return response()->json([
                'message' => 'Logo uploaded successfully',
                'logo' => $project->logo,
                'logo_google_drive_file_id' => $project->logo_google_drive_file_id
            ]);
        }

        return response()->json([
            'message' => 'No logo was uploaded',
        ], 400);
    } catch (ValidationException $e) {
        return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
    } catch (\Exception $e) {
        Log::error('Error uploading logo: ' . $e->getMessage(), ['project_id' => $project->id, 'error' => $e->getTraceAsString()]);
        return response()->json(['message' => 'Failed to upload logo', 'error' => $e->getMessage()], 500);
    }
}
```

### Database Fields
The implementation uses existing database fields in the `projects` table:
- `logo`: Stores the path to the logo file in the local storage
- `logo_google_drive_file_id`: Stores the Google Drive file ID if the logo is uploaded to Google Drive

### Storage
The logo files are stored in the `logos` directory on the `public` disk. If a Google Drive folder ID is available for the project, the logo is also uploaded to Google Drive.

### Permissions
The endpoint requires the user to have permission to manage the project, which is checked using the `canManageProjects` method.

## Usage
To upload a logo for a project, send a POST request to `/api/projects/{project}/logo` with a file upload named "logo". The file must be an image and must not exceed 2MB in size.

### Example Request
```
POST /api/projects/1/logo
Content-Type: multipart/form-data

logo: [file]
```

### Example Response
```json
{
    "message": "Logo uploaded successfully",
    "logo": "logos/abcdef123456.jpg",
    "logo_google_drive_file_id": "1234567890abcdefghijklmnopqrstuvwxyz"
}
```

## Error Handling
The endpoint handles various error cases:
- 403 Forbidden: If the user does not have permission to manage the project
- 400 Bad Request: If no logo file is provided
- 422 Unprocessable Entity: If the validation fails (e.g., file is not an image or exceeds the size limit)
- 500 Internal Server Error: If an unexpected error occurs during the upload process

## Notes
- The implementation follows the same pattern as the existing logo upload code in the `store` and `update` methods of the `ProjectActionController` class.
- If a project already has a logo, the existing logo is deleted before the new one is stored.
- The logo is uploaded to Google Drive only if the project has a Google Drive folder ID.
