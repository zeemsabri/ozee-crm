<?php

// This script tests the fix for the availability endpoint
// It sends a POST request to /api/availabilities with the same payload that was causing the error

// You need to run this script with a valid authentication token
// Example: php test-availability-fix.php

// Replace with your actual authentication token
$token = "YOUR_AUTH_TOKEN";

// The payload that was causing the error
$payload = [
    "date" => "2025-07-31",
    "is_available" => true,
    "reason" => null,
    "time_slots" => [
        [
            "end_time" => "17:46",
            "start_time" => "16:46"
        ],
        [
            "start_time" => "14:59",
            "end_time" => "16:58"
        ]
    ]
];

// Initialize cURL
$ch = curl_init();

// Set cURL options
curl_setopt($ch, CURLOPT_URL, "http://localhost:8000/api/availabilities");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Accept: application/json",
    "Authorization: Bearer " . $token
]);

// Execute the request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Check for errors
if (curl_errno($ch)) {
    echo "cURL Error: " . curl_error($ch) . "\n";
} else {
    echo "HTTP Status Code: " . $httpCode . "\n";
    echo "Response: " . $response . "\n";
}

// Close cURL
curl_close($ch);

// If the fix is successful, you should see a 201 status code and a success message
// If there's still an issue, you'll see an error message
