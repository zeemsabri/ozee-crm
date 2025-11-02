<?php

// Test script to verify the project show API endpoint is working correctly

// Make a request to the API endpoint for a specific project
$projectId = 11; // The project ID from the issue description
$url = "http://localhost:8000/api/projects/{$projectId}";

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

        // Display basic project info
        echo 'Project ID: '.$data['id']."\n";
        echo 'Project Name: '.$data['name']."\n";
        echo 'Project Status: '.$data['status']."\n\n";

        // Check if notes were properly decrypted
        if (isset($data['notes']) && is_array($data['notes'])) {
            echo 'Number of notes: '.count($data['notes'])."\n";

            // Check each note
            foreach ($data['notes'] as $index => $note) {
                echo 'Note #'.($index + 1).":\n";
                echo '- ID: '.$note['id']."\n";
                echo '- Content: '.(strlen($note['content']) > 50 ?
                      substr($note['content'], 0, 50).'...' :
                      $note['content'])."\n";

                // Check if this note has the placeholder text (indicating decryption failed)
                if ($note['content'] === '[Encrypted content could not be decrypted]') {
                    echo "  (This note had a decryption error, but was handled gracefully)\n";
                }

                echo "\n";
            }
        } else {
            echo "No notes found in the project data.\n";
        }

        // Check for other important project data
        echo "Other project data:\n";
        echo '- Has clients: '.(isset($data['clients']) ? 'Yes ('.count($data['clients']).')' : 'No')."\n";
        echo '- Has users: '.(isset($data['users']) ? 'Yes ('.count($data['users']).')' : 'No')."\n";
        echo '- Has transactions: '.(isset($data['transactions']) ? 'Yes ('.count($data['transactions']).')' : 'No')."\n";
    } else {
        echo 'Failed to parse JSON response: '.json_last_error_msg()."\n";
        echo 'Raw response: '.$response."\n";
    }
} else {
    echo "Request failed with status code $httpCode\n";
    echo 'Response: '.$response."\n";
}

echo "Test completed.\n";
