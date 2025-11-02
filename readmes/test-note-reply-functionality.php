<?php

// Test script to verify the note reply functionality in the frontend

echo "=== Testing Note Reply Functionality ===\n\n";

// Function to simulate the API request for replying to a note
function simulateReplyToNote($projectId, $noteId, $content)
{
    echo "Simulating reply to note...\n";
    echo "Project ID: $projectId\n";
    echo "Note ID: $noteId\n";
    echo "Reply content: $content\n\n";

    // Construct the API endpoint URL
    $url = "http://localhost:8000/api/projects/$projectId/notes/$noteId/reply";

    // Prepare the request data
    $data = json_encode(['content' => $content]);

    // Set up cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer YOUR_AUTH_TOKEN', // Replace with a valid token
    ]);

    // Execute the request
    echo "Sending POST request to: $url\n";
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Process the response
    echo "HTTP Status Code: $httpCode\n";

    if ($httpCode >= 200 && $httpCode < 300) {
        echo "Request successful!\n";

        // Parse the JSON response
        $data = json_decode($response, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            echo "Successfully parsed JSON response.\n";

            // Check if the reply was successful
            if (isset($data['success']) && $data['success']) {
                echo "Reply sent successfully!\n";

                // Display the new note details
                if (isset($data['note'])) {
                    echo 'New note created with ID: '.$data['note']['id']."\n";
                    echo 'Content: '.$data['note']['content']."\n";
                    echo 'Chat message ID: '.($data['note']['chat_message_id'] ?? 'N/A')."\n";
                }
            } else {
                echo 'Reply failed: '.($data['message'] ?? 'Unknown error')."\n";
            }
        } else {
            echo 'Failed to parse JSON response: '.json_last_error_msg()."\n";
            echo 'Raw response: '.$response."\n";
        }
    } else {
        echo "Request failed with status code $httpCode\n";
        echo 'Response: '.$response."\n";
    }

    echo "\n";
}

// Test cases

// Test case 1: Valid reply to a note
echo "Test Case 1: Valid reply to a note\n";
simulateReplyToNote(1, 1, 'This is a test reply to note #1');

// Test case 2: Reply to a note without chat_message_id
echo "Test Case 2: Reply to a note without chat_message_id\n";
simulateReplyToNote(1, 2, 'This reply should fail because the note has no chat_message_id');

// Test case 3: Reply to a note in a project without Google Chat space
echo "Test Case 3: Reply to a note in a project without Google Chat space\n";
simulateReplyToNote(2, 3, 'This reply should fail because the project has no Google Chat space');

echo "=== Test completed ===\n";
echo "Note: For these tests to work properly, you need to:\n";
echo "1. Replace 'YOUR_AUTH_TOKEN' with a valid authentication token\n";
echo "2. Use valid project and note IDs from your database\n";
echo "3. Ensure at least one note has a chat_message_id and its project has a Google Chat space\n";
