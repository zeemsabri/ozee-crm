<?php

/**
 * Test script for the editAndApprove function in EmailController
 *
 * This script tests both scenarios:
 * 1. Regular HTML email (body_html)
 * 2. Template-based email (template_id)
 */

// Set your API base URL
$baseUrl = 'http://localhost:8000';

// Set your API token (you need to replace this with a valid token)
$apiToken = 'YOUR_API_TOKEN';

// Email IDs to test (you need to replace these with valid email IDs)
$regularEmailId = 1; // ID of a regular HTML email
$templateEmailId = 2; // ID of a template-based email

// Function to make API requests
function makeRequest($url, $method, $token, $data = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = [
        'Accept: application/json',
        'Authorization: Bearer ' . $token
    ];

    if ($data) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'response' => $response
    ];
}

// Test regular HTML email
echo "Testing editAndApprove with regular HTML email\n";
echo "============================================\n\n";

$regularEmailUrl = "{$baseUrl}/api/emails/{$regularEmailId}/edit-and-approve";
$regularEmailData = [
    'subject' => 'Updated Subject for Regular Email',
    'body' => '<p>This is an updated body for a regular HTML email.</p>',
    'composition_type' => 'custom'
];

echo "URL: {$regularEmailUrl}\n";
echo "Data: " . json_encode($regularEmailData, JSON_PRETTY_PRINT) . "\n\n";

$regularResult = makeRequest($regularEmailUrl, 'POST', $apiToken, $regularEmailData);

echo "HTTP Code: {$regularResult['code']}\n";
if ($regularResult['code'] == 200) {
    $data = json_decode($regularResult['response'], true);
    echo "Success! Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "Error: " . $regularResult['response'] . "\n";
}

echo "\n\n";

// Test template-based email
echo "Testing editAndApprove with template-based email\n";
echo "=============================================\n\n";

$templateEmailUrl = "{$baseUrl}/api/emails/{$templateEmailId}/edit-and-approve";
$templateEmailData = [
    'subject' => 'Updated Subject for Template Email',
    'composition_type' => 'template',
    'template_id' => 1, // Replace with a valid template ID
    'template_data' => [
        'dynamic_field_1' => 'Custom value 1',
        'dynamic_field_2' => 'Custom value 2'
    ]
];

echo "URL: {$templateEmailUrl}\n";
echo "Data: " . json_encode($templateEmailData, JSON_PRETTY_PRINT) . "\n\n";

$templateResult = makeRequest($templateEmailUrl, 'POST', $apiToken, $templateEmailData);

echo "HTTP Code: {$templateResult['code']}\n";
if ($templateResult['code'] == 200) {
    $data = json_decode($templateResult['response'], true);
    echo "Success! Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "Error: " . $templateResult['response'] . "\n";
}

echo "\n";
echo "Test completed.\n";
