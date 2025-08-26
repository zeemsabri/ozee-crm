<?php

// Test script to verify the project note reply functionality

// Mock classes to simulate the behavior
class MockProject {
    public $id = 1;
    public $name = 'Test Project';
    public $google_chat_id = 'spaces/ABCDEF';

    public function notes() {
        return new MockNotesRelation();
    }
}

class MockProjectNote {
    public $id = 1;
    public $content = 'This is the original note';
    public $user_id = 1;
    public $chat_message_id = 'spaces/ABCDEF/messages/12345';
    public $created_at = '2025-07-22 10:00:00';
}

class MockNotesRelation {
    public function create($data) {
        $note = new \stdClass();
        $note->id = rand(1000, 9999);
        $note->content = $data['content']; // This would be encrypted in the real app
        $note->user_id = $data['user_id'];
        $note->chat_message_id = $data['chat_message_id'] ?? null;
        $note->created_at = date('Y-m-d H:i:s');
        return $note;
    }
}

class MockGoogleChatService {
    public function sendThreadedMessage($spaceName, $threadName, $messageText) {
        echo "Sending threaded message to Google Chat space: $spaceName\n";
        echo "Thread name: $threadName\n";
        echo "Message text: $messageText\n";

        // Simulate successful message sending
        return [
            'name' => 'spaces/ABCDEF/messages/' . rand(10000, 99999),
            'text' => $messageText,
            'sender' => 'users/bot',
            'createTime' => date('Y-m-d\TH:i:s\Z'),
            'thread' => $threadName
        ];
    }
}

class MockUser {
    public $id = 1;
    public $name = 'Test User';
    public $email = 'test@example.com';
}

class MockAuth {
    private static $user;

    public static function setUser($user) {
        self::$user = $user;
    }

    public static function user() {
        return self::$user;
    }

    public static function id() {
        return self::$user ? self::$user->id : null;
    }
}

class MockCrypt {
    public static function encryptString($value) {
        return 'ENCRYPTED:' . $value;
    }

    public static function decryptString($value) {
        if (strpos($value, 'ENCRYPTED:') === 0) {
            return substr($value, 10);
        }
        return $value;
    }
}

class MockLog {
    public static function info($message, $context = []) {
        echo "LOG INFO: $message\n";
        if (!empty($context)) {
            echo "Context: " . json_encode($context, JSON_PRETTY_PRINT) . "\n";
        }
    }

    public static function error($message, $context = []) {
        echo "LOG ERROR: $message\n";
        if (!empty($context)) {
            echo "Context: " . json_encode($context, JSON_PRETTY_PRINT) . "\n";
        }
    }
}

// Simulate the ProjectController's replyToNote method
function simulateReplyToNote($project, $note, $replyContent) {
    echo "=== Simulating ProjectController::replyToNote ===\n";

    // Setup mocks
    $user = new MockUser();
    MockAuth::setUser($user);
    $googleChatService = new MockGoogleChatService();

    echo "Project: {$project->name} (ID: {$project->id})\n";
    echo "Original Note ID: {$note->id}\n";
    echo "Original Note Content: {$note->content}\n";
    echo "Original Note Chat Message ID: {$note->chat_message_id}\n";
    echo "User: {$user->name} (ID: {$user->id})\n";
    echo "Google Chat Space: {$project->google_chat_id}\n\n";

    // Check if the project has a Google Chat space
    if (!$project->google_chat_id) {
        echo "Error: This project does not have a Google Chat space.\n";
        return [
            'message' => 'This project does not have a Google Chat space.',
            'success' => false
        ];
    }

    // Check if the note has a chat_message_id
    if (!$note->chat_message_id) {
        echo "Error: This note does not have an associated Google Chat message.\n";
        return [
            'message' => 'This note does not have an associated Google Chat message.',
            'success' => false
        ];
    }

    $messageText = "💬 *{$user->name}*: " . $replyContent;

    try {
        // Extract thread name from the message ID
        // Message ID format: spaces/{space}/messages/{message}
        // Thread name format: spaces/{space}/threads/{thread}
        $messageParts = explode('/', $note->chat_message_id);
        if (count($messageParts) >= 4) {
            $spaceName = $messageParts[0] . '/' . $messageParts[1];
            $threadName = $spaceName . '/threads/' . $messageParts[3];

            echo "Extracted space name: $spaceName\n";
            echo "Constructed thread name: $threadName\n\n";

            // Send the reply
            $response = $googleChatService->sendThreadedMessage(
                $project->google_chat_id,
                $threadName,
                $messageText
            );

            // Create a new note for the reply
            $replyNote = $project->notes()->create([
                'content' => MockCrypt::encryptString($replyContent),
                'user_id' => MockAuth::id(),
                'chat_message_id' => $response['name'] ?? null,
            ]);

            // Set the decrypted content for the response
            $replyNote->content = $replyContent;

            MockLog::info('Sent reply to note in Google Chat thread', [
                'project_id' => $project->id,
                'space_name' => $project->google_chat_id,
                'thread_name' => $threadName,
                'user_id' => $user->id,
                'original_note_id' => $note->id,
                'reply_note_id' => $replyNote->id,
                'chat_message_id' => $response['name'] ?? null
            ]);

            echo "\nReply note created with ID: {$replyNote->id}\n";
            echo "Reply note content: {$replyNote->content}\n";
            echo "Reply note chat message ID: {$replyNote->chat_message_id}\n\n";

            return [
                'message' => 'Reply sent successfully',
                'note' => $replyNote,
                'success' => true
            ];

        } else {
            throw new \Exception('Invalid message ID format');
        }
    } catch (\Exception $e) {
        MockLog::error('Failed to send reply to note in Google Chat thread', [
            'project_id' => $project->id,
            'space_name' => $project->google_chat_id,
            'note_id' => $note->id,
            'error' => $e->getMessage()
        ]);

        return [
            'message' => 'Failed to send reply: ' . $e->getMessage(),
            'success' => false
        ];
    }
}

// Run the test with a valid project and note
echo "Running test with a valid project and note...\n\n";
$project = new MockProject();
$note = new MockProjectNote();
$result1 = simulateReplyToNote($project, $note, "This is a reply to the original note.");

// Run test with a note that has no chat_message_id
echo "\nRunning test with a note that has no chat_message_id...\n\n";
$noteNoMessageId = new MockProjectNote();
$noteNoMessageId->chat_message_id = null;
$result2 = simulateReplyToNote($project, $noteNoMessageId, "This reply should fail.");

// Run test with a project that has no Google Chat space
echo "\nRunning test with a project that has no Google Chat space...\n\n";
$projectNoChat = new MockProject();
$projectNoChat->google_chat_id = null;
$result3 = simulateReplyToNote($projectNoChat, $note, "This reply should also fail.");

// Run test with an invalid message ID format
echo "\nRunning test with an invalid message ID format...\n\n";
$noteInvalidMessageId = new MockProjectNote();
$noteInvalidMessageId->chat_message_id = 'invalid-format';
$result4 = simulateReplyToNote($project, $noteInvalidMessageId, "This reply should fail due to invalid message ID format.");

echo "\n=== All tests completed ===\n";
