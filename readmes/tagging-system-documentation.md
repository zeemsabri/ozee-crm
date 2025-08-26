# Tagging System Documentation

This document provides an overview of the tagging system implementation in the Laravel application.

## Database Schema

The tagging system uses two tables:

1. **`tags` table**:
   - `id` (primary key, auto-increment)
   - `name` (string, unique)
   - `slug` (string, unique, automatically generated from name)
   - `timestamps`

2. **`taggables` pivot table**:
   - `tag_id` (unsigned big integer, foreign key to `tags.id`, onDelete cascade)
   - `taggable_id` (unsigned big integer, part of polymorphic relationship)
   - `taggable_type` (string, part of polymorphic relationship)
   - Composite primary key on `(tag_id, taggable_id, taggable_type)`

## Models

### Tag Model

The `Tag` model (`app/Models/Tag.php`) has been updated to:
- Use the `HasFactory` trait
- Have `$fillable` for `name` and `slug`
- Implement a `booted` method to automatically generate the `slug` from the `name` before creation
- Define polymorphic `morphedByMany` relationships for common taggable models (tasks, projects, documents)

### Taggable Trait

The `Taggable` trait (`app/Models/Traits/Taggable.php`) provides:
- A polymorphic `morphToMany` relationship named `tags()` to the `Tag` model
- A `syncTags(array|string $tags)` method that:
  - Accepts either a comma-separated string or an array of tag names
  - Trims and splits the string if necessary
  - Iterates through each tag name
  - For each name, `firstOrCreate` the `Tag` in the database (creating a `slug` if new)
  - Collects the IDs of all found/created tags
  - Uses the Eloquent `sync()` method on the `tags()` relationship to attach the collected tag IDs to the current model

## Middleware

The `ProcessTags` middleware (`app/Http/Middleware/ProcessTags.php`) pre-processes the `tags` input from incoming requests:
- Checks if the request has a `tags` input and if it's a string
- If true, explodes the comma-separated string into an array of individual tag names, trimming whitespace
- Iterates through each tag name
- For each name, uses `App\Models\Tag::firstOrCreate()` to find the tag by `name` or create it if it doesn't exist
- Collects the `id` of each processed tag
- Finally, uses `$request->merge(['tags' => $tagIds])` to overwrite the original `tags` string input with an array of tag IDs

## Controllers

### TagController

The `TagController` (`app/Http/Controllers/TagController.php`) provides:
- A `search(Request $request)` method that:
  - Accepts a `query` parameter from the request
  - Searches the `tags` table where the `name` is like the query string (e.g., `LIKE %query%`)
  - Limits results to 10
  - Returns a JSON response containing an array of objects, each with `id` and `name` of the matching tags

## Routes

The following routes have been added:
- `GET /api/tags/search` - Search for tags based on a query string
- The `process.tags` middleware has been registered and applied to the `store` and `update` methods of the `TaskController`

## Usage

### Using the Taggable Trait

To make a model taggable, add the `Taggable` trait to the model:

```php
use App\Models\Traits\Taggable;

class YourModel extends Model
{
    use HasFactory, Taggable;
    
    // ...
}
```

### Syncing Tags

To sync tags with a model, use the `syncTags` method:

```php
// Using a comma-separated string
$model->syncTags('tag1, tag2, tag3');

// Using an array
$model->syncTags(['tag1', 'tag2', 'tag3']);
```

### Retrieving Tags

To retrieve the tags for a model, use the `tags` relationship:

```php
// Get all tags for a model
$tags = $model->tags;

// Check if a model has a specific tag
if ($model->tags->contains('name', 'tag1')) {
    // Do something
}
```

### Using the ProcessTags Middleware

To apply the `ProcessTags` middleware to a route, add it to the route definition:

```php
Route::post('your-route', [YourController::class, 'store'])->middleware('process.tags');
```

Or apply it to specific methods of a resource controller:

```php
Route::apiResource('your-resource', YourController::class)->middleware([
    'process.tags' => ['only' => ['store', 'update']]
]);
```

### Searching for Tags

To search for tags, make a GET request to the `/api/tags/search` endpoint with a `query` parameter:

```javascript
// Example using fetch API
fetch('/api/tags/search?query=your-query')
    .then(response => response.json())
    .then(data => {
        // data is an array of objects with id and name properties
        console.log(data);
    });
```

## Migration

To apply the database migrations, run:

```bash
php artisan migrate
```

This will:
1. Modify the existing `tags` table to add the `slug` field, make the `name` field unique, and remove the `created_by_user_id` field
2. Create the new `taggables` pivot table for the polymorphic relationship
