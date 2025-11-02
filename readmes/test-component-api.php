<?php

// This script tests the component API endpoints

// Set up the base URL for testing
$baseUrl = 'http://localhost:8000/api';

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

// Test getting all components
function testGetComponents($baseUrl)
{
    echo "Testing getting all components...\n";

    $response = makeRequest('GET', "$baseUrl/components");

    if ($response) {
        echo 'Status: '.$response['status']."\n";
        if ($response['status'] === 200) {
            echo "Components retrieved successfully!\n";
            echo 'Count: '.count($response['body'])."\n";
            if (count($response['body']) > 0) {
                echo 'First component: '.$response['body'][0]['name']."\n";
            }
        } else {
            echo "Failed to retrieve components.\n";
            print_r($response['body']);
        }
    }
}

// Test creating a component
function testCreateComponent($baseUrl)
{
    echo "\nTesting component creation...\n";

    $data = [
        'name' => 'Test Component '.time(),
        'type' => 'Custom',
        'definition' => json_encode([
            'default' => [
                'size' => [
                    'width' => 200,
                    'height' => 100,
                ],
                'content' => [
                    'text' => 'Test Component',
                ],
            ],
        ]),
    ];

    $response = makeRequest('POST', "$baseUrl/components", $data);

    if ($response) {
        echo 'Status: '.$response['status']."\n";
        if ($response['status'] === 201) {
            echo "Component created successfully!\n";
            echo 'Component ID: '.$response['body']['component']['id']."\n";

            return $response['body']['component']['id'];
        } else {
            echo "Failed to create component.\n";
            print_r($response['body']);
        }
    }

    return null;
}

// Test creating a component with an icon
function testCreateComponentWithIcon($baseUrl)
{
    echo "\nTesting component creation with icon...\n";

    $data = [
        'name' => 'Test Component With Icon '.time(),
        'type' => 'Custom',
        'definition' => json_encode([
            'default' => [
                'size' => [
                    'width' => 200,
                    'height' => 100,
                ],
                'content' => [
                    'text' => 'Test Component With Icon',
                ],
            ],
        ]),
        'icon_name' => 'Test Icon '.time(),
        'icon_svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>',
    ];

    $response = makeRequest('POST', "$baseUrl/components", $data);

    if ($response) {
        echo 'Status: '.$response['status']."\n";
        if ($response['status'] === 201) {
            echo "Component with icon created successfully!\n";
            echo 'Component ID: '.$response['body']['component']['id']."\n";
            echo 'Icon ID: '.$response['body']['component']['icon_id']."\n";

            return $response['body']['component']['id'];
        } else {
            echo "Failed to create component with icon.\n";
            print_r($response['body']);
        }
    }

    return null;
}

// Test getting a specific component
function testGetComponent($baseUrl, $componentId)
{
    echo "\nTesting getting specific component...\n";

    $response = makeRequest('GET', "$baseUrl/components/$componentId");

    if ($response) {
        echo 'Status: '.$response['status']."\n";
        if ($response['status'] === 200) {
            echo "Component retrieved successfully!\n";
            echo 'Name: '.$response['body']['name']."\n";
            echo 'Type: '.$response['body']['type']."\n";
            if ($response['body']['icon']) {
                echo "Has icon: Yes\n";
            } else {
                echo "Has icon: No\n";
            }
        } else {
            echo "Failed to retrieve component.\n";
            print_r($response['body']);
        }
    }
}

// Test updating a component
function testUpdateComponent($baseUrl, $componentId)
{
    echo "\nTesting updating component...\n";

    $data = [
        'name' => 'Updated Component '.time(),
        'definition' => json_encode([
            'default' => [
                'size' => [
                    'width' => 300,
                    'height' => 150,
                ],
                'content' => [
                    'text' => 'Updated Component',
                ],
            ],
        ]),
    ];

    $response = makeRequest('PUT', "$baseUrl/components/$componentId", $data);

    if ($response) {
        echo 'Status: '.$response['status']."\n";
        if ($response['status'] === 200) {
            echo "Component updated successfully!\n";
            echo 'New name: '.$response['body']['component']['name']."\n";
        } else {
            echo "Failed to update component.\n";
            print_r($response['body']);
        }
    }
}

// Test deleting a component
function testDeleteComponent($baseUrl, $componentId)
{
    echo "\nTesting deleting component...\n";

    $response = makeRequest('DELETE', "$baseUrl/components/$componentId");

    if ($response) {
        echo 'Status: '.$response['status']."\n";
        if ($response['status'] === 204) {
            echo "Component deleted successfully!\n";
        } else {
            echo "Failed to delete component.\n";
            print_r($response['body']);
        }
    }
}

// Test invalid component creation (missing required fields)
function testInvalidComponentCreation($baseUrl)
{
    echo "\nTesting invalid component creation (missing required fields)...\n";

    $data = [
        'name' => 'Invalid Component',
        // Missing type and definition
    ];

    $response = makeRequest('POST', "$baseUrl/components", $data);

    if ($response) {
        echo 'Status: '.$response['status']."\n";
        if ($response['status'] === 422) {
            echo "Validation failed as expected!\n";
            echo 'Error fields: '.implode(', ', array_keys($response['body']['errors']))."\n";
        } else {
            echo "Unexpected response.\n";
            print_r($response['body']);
        }
    }
}

// Test invalid component creation (invalid definition)
function testInvalidDefinition($baseUrl)
{
    echo "\nTesting invalid component creation (invalid definition)...\n";

    $data = [
        'name' => 'Invalid Definition Component',
        'type' => 'Custom',
        'definition' => json_encode([
            // Missing default.size properties
            'default' => [
                'content' => [
                    'text' => 'Invalid Definition',
                ],
            ],
        ]),
    ];

    $response = makeRequest('POST', "$baseUrl/components", $data);

    if ($response) {
        echo 'Status: '.$response['status']."\n";
        if ($response['status'] === 422) {
            echo "Validation failed as expected!\n";
            if (isset($response['body']['errors']['definition'])) {
                echo 'Definition error: '.$response['body']['errors']['definition'][0]."\n";
            } else {
                print_r($response['body']['errors']);
            }
        } else {
            echo "Unexpected response.\n";
            print_r($response['body']);
        }
    }
}

// Run the tests
echo "Starting component API tests...\n\n";

// Test getting components first to see if there are any
testGetComponents($baseUrl);

// Test creating a component
$componentId = testCreateComponent($baseUrl);

// Test creating a component with an icon
$componentWithIconId = testCreateComponentWithIcon($baseUrl);

if ($componentId) {
    // Test getting the component
    testGetComponent($baseUrl, $componentId);

    // Test updating the component
    testUpdateComponent($baseUrl, $componentId);

    // Test deleting the component
    // Uncomment the line below to test deletion
    // testDeleteComponent($baseUrl, $componentId);
}

if ($componentWithIconId) {
    // Test getting the component with icon
    testGetComponent($baseUrl, $componentWithIconId);

    // Test deleting the component with icon
    // Uncomment the line below to test deletion
    // testDeleteComponent($baseUrl, $componentWithIconId);
}

// Test invalid component creation
testInvalidComponentCreation($baseUrl);

// Test invalid definition
testInvalidDefinition($baseUrl);

echo "\nComponent API tests completed.\n";
