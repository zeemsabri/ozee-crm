<?php

// This script tests the API endpoints for clients and users
// Run this script with: php test-api-endpoints.php

require __DIR__.'/vendor/autoload.php';

// Bootstrap the Laravel application
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

echo "Testing clients and users API endpoints...\n\n";

// Get a user with appropriate permissions
$user = User::whereHas('role', function ($query) {
    $query->where('slug', 'super-admin')
        ->orWhere('slug', 'manager');
})->first();

if (! $user) {
    echo "Error: No user with appropriate permissions found.\n";
    exit(1);
}

echo "Using user: {$user->name} (Role: {$user->role->name})\n\n";

// Log in as the user
Auth::login($user);

// Get a token for API requests
$token = $user->createToken('test-token')->plainTextToken;

echo "Testing /api/clients endpoint...\n";
try {
    $response = Http::withToken($token)->get(url('/api/clients'));

    if ($response->successful()) {
        $clients = $response->json();
        echo "✓ Successfully fetched clients\n";
        echo "  - Response status: {$response->status()}\n";
        echo '  - Response structure: '.json_encode(array_keys(is_array($clients) ? $clients : []))."\n";

        if (isset($clients['data']) && is_array($clients['data'])) {
            echo '  - Number of clients in data array: '.count($clients['data'])."\n";
            if (count($clients['data']) > 0) {
                echo '  - First client: '.json_encode($clients['data'][0])."\n";
            }
        } elseif (is_array($clients)) {
            echo '  - Number of clients: '.count($clients)."\n";
            if (count($clients) > 0) {
                echo '  - First client: '.json_encode($clients[0])."\n";
            }
        } else {
            echo '  - Unexpected response format: '.gettype($clients)."\n";
            echo '  - Full response: '.json_encode($clients)."\n";
        }
    } else {
        echo "✗ Failed to fetch clients\n";
        echo "  - Response status: {$response->status()}\n";
        echo "  - Response body: {$response->body()}\n";
    }
} catch (\Exception $e) {
    echo "✗ Exception when fetching clients: {$e->getMessage()}\n";
}

echo "\nTesting /api/users endpoint...\n";
try {
    $response = Http::withToken($token)->get(url('/api/users'));

    if ($response->successful()) {
        $users = $response->json();
        $userCount = is_array($users) ? count($users) : 0;

        echo "✓ Successfully fetched users\n";
        echo "  - Response status: {$response->status()}\n";
        echo "  - Number of users: {$userCount}\n";

        if ($userCount > 0) {
            echo '  - First user: '.json_encode($users[0])."\n";
        } else {
            echo "  - No users found in the response\n";
        }
    } else {
        echo "✗ Failed to fetch users\n";
        echo "  - Response status: {$response->status()}\n";
        echo "  - Response body: {$response->body()}\n";
    }
} catch (\Exception $e) {
    echo "✗ Exception when fetching users: {$e->getMessage()}\n";
}

echo "\nTest completed.\n";
