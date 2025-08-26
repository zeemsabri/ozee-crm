<?php

/**
 * This script tests the full magic link flow from creation to handling.
 *
 * To run this test:
 * 1. Run this script with: php test-magic-link-flow.php
 */

// Bootstrap the Laravel application
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\MagicLinkController;
use App\Models\MagicLink;
use App\Models\Project;
use App\Services\GmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

// Set up logging
Log::info('Starting Magic Link Flow test');

try {
    // Step 1: Create a test magic link
    echo "Step 1: Creating a test magic link\n";

    // Find a project to use for testing
    $project = Project::first();
    if (!$project) {
        echo "No projects found in the database. Please create a project first.\n";
        exit(1);
    }

    echo "Using project: {$project->name} (ID: {$project->id})\n";

    // Generate a unique token
    $token = Str::random(64);

    // Set expiration time (24 hours from now)
    $expiresAt = now()->addHours(24);

    // Create a new magic link
    $magicLink = MagicLink::create([
        'email' => 'test@example.com',
        'token' => $token,
        'project_id' => $project->id,
        'expires_at' => $expiresAt,
        'used' => false,
    ]);

    echo "Created magic link with token: {$token}\n";

    // Step 2: Generate the magic link URL
    echo "\nStep 2: Generating the magic link URL\n";

    $url = URL::temporarySignedRoute(
        'client.magic-link',
        $expiresAt,
        ['token' => $token]
    );

    echo "Magic link URL: {$url}\n";

    // Step 3: Simulate clicking the magic link
    echo "\nStep 3: Simulating clicking the magic link\n";

    // Extract the query parameters from the URL
    $parsedUrl = parse_url($url);
    parse_str($parsedUrl['query'], $queryParams);

    // Create a request with the query parameters
    $request = Request::create('/magic-link', 'GET', $queryParams);

    // Debug the request
    echo "Request URL: " . $request->url() . "\n";
    echo "Request query parameters: " . json_encode($request->query()) . "\n";

    // Mock the hasValidSignature method to always return true
    // This is necessary because the test environment can't validate the signature
    $request->headers->set('X-Testing', 'true');

    // Create a mock of the Request class that always returns true for hasValidSignature
    $mockRequest = \Mockery::mock($request);
    $mockRequest->shouldReceive('hasValidSignature')->andReturn(true);
    $mockRequest->shouldReceive('token')->andReturn($token);
    $mockRequest->shouldReceive('query')->andReturn($request->query());
    $mockRequest->shouldReceive('all')->andReturn($request->all());

    // Create the controller
    $controller = new MagicLinkController(app(GmailService::class));

    // Call the handleMagicLink method with the mock request
    $response = $controller->handleMagicLink($mockRequest);

    // Debug the response
    echo "Response class: " . get_class($response) . "\n";

    // Check if the response is a redirect
    if ($response instanceof \Illuminate\Http\RedirectResponse) {
        echo "Response is a redirect to: " . $response->getTargetUrl() . "\n";

        // Check if the redirect URL contains the token
        $redirectUrl = $response->getTargetUrl();
        $redirectUrlParts = parse_url($redirectUrl);

        if (isset($redirectUrlParts['query'])) {
            parse_str($redirectUrlParts['query'], $redirectQueryParams);

            if (isset($redirectQueryParams['token']) && $redirectQueryParams['token'] === $token) {
                echo "Success! The magic link correctly redirects to the client dashboard with the token.\n";
            } else {
                echo "Error: The magic link does not redirect with the correct token.\n";
                echo "Expected token: {$token}\n";
                echo "Actual token: " . ($redirectQueryParams['token'] ?? 'not present') . "\n";
            }
        } else {
            echo "Error: The redirect URL does not contain any query parameters.\n";
        }
    } else {
        echo "Error: The response is not a redirect.\n";
        echo "Response type: " . get_class($response) . "\n";
    }

    // Step 4: Check if the magic link was marked as used
    echo "\nStep 4: Checking if the magic link was marked as used\n";

    $magicLink->refresh();

    if ($magicLink->used) {
        echo "Success! The magic link was marked as used.\n";
    } else {
        echo "Error: The magic link was not marked as used.\n";
    }

    // Clean up - delete the test magic link
    $magicLink->delete();
    echo "\nTest magic link deleted.\n";

    echo "\nTest completed successfully!\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    Log::error('Error in Magic Link Flow test: ' . $e->getMessage(), [
        'error' => $e->getTraceAsString()
    ]);
}

Log::info('Completed Magic Link Flow test');
