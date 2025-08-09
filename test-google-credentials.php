<?php

use App\Models\User;
use App\Models\GoogleAccounts;
use Illuminate\Support\Facades\Auth;

// This script tests the hasGoogleCredentials method to ensure it correctly
// determines when a user has valid Google credentials

// Get a user
$user = User::first();
if (!$user) {
    echo "No users found in the database.\n";
    exit(1);
}

echo "Testing with user: {$user->name} (ID: {$user->id})\n";

// Check if user has Google credentials
$hasCredentials = $user->hasGoogleCredentials();
echo "User has Google credentials: " . ($hasCredentials ? "Yes" : "No") . "\n";

// Get the Google account if it exists
$googleAccount = $user->googleAccount()->first();
if ($googleAccount) {
    echo "Google account found:\n";
    echo "- Email: {$googleAccount->email}\n";
    echo "- Created: " . date('Y-m-d H:i:s', $googleAccount->created) . "\n";
    echo "- Expires in: {$googleAccount->expires_in} seconds\n";

    // Store original values
    $originalCreated = $googleAccount->created;
    $originalExpiresIn = $googleAccount->expires_in;
    $originalRefreshToken = $googleAccount->refresh_token;

    // First test: Simulate an expired token by setting created time to a past date
    echo "\nTest 1: Simulating an expired token that can be refreshed...\n";
    $googleAccount->created = time() - $googleAccount->expires_in - 3600; // 1 hour past expiration
    $googleAccount->save();

    // Check if token is expired
    $isExpired = $googleAccount->isExpired();
    echo "Token is expired: " . ($isExpired ? "Yes" : "No") . "\n";

    if ($isExpired) {
        echo "Attempting to refresh token...\n";
        try {
            $googleUserService = app(\App\Services\GoogleUserService::class);
            $refreshed = $googleUserService->refreshToken($googleAccount);
            echo "Token refreshed successfully.\n";
        } catch (\Exception $e) {
            echo "Failed to refresh token: " . $e->getMessage() . "\n";
        }

        // Check again after refresh attempt
        echo "User has Google credentials after refresh attempt: " .
             ($user->hasGoogleCredentials() ? "Yes" : "No") . "\n";
    }

    // Second test: Simulate an expired token with invalid refresh token
    echo "\nTest 2: Simulating an expired token that cannot be refreshed...\n";

    // Create a mock GoogleUserService that always throws an exception
    class MockGoogleUserService extends \App\Services\GoogleUserService {
        public function refreshToken(\App\Models\GoogleAccounts $googleAccount) {
            throw new \Exception("Mock refresh token failure");
        }
    }

    // Register the mock in the service container
    app()->singleton(\App\Services\GoogleUserService::class, function($app) {
        return new MockGoogleUserService();
    });

    // Set an invalid refresh token
    $googleAccount->refresh_token = 'invalid_refresh_token';
    $googleAccount->created = time() - $googleAccount->expires_in - 3600; // 1 hour past expiration
    $googleAccount->save();

    // Check if token is expired
    $isExpired = $googleAccount->isExpired();
    echo "Token is expired: " . ($isExpired ? "Yes" : "No") . "\n";

    // Check if hasGoogleCredentials returns false with invalid refresh token
    echo "Testing hasGoogleCredentials with invalid refresh token...\n";
    $hasCredentials = $user->hasGoogleCredentials();
    echo "User has Google credentials with invalid refresh token: " . ($hasCredentials ? "Yes (unexpected)" : "No (expected)") . "\n";

    // Directly test the GoogleUserService to see if it throws an exception
    echo "Directly testing GoogleUserService with invalid refresh token...\n";
    try {
        $googleUserService = app(\App\Services\GoogleUserService::class);
        $refreshed = $googleUserService->refreshToken($googleAccount);
        echo "Token refreshed successfully (unexpected).\n";
    } catch (\Exception $e) {
        echo "Failed to refresh token as expected: " . $e->getMessage() . "\n";
    }
} else {
    echo "No Google account found for this user.\n";
}

// Restore original values if we modified them
if (isset($originalCreated) && isset($originalExpiresIn) && isset($originalRefreshToken)) {
    echo "\nRestoring original token values...\n";
    $googleAccount->created = $originalCreated;
    $googleAccount->expires_in = $originalExpiresIn;
    $googleAccount->refresh_token = $originalRefreshToken;
    $googleAccount->save();
    echo "Original values restored.\n";
}

echo "\nTest completed.\n";
