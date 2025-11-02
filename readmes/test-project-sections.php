<?php

/**
 * Test script for the new project sections endpoints
 *
 * This script tests the new endpoints created for fetching project data by section
 * based on user permissions.
 */

// Set up the test environment
require_once __DIR__.'/vendor/autoload.php';

// Load the .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Initialize the application
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Create a request to the application
$request = Illuminate\Http\Request::create('/api/login', 'POST', [
    'email' => 'test@example.com', // Replace with a valid user email
    'password' => 'test123', // Replace with the correct password
]);

// Send the request to the application
$response = $kernel->handle($request);
$content = json_decode($response->getContent(), true);

// Check if login was successful
if (! isset($content['token'])) {
    echo "Login failed. Please check your credentials.\n";
    exit(1);
}

// Get the token
$token = $content['token'];
echo 'Login successful. Token: '.$token."\n";

// Test project ID
$projectId = 11; // Replace with a valid project ID

// Test the basic info endpoint
echo "\nTesting basic info endpoint...\n";
$request = Illuminate\Http\Request::create("/api/projects/{$projectId}/sections/basic", 'GET');
$request->headers->set('Authorization', 'Bearer '.$token);
$response = $kernel->handle($request);
$content = json_decode($response->getContent(), true);

// Check the response
if ($response->getStatusCode() === 200) {
    echo "Basic info endpoint successful.\n";
    echo 'Project name: '.$content['name']."\n";
} else {
    echo 'Basic info endpoint failed with status code: '.$response->getStatusCode()."\n";
    echo 'Response: '.$response->getContent()."\n";
}

// Test the clients and users endpoint
echo "\nTesting clients and users endpoint...\n";
$request = Illuminate\Http\Request::create("/api/projects/{$projectId}/sections/clients-users", 'GET');
$request->headers->set('Authorization', 'Bearer '.$token);
$response = $kernel->handle($request);
$content = json_decode($response->getContent(), true);

// Check the response
if ($response->getStatusCode() === 200) {
    echo "Clients and users endpoint successful.\n";
    if (isset($content['clients'])) {
        echo 'Number of clients: '.count($content['clients'])."\n";
    } else {
        echo "No clients data or user doesn't have permission to view clients.\n";
    }

    if (isset($content['users'])) {
        echo 'Number of users: '.count($content['users'])."\n";
    } else {
        echo "No users data or user doesn't have permission to view users.\n";
    }
} else {
    echo 'Clients and users endpoint failed with status code: '.$response->getStatusCode()."\n";
    echo 'Response: '.$response->getContent()."\n";
}

// Test the services and payment endpoint
echo "\nTesting services and payment endpoint...\n";
$request = Illuminate\Http\Request::create("/api/projects/{$projectId}/sections/services-payment", 'GET');
$request->headers->set('Authorization', 'Bearer '.$token);
$response = $kernel->handle($request);
$content = json_decode($response->getContent(), true);

// Check the response
if ($response->getStatusCode() === 200) {
    echo "Services and payment endpoint successful.\n";
    echo 'Payment type: '.($content['payment_type'] ?? 'Not available')."\n";
    echo 'Total amount: '.($content['total_amount'] ?? 'Not available')."\n";
} else {
    echo 'Services and payment endpoint failed with status code: '.$response->getStatusCode()."\n";
    echo 'Response: '.$response->getContent()."\n";
}

// Test the transactions endpoint
echo "\nTesting transactions endpoint...\n";
$request = Illuminate\Http\Request::create("/api/projects/{$projectId}/sections/transactions", 'GET');
$request->headers->set('Authorization', 'Bearer '.$token);
$response = $kernel->handle($request);
$content = json_decode($response->getContent(), true);

// Check the response
if ($response->getStatusCode() === 200) {
    echo "Transactions endpoint successful.\n";
    echo 'Number of transactions: '.count($content)."\n";
} else {
    echo 'Transactions endpoint failed with status code: '.$response->getStatusCode()."\n";
    echo 'Response: '.$response->getContent()."\n";
}

// Test the documents endpoint
echo "\nTesting documents endpoint...\n";
$request = Illuminate\Http\Request::create("/api/projects/{$projectId}/sections/documents", 'GET');
$request->headers->set('Authorization', 'Bearer '.$token);
$response = $kernel->handle($request);
$content = json_decode($response->getContent(), true);

// Check the response
if ($response->getStatusCode() === 200) {
    echo "Documents endpoint successful.\n";
    echo 'Number of documents: '.count($content)."\n";
} else {
    echo 'Documents endpoint failed with status code: '.$response->getStatusCode()."\n";
    echo 'Response: '.$response->getContent()."\n";
}

// Test the notes endpoint
echo "\nTesting notes endpoint...\n";
$request = Illuminate\Http\Request::create("/api/projects/{$projectId}/sections/notes", 'GET');
$request->headers->set('Authorization', 'Bearer '.$token);
$response = $kernel->handle($request);
$content = json_decode($response->getContent(), true);

// Check the response
if ($response->getStatusCode() === 200) {
    echo "Notes endpoint successful.\n";
    echo 'Number of notes: '.count($content)."\n";
} else {
    echo 'Notes endpoint failed with status code: '.$response->getStatusCode()."\n";
    echo 'Response: '.$response->getContent()."\n";
}

// Test the contract details endpoint
echo "\nTesting contract details endpoint...\n";
$request = Illuminate\Http\Request::create("/api/projects/{$projectId}/contract-details", 'GET');
$request->headers->set('Authorization', 'Bearer '.$token);
$response = $kernel->handle($request);
$content = json_decode($response->getContent(), true);

// Check the response
if ($response->getStatusCode() === 200) {
    echo "Contract details endpoint successful.\n";
    echo 'Contract details: '.json_encode($content)."\n";
} else {
    echo 'Contract details endpoint failed with status code: '.$response->getStatusCode()."\n";
    echo 'Response: '.$response->getContent()."\n";
}

echo "\nAll tests completed.\n";
