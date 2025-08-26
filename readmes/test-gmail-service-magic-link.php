<?php

/**
 * This script tests the MagicLinkController's ability to send emails using the GmailService.
 *
 * To run this test:
 * 1. Make sure you have a valid Google API token in storage/app/google_tokens.json
 * 2. Run this script with: php test-gmail-service-magic-link.php
 */

// Bootstrap the Laravel application
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\MagicLinkController;
use App\Models\Project;
use App\Services\GmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

// Set up logging
Log::info('Starting GmailService Magic Link test');

try {
    // Create a test project if needed
    $project = Project::first();
    if (!$project) {
        echo "No projects found in the database. Please create a project first.\n";
        exit(1);
    }

    // Make sure the project has at least one client
    if (!$project->clients || !count($project->clients)) {
        echo "The project has no clients. Please add a client to the project first.\n";
        exit(1);
    }

    // Get the first client's ID and email
    $client = $project->clients[0];
    $clientId = $client->id;
    $clientEmail = $client->email;

    // Create a request with the client's ID (updated to use client_id instead of email)
    $request = Request::create('/api/projects/' . $project->id . '/magic-link', 'POST', [
        'client_id' => $clientId
    ]);

    // Create the controller with the GmailService
    $gmailService = app(GmailService::class);
    $controller = new MagicLinkController($gmailService);

    // Call the sendMagicLink method
    $response = $controller->sendMagicLink($request, $project->id);

    // Check the response
    $responseData = json_decode($response->getContent(), true);

    if ($responseData['success']) {
        echo "Success! Magic link sent to {$clientEmail} using GmailService.\n";
        echo "Response: " . $response->getContent() . "\n";
    } else {
        echo "Failed to send magic link.\n";
        echo "Response: " . $response->getContent() . "\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    Log::error('Error in GmailService Magic Link test: ' . $e->getMessage(), [
        'error' => $e->getTraceAsString()
    ]);
}

Log::info('Completed GmailService Magic Link test');
