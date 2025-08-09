<?php

/**
 * Test script for the new API token endpoint
 *
 * This script tests the /api/token endpoint which is used for third-party app authentication
 * It sends a POST request with email and password and expects a token in response
 */

// Replace with actual credentials for testing
$email = 'test@example.com';
$password = 'password';

// API endpoint URL
$url = 'http://localhost:8000/api/token';

// Prepare the request data
$data = [
    'email' => $email,
    'password' => $password
];

// Initialize cURL session
$ch = curl_init($url);

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
echo "HTTP Status Code: " . $httpCode . "\n\n";

if ($httpCode == 200) {
    $responseData = json_decode($response, true);
    echo "Authentication successful!\n";
    echo "Token: " . $responseData['token'] . "\n";
    echo "User ID: " . $responseData['user']['id'] . "\n";
    echo "User Name: " . $responseData['user']['name'] . "\n";
    echo "User Email: " . $responseData['user']['email'] . "\n";
    echo "Role: " . $responseData['role'] . "\n";
} else {
    echo "Authentication failed.\n";
    echo "Response: " . $response . "\n";
}

// Test with invalid credentials
echo "\n\nTesting with invalid credentials:\n";

$data = [
    'email' => $email,
    'password' => 'wrong_password'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: " . $httpCode . "\n";
echo "Response: " . $response . "\n";

echo "\n\nUsage Instructions:\n";
echo "1. Send a POST request to " . $url . "\n";
echo "2. Include 'email' and 'password' in the request body\n";
echo "3. Set 'Accept: application/json' header\n";
echo "4. On successful authentication, you'll receive a token\n";
echo "5. Use this token in subsequent requests by adding the header:\n";
echo "   'Authorization: Bearer {token}'\n";
