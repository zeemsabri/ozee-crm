<?php

// Test script to verify the decryption issue in Show.vue vs ProjectForm
// This script simulates API calls to both endpoints to compare responses

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Project;
use App\Models\ProjectNote;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

echo "=== Testing Project Notes Decryption in Show.vue vs ProjectForm ===\n\n";

// Function to test both API endpoints for a project
function testProjectNotesDecryption($projectId) {
    // Find the project
    $project = Project::find($projectId);
    if (!$project) {
        echo "Error: Project with ID $projectId not found.\n";
        return false;
    }

    echo "Testing project: {$project->name} (ID: {$project->id})\n\n";

    // Find a super admin user to use for authentication
    $superAdmin = User::whereHas('role', function($query) {
        $query->where('slug', 'super-admin');
    })->first();

    if (!$superAdmin) {
        echo "Error: No super admin user found for testing.\n";
        return false;
    }

    // Login as super admin
    Auth::login($superAdmin);
    echo "Logged in as: {$superAdmin->name} (ID: {$superAdmin->id})\n\n";

    // Test 1: Call the ProjectController show method (used by Show.vue)
    echo "Test 1: Calling ProjectController show method (used by Show.vue)\n";
    $controller = new \App\Http\Controllers\Api\ProjectController(
        app(\App\Services\GmailService::class),
        app(\App\Services\GoogleDriveService::class),
        app(\App\Services\GoogleChatService::class)
    );

    $response1 = $controller->show($project);
    $data1 = json_decode($response1->getContent(), true);

    echo "Response status: " . $response1->getStatusCode() . "\n";

    if (isset($data1['notes']) && is_array($data1['notes'])) {
        echo "Notes found: " . count($data1['notes']) . "\n";

        // Check the first few notes
        $notesToShow = min(3, count($data1['notes']));
        for ($i = 0; $i < $notesToShow; $i++) {
            $note = $data1['notes'][$i];
            echo "Note #" . ($i + 1) . " (ID: {$note['id']}):\n";
            echo "- Content: " . (strlen($note['content']) > 50 ?
                substr($note['content'], 0, 50) . "..." :
                $note['content']) . "\n";

            // Check if this note has the placeholder text (indicating decryption failed)
            if ($note['content'] === '[Encrypted content could not be decrypted]') {
                echo "  (This note had a decryption error, but was handled gracefully)\n";
            }

            echo "\n";
        }
    } else {
        echo "No notes found in the response.\n";
    }

    echo "\n";

    // Test 2: Call the ProjectSectionController getNotes method (used by ProjectForm)
    echo "Test 2: Calling ProjectSectionController getNotes method (used by ProjectForm)\n";
    $sectionController = new \App\Http\Controllers\Api\ProjectSectionController();

    $response2 = $sectionController->getNotes($project);
    $data2 = json_decode($response2->getContent(), true);

    echo "Response status: " . $response2->getStatusCode() . "\n";

    if (is_array($data2) && count($data2) > 0) {
        echo "Notes found: " . count($data2) . "\n";

        // Check the first few notes
        $notesToShow = min(3, count($data2));
        for ($i = 0; $i < $notesToShow; $i++) {
            $note = $data2[$i];
            echo "Note #" . ($i + 1) . " (ID: {$note['id']}):\n";
            echo "- Content: " . (strlen($note['content']) > 50 ?
                substr($note['content'], 0, 50) . "..." :
                $note['content']) . "\n";

            // Check if this note has the placeholder text (indicating decryption failed)
            if ($note['content'] === '[Encrypted content could not be decrypted]') {
                echo "  (This note had a decryption error, but was handled gracefully)\n";
            }

            echo "\n";
        }
    } else {
        echo "No notes found in the response.\n";
    }

    // Compare the two responses
    echo "\n=== Comparison ===\n";
    $notes1Count = isset($data1['notes']) ? count($data1['notes']) : 0;
    $notes2Count = is_array($data2) ? count($data2) : 0;

    echo "ProjectController (Show.vue) notes count: $notes1Count\n";
    echo "ProjectSectionController (ProjectForm) notes count: $notes2Count\n\n";

    if ($notes1Count > 0 && $notes2Count > 0) {
        // Compare the first note from each response
        $note1 = $data1['notes'][0];
        $note2 = $data2[0];

        echo "First note comparison:\n";
        echo "- Show.vue note ID: {$note1['id']}, content: " . substr($note1['content'], 0, 30) . "...\n";
        echo "- ProjectForm note ID: {$note2['id']}, content: " . substr($note2['content'], 0, 30) . "...\n";

        if ($note1['id'] === $note2['id']) {
            echo "Same note ID, content " . ($note1['content'] === $note2['content'] ? "matches" : "differs") . "\n";
        } else {
            echo "Different note IDs\n";
        }
    }

    return true;
}

// Find the first project in the database
$project = Project::first();
if ($project) {
    echo "Found project with ID: {$project->id}\n";
    testProjectNotesDecryption($project->id);
} else {
    echo "No projects found in the database.\n";
}

echo "\n=== Test completed ===\n";
