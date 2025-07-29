<?php

// Test script for universal polymorphic tagging system
// This script tests the tagging functionality with different models

// Bootstrap the Laravel application
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Project;
use App\Models\Document;
use App\Models\Email;
use App\Models\Milestone;
use App\Models\ProjectNote;
use App\Models\Resource;
use App\Models\Client;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;

echo "Starting universal tagging system test...\n\n";

// Function to test tagging on a model
function testTagging($model, $modelName) {
    echo "Testing tagging on {$modelName}...\n";

    // Get the first instance of the model
    $instance = $model::first();

    if (!$instance) {
        echo "  No {$modelName} instances found. Skipping test.\n";
        return;
    }

    echo "  Using {$modelName} with ID: {$instance->id}\n";

    // Test syncTags with array
    $tagArray = ['test-tag-1', 'test-tag-2', 'test-tag-3'];
    echo "  Syncing tags using array: " . implode(', ', $tagArray) . "\n";
    $instance->syncTags($tagArray);

    // Verify tags were added
    $tags = $instance->tags;
    echo "  Tags after sync: " . $tags->pluck('name')->implode(', ') . "\n";

    // Test syncTags with string
    $tagString = 'test-tag-4, test-tag-5, test-tag-6';
    echo "  Syncing tags using string: {$tagString}\n";
    $instance->syncTags($tagString);

    // Verify tags were updated
    $instance->refresh();
    $tags = $instance->tags;
    echo "  Tags after second sync: " . $tags->pluck('name')->implode(', ') . "\n";

    // Test tag relationship
    echo "  Testing tag relationship...\n";
    $tagCount = $instance->tags()->count();
    echo "  Tag count: {$tagCount}\n";

    // Test retrieving models by tag
    $firstTag = $instance->tags()->first();
    if ($firstTag) {
        echo "  Testing retrieving {$modelName}s with tag '{$firstTag->name}'...\n";
        $taggedModels = $firstTag->$modelName()->get();
        echo "  Found " . $taggedModels->count() . " {$modelName}(s) with this tag.\n";
    }

    echo "  Test completed for {$modelName}.\n\n";
}

// Test tagging on each model
try {
    DB::beginTransaction();

    testTagging(new Project(), 'projects');
    testTagging(new Document(), 'documents');
    testTagging(new Email(), 'emails');
    testTagging(new Milestone(), 'milestones');
    testTagging(new ProjectNote(), 'projectNotes');
    testTagging(new Resource(), 'resources');
    testTagging(new Client(), 'clients');

    // Show all tags in the system
    echo "All tags in the system:\n";
    $allTags = Tag::all();
    foreach ($allTags as $tag) {
        echo "  - {$tag->name} (ID: {$tag->id}, Slug: {$tag->slug})\n";
        echo "    Used by: ";
        echo "Projects: " . $tag->projects()->count() . ", ";
        echo "Documents: " . $tag->documents()->count() . ", ";
        echo "Emails: " . $tag->emails()->count() . ", ";
        echo "Milestones: " . $tag->milestones()->count() . ", ";
        echo "ProjectNotes: " . $tag->projectNotes()->count() . ", ";
        echo "Resources: " . $tag->resources()->count() . ", ";
        echo "Clients: " . $tag->clients()->count() . "\n";
    }

    // Rollback the transaction to avoid affecting the database
    DB::rollBack();
    echo "\nTest completed. All changes have been rolled back.\n";
} catch (Exception $e) {
    DB::rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
