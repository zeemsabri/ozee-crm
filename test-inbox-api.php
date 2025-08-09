<?php

// Test script for Inbox API endpoints

// Get the auth token (you'll need to replace this with a valid token)
$token = 'YOUR_AUTH_TOKEN'; // Replace with a valid token

// API endpoints to test
$endpoints = [
    'new-emails' => '/api/inbox/new-emails',
    'all-emails' => '/api/inbox/all-emails',
    'waiting-approval' => '/api/inbox/waiting-approval'
];

// Base URL
$baseUrl = 'http://localhost:8000'; // Adjust if your local server uses a different URL

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

// Test each endpoint
foreach ($endpoints as $name => $endpoint) {
    echo "Testing $name endpoint...\n";
    $url = $baseUrl . $endpoint;
    $result = makeRequest($url, $token);

    echo "Status code: " . $result['code'] . "\n";

    if ($result['code'] == 200) {
        echo "Success! Response:\n";
        $data = json_decode($result['response'], true);
        echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "Error! Response:\n";
        echo $result['response'] . "\n";
    }

    echo "----------------------------------------\n";
}

echo "All tests completed.\n";
