<?php

// This is a simple test script to verify that the ProjectDeliverableAction controller
// correctly creates ProjectNote instances instead of DeliverableComment instances.

// Import necessary classes
use App\Http\Controllers\Api\ProjectDashboard\ProjectDeliverableAction;
use App\Models\Deliverable;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\User;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// Bootstrap Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Find a test project and deliverable
$project = Project::first();
if (! $project) {
    echo "No projects found. Please create a project first.\n";
    exit(1);
}

$deliverable = Deliverable::where('project_id', $project->id)->first();
if (! $deliverable) {
    echo "No deliverables found for project {$project->id}. Please create a deliverable first.\n";
    exit(1);
}

// Find a test user
$user = User::first();
if (! $user) {
    echo "No users found. Please create a user first.\n";
    exit(1);
}

// Login as the test user
Auth::login($user);

// Create a mock request with comment data
$request = Request::create(
    "/api/projects/{$project->id}/deliverables/{$deliverable->id}/comments",
    'POST',
    [
        'comment_text' => 'This is a test comment from the test script.',
        'context' => 'Test context',
    ]
);

// Create an instance of the controller
$controller = new ProjectDeliverableAction(new GoogleDriveService);

// Call the addComment method
$response = $controller->addComment($request, $project, $deliverable);

// Check if the response is successful
$responseData = json_decode($response->getContent(), true);
if ($response->getStatusCode() === 201 && isset($responseData['message']) && $responseData['message'] === 'Comment added successfully.') {
    echo "Test passed! Comment added successfully.\n";

    // Verify that a ProjectNote was created
    echo "Looking for ProjectNotes related to deliverable ID: {$deliverable->id}\n";

    // Check all ProjectNotes for this deliverable
    $notes = ProjectNote::where('noteable_id', $deliverable->id)
        ->where('noteable_type', get_class($deliverable))
        ->latest()
        ->get();

    if ($notes->count() > 0) {
        echo "Found {$notes->count()} ProjectNotes for this deliverable.\n";

        $noteNumber = 1;
        foreach ($notes as $note) {
            echo "\nNote #{$noteNumber}:\n";
            $noteNumber++;
            echo "ID: {$note->id}\n";
            echo 'Content: '.($note->content ?? 'NULL')."\n";
            echo "Creator ID: {$note->creator_id}\n";
            echo "Creator Type: {$note->creator_type}\n";
            echo "Created at: {$note->created_at}\n";
        }

        // Also check the response data
        echo "\nResponse data from controller:\n";
        print_r($responseData);
    } else {
        echo "Error: No ProjectNotes found for this deliverable.\n";

        // Check if there are any ProjectNotes in the database at all
        $totalNotes = ProjectNote::count();
        echo "Total ProjectNotes in database: {$totalNotes}\n";

        if ($totalNotes > 0) {
            $latestNotes = ProjectNote::latest()->take(5)->get();
            echo "Latest 5 ProjectNotes:\n";
            foreach ($latestNotes as $note) {
                echo "ID: {$note->id}, Noteable ID: {$note->noteable_id}, Noteable Type: {$note->noteable_type}, Created: {$note->created_at}\n";
            }
        }
    }
} else {
    echo "Test failed! Response status code: {$response->getStatusCode()}\n";
    echo "Response content: {$response->getContent()}\n";
}
