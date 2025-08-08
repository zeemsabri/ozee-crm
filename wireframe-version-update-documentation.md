# Wireframe Version Update Implementation

## Overview
This document describes the implementation of a new API endpoint to update a specific version of a wireframe. The endpoint allows updating an existing wireframe version by specifying the version number as a query parameter.

## API Endpoint
- **URL**: `http://localhost:8000/api/projects/{projectId}/wireframes/{id}?version={versionNumber}`
- **Method**: PUT
- **Authentication**: Required (Laravel Sanctum)

## Request Parameters
- **projectId**: The ID of the project that the wireframe belongs to
- **id**: The ID of the wireframe
- **version**: The version number of the wireframe version to update (query parameter)

## Request Body
```json
{
  "data": {},
  "name": "Name"
}
```

- **data**: JSON object containing the wireframe data
- **name**: (Optional) New name for the wireframe

## Response
```json
{
  "wireframe": {
    "id": 1,
    "project_id": 2,
    "name": "Name",
    "created_at": "2023-01-01T00:00:00.000000Z",
    "updated_at": "2023-01-01T00:00:00.000000Z"
  },
  "version": {
    "id": 1,
    "wireframe_id": 1,
    "version_number": 1,
    "data": {},
    "status": "draft",
    "created_at": "2023-01-01T00:00:00.000000Z",
    "updated_at": "2023-01-01T00:00:00.000000Z"
  },
  "action": "updated_version"
}
```

## Implementation Details
The implementation modifies the existing `update` method in the `WireframeController` to check for a version query parameter. If a version parameter is provided and is numeric, the controller finds the specific version and updates it. If no version parameter is provided, the controller falls back to the original behavior of updating the latest draft or creating a new draft.

### Code Changes
The following changes were made to the `WireframeController`:

1. Added code to check for a version query parameter
2. Added logic to find and update a specific version if the version parameter is provided
3. Added activity logging for version updates
4. Added a new action type "updated_version" in the response

## Testing
A test script `test-wireframe-version-update.php` was created to verify the functionality. The script makes a PUT request to the endpoint with a version parameter and checks if the response indicates a successful update.

## Usage Example
```php
// PHP example using cURL
$ch = curl_init('http://localhost:8000/api/projects/2/wireframes/1?version=1');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'name' => 'Updated Wireframe Name',
    'data' => json_encode([
        'elements' => [
            [
                'id' => 'element1',
                'type' => 'text',
                'content' => 'This is updated content'
            ]
        ]
    ])
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
- If the name is provided, the wireframe's name will be updated
- The data field is required and must be valid JSON
