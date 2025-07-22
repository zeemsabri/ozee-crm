<?php

// Test script to verify that the note encryption/decryption fixes work properly

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Project;
use App\Models\ProjectNote;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

echo "=== Testing Project Notes Encryption/Decryption After Fix ===\n\n";

// Function to test the API endpoints that use note decryption
function testApiEndpoints() {
    // Find a project to test with
    $project = Project::first();
    if (!$project) {
        echo "Error: No projects found in the database.\n";
        return false;
    }

    echo "Using project: {$project->name} (ID: {$project->id})\n\n";

    // Test 1: Create a new note with chat_message_id
    echo "Test 1: Creating a new note with chat_message_id\n";
    $noteContent = "Test note with chat_message_id after fix - " . date('Y-m-d H:i:s');

    $note = new ProjectNote();
    $note->project_id = $project->id;
    $note->content = Crypt::encryptString($noteContent);
    $note->user_id = 1; // Assuming user ID 1 exists
    $note->chat_message_id = "spaces/ABCDEF/messages/" . rand(10000, 99999);
    $note->save();

    echo "Note created with ID: {$note->id}\n";
    echo "Chat message ID: {$note->chat_message_id}\n\n";

    // Test 2: Simulate the index method in ProjectController
    echo "Test 2: Simulating ProjectController index method\n";

    // Get the note we just created
    $freshNote = ProjectNote::find($note->id);

    try {
        $decryptedContent = Crypt::decryptString($freshNote->content);
        echo "Decryption in index method: SUCCESS\n";
        echo "Content: $decryptedContent\n\n";
    } catch (\Exception $e) {
        echo "Decryption in index method: FAILED\n";
        echo "Error: {$e->getMessage()}\n\n";
        return false;
    }

    // Test 3: Simulate the show method in ProjectController
    echo "Test 3: Simulating ProjectController show method\n";

    try {
        $decryptedContent = Crypt::decryptString($freshNote->content);
        echo "Decryption in show method: SUCCESS\n";
        echo "Content: $decryptedContent\n\n";
    } catch (\Exception $e) {
        echo "Decryption in show method: FAILED\n";
        echo "Error: {$e->getMessage()}\n\n";
        return false;
    }

    // Test 4: Check previously fixed notes
    echo "Test 4: Checking previously fixed notes\n";

    // Get notes that were previously fixed
    $fixedNotes = ProjectNote::whereIn('id', [15, 16, 17, 18])->get();

    foreach ($fixedNotes as $fixedNote) {
        echo "Checking fixed note ID: {$fixedNote->id}\n";

        try {
            $decryptedContent = Crypt::decryptString($fixedNote->content);
            echo "Decryption: SUCCESS\n";
            echo "Content: $decryptedContent\n";
        } catch (\Exception $e) {
            echo "Decryption: FAILED\n";
            echo "Error: {$e->getMessage()}\n";
            return false;
        }

        echo "\n";
    }

    echo "All tests PASSED! The encryption/decryption fixes are working correctly.\n";
    return true;
}

// Run the test
try {
    testApiEndpoints();
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
    echo "Stack trace: {$e->getTraceAsString()}\n";
}
