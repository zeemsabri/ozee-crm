# Backend Integration Guide: Chat Pagination

To improve performance and support local caching in the NativePHP application, the Chat API needs to support cursor-based pagination.

## Endpoints to Update

### `GET /projects/{projectId}/chat`

#### Current Request
`GET /projects/{projectId}/chat?topic_id={topicId}`

#### Proposed Request
`GET /projects/{projectId}/chat?topic_id={topicId}&since_id={id}&before_id={id}&per_page={per_page}`

**Parameters:**
- `topic_id` (required): The ID of the topic.
- `since_id` (optional): Return messages created **after** this ID (used for fetching new messages).
- `before_id` (optional): Return messages created **before** this ID (used for loading older history).
- `per_page` (optional, default: 50): Number of messages to return.

## Implementation Details

### Recommended Logic for Backend
To support both real-time updates and historical scrolling, the backend should handle the `since_id` and `before_id` filters. **Important**: Always return messages in **descending order (newest first)** to simplify pagination.

```php
$query = Message::where('project_id', $projectId)
    ->where('topic_id', $topicId);

if ($request->has('since_id')) {
    // Return messages newer than since_id
    $query->where('id', '>', $request->since_id);
} elseif ($request->has('before_id')) {
    // Return messages older than before_id
    $query->where('id', '<', $request->before_id);
}

// Always order by Newest First
return $query->orderBy('id', 'desc')->paginate($request->input('per_page', 50));
```

## Expected Response Format

The response should include a way to know if more data exists:

```json
{
    "data": [...],
    "meta": {
        "current_page": 1,
        "per_page": 50,
        "total": 1500,
        "has_more": true 
    }
}
```

## Delta Sync (Optional but Recommended)

To speed up initial app load, it would be beneficial to have a `since_id` parameter:
- `GET /projects/{projectId}/chat?topic_id={topicId}&since_id={lastLocalId}`
- Returns all messages created *after* `since_id`.
- If no `since_id` is provided, return the latest `per_page` messages.
