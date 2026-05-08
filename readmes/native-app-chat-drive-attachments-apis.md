# Native App Chat Attachment APIs (CRM Parity)

This document covers the native-app chat endpoints needed to match CRM behavior for:

- Browsing Google Drive folders and selecting files to share
- Creating a new Google Doc and sharing it in chat
- Uploading image or document files and sharing in chat

All endpoints are under:

- `/api/native-app`

Authentication:

- `auth:sanctum` is required on all endpoints in this document.
- Send `Authorization: Bearer <token>`.

## Common Context

Path parameter:

- `project` (integer): project id

Optional chat context fields used by most write endpoints:

- `message` (string, optional): caption/text with the attachment
- `parent_id` (integer, optional): reply target chat message id
- `telegram_topic_id` (integer, optional): target topic id

## 1) Browse Google Drive Folder

Endpoint:

- `GET /api/native-app/projects/{project}/chat/drive-documents/browse`

Query params:

- `folder_id` (string, optional): Drive folder id to browse. If omitted, project root Drive folder is used.
- `page_size` (integer, optional, min 1, max 200, default 100)

Success response (200):

```json
{
  "root_folder_id": "1AbC...",
  "current_folder_id": "1AbC...",
  "items": [
    {
      "id": "17x...",
      "name": "Design Brief",
      "mime_type": "application/vnd.google-apps.document",
      "is_folder": false,
      "web_view_link": "https://docs.google.com/document/d/.../edit",
      "thumbnail": "https://...",
      "size": null,
      "modified_time": "2026-05-09T10:00:00.000Z",
      "parents": ["1AbC..."]
    },
    {
      "id": "9Yz...",
      "name": "Assets",
      "mime_type": "application/vnd.google-apps.folder",
      "is_folder": true,
      "web_view_link": null,
      "thumbnail": null,
      "size": null,
      "modified_time": "2026-05-08T09:00:00.000Z",
      "parents": ["1AbC..."]
    }
  ]
}
```

Common errors:

- `422` when project Drive folder is not configured
- `422` when Drive API fetch fails

Example:

```bash
curl -X GET "https://<host>/api/native-app/projects/123/chat/drive-documents/browse?page_size=100" \
  -H "Authorization: Bearer <token>" \
  -H "Accept: application/json"
```

## 2) Share Existing Google Drive File

Endpoint:

- `POST /api/native-app/projects/{project}/chat/drive-documents/reference`

Body (JSON):

- `drive_file_id` (string, optional)
- `drive_url` (url string, optional)
- At least one of `drive_file_id` or `drive_url` is required
- `name` (string, optional)
- `mime_type` (string, optional)
- `message`, `parent_id`, `telegram_topic_id` (optional chat context)

Success response (200):

- Returns the created chat message with `attachments` populated.

```json
{
  "id": 9876,
  "type": "file",
  "user": "John Doe",
  "user_id": 11,
  "client_id": null,
  "initials": "JO",
  "color": "bg-indigo-600",
  "message": "Shared a Google Drive file",
  "parent": null,
  "reads": [],
  "time": "1 second ago",
  "created_at": "2026-05-09 12:30:14",
  "is_me": true,
  "source": "crm",
  "attachments": [
    {
      "id": 4567,
      "filename": "Specs v2",
      "mime_type": "application/vnd.google-apps.document",
      "file_size": null,
      "path": "https://docs.google.com/document/d/.../edit",
      "url": "https://docs.google.com/document/d/.../edit",
      "thumbnail_url": "https://...",
      "google_drive_file_id": "17x..."
    }
  ]
}
```

Common errors:

- `422` when neither `drive_file_id` nor `drive_url` is provided
- `422` when validation fails

Example:

```bash
curl -X POST "https://<host>/api/native-app/projects/123/chat/drive-documents/reference" \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "drive_file_id": "17x...",
    "name": "Specs v2",
    "mime_type": "application/vnd.google-apps.document",
    "message": "Please review",
    "telegram_topic_id": 44
  }'
```

## 3) Create New Google Document and Share

Endpoint:

- `POST /api/native-app/projects/{project}/chat/drive-documents/create`

Body (JSON):

- `title` (string, required, max 255)
- `message`, `parent_id`, `telegram_topic_id` (optional chat context)

Success response (200):

- Returns the created chat message with one Drive attachment.

Common errors:

- `422` when validation fails
- `422` when Drive document creation fails

Example:

```bash
curl -X POST "https://<host>/api/native-app/projects/123/chat/drive-documents/create" \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "title": "Client Notes - Sprint 5",
    "message": "Draft created",
    "telegram_topic_id": 44
  }'
```

## 4) Upload Image/Document and Share

Endpoint:

- `POST /api/native-app/projects/{project}/chat/attachments`

Body (multipart/form-data):

- `files[]` (required, one or more files)
- `files.*` max size: 20480 KB (20 MB per file)
- `message`, `parent_id`, `telegram_topic_id` (optional chat context)

Success response (200):

- Returns the created chat message with uploaded file attachments.

Common errors:

- `422` when no files are sent
- `422` when a file exceeds size limits or upload/processing fails

Example:

```bash
curl -X POST "https://<host>/api/native-app/projects/123/chat/attachments" \
  -H "Authorization: Bearer <token>" \
  -H "Accept: application/json" \
  -F "files[]=@/path/to/mockup.png" \
  -F "files[]=@/path/to/spec.pdf" \
  -F "message=Latest assets" \
  -F "telegram_topic_id=44"
```

## Client Integration Notes (for CRM-like UX)

- Use browse endpoint to navigate folders and list files.
- For folder items where `is_folder=true`, call browse again with `folder_id=<item.id>`.
- For file items where `is_folder=false`, call reference endpoint with `drive_file_id`.
- For creating docs, call create endpoint then render returned attachment immediately.
- For upload flow, call attachments endpoint and render returned message immediately.
- Do not wait only on realtime events to render new attachment messages; append API response immediately and de-duplicate by message id when realtime event arrives.
