<?php

// Test script to verify the wireframe version update functionality using path parameters
// Usage: php test-wireframe-version-path-update.php

$projectId = 2; // Replace with an actual project ID
$wireframeId = 1; // Replace with an actual wireframe ID
$versionNumber = 3; // Replace with an actual version number

$baseUrl = 'http://localhost:8000/api';
$endpoint = "{$baseUrl}/projects/{$projectId}/wireframes/{$wireframeId}/versions/{$versionNumber}";

// Prepare the payload
$payload = [
    'name' => 'Draft'
];

// Make the PUT request
$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json'
]);

// Add authentication if needed
// curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer YOUR_TOKEN_HERE']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Output the results
echo "HTTP Status Code: " . $httpCode . "\n";
echo "Response:\n";
echo json_encode(json_decode($response), JSON_PRETTY_PRINT) . "\n";

// Verify the response
$responseData = json_decode($response, true);
if (isset($responseData['wireframe']) && isset($responseData['version']) && $responseData['version']['version_number'] == $versionNumber) {
    echo "\nSUCCESS: Version {$versionNumber} was updated successfully.\n";
} else {
    echo "\nFAILURE: Version update did not work as expected.\n";
}
