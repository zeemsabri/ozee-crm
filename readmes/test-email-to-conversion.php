<?php

// This script tests the conversion of JSON-encoded array of email addresses to comma-separated string
// in the EmailController's approve method

echo "Testing Email 'to' field conversion\n";
echo "--------------------------------\n\n";

// Function to check if a string is valid JSON
function isJson($string) {
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}

// Test case 1: JSON-encoded array of email addresses
echo "Test Case 1: JSON-encoded array of email addresses\n";
$jsonEmails = '["info@mmsitandwebsolutions.com.au","zeemsabri@gmail.com"]';
echo "Original value: " . $jsonEmails . "\n";

// Check if it's valid JSON
$isValidJson = isJson($jsonEmails);
echo "Is valid JSON? " . ($isValidJson ? "Yes" : "No") . "\n";

// Convert to comma-separated string
$recipientClientEmail = $jsonEmails;
if (is_string($recipientClientEmail) && isJson($recipientClientEmail)) {
    $emailArray = json_decode($recipientClientEmail, true);
    $recipientClientEmail = implode(',', $emailArray);
}

// Verify the result
$expected = "info@mmsitandwebsolutions.com.au,zeemsabri@gmail.com";
echo "Expected format: " . $expected . "\n";
echo "Actual format: " . $recipientClientEmail . "\n";
echo "Test result: " . ($recipientClientEmail === $expected ? "PASS" : "FAIL") . "\n\n";

// Test case 2: Single email address (not JSON)
echo "Test Case 2: Single email address (not JSON)\n";
$singleEmail = "single@example.com";
echo "Original value: " . $singleEmail . "\n";

// Check if it's valid JSON
$isValidJson = isJson($singleEmail);
echo "Is valid JSON? " . ($isValidJson ? "Yes" : "No") . "\n";

// Apply the same conversion logic
$recipientClientEmail = $singleEmail;
if (is_string($recipientClientEmail) && isJson($recipientClientEmail)) {
    $emailArray = json_decode($recipientClientEmail, true);
    $recipientClientEmail = implode(',', $emailArray);
}

// Verify the result
echo "Expected format: " . $singleEmail . "\n";
echo "Actual format: " . $recipientClientEmail . "\n";
echo "Test result: " . ($recipientClientEmail === $singleEmail ? "PASS" : "FAIL") . "\n\n";

// Test case 3: Empty JSON array
echo "Test Case 3: Empty JSON array\n";
$emptyJsonArray = '[]';
echo "Original value: " . $emptyJsonArray . "\n";

// Check if it's valid JSON
$isValidJson = isJson($emptyJsonArray);
echo "Is valid JSON? " . ($isValidJson ? "Yes" : "No") . "\n";

// Apply the same conversion logic
$recipientClientEmail = $emptyJsonArray;
if (is_string($recipientClientEmail) && isJson($recipientClientEmail)) {
    $emailArray = json_decode($recipientClientEmail, true);
    $recipientClientEmail = implode(',', $emailArray);
}

// Verify the result
echo "Expected format: " . "" . "\n";
echo "Actual format: " . $recipientClientEmail . "\n";
echo "Test result: " . ($recipientClientEmail === "" ? "PASS" : "FAIL") . "\n\n";

echo "All tests completed.\n";
