<?php

/**
 * Test script for project section permissions
 *
 * This script tests the changes made to the ProjectSectionController to ensure that
 * users with the appropriate permissions can view clients and users data.
 *
 * Usage:
 * php test-project-section-permissions.php
 */

// Configuration
$baseUrl = 'http://localhost:8000/api';
$projectId = 2; // Replace with a valid project ID
$token = ''; // Replace with a valid user token

// Test cases
$testCases = [
    [
        'name' => 'Get clients and users with view_project_clients permission',
        'endpoint' => "/projects/{$projectId}/sections/clients-users",
        'method' => 'GET',
        'token' => $token,
        'expectedStatus' => 200,
        'expectedData' => ['clients'],
    ],
    [
        'name' => 'Get clients and users with manage_project_clients permission',
        'endpoint' => "/projects/{$projectId}/sections/clients-users",
        'method' => 'GET',
        'token' => $token,
        'expectedStatus' => 200,
        'expectedData' => ['clients'],
    ],
    [
        'name' => 'Get clients and users with view_project_users permission',
        'endpoint' => "/projects/{$projectId}/sections/clients-users",
        'method' => 'GET',
        'token' => $token,
        'expectedStatus' => 200,
        'expectedData' => ['users'],
    ],
    [
        'name' => 'Get clients and users with manage_project_users permission',
        'endpoint' => "/projects/{$projectId}/sections/clients-users",
        'method' => 'GET',
        'token' => $token,
        'expectedStatus' => 200,
        'expectedData' => ['users'],
    ],
];

// Run tests
foreach ($testCases as $testCase) {
    echo "Running test: {$testCase['name']}\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl.$testCase['endpoint']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $testCase['method']);

    $headers = [
        'Accept: application/json',
        'Authorization: Bearer '.$testCase['token'],
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = $httpCode === $testCase['expectedStatus'] ? 'PASS' : 'FAIL';
    echo "  HTTP Status: {$result} (Expected: {$testCase['expectedStatus']}, Got: {$httpCode})\n";

    if ($result === 'PASS') {
        $data = json_decode($response, true);
        foreach ($testCase['expectedData'] as $key) {
            $hasKey = isset($data[$key]);
            $keyResult = $hasKey ? 'PASS' : 'FAIL';
            echo "  Data Key '{$key}': {$keyResult}\n";

            if ($hasKey && is_array($data[$key])) {
                echo "  {$key} count: ".count($data[$key])."\n";
            }
        }
    } else {
        echo "  Response: {$response}\n";
    }

    echo "\n";
}

echo "All tests completed.\n";

/**
 * Manual Testing Instructions:
 *
 * 1. Log in as a user with one of the following permissions:
 *    - view_project_clients
 *    - manage_project_clients
 *    - view_project_users
 *    - manage_project_users
 *
 * 2. Navigate to a project form and go to the Clients and Users tab.
 *
 * 3. Verify that the appropriate data is displayed:
 *    - If you have view_project_clients or manage_project_clients, you should see the clients data.
 *    - If you have view_project_users or manage_project_users, you should see the users data.
 *
 * 4. You can also use the browser's developer tools to check the API response:
 *    - Open the Network tab
 *    - Look for the request to /api/projects/{id}/sections/clients-users
 *    - Check the response to ensure it contains the expected data
 */
