# Wireframe Version Update API (Path Parameter)

## Overview
This document describes the implementation of a new API endpoint to update a specific version of a wireframe using path parameters. The endpoint allows updating an existing wireframe version by specifying the version number directly in the URL path.

## API Endpoint
- **URL**: `http://localhost:8000/api/projects/{projectId}/wireframes/{id}/versions/{versionNumber}`
- **Method**: PUT
- **Authentication**: Required (Laravel Sanctum)

## Request Parameters
- **projectId**: The ID of the project that the wireframe belongs to
- **id**: The ID of the wireframe
- **versionNumber**: The version number of the wireframe version to update

## Request Body
```json
{
  "name": "Draft"
}
```

- **name**: New name for the wireframe

## Response
```json
{
  "wireframe": {
    "id": 1,
    "project_id": 2,
    "name": "Draft",
    "created_at": "2023-01-01T00:00:00.000000Z",
    "updated_at": "2023-01-01T00:00:00.000000Z"
  },
  "version": {
    "id": 3,
    "wireframe_id": 1,
    "version_number": 3,
    "data": {},
    "status": "draft",
    "created_at": "2023-01-01T00:00:00.000000Z",
    "updated_at": "2023-01-01T00:00:00.000000Z"
  }
}
```

## Implementation Details
The implementation adds a new route and controller method to handle updating a specific wireframe version:

1. Added a new route `PUT projects/{projectId}/wireframes/{id}/versions/{versionNumber}` that maps to the `updateVersion` method in the `WireframeController`
2. Implemented the `updateVersion` method that:
   - Validates the request (requires a name field)
   - Finds the wireframe and specific version
   - Updates the wireframe name
   - Logs the activity
   - Returns the updated wireframe and version

## Testing
A test script `test-wireframe-version-path-update.php` was created to verify the functionality. The script makes a PUT request to the endpoint and checks if the response indicates a successful update.

## Usage Example
```php
// PHP example using cURL
$ch = curl_init('http://localhost:8000/api/projects/2/wireframes/1/versions/3');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'name' => 'Draft'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json',
    'Authorization: Bearer YOUR_TOKEN_HERE'
]);
$response = curl_exec($ch);
curl_close($ch);
```

## Notes
- The endpoint requires authentication
- The wireframe must exist and belong to the specified project
- The version must exist for the specified wireframe
- Only the wireframe name is updated, not the version data
