<?php

// Test script to verify the projects API endpoint is working correctly

// Make a request to the API endpoint
$url = 'http://localhost:8000/api/projects';

// Get the authentication token (you would need to replace this with a valid token)
// For testing purposes, you might need to log in first and get a token
$token = 'YOUR_AUTH_TOKEN'; // Replace with a valid token

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Bearer '.$token,
]);

echo "Sending request to $url\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: $httpCode\n";

if ($httpCode >= 200 && $httpCode < 300) {
    echo "Request successful!\n";

    // Parse the JSON response
    $data = json_decode($response, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        echo "Successfully parsed JSON response.\n";
        echo 'Number of projects: '.count($data)."\n\n";

        // Display some basic info about each project
        foreach ($data as $index => $project) {
            echo 'Project #'.($index + 1).":\n";
            echo '- ID: '.$project['id']."\n";
            echo '- Name: '.$project['name']."\n";
            echo '- Status: '.$project['status']."\n";

            // Check if notes were properly decrypted
            if (isset($project['notes']) && is_array($project['notes'])) {
                echo '- Number of notes: '.count($project['notes'])."\n";

                // Check the first few notes
                $notesToShow = min(3, count($project['notes']));
                for ($i = 0; $i < $notesToShow; $i++) {
                    $note = $project['notes'][$i];
                    echo '  - Note #'.($i + 1).': '.
                         (strlen($note['content']) > 50 ?
                          substr($note['content'], 0, 50).'...' :
                          $note['content'])."\n";
                }
            } else {
                echo "- No notes found.\n";
            }

            echo "\n";
        }
    } else {
        echo 'Failed to parse JSON response: '.json_last_error_msg()."\n";
        echo 'Raw response: '.$response."\n";
    }
} else {
    echo "Request failed with status code $httpCode\n";
    echo 'Response: '.$response."\n";
}

echo "Test completed.\n";
