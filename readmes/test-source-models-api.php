<?php

/**
 * Test script for the source-models API endpoint
 *
 * This script tests the /api/source-models/{modelName} endpoint
 * which is used by the email template editor to fetch model data
 * for dropdowns and multi-selects.
 */

// Set your API base URL
$baseUrl = 'http://localhost:8000';

// Set your API token (you need to replace this with a valid token)
$apiToken = 'YOUR_API_TOKEN';

// Models to test
$models = [
    'App\\Models\\Deliverable',
    'App\\Models\\Client',
    'App\\Models\\User',
    'App\\Models\\Project'
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

// Test each model
echo "Testing source-models API endpoint\n";
echo "=================================\n\n";

foreach ($models as $model) {
    $encodedModel = urlencode($model);
    $url = "{$baseUrl}/api/source-models/{$encodedModel}";

    echo "Testing model: {$model}\n";
    echo "URL: {$url}\n";

    $result = makeRequest($url, $apiToken);

    echo "HTTP Code: {$result['code']}\n";

    if ($result['code'] == 200) {
        $data = json_decode($result['response'], true);
        $count = count($data);
        echo "Success! Received {$count} records\n";
    } else {
        echo "Error: " . $result['response'] . "\n";
    }

    echo "\n";
}

echo "Test completed.\n";
