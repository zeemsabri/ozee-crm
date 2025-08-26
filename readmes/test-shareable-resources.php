<?php

/**
 * Test script for ShareableResource API endpoints
 *
 * This script tests the CRUD operations for the ShareableResource API.
 * It requires that you have a valid authentication token.
 *
 * Usage:
 * php test-shareable-resources.php
 */

// Replace with your actual API token
$token = 'your_api_token_here';

// Base URL for the API
$baseUrl = 'http://localhost:8000/api';

// Headers for all requests
$headers = [
    'Accept: application/json',
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token,
];

echo "Testing ShareableResource API endpoints...\n\n";

// 1. Create a new shareable resource
echo "1. Creating a new shareable resource...\n";
$createData = [
    'title' => 'Test YouTube Video',
    'description' => 'This is a test YouTube video for demonstration purposes.',
    'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    'type' => 'youtube',
    'thumbnail_url' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/0.jpg',
    'visible_to_client' => true,
    'tag_ids' => [], // Add tag IDs if you have any
];

$ch = curl_init($baseUrl . '/shareable-resources');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($createData));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status Code: " . $statusCode . "\n";
if ($statusCode === 201) {
    $resource = json_decode($response, true);
    echo "Resource created with ID: " . $resource['id'] . "\n";
    $resourceId = $resource['id'];
} else {
    echo "Failed to create resource: " . $response . "\n";
    exit(1);
}

echo "\n";

// 2. Get the created resource
echo "2. Getting the created resource...\n";
$ch = curl_init($baseUrl . '/shareable-resources/' . $resourceId);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status Code: " . $statusCode . "\n";
if ($statusCode === 200) {
    $resource = json_decode($response, true);
    echo "Resource title: " . $resource['title'] . "\n";
} else {
    echo "Failed to get resource: " . $response . "\n";
}

echo "\n";

// 3. Update the resource
echo "3. Updating the resource...\n";
$updateData = [
    'title' => 'Updated Test YouTube Video',
    'description' => 'This is an updated test YouTube video for demonstration purposes.',
];

$ch = curl_init($baseUrl . '/shareable-resources/' . $resourceId);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status Code: " . $statusCode . "\n";
if ($statusCode === 200) {
    $resource = json_decode($response, true);
    echo "Updated resource title: " . $resource['title'] . "\n";
} else {
    echo "Failed to update resource: " . $response . "\n";
}

echo "\n";

// 4. Get all resources
echo "4. Getting all resources...\n";
$ch = curl_init($baseUrl . '/shareable-resources');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status Code: " . $statusCode . "\n";
if ($statusCode === 200) {
    $resources = json_decode($response, true);
    echo "Number of resources: " . count($resources['data']) . "\n";
} else {
    echo "Failed to get resources: " . $response . "\n";
}

echo "\n";

// 5. Delete the resource
echo "5. Deleting the resource...\n";
$ch = curl_init($baseUrl . '/shareable-resources/' . $resourceId);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status Code: " . $statusCode . "\n";
if ($statusCode === 204) {
    echo "Resource deleted successfully.\n";
} else {
    echo "Failed to delete resource: " . $response . "\n";
}

echo "\n";
echo "Testing completed.\n";
