<?php

// Test script to verify the wireframe versions endpoint

// Configuration
$baseUrl = 'http://localhost:8000';
$projectId = 2;
$wireframeId = 1;
$endpoint = "/api/projects/{$projectId}/wireframes/{$wireframeId}/versions";
$url = $baseUrl . $endpoint;

// Make the request
echo "Testing endpoint: {$url}\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);

// If authentication is required, add the appropriate headers
// curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer YOUR_TOKEN_HERE']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Output results
echo "HTTP Status Code: {$httpCode}\n";
echo "Response:\n";
echo json_encode(json_decode($response), JSON_PRETTY_PRINT);
echo "\n";
