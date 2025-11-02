<?php

// This is a simplified test script that simulates the decryption error handling logic
// without requiring Laravel facades

// Function to simulate the decryption logic in the show method
function testDecryption($content, $noteId)
{
    try {
        // Simulate decryption - in real code this would be Crypt::decryptString()
        // For testing, we'll just consider strings starting with "ENCRYPTED:" as valid
        if (strpos($content, 'ENCRYPTED:') === 0) {
            $decrypted = substr($content, 10); // Remove "ENCRYPTED:" prefix
            echo "Successfully decrypted content: $decrypted\n";

            return $decrypted;
        } else {
            // Simulate a decryption exception
            throw new Exception('The payload is invalid.');
        }
    } catch (Exception $e) {
        echo "LOG ERROR: Failed to decrypt note content in show method\n";
        echo 'Context: '.json_encode([
            'note_id' => $noteId,
            'error' => $e->getMessage(),
        ], JSON_PRETTY_PRINT)."\n";

        return '[Encrypted content could not be decrypted]';
    }
}

echo "=== Testing Project Show Decryption Error Handling ===\n\n";

// Test case 1: Valid encrypted content
echo "Test Case 1: Valid encrypted content\n";
$validContent = 'ENCRYPTED:This is a valid note';
$result1 = testDecryption($validContent, 1);
echo "Result: $result1\n\n";

// Test case 2: Invalid encrypted content
echo "Test Case 2: Invalid encrypted content\n";
$invalidContent = 'This is not properly encrypted content';
$result2 = testDecryption($invalidContent, 2);
echo "Result: $result2\n\n";

// Test case 3: Empty content
echo "Test Case 3: Empty content\n";
$emptyContent = '';
$result3 = testDecryption($emptyContent, 3);
echo "Result: $result3\n\n";

// Test case 4: Corrupted encrypted content
echo "Test Case 4: Corrupted encrypted content\n";
$corruptedContent = 'CORR:This will be treated as corrupted';
$result4 = testDecryption($corruptedContent, 4);
echo "Result: $result4\n\n";

echo "=== All tests completed ===\n";
echo "The error handling is working correctly if all invalid cases show the placeholder message.\n";
echo "This simulates the behavior of our fix in the ProjectController show method.\n";
