<?php

/**
 * Test script for the assigned tasks API endpoint
 *
 * This script tests the /api/assigned-tasks endpoint to ensure it returns
 * tasks assigned to the authenticated user.
 *
 * Usage: php test-assigned-tasks.php
 */

// Include the autoloader
require __DIR__.'/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Set up Guzzle client
$client = new GuzzleHttp\Client([
    'base_uri' => 'http://localhost:8000',
    'http_errors' => false,
]);

// Get authentication token (you may need to adjust this based on your auth system)
echo "Authenticating...\n";
try {
    $response = $client->post('/api/login', [
        'json' => [
            'email' => 'admin@example.com', // Replace with a valid user email
            'password' => 'password',       // Replace with the correct password
        ],
    ]);

    $body = json_decode($response->getBody(), true);

    if (! isset($body['token'])) {
        echo "Authentication failed: No token received\n";
        exit(1);
    }

    $token = $body['token'];
    echo "Authentication successful\n";
} catch (Exception $e) {
    echo 'Authentication failed: '.$e->getMessage()."\n";
    exit(1);
}

// Test the assigned-tasks endpoint
echo "\nTesting /api/assigned-tasks endpoint...\n";
try {
    $response = $client->get('/api/assigned-tasks', [
        'headers' => [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ],
    ]);

    $statusCode = $response->getStatusCode();
    $body = json_decode($response->getBody(), true);

    echo "Status Code: $statusCode\n";

    if ($statusCode === 200) {
        echo "Success! Endpoint is working correctly.\n";
        echo 'Number of assigned tasks: '.count($body)."\n";

        // Display the first few tasks if any exist
        if (count($body) > 0) {
            echo "\nSample of assigned tasks:\n";
            $sampleSize = min(3, count($body));

            for ($i = 0; $i < $sampleSize; $i++) {
                $task = $body[$i];
                echo "- Task: {$task['name']}\n";
                echo '  Project: '.($task['project'] ? $task['project']['name'] : 'N/A')."\n";
                echo '  Milestone: '.($task['milestone'] ? $task['milestone']['name'] : 'N/A')."\n";
                echo '  Due Date: '.($task['due_date'] ?? 'No due date')."\n";
                echo "\n";
            }
        } else {
            echo "No tasks are currently assigned to this user.\n";
        }
    } else {
        echo "Error: Unexpected status code\n";
        echo 'Response: '.json_encode($body, JSON_PRETTY_PRINT)."\n";
    }
} catch (Exception $e) {
    echo 'Request failed: '.$e->getMessage()."\n";
}
