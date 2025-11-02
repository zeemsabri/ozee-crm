<?php

/**
 * Test script for the project clients endpoint
 *
 * This script tests the new endpoint that returns clients based on permissions:
 * - Returns all clients if the user has manage_project_clients permission
 * - Returns only the project clients if the user has view_project_clients permission
 * - Returns no clients if the user doesn't have either permission
 *
 * Usage:
 * php test-project-clients-endpoint.php
 */

// Configuration
$baseUrl = 'http://localhost:8000/api';
$projectId = 2; // Replace with a valid project ID
$superAdminToken = ''; // Replace with a super admin token
$managerToken = ''; // Replace with a manager token
$employeeToken = ''; // Replace with an employee token
$contractorToken = ''; // Replace with a contractor token

// Test cases
$testCases = [
    [
        'name' => 'Super Admin can see all clients',
        'endpoint' => "/projects/{$projectId}/clients",
        'method' => 'GET',
        'token' => $superAdminToken,
        'expectedStatus' => 200,
        'expectedCount' => 'all', // Should return all clients
    ],
    [
        'name' => 'User with manage_project_clients permission can see all clients',
        'endpoint' => "/projects/{$projectId}/clients",
        'method' => 'GET',
        'token' => $managerToken, // Assuming manager has manage_project_clients permission
        'expectedStatus' => 200,
        'expectedCount' => 'all', // Should return all clients
    ],
    [
        'name' => 'User with view_project_clients permission can see only project clients',
        'endpoint' => "/projects/{$projectId}/clients",
        'method' => 'GET',
        'token' => $employeeToken, // Assuming employee has view_project_clients permission
        'expectedStatus' => 200,
        'expectedCount' => 'project', // Should return only project clients
    ],
    [
        'name' => 'User without permissions cannot see any clients',
        'endpoint' => "/projects/{$projectId}/clients",
        'method' => 'GET',
        'token' => $contractorToken, // Assuming contractor has no permissions
        'expectedStatus' => 200,
        'expectedCount' => 'none', // Should return empty array
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
        $count = is_array($data) ? count($data) : 0;
        echo "  Client count: {$count}\n";

        // Check if the count matches the expected count
        if ($testCase['expectedCount'] === 'all') {
            echo "  Expected: All clients\n";
        } elseif ($testCase['expectedCount'] === 'project') {
            echo "  Expected: Only project clients\n";
        } elseif ($testCase['expectedCount'] === 'none') {
            $result = $count === 0 ? 'PASS' : 'FAIL';
            echo "  None: {$result} (Expected: 0, Got: {$count})\n";
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
 * 1. Log in to the application with different user roles:
 *    - Super Admin
 *    - User with manage_project_clients permission
 *    - User with view_project_clients permission
 *    - User without either permission
 *
 * 2. Navigate to a project form and go to the Clients and Users tab.
 *
 * 3. Check the dropdown for clients and verify that:
 *    - Super Admin and users with manage_project_clients permission see all clients
 *    - Users with view_project_clients permission see only clients in the current project
 *    - Users without either permission see no clients
 *
 * 4. You can also use the browser's developer tools to check the API response:
 *    - Open the Network tab
 *    - Look for the request to /api/projects/{id}/clients
 *    - Check the response to ensure it contains the expected clients
 */
