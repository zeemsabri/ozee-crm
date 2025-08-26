<?php

// Test script to reproduce and verify the encryption/decryption issue with project notes
// after adding chat_message_id field

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Project;
use App\Models\ProjectNote;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

echo "=== Testing Project Notes Encryption/Decryption ===\n\n";

// Function to test note creation and retrieval
function testNoteEncryptionDecryption() {
    // Find a project to test with
    $project = Project::first();
    if (!$project) {
        echo "Error: No projects found in the database.\n";
        return false;
    }

    echo "Using project: {$project->name} (ID: {$project->id})\n\n";

    // Test case 1: Create a note without chat_message_id
    echo "Test Case 1: Create a note without chat_message_id\n";
    $noteContent = "Test note without chat_message_id - " . date('Y-m-d H:i:s');
    $encryptedContent = Crypt::encryptString($noteContent);

    $note1 = new ProjectNote();
    $note1->project_id = $project->id;
    $note1->content = $encryptedContent;
    $note1->user_id = 1; // Assuming user ID 1 exists
    $note1->save();

    echo "Note created with ID: {$note1->id}\n";

    // Test case 2: Create a note with chat_message_id
    echo "\nTest Case 2: Create a note with chat_message_id\n";
    $noteContent2 = "Test note with chat_message_id - " . date('Y-m-d H:i:s');
    $encryptedContent2 = Crypt::encryptString($noteContent2);

    $note2 = new ProjectNote();
    $note2->project_id = $project->id;
    $note2->content = $encryptedContent2;
    $note2->user_id = 1; // Assuming user ID 1 exists
    $note2->chat_message_id = "spaces/ABCDEF/messages/" . rand(10000, 99999);
    $note2->save();

    echo "Note created with ID: {$note2->id}\n";
    echo "Chat message ID: {$note2->chat_message_id}\n";

    // Test case 3: Update an existing note to add chat_message_id
    echo "\nTest Case 3: Update an existing note to add chat_message_id\n";
    $noteContent3 = "Test note for updating - " . date('Y-m-d H:i:s');
    $encryptedContent3 = Crypt::encryptString($noteContent3);

    $note3 = new ProjectNote();
    $note3->project_id = $project->id;
    $note3->content = $encryptedContent3;
    $note3->user_id = 1; // Assuming user ID 1 exists
    $note3->save();

    echo "Note created with ID: {$note3->id}\n";

    // Now update the note to add chat_message_id
    $note3->chat_message_id = "spaces/ABCDEF/messages/" . rand(10000, 99999);
    $note3->save();

    echo "Note updated with chat_message_id: {$note3->chat_message_id}\n";

    // Now retrieve and try to decrypt all notes
    echo "\nRetrieving and decrypting notes...\n";

    // Retrieve notes
    $notes = ProjectNote::whereIn('id', [$note1->id, $note2->id, $note3->id])->get();

    foreach ($notes as $note) {
        echo "\nNote ID: {$note->id}\n";
        echo "Has chat_message_id: " . ($note->chat_message_id ? "Yes ({$note->chat_message_id})" : "No") . "\n";

        try {
            $decryptedContent = Crypt::decryptString($note->content);
            echo "Decryption: SUCCESS\n";
            echo "Content: {$decryptedContent}\n";
        } catch (\Exception $e) {
            echo "Decryption: FAILED\n";
            echo "Error: {$e->getMessage()}\n";
        }
    }

    echo "\nTest completed.\n";
    return true;
}

// Run the test
try {
    testNoteEncryptionDecryption();
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
    echo "Stack trace: {$e->getTraceAsString()}\n";
}
