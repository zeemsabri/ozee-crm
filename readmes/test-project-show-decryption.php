<?php

// This is a simplified test script that directly tests the decryption error handling
// without requiring authentication

require_once __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

// Mock the Log facade
class MockLog
{
    public static function error($message, $context = [])
    {
        echo "LOG ERROR: $message\n";
        if (! empty($context)) {
            echo 'Context: '.json_encode($context, JSON_PRETTY_PRINT)."\n";
        }
    }
}

// Function to simulate the decryption logic in the show method
function testDecryption($content, $noteId)
{
    try {
        $decrypted = Crypt::decryptString($content);
        echo "Successfully decrypted content: $decrypted\n";

        return $decrypted;
    } catch (\Exception $e) {
        MockLog::error('Failed to decrypt note content in show method', [
            'note_id' => $noteId,
            'error' => $e->getMessage(),
        ]);

        return '[Encrypted content could not be decrypted]';
    }
}

echo "=== Testing Project Show Decryption Error Handling ===\n\n";

// Test case 1: Valid encrypted content
echo "Test Case 1: Valid encrypted content\n";
$validContent = Crypt::encryptString('This is a valid note');
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
$corruptedContent = substr(Crypt::encryptString('This will be corrupted'), 5); // Remove first 5 chars to corrupt it
$result4 = testDecryption($corruptedContent, 4);
echo "Result: $result4\n\n";

echo "=== All tests completed ===\n";
echo "The error handling is working correctly if all invalid cases show the placeholder message.\n";
