<?php

/**
 * Test script for the weekly availability reminder functionality
 *
 * This script tests:
 * 1. The shouldShowPrompt method in the AvailabilityController
 * 2. The behavior of the reminder based on the day of the week
 * 3. The behavior of the reminder based on whether the user has already submitted availability
 *
 * Usage: php test-availability-reminder.php
 */

// Set up cURL for API requests
function makeRequest($method, $endpoint, $data = null, $token = null)
{
    $url = 'http://localhost:8000/api/'.$endpoint;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = ['Accept: application/json'];

    if ($token) {
        $headers[] = 'Authorization: Bearer '.$token;
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
        'body' => json_decode($response, true),
    ];
}

// Login to get a token
function login($email, $password)
{
    $response = makeRequest('POST', 'login', [
        'email' => $email,
        'password' => $password,
    ]);

    if ($response['code'] === 200 && isset($response['body']['token'])) {
        return $response['body']['token'];
    }

    echo 'Login failed: '.json_encode($response).PHP_EOL;

    return null;
}

// Test the weekly reminder functionality
function testWeeklyReminder()
{
    // Replace with valid credentials
    $token = login('admin@example.com', 'password');

    if (! $token) {
        echo 'Cannot proceed without authentication token.'.PHP_EOL;

        return;
    }

    echo 'Authentication successful.'.PHP_EOL;

    // Test 1: Check if the prompt should be shown
    echo "\nTest 1: Checking if the prompt should be shown:".PHP_EOL;
    $promptResponse = makeRequest('GET', 'availability-prompt', null, $token);
    echo 'Response code: '.$promptResponse['code'].PHP_EOL;

    if ($promptResponse['code'] === 200) {
        $shouldShowPrompt = $promptResponse['body']['should_show_prompt'];
        $nextWeekStart = $promptResponse['body']['next_week_start'];
        $nextWeekEnd = $promptResponse['body']['next_week_end'];

        echo 'Should show prompt: '.($shouldShowPrompt ? 'Yes' : 'No').PHP_EOL;
        echo 'Next week start: '.$nextWeekStart.PHP_EOL;
        echo 'Next week end: '.$nextWeekEnd.PHP_EOL;

        // Test 2: Simulate different days of the week
        echo "\nTest 2: Simulating different days of the week:".PHP_EOL;
        echo "Note: This test requires modifying the server code to accept a 'test_date' parameter.".PHP_EOL;
        echo 'In a real implementation, you would modify the AvailabilityController to accept this parameter for testing.'.PHP_EOL;

        // Example of how this would work if implemented:
        // $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        // foreach ($days as $index => $day) {
        //     $testDate = date('Y-m-d', strtotime("next $day"));
        //     $response = makeRequest('GET', 'availability-prompt?test_date=' . $testDate, null, $token);
        //     $shouldShow = $response['body']['should_show_prompt'];
        //     echo "$day: Should show prompt: " . ($shouldShow ? 'Yes' : 'No') . PHP_EOL;
        // }

        // Test 3: Submit availability for next week and check if prompt is still shown
        if ($shouldShowPrompt) {
            echo "\nTest 3: Submitting availability for next week and checking if prompt is still shown:".PHP_EOL;

            // Submit availability for each day of next week
            $startDate = new DateTime($nextWeekStart);
            $endDate = new DateTime($nextWeekEnd);
            $interval = new DateInterval('P1D');
            $dateRange = new DatePeriod($startDate, $interval, $endDate);

            foreach ($dateRange as $date) {
                $dateStr = $date->format('Y-m-d');
                echo "Submitting availability for $dateStr...".PHP_EOL;

                $storeResponse = makeRequest('POST', 'availabilities', [
                    'date' => $dateStr,
                    'is_available' => true,
                    'time_slots' => [
                        ['start_time' => '09:00', 'end_time' => '12:00'],
                        ['start_time' => '13:00', 'end_time' => '17:00'],
                    ],
                ], $token);

                if ($storeResponse['code'] === 201 || $storeResponse['code'] === 409) {
                    echo 'Availability submitted successfully.'.PHP_EOL;
                } else {
                    echo 'Failed to submit availability: '.json_encode($storeResponse['body']).PHP_EOL;
                }
            }

            // Check if prompt is still shown after submitting availability
            echo "\nChecking if prompt is still shown after submitting availability:".PHP_EOL;
            $promptResponseAfter = makeRequest('GET', 'availability-prompt', null, $token);
            $shouldShowPromptAfter = $promptResponseAfter['body']['should_show_prompt'];

            echo 'Should show prompt after submitting: '.($shouldShowPromptAfter ? 'Yes' : 'No').PHP_EOL;

            if ($shouldShowPromptAfter) {
                echo 'Test failed: Prompt is still shown after submitting availability for all days of next week.'.PHP_EOL;
            } else {
                echo 'Test passed: Prompt is no longer shown after submitting availability for all days of next week.'.PHP_EOL;
            }
        } else {
            echo "\nSkipping Test 3: Prompt is not shown, so no need to test submission.".PHP_EOL;
        }
    } else {
        echo 'Failed to check if prompt should be shown: '.json_encode($promptResponse['body']).PHP_EOL;
    }

    echo "\nAll tests completed.".PHP_EOL;
}

// Run the tests
testWeeklyReminder();
