<?php

// Test script to verify project names are displayed in email lists
// This script tests the API endpoints to ensure they return project data

// Replace with a valid auth token for testing
$token = 'YOUR_AUTH_TOKEN';

// Base URL - adjust if your local server uses a different URL
$baseUrl = 'http://localhost:8000';

// API endpoints to test
$endpoints = [
    'new-emails' => '/api/inbox/new-emails',
    'all-emails' => '/api/inbox/all-emails',
    'waiting-approval' => '/api/inbox/waiting-approval'
];

// Function to make API requests
function makeRequest($url, $token) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Bearer ' . $token
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'response' => $response
    ];
}

// Function to check if project data is included in the response
function checkProjectData($data) {
    if (is_array($data)) {
        foreach ($data as $email) {
            if (isset($email['conversation']) && isset($email['conversation']['project'])) {
                echo "✓ Project data found: " . $email['conversation']['project']['name'] . "\n";
                return true;
            }
        }
    }
    echo "✗ No project data found in the response\n";
    return false;
}

// Test each endpoint
foreach ($endpoints as $name => $endpoint) {
    echo "\nTesting $name endpoint...\n";
    $url = $baseUrl . $endpoint;
    $result = makeRequest($url, $token);

    echo "Status code: " . $result['code'] . "\n";

    if ($result['code'] == 200) {
        $data = json_decode($result['response'], true);

        if ($name === 'waiting-approval') {
            // For waiting-approval, check both incoming and outgoing emails
            echo "Checking outgoing emails:\n";
            checkProjectData($data['outgoing']);

            echo "Checking incoming emails:\n";
            checkProjectData($data['incoming']);
        } else {
            // For other endpoints, check the direct response
            checkProjectData($data);
        }
    } else {
        echo "Error! Response:\n";
        echo $result['response'] . "\n";
    }

    echo "----------------------------------------\n";
}

echo "\nTest completed. Please also verify in the browser that project names are displayed correctly in the UI.\n";
