<?php

/**
 * Test script for the improved shouldShowPrompt method in AvailabilityController
 *
 * This script tests the following scenarios:
 * 1. User has no availability entries for next week
 * 2. User has availability entries for some weekdays but not all
 * 3. User has availability entries for all weekdays (Monday to Friday)
 *
 * Usage: php test-availability-prompt-improvement.php
 */

// Set up cURL for API requests
function makeRequest($method, $endpoint, $data = null, $token = null) {
    $url = "http://localhost:8000/api/" . $endpoint;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = ['Accept: application/json'];

    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    if ($data && ($method === 'POST' || $method === 'PUT')) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $headers[] = 'Content-Type: application/json';
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true)
    ];
}

// Login to get a token
function login($email, $password) {
    $response = makeRequest('POST', 'login', [
        'email' => $email,
        'password' => $password
    ]);

    if ($response['code'] === 200 && isset($response['body']['token'])) {
        return $response['body']['token'];
    }

    echo "Login failed: " . json_encode($response) . PHP_EOL;
    return null;
}

// Test the improved shouldShowPrompt method
function testImprovedShouldShowPrompt() {
    // Replace with valid credentials
    $token = login('admin@example.com', 'password');

    if (!$token) {
        echo "Cannot proceed without authentication token." . PHP_EOL;
        return;
    }

    echo "Authentication successful." . PHP_EOL;

    // Test 1: Check the shouldShowPrompt endpoint
    echo "\nTest 1: Checking the improved shouldShowPrompt endpoint:" . PHP_EOL;
    $promptResponse = makeRequest('GET', 'availability-prompt', null, $token);
    echo "Response code: " . $promptResponse['code'] . PHP_EOL;

    if ($promptResponse['code'] === 200) {
        echo "Response body: " . json_encode($promptResponse['body'], JSON_PRETTY_PRINT) . PHP_EOL;

        // Extract information from the response
        $shouldShowPrompt = $promptResponse['body']['should_show_prompt'];
        $weekdaysCovered = $promptResponse['body']['weekdays_covered'] ?? [];
        $allWeekdaysCovered = $promptResponse['body']['all_weekdays_covered'] ?? false;

        echo "Should show prompt: " . ($shouldShowPrompt ? 'Yes' : 'No') . PHP_EOL;
        echo "Weekdays covered: " . implode(', ', $weekdaysCovered) . PHP_EOL;
        echo "All weekdays covered: " . ($allWeekdaysCovered ? 'Yes' : 'No') . PHP_EOL;

        // Test 2: Submit availability for some weekdays
        echo "\nTest 2: Submitting availability for some weekdays:" . PHP_EOL;

        // Get next week's dates
        $nextWeekStart = $promptResponse['body']['next_week_start'];
        $nextWeekDates = [];

        // Generate dates for Monday, Wednesday, and Friday of next week
        $monday = new DateTime($nextWeekStart);
        while ($monday->format('N') != 1) { // 1 = Monday in ISO-8601 format
            $monday->modify('+1 day');
        }
        $nextWeekDates[] = $monday->format('Y-m-d');

        $wednesday = clone $monday;
        $wednesday->modify('+2 days'); // Wednesday is 2 days after Monday
        $nextWeekDates[] = $wednesday->format('Y-m-d');

        $friday = clone $monday;
        $friday->modify('+4 days'); // Friday is 4 days after Monday
        $nextWeekDates[] = $friday->format('Y-m-d');

        echo "Submitting availability for: " . implode(', ', $nextWeekDates) . PHP_EOL;

        // Submit availability for these dates
        $availabilities = [];
        foreach ($nextWeekDates as $date) {
            $availabilities[] = [
                'date' => $date,
                'is_available' => true,
                'time_slots' => [
                    ['start_time' => '09:00', 'end_time' => '12:00'],
                    ['start_time' => '13:00', 'end_time' => '17:00']
                ]
            ];
        }

        $batchResponse = makeRequest('POST', 'availabilities/batch', [
            'availabilities' => $availabilities
        ], $token);

        if ($batchResponse['code'] === 201) {
            echo "Availability submitted successfully for some weekdays." . PHP_EOL;

            // Check shouldShowPrompt again
            echo "\nChecking shouldShowPrompt after submitting for some weekdays:" . PHP_EOL;
            $promptResponseAfter = makeRequest('GET', 'availability-prompt', null, $token);

            if ($promptResponseAfter['code'] === 200) {
                $shouldShowPromptAfter = $promptResponseAfter['body']['should_show_prompt'];
                $weekdaysCoveredAfter = $promptResponseAfter['body']['weekdays_covered'] ?? [];
                $allWeekdaysCoveredAfter = $promptResponseAfter['body']['all_weekdays_covered'] ?? false;

                echo "Should show prompt: " . ($shouldShowPromptAfter ? 'Yes' : 'No') . PHP_EOL;
                echo "Weekdays covered: " . implode(', ', $weekdaysCoveredAfter) . PHP_EOL;
                echo "All weekdays covered: " . ($allWeekdaysCoveredAfter ? 'Yes' : 'No') . PHP_EOL;

                // Test 3: Submit availability for all weekdays
                echo "\nTest 3: Submitting availability for all weekdays:" . PHP_EOL;

                // Generate dates for Tuesday and Thursday of next week
                $tuesday = clone $monday;
                $tuesday->modify('+1 day'); // Tuesday is 1 day after Monday
                $nextWeekDates[] = $tuesday->format('Y-m-d');

                $thursday = clone $monday;
                $thursday->modify('+3 days'); // Thursday is 3 days after Monday
                $nextWeekDates[] = $thursday->format('Y-m-d');

                echo "Submitting availability for: " . $tuesday->format('Y-m-d') . ", " . $thursday->format('Y-m-d') . PHP_EOL;

                // Submit availability for these dates
                $availabilities = [
                    [
                        'date' => $tuesday->format('Y-m-d'),
                        'is_available' => true,
                        'time_slots' => [
                            ['start_time' => '09:00', 'end_time' => '12:00'],
                            ['start_time' => '13:00', 'end_time' => '17:00']
                        ]
                    ],
                    [
                        'date' => $thursday->format('Y-m-d'),
                        'is_available' => true,
                        'time_slots' => [
                            ['start_time' => '09:00', 'end_time' => '12:00'],
                            ['start_time' => '13:00', 'end_time' => '17:00']
                        ]
                    ]
                ];

                $batchResponse = makeRequest('POST', 'availabilities/batch', [
                    'availabilities' => $availabilities
                ], $token);

                if ($batchResponse['code'] === 201) {
                    echo "Availability submitted successfully for all weekdays." . PHP_EOL;

                    // Check shouldShowPrompt again
                    echo "\nChecking shouldShowPrompt after submitting for all weekdays:" . PHP_EOL;
                    $promptResponseFinal = makeRequest('GET', 'availability-prompt', null, $token);

                    if ($promptResponseFinal['code'] === 200) {
                        $shouldShowPromptFinal = $promptResponseFinal['body']['should_show_prompt'];
                        $weekdaysCoveredFinal = $promptResponseFinal['body']['weekdays_covered'] ?? [];
                        $allWeekdaysCoveredFinal = $promptResponseFinal['body']['all_weekdays_covered'] ?? false;

                        echo "Should show prompt: " . ($shouldShowPromptFinal ? 'Yes' : 'No') . PHP_EOL;
                        echo "Weekdays covered: " . implode(', ', $weekdaysCoveredFinal) . PHP_EOL;
                        echo "All weekdays covered: " . ($allWeekdaysCoveredFinal ? 'Yes' : 'No') . PHP_EOL;

                        if (!$shouldShowPromptFinal && $allWeekdaysCoveredFinal) {
                            echo "\nTest passed: Prompt is not shown after submitting availability for all weekdays." . PHP_EOL;
                        } else {
                            echo "\nTest failed: Prompt is still shown after submitting availability for all weekdays." . PHP_EOL;
                        }
                    }
                } else {
                    echo "Failed to submit availability for all weekdays: " . json_encode($batchResponse['body']) . PHP_EOL;
                }
            }
        } else {
            echo "Failed to submit availability for some weekdays: " . json_encode($batchResponse['body']) . PHP_EOL;
        }
    } else {
        echo "Failed to check shouldShowPrompt: " . json_encode($promptResponse['body']) . PHP_EOL;
    }

    echo "\nAll tests completed." . PHP_EOL;
}

// Run the tests
echo "=== Testing Improved shouldShowPrompt Method ===" . PHP_EOL;
testImprovedShouldShowPrompt();
echo "=== Test Complete ===" . PHP_EOL;
