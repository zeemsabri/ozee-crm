<?php

// This script tests the getProjectsSimplified endpoint to ensure it returns the expected data
// including the user's role for each project.

// Make sure you're authenticated before running this script

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set up cURL to make the request with cookies (for authentication)
$ch = curl_init('http://localhost:8000/api/projects/simplified');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookies.txt'); // Use your cookie file if available
curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies.txt');

// Execute the request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Status Code: " . $httpCode . "\n";
if ($error) {
    echo "cURL Error: " . $error . "\n";
}

echo "Raw Response:\n" . $response . "\n\n";

// Decode the JSON response
$data = json_decode($response, true);

// Print the decoded response
echo "Decoded Response:\n";
print_r($data);

// Check if the response includes the role field
$hasRoleField = false;
if (!empty($data) && is_array($data)) {
    foreach ($data as $project) {
        if (isset($project['role'])) {
            $hasRoleField = true;
            break;
        }
    }
}

echo "\nDoes the response include the role field? " . ($hasRoleField ? "Yes" : "No") . "\n";
