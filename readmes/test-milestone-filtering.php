<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Project;
use App\Models\Milestone;
use Illuminate\Support\Facades\Route;

echo "Testing milestone filtering...\n\n";

// Step 1: Find or create test projects
echo "Step 1: Setting up test projects and milestones\n";
$project1 = Project::first();
if (!$project1) {
    echo "No projects found. Please create a project first.\n";
    exit;
}

$project2 = Project::skip(1)->first();
if (!$project2 && $project1) {
    // Create a second project if only one exists
    $project2 = new Project();
    $project2->name = "Test Project 2 for Milestone Filtering";
    $project2->description = "This is a test project for milestone filtering";
    $project2->status = "active";
    $project2->project_type = "Test";
    $project2->payment_type = "one_off";
    $project2->save();
    echo "Created second test project: {$project2->name} (ID: {$project2->id})\n";
} elseif (!$project2) {
    echo "Could not find or create a second project.\n";
    exit;
}

echo "Using projects:\n";
echo "- Project 1: {$project1->name} (ID: {$project1->id})\n";
echo "- Project 2: {$project2->name} (ID: {$project2->id})\n";

// Step 2: Create test milestones for each project
echo "\nStep 2: Creating test milestones\n";

// Delete existing test milestones
Milestone::where('name', 'like', 'Test Milestone for Filtering%')->delete();

// Create milestones for project 1
$milestone1_1 = new Milestone();
$milestone1_1->name = "Test Milestone for Filtering 1-1";
$milestone1_1->description = "This is test milestone 1 for project 1";
$milestone1_1->status = "Not Started";
$milestone1_1->project_id = $project1->id;
$milestone1_1->save();

$milestone1_2 = new Milestone();
$milestone1_2->name = "Test Milestone for Filtering 1-2";
$milestone1_2->description = "This is test milestone 2 for project 1";
$milestone1_2->status = "In Progress";
$milestone1_2->project_id = $project1->id;
$milestone1_2->save();

// Create milestones for project 2
$milestone2_1 = new Milestone();
$milestone2_1->name = "Test Milestone for Filtering 2-1";
$milestone2_1->description = "This is test milestone 1 for project 2";
$milestone2_1->status = "Not Started";
$milestone2_1->project_id = $project2->id;
$milestone2_1->save();

$milestone2_2 = new Milestone();
$milestone2_2->name = "Test Milestone for Filtering 2-2";
$milestone2_2->description = "This is test milestone 2 for project 2";
$milestone2_2->status = "Completed";
$milestone2_2->project_id = $project2->id;
$milestone2_2->save();

echo "Created test milestones:\n";
echo "- Project 1: {$milestone1_1->name} (ID: {$milestone1_1->id})\n";
echo "- Project 1: {$milestone1_2->name} (ID: {$milestone1_2->id})\n";
echo "- Project 2: {$milestone2_1->name} (ID: {$milestone2_1->id})\n";
echo "- Project 2: {$milestone2_2->name} (ID: {$milestone2_2->id})\n";

// Step 3: Test the general route with project_id query parameter
echo "\nStep 3: Testing general route with project_id query parameter\n";

// Simulate a request to /api/milestones?project_id=X
$request = Request::create("/api/milestones?project_id={$project1->id}", 'GET');
$controller = new App\Http\Controllers\Api\MilestoneController();
$response = $controller->index($request);
$milestones = json_decode($response->getContent(), true);

echo "Milestones for Project 1 using query parameter:\n";
foreach ($milestones as $milestone) {
    echo "- {$milestone['name']} (ID: {$milestone['id']}, Project ID: {$milestone['project_id']})\n";
}

// Verify that only project 1 milestones are returned
$correctFiltering = true;
foreach ($milestones as $milestone) {
    if ($milestone['project_id'] != $project1->id) {
        $correctFiltering = false;
        echo "❌ Found milestone with incorrect project_id: {$milestone['project_id']} (expected: {$project1->id})\n";
    }
}

if ($correctFiltering) {
    echo "✅ All milestones have the correct project_id\n";
} else {
    echo "❌ Some milestones have incorrect project_id\n";
}

// Step 4: Test the project-specific route
echo "\nStep 4: Testing project-specific route\n";

// Create a route for testing
Route::get('test-project-milestones/{project}', [App\Http\Controllers\Api\MilestoneController::class, 'index']);

// Simulate a request to /api/projects/X/milestones
$request = Request::create("/test-project-milestones/{$project2->id}", 'GET');
$request->setRouteResolver(function () use ($request, $project2) {
    $route = new \Illuminate\Routing\Route('GET', 'test-project-milestones/{project}', []);
    $route->bind($request);
    $route->setParameter('project', $project2);
    return $route;
});

$controller = new App\Http\Controllers\Api\MilestoneController();
$response = $controller->index($request);
$milestones = json_decode($response->getContent(), true);

echo "Milestones for Project 2 using route parameter:\n";
foreach ($milestones as $milestone) {
    echo "- {$milestone['name']} (ID: {$milestone['id']}, Project ID: {$milestone['project_id']})\n";
}

// Verify that only project 2 milestones are returned
$correctFiltering = true;
foreach ($milestones as $milestone) {
    if ($milestone['project_id'] != $project2->id) {
        $correctFiltering = false;
        echo "❌ Found milestone with incorrect project_id: {$milestone['project_id']} (expected: {$project2->id})\n";
    }
}

if ($correctFiltering) {
    echo "✅ All milestones have the correct project_id\n";
} else {
    echo "❌ Some milestones have incorrect project_id\n";
}

// Step 5: Clean up test data
echo "\nStep 5: Cleaning up test data\n";
$milestone1_1->delete();
$milestone1_2->delete();
$milestone2_1->delete();
$milestone2_2->delete();

// Only delete project2 if we created it
if (isset($createdProject2) && $createdProject2) {
    $project2->delete();
    echo "Deleted test project 2\n";
}

echo "Deleted test milestones\n";
echo "\nTest completed.\n";
