<?php

// This script tests the wireframe API endpoints

// Set up the base URL and project ID for testing
$baseUrl = 'http://localhost:8000/api';
$projectId = 1; // Replace with an actual project ID from your database

// Function to make API requests
function makeRequest($method, $url, $data = null)
{
    $curl = curl_init();

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
    ];

    // Add authentication token if available
    $token = getenv('API_TOKEN');
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);

    curl_close($curl);

    if ($error) {
        echo 'cURL Error: '.$error."\n";

        return null;
    }

    return [
        'status' => $httpCode,
        'body' => json_decode($response, true),
    ];
}

// Test creating a wireframe
function testCreateWireframe($baseUrl, $projectId)
{
    echo "Testing wireframe creation...\n";

    $data = [
        'name' => 'Test Wireframe '.time(),
        'data' => json_encode([
            'components' => [],
            'canvasSize' => ['width' => 1280, 'height' => 720],
            'view' => ['scale' => 1, 'panOffset' => ['x' => 0, 'y' => 0]],
        ]),
    ];

    $response = makeRequest('POST', "$baseUrl/projects/$projectId/wireframes", $data);

    if ($response) {
        echo 'Status: '.$response['status']."\n";
        if ($response['status'] === 201) {
            echo "Wireframe created successfully!\n";
            echo 'Wireframe ID: '.$response['body']['wireframe']['id']."\n";
            echo 'Version: '.$response['body']['version']['version_number']."\n";

            return $response['body']['wireframe']['id'];
        } else {
            echo "Failed to create wireframe.\n";
            print_r($response['body']);
        }
    }

    return null;
}

// Test getting wireframes for a project
function testGetWireframes($baseUrl, $projectId)
{
    echo "\nTesting getting wireframes for project...\n";

    $response = makeRequest('GET', "$baseUrl/projects/$projectId/wireframes");

    if ($response) {
        echo 'Status: '.$response['status']."\n";
        if ($response['status'] === 200) {
            echo "Wireframes retrieved successfully!\n";
            echo 'Count: '.count($response['body'])."\n";
            if (count($response['body']) > 0) {
                echo 'First wireframe: '.$response['body'][0]['name']."\n";
            }
        } else {
            echo "Failed to retrieve wireframes.\n";
            print_r($response['body']);
        }
    }
}

// Test getting a specific wireframe
function testGetWireframe($baseUrl, $projectId, $wireframeId)
{
    echo "\nTesting getting specific wireframe...\n";

    $response = makeRequest('GET', "$baseUrl/projects/$projectId/wireframes/$wireframeId");

    if ($response) {
        echo 'Status: '.$response['status']."\n";
        if ($response['status'] === 200) {
            echo "Wireframe retrieved successfully!\n";
            echo 'Name: '.$response['body']['wireframe']['name']."\n";
            echo 'Version: '.$response['body']['version']['version_number']."\n";
        } else {
            echo "Failed to retrieve wireframe.\n";
            print_r($response['body']);
        }
    }
}

// Test updating a wireframe
function testUpdateWireframe($baseUrl, $projectId, $wireframeId)
{
    echo "\nTesting updating wireframe...\n";

    $data = [
        'data' => json_encode([
            'components' => [
                [
                    'id' => 'test-component-'.time(),
                    'type' => 'Container',
                    'position' => ['x' => 100, 'y' => 100],
                    'size' => ['width' => 200, 'height' => 200],
                    'content' => [],
                ],
            ],
            'canvasSize' => ['width' => 1280, 'height' => 720],
            'view' => ['scale' => 1, 'panOffset' => ['x' => 0, 'y' => 0]],
        ]),
    ];

    $response = makeRequest('PUT', "$baseUrl/projects/$projectId/wireframes/$wireframeId", $data);

    if ($response) {
        echo 'Status: '.$response['status']."\n";
        if ($response['status'] === 200) {
            echo "Wireframe updated successfully!\n";
            echo 'Action: '.$response['body']['action']."\n";
        } else {
            echo "Failed to update wireframe.\n";
            print_r($response['body']);
        }
    }
}

