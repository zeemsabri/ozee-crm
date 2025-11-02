<?php

/**
 * Test script for the project users endpoint
 *
 * This script tests the new endpoint that returns users based on permissions:
 * - Returns all users if the user has manage_project_users permission
 * - Returns only the project users if the user has view_project_users permission
 * - Returns only the authenticated user if the user doesn't have either permission
 *
 * Usage:
 * php test-project-users-endpoint.php
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
        'name' => 'Super Admin can see all users',
        'endpoint' => "/projects/{$projectId}/users",
        'method' => 'GET',
        'token' => $superAdminToken,
        'expectedStatus' => 200,
        'expectedCount' => 'all', // Should return all users
    ],
    [
        'name' => 'User with manage_project_users permission can see all users',
        'endpoint' => "/projects/{$projectId}/users",
        'method' => 'GET',
        'token' => $managerToken, // Assuming manager has manage_project_users permission
        'expectedStatus' => 200,
        'expectedCount' => 'all', // Should return all users
    ],
    [
        'name' => 'User with view_project_users permission can see only project users',
        'endpoint' => "/projects/{$projectId}/users",
        'method' => 'GET',
        'token' => $employeeToken, // Assuming employee has view_project_users permission
        'expectedStatus' => 200,
        'expectedCount' => 'project', // Should return only project users
    ],
    [
        'name' => 'User without permissions can see only themselves',
        'endpoint' => "/projects/{$projectId}/users",
        'method' => 'GET',
        'token' => $contractorToken, // Assuming contractor has no permissions
        'expectedStatus' => 200,
        'expectedCount' => 'self', // Should return only the authenticated user
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
        $count = count($data);
        echo "  User count: {$count}\n";

        // Check if the count matches the expected count
        if ($testCase['expectedCount'] === 'all') {
            echo "  Expected: All users\n";
        } elseif ($testCase['expectedCount'] === 'project') {
            echo "  Expected: Only project users\n";
        } elseif ($testCase['expectedCount'] === 'self') {
            $result = $count === 1 ? 'PASS' : 'FAIL';
            echo "  Self only: {$result} (Expected: 1, Got: {$count})\n";
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
 *    - User with manage_project_users permission
 *    - User with view_project_users permission
 *    - User without either permission
 *
 * 2. Navigate to a project form and go to the Clients and Users tab.
 *
 * 3. Check the dropdown for users and verify that:
 *    - Super Admin and users with manage_project_users permission see all users
 *    - Users with view_project_users permission see only users in the current project
 *    - Users without either permission see only themselves
 *
 * 4. You can also use the browser's developer tools to check the API response:
 *    - Open the Network tab
 *    - Look for the request to /api/projects/{id}/users
 *    - Check the response to ensure it contains the expected users
 */
