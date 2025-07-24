<?php

// This script tests the notes search functionality in the ProjectSectionController

// Bootstrap the Laravel application
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

// Import necessary classes
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\ProjectSectionController;

// Get a project with notes
$project = Project::has('notes')->first();

if (!$project) {
    echo "No project with notes found. Test cannot continue.\n";
    exit(1);
}

// Get a user with access to the project
$user = User::whereHas('projects', function($query) use ($project) {
    $query->where('projects.id', $project->id);
})->first();

if (!$user) {
    echo "No user with access to the project found. Test cannot continue.\n";
    exit(1);
}

// Login as the user
Auth::login($user);

echo "Testing notes search functionality for project ID: {$project->id}\n";
echo "Logged in as user: {$user->name} (ID: {$user->id})\n\n";

// Create a controller instance
$controller = new ProjectSectionController();

// Test 1: Get all notes (no filters)
$request = new Request();
$response = $controller->getNotes($project, $request);
$allNotes = json_decode($response->getContent());
echo "Total notes found: " . count($allNotes) . "\n";

// Test 2: Filter by date range
$startDate = date('Y-m-d', strtotime('-30 days'));
$endDate = date('Y-m-d');
$request = new Request([
    'start_date' => $startDate,
    'end_date' => $endDate
]);
$response = $controller->getNotes($project, $request);
$dateFilteredNotes = json_decode($response->getContent());
echo "Notes in last 30 days: " . count($dateFilteredNotes) . "\n";

// Test 3: Search for a term that should exist in at least one note
// First, let's get a word from one of the decrypted notes to search for
$searchWord = null;
foreach ($allNotes as $note) {
    if ($note->content !== '[Encrypted content could not be decrypted]') {
        // Get the first word that's at least 5 characters long
        $words = explode(' ', $note->content);
        foreach ($words as $word) {
            if (strlen($word) >= 5) {
                $searchWord = $word;
                break 2;
            }
        }
    }
}

if ($searchWord) {
    echo "Searching for term: '{$searchWord}'\n";
    $request = new Request([
        'search' => $searchWord
    ]);
    $response = $controller->getNotes($project, $request);
    $searchFilteredNotes = json_decode($response->getContent());
    echo "Notes containing '{$searchWord}': " . count($searchFilteredNotes) . "\n";

    if (count($searchFilteredNotes) > 0) {
        echo "✅ Text search is working correctly!\n";
    } else {
        echo "❌ Text search returned no results. This might be an issue.\n";
    }
} else {
    echo "Could not find a suitable search term in the notes.\n";
}

// Test 4: Search for a random term that likely doesn't exist
$randomTerm = 'xyzabc123456';
echo "\nSearching for random term: '{$randomTerm}'\n";
$request = new Request([
    'search' => $randomTerm
]);
$response = $controller->getNotes($project, $request);
$randomSearchNotes = json_decode($response->getContent());
echo "Notes containing '{$randomTerm}': " . count($randomSearchNotes) . "\n";

if (count($randomSearchNotes) === 0) {
    echo "✅ Random term search correctly returned no results.\n";
} else {
    echo "❌ Random term search unexpectedly returned results. This might be an issue.\n";
}

echo "\nTest completed.\n";