// Test publishing a wireframe
function testPublishWireframe($baseUrl, $projectId, $wireframeId)
{
    echo "\nTesting publishing wireframe...\n";

    $response = makeRequest('POST', "$baseUrl/projects/$projectId/wireframes/$wireframeId/publish");

    if ($response) {
        echo 'Status: '.$response['status']."\n";
        if ($response['status'] === 200) {
            echo "Wireframe published successfully!\n";
            echo 'Status: '.$response['body']['version']['status']."\n";
        } else {
            echo "Failed to publish wireframe.\n";
            print_r($response['body']);
        }
    }
}

// Test creating a new version
function testCreateNewVersion($baseUrl, $projectId, $wireframeId)
{
    echo "\nTesting creating new version...\n";

    $data = [
        'data' => json_encode([
            'components' => [
                [
                    'id' => 'test-component-'.time(),
                    'type' => 'Container',
                    'position' => ['x' => 200, 'y' => 200],
                    'size' => ['width' => 300, 'height' => 300],
                    'content' => [],
                ],
            ],
            'canvasSize' => ['width' => 1280, 'height' => 720],
            'view' => ['scale' => 1, 'panOffset' => ['x' => 0, 'y' => 0]],
        ]),
    ];

    $response = makeRequest('POST', "$baseUrl/projects/$projectId/wireframes/$wireframeId/versions", $data);

    if ($response) {
        echo 'Status: '.$response['status']."\n";
        if ($response['status'] === 201) {
            echo "New version created successfully!\n";
            echo 'Version: '.$response['body']['version']['version_number']."\n";
        } else {
            echo "Failed to create new version.\n";
            print_r($response['body']);
        }
    }
}

// Test getting wireframe logs
function testGetWireframeLogs($baseUrl, $projectId, $wireframeId)
{
    echo "\nTesting getting wireframe logs...\n";

    $response = makeRequest('GET', "$baseUrl/projects/$projectId/wireframes/$wireframeId/logs");

    if ($response) {
        echo 'Status: '.$response['status']."\n";
        if ($response['status'] === 200) {
            echo "Wireframe logs retrieved successfully!\n";
            echo 'Log count: '.count($response['body'])."\n";
            if (count($response['body']) > 0) {
                echo 'Latest log: '.$response['body'][0]['description']."\n";
            }
        } else {
            echo "Failed to retrieve wireframe logs.\n";
            print_r($response['body']);
        }
    }
}

// Test deleting a wireframe
function testDeleteWireframe($baseUrl, $projectId, $wireframeId)
{
    echo "\nTesting deleting wireframe...\n";

    $response = makeRequest('DELETE', "$baseUrl/projects/$projectId/wireframes/$wireframeId");

    if ($response) {
        echo 'Status: '.$response['status']."\n";
        if ($response['status'] === 204) {
            echo "Wireframe deleted successfully!\n";
        } else {
            echo "Failed to delete wireframe.\n";
            print_r($response['body']);
        }
    }
}

// Run the tests
echo "Starting wireframe API tests...\n\n";

// Test getting wireframes first to see if there are any
testGetWireframes($baseUrl, $projectId);

// Create a new wireframe for testing
$wireframeId = testCreateWireframe($baseUrl, $projectId);

if ($wireframeId) {
    // Test getting the wireframe
    testGetWireframe($baseUrl, $projectId, $wireframeId);

    // Test updating the wireframe
    testUpdateWireframe($baseUrl, $projectId, $wireframeId);

    // Test publishing the wireframe
    testPublishWireframe($baseUrl, $projectId, $wireframeId);

    // Test creating a new version
    testCreateNewVersion($baseUrl, $projectId, $wireframeId);

    // Test getting wireframe logs
    testGetWireframeLogs($baseUrl, $projectId, $wireframeId);

    // Test deleting the wireframe
    // Uncomment the line below to test deletion
    // testDeleteWireframe($baseUrl, $projectId, $wireframeId);
}

echo "\nWireframe API tests completed.\n";
