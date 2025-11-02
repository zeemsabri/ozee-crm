<?php

// Test script to simulate the request from NotesModal component

require_once __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

echo "=== Testing NotesModal Request Format ===\n\n";

// Find a user and project to use for testing
$user = User::first();
if (! $user) {
    echo "Error: No users found in the database.\n";
    exit(1);
}

$project = Project::first();
if (! $project) {
    echo "Error: No projects found in the database.\n";
    exit(1);
}

echo "Using user: {$user->name} (ID: {$user->id})\n";
echo "Using project: {$project->name} (ID: {$project->id})\n\n";

// Login as the user
Auth::login($user);
echo "Logged in as: {$user->name}\n\n";

// Create a request object that mimics the NotesModal component's request
$requestData = [
    'notes' => [
        [
            'content' => 'Test note from simulation script - '.date('Y-m-d H:i:s'),
        ],
    ],
];

echo 'Request data: '.json_encode($requestData, JSON_PRETTY_PRINT)."\n\n";

// Get the controller instance
$controller = app()->make(\App\Http\Controllers\Api\ProjectController::class);

// Create a request instance
$request = Request::create("/api/projects/{$project->id}/notes", 'POST', $requestData);
$request->setUserResolver(function () use ($user) {
    return $user;
});

// Execute the controller method directly
try {
    echo "Calling ProjectController::addNotes method directly...\n";
    $response = $controller->addNotes($request, $project);

    // Get the response content
    $content = $response->getContent();
    $statusCode = $response->getStatusCode();

    echo "Response status code: {$statusCode}\n";
    echo "Response content: {$content}\n\n";

    // Check if the note was created with encryption
    $data = json_decode($content, true);
    if (is_array($data) && ! empty($data)) {
        $noteId = $data[0]['id'] ?? null;

        if ($noteId) {
            echo "Note created with ID: {$noteId}\n";

            // Fetch the note from the database
            $note = \App\Models\ProjectNote::find($noteId);

            if ($note) {
                echo 'Note content in database: '.substr($note->content, 0, 50).(strlen($note->content) > 50 ? '...' : '')."\n";

                // Check if content appears to be encrypted
                $appearsEncrypted = (
                    strpos($note->content, 'eyJ') === 0 && // Laravel encryption typically starts with this
                    strpos($note->content, ':') !== false && // Contains colons
                    strlen($note->content) > 100 // Encrypted content is usually long
                );

                echo 'Appears encrypted: '.($appearsEncrypted ? 'Yes' : 'No')."\n";

                // Try to decrypt
                try {
                    $decrypted = \Illuminate\Support\Facades\Crypt::decryptString($note->content);
                    echo "Decryption: SUCCESS\n";
                    echo 'Decrypted content: '.$decrypted."\n";
                } catch (\Exception $e) {
                    echo "Decryption: FAILED\n";
                    echo "Error: {$e->getMessage()}\n";
                }
            } else {
                echo "Error: Could not find the created note in the database.\n";
            }
        } else {
            echo "Error: No note ID found in the response.\n";
        }
    } else {
        echo "Error: Invalid response format.\n";
    }
} catch (\Exception $e) {
    echo "Error executing controller method: {$e->getMessage()}\n";
    echo "Stack trace: {$e->getTraceAsString()}\n";
}

echo "\n=== Test completed ===\n";
