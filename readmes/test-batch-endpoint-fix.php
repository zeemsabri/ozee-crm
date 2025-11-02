<?php

/**
 * Test script for the fixed batch endpoint
 *
 * This script tests the batch endpoint with the same payload that was causing the error.
 *
 * Usage: php test-batch-endpoint-fix.php
 */

// Set up cURL for API requests
function makeRequest($method, $endpoint, $data = null, $token = null)
{
    $url = 'http://localhost:8000/api/'.$endpoint;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = ['Accept: application/json'];

    if ($token) {
        $headers[] = 'Authorization: Bearer '.$token;
    }

    if ($data && ($method === 'POST' || $method === 'PUT')) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $headers[] = 'Content-Type: application/json';
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true),
    ];
}

// Login to get a token
function login($email, $password)
{
    $response = makeRequest('POST', 'login', [
        'email' => $email,
        'password' => $password,
    ]);

    if ($response['code'] === 200 && isset($response['body']['token'])) {
        return $response['body']['token'];
    }

    echo 'Login failed: '.json_encode($response).PHP_EOL;

    return null;
}

// Test the batch endpoint
function testBatchEndpoint()
{
    // Replace with valid credentials
    $token = login('admin@example.com', 'password');

    if (! $token) {
        echo 'Cannot proceed without authentication token.'.PHP_EOL;

        return;
    }

    echo 'Authentication successful.'.PHP_EOL;

    // Test payload that was causing the error
    $payload = [
        'availabilities' => [
            [
                'date' => '2025-07-28',
                'is_available' => true,
                'reason' => null,
                'time_slots' => [
                    [
                        'start_time' => '11:53',
                        'end_time' => '12:54',
                    ],
                ],
            ],
        ],
    ];

    echo "\nTesting POST /api/availabilities/batch with payload:".PHP_EOL;
    echo test - batch - endpoint - fix.phpjson_encode($payload, JSON_PRETTY_PRINT).PHP_EOL;

    $response = makeRequest('POST', 'availabilities/batch', $payload, $token);

    echo "\nResponse code: ".$response['code'].PHP_EOL;

    if ($response['code'] === 201) {
        echo 'Success! The batch endpoint is working correctly.'.PHP_EOL;
        echo 'Response body: '.json_encode($response['body'], JSON_PRETTY_PRINT).PHP_EOL;
    } else {
        echo 'Error: The batch endpoint returned an error.'.PHP_EOL;
        echo 'Response body: '.json_encode($response['body'], JSON_PRETTY_PRINT).PHP_EOL;
    }
}

// Run the test
echo '=== Testing Batch Endpoint Fix ==='.PHP_EOL;
testBatchEndpoint();
echo '=== Test Complete ==='.PHP_EOL;
