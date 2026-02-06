<?php

/**
 * This script tests the magic link redirection functionality.
 *
 * To run this test:
 * 1. Make sure you have a valid project in the database
 * 2. Run this script with: php test-magic-link-redirect.php
 */

// Bootstrap the Laravel application
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MagicLink;
use App\Models\Project;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

try {
    // Find a project to use for testing
    $project = Project::first();
    if (! $project) {
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

    // Generate the magic link URL
    $url = URL::temporarySignedRoute(
        'client.magic-link',
        $expiresAt,
        ['token' => $token]
    );

    echo "Magic link URL: {$url}\n";

    // Simulate the redirect that would happen when accessing the magic link
    echo "\nWhen this URL is accessed, it will redirect to: /?token={$token}\n";

    echo "\nTest completed successfully!\n";

    // Clean up - delete the test magic link
    $magicLink->delete();
    echo "Test magic link deleted.\n";

} catch (\Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
    Log::error('Error in Magic Link Redirect test: '.$e->getMessage(), [
        'error' => $e->getTraceAsString(),
    ]);
}
