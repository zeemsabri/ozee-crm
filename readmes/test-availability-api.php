<?php

/**
 * Test script for the Availability API endpoints
 *
 * This script tests the following endpoints:
 * - GET /api/availabilities (index)
 * - POST /api/availabilities (store)
 * - GET /api/availabilities/{id} (show)
 * - PUT /api/availabilities/{id} (update)
 * - DELETE /api/availabilities/{id} (destroy)
 * - GET /api/weekly-availabilities (getWeeklyAvailabilities)
 * - GET /api/availability-prompt (shouldShowPrompt)
 *
 * Usage: php test-availability-api.php
 */

// Set up cURL for API requests
function makeRequest($method, $endpoint, $data = null, $token = null) {
    $url = "http://localhost:8000/api/" . $endpoint;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = ['Accept: application/json'];

    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
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
        'body' => json_decode($response, true)
    ];
}

// Login to get a token
function login($email, $password) {
    $response = makeRequest('POST', 'login', [
        'email' => $email,
        'password' => $password
    ]);

    if ($response['code'] === 200 && isset($response['body']['token'])) {
        return $response['body']['token'];
    }

    echo "Login failed: " . json_encode($response) . PHP_EOL;
    return null;
}

// Main test function
function testAvailabilityApi() {
    // Replace with valid credentials
    $token = login('admin@example.com', 'password');

    if (!$token) {
        echo "Cannot proceed without authentication token." . PHP_EOL;
        return;
    }

    echo "Authentication successful." . PHP_EOL;

    // Test 1: Get availability prompt
    echo "\nTesting GET /api/availability-prompt:" . PHP_EOL;
    $promptResponse = makeRequest('GET', 'availability-prompt', null, $token);
    echo "Response code: " . $promptResponse['code'] . PHP_EOL;
    echo "Response body: " . json_encode($promptResponse['body'], JSON_PRETTY_PRINT) . PHP_EOL;

    // Test 2: Get availabilities (index)
    echo "\nTesting GET /api/availabilities:" . PHP_EOL;
    $indexResponse = makeRequest('GET', 'availabilities', null, $token);
    echo "Response code: " . $indexResponse['code'] . PHP_EOL;
    echo "Found " . count($indexResponse['body']['availabilities'] ?? []) . " availabilities." . PHP_EOL;

    // Test 3: Create a new availability (store)
    echo "\nTesting POST /api/availabilities:" . PHP_EOL;
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $storeResponse = makeRequest('POST', 'availabilities', [
        'date' => $tomorrow,
        'is_available' => true,
        'time_slots' => [
            ['start_time' => '09:00', 'end_time' => '12:00'],
            ['start_time' => '13:00', 'end_time' => '17:00']
        ]
    ], $token);
    echo "Response code: " . $storeResponse['code'] . PHP_EOL;

    if ($storeResponse['code'] === 201 || $storeResponse['code'] === 409) {
        $availabilityId = $storeResponse['body']['availability']['id'] ?? null;

        if (!$availabilityId && isset($storeResponse['body']['availability']['id'])) {
            $availabilityId = $storeResponse['body']['availability']['id'];
        }

        if ($availabilityId) {
            // Test 4: Get a specific availability (show)
            echo "\nTesting GET /api/availabilities/{$availabilityId}:" . PHP_EOL;
            $showResponse = makeRequest('GET', "availabilities/{$availabilityId}", null, $token);
            echo "Response code: " . $showResponse['code'] . PHP_EOL;

            // Test 5: Update an availability (update)
            echo "\nTesting PUT /api/availabilities/{$availabilityId}:" . PHP_EOL;
            $updateResponse = makeRequest('PUT', "availabilities/{$availabilityId}", [
                'is_available' => true,
                'time_slots' => [
                    ['start_time' => '10:00', 'end_time' => '12:00'],
                    ['start_time' => '14:00', 'end_time' => '18:00']
                ]
            ], $token);
            echo "Response code: " . $updateResponse['code'] . PHP_EOL;

            // Test 6: Delete an availability (destroy)
            echo "\nTesting DELETE /api/availabilities/{$availabilityId}:" . PHP_EOL;
            $deleteResponse = makeRequest('DELETE', "availabilities/{$availabilityId}", null, $token);
            echo "Response code: " . $deleteResponse['code'] . PHP_EOL;
        } else {
            echo "Could not get availability ID from response." . PHP_EOL;
        }
    } else {
        echo "Failed to create availability: " . json_encode($storeResponse['body']) . PHP_EOL;
    }

    // Test 7: Get weekly availabilities
    echo "\nTesting GET /api/weekly-availabilities:" . PHP_EOL;
    $weeklyResponse = makeRequest('GET', 'weekly-availabilities', null, $token);
    echo "Response code: " . $weeklyResponse['code'] . PHP_EOL;

    echo "\nAll tests completed." . PHP_EOL;
}

// Run the tests
testAvailabilityApi();
