<?php

// Simple test script to verify the extraction of ID from chat_name in addMembersToSpace

// Mock response data
$mockResponses = [
    (object) [
        'member' => (object) [
            'name' => 'users/test@example.com',
        ],
    ],
    (object) [
        'member' => (object) [
            'name' => 'users/12345',
        ],
    ],
    (object) [
        'member' => (object) [
            'name' => 'some-other-format',
        ],
    ],
    (object) [
        'member' => null,
    ],
];

// Test function that simulates the core logic of chat_name extraction
function testChatNameExtraction($response)
{
    $memberName = $response->member?->name;
    $chatName = $memberName;

    echo 'Original member name: '.($memberName ?? 'NULL')."\n";

    if ($memberName && strpos($memberName, 'users/') === 0) {
        $chatName = substr($memberName, 6); // Remove 'users/' prefix
        echo "Extracted chat_name: $chatName\n";
    } else {
        echo 'No extraction needed, using original: '.($chatName ?? 'NULL')."\n";
    }

    return $chatName;
}

// Run the tests
echo "=== Testing chat_name extraction logic ===\n\n";

foreach ($mockResponses as $index => $response) {
    echo 'Test case #'.($index + 1).":\n";
    $result = testChatNameExtraction($response);
    echo 'Final result: '.($result ?? 'NULL')."\n\n";
}

echo "All tests completed!\n";
