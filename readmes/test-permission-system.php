<?php

/**
 * Test script for the permission system
 *
 * This script tests the permission system with different user roles and permissions.
 * It makes API requests to the backend to verify that the permission system is working correctly.
 *
 * Usage:
 * php test-permission-system.php
 */

// Configuration
$baseUrl = 'http://localhost:8000/api';
$adminToken = ''; // Add your admin token here
$managerToken = ''; // Add your manager token here
$employeeToken = ''; // Add your employee token here
$contractorToken = ''; // Add your contractor token here

// Test cases
$testCases = [
    // Test global permissions
    [
        'name' => 'Admin can view all permissions',
        'endpoint' => '/permissions',
        'method' => 'GET',
        'token' => $adminToken,
        'expectedStatus' => 200,
    ],
    [
        'name' => 'Manager cannot view all permissions without permission',
        'endpoint' => '/permissions',
        'method' => 'GET',
        'token' => $managerToken,
        'expectedStatus' => 403,
    ],
    [
        'name' => 'Admin can manage roles',
        'endpoint' => '/roles',
        'method' => 'GET',
        'token' => $adminToken,
        'expectedStatus' => 200,
    ],
    [
        'name' => 'Manager cannot manage roles without permission',
        'endpoint' => '/roles',
        'method' => 'GET',
        'token' => $managerToken,
        'expectedStatus' => 403,
    ],

    // Test project-specific permissions
    [
        'name' => 'Admin can view project permissions',
        'endpoint' => '/projects/1/permissions',
        'method' => 'GET',
        'token' => $adminToken,
        'expectedStatus' => 200,
    ],
    [
        'name' => 'Manager with project permission can view project permissions',
        'endpoint' => '/projects/1/permissions',
        'method' => 'GET',
        'token' => $managerToken,
        'expectedStatus' => 200,
    ],
    [
        'name' => 'Employee without project permission cannot view project permissions',
        'endpoint' => '/projects/1/permissions',
        'method' => 'GET',
        'token' => $employeeToken,
        'expectedStatus' => 403,
    ],
    [
        'name' => 'Contractor with project permission can view project permissions',
        'endpoint' => '/projects/1/permissions',
        'method' => 'GET',
        'token' => $contractorToken,
        'expectedStatus' => 200,
    ],
];

// Run tests
foreach ($testCases as $testCase) {
    echo "Running test: {$testCase['name']}\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . $testCase['endpoint']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $testCase['method']);

    $headers = [
        'Accept: application/json',
        'Authorization: Bearer ' . $testCase['token'],
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = $httpCode === $testCase['expectedStatus'] ? 'PASS' : 'FAIL';
    echo "  Result: {$result} (Expected: {$testCase['expectedStatus']}, Got: {$httpCode})\n";

    if ($result === 'FAIL') {
        echo "  Response: {$response}\n";
    }

    echo "\n";
}

echo "All tests completed.\n";
