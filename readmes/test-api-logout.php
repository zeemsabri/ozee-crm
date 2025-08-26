<?php

/**
 * Test script for the new API token logout endpoint
 *
 * This script tests the /api/logout-token endpoint which is used for third-party app logout
 * It first obtains a token using the /api/token endpoint, then uses that token to test the logout endpoint
 */

// Replace with actual credentials for testing
$email = 'test@example.com';
$password = 'password';

// API endpoints
$tokenUrl = 'http://localhost:8000/api/token';
$logoutUrl = 'http://localhost:8000/api/logout-token';

echo "Step 1: Obtaining an API token\n";
echo "==============================\n";

// Prepare the request data for token
$data = [
    'email' => $email,
    'password' => $password
];

// Initialize cURL session for token request
$ch = curl_init($tokenUrl);

// Set cURL options
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json'
]);

// Execute the request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Close cURL session
curl_close($ch);

// Output the results
echo "HTTP Status Code: " . $httpCode . "\n";

if ($httpCode == 200) {
    $responseData = json_decode($response, true);
    echo "Authentication successful!\n";
    echo "Token: " . $responseData['token'] . "\n\n";

    $token = $responseData['token'];

    echo "Step 2: Testing the logout endpoint\n";
    echo "==================================\n";

    // Initialize cURL session for logout request
    $ch = curl_init($logoutUrl);

    // Set cURL options for logout
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]);

    // Execute the logout request
    $logoutResponse = curl_exec($ch);
    $logoutHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Close cURL session
    curl_close($ch);

    // Output the logout results
    echo "HTTP Status Code: " . $logoutHttpCode . "\n";

    if ($logoutHttpCode == 200) {
        $logoutData = json_decode($logoutResponse, true);
        echo "Logout successful!\n";
        echo "Response: " . $logoutResponse . "\n\n";

        echo "Step 3: Verifying token is invalidated\n";
        echo "=====================================\n";

        // Try to use the token again to verify it's been invalidated
        $ch = curl_init('http://localhost:8000/api/user');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Accept: application/json'
        ]);

        $verifyResponse = curl_exec($ch);
        $verifyHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        echo "HTTP Status Code: " . $verifyHttpCode . "\n";

        if ($verifyHttpCode == 401) {
            echo "Token verification test passed! Token has been invalidated.\n";
        } else {
            echo "Token verification test failed! Token is still valid.\n";
            echo "Response: " . $verifyResponse . "\n";
        }
    } else {
        echo "Logout failed.\n";
        echo "Response: " . $logoutResponse . "\n";
    }
} else {
    echo "Authentication failed. Cannot proceed with logout test.\n";
    echo "Response: " . $response . "\n";
}

echo "\n\nUsage Instructions:\n";
echo "1. To logout, send a POST request to " . $logoutUrl . "\n";
echo "2. Include the Authorization header with the token:\n";
echo "   'Authorization: Bearer {token}'\n";
echo "3. On successful logout, the token will be invalidated\n";
