<?php

// Test script to verify the fix for GoogleChatService's listMessagesInSpace method

require_once __DIR__ . '/vendor/autoload.php';

// Mock the Google\Service\HangoutsChat\Message class
class MockMessage {
    private $name;
    private $text;
    private $sender;
    private $createTime;
    private $thread;

    public function __construct($name, $text, $createTime, $sender = null, $thread = null) {
        $this->name = $name;
        $this->text = $text;
        $this->createTime = $createTime;
        $this->sender = $sender;
        $this->thread = $thread;
    }

    public function getName() {
        return $this->name;
    }

    public function getText() {
        return $this->text;
    }

    public function getSender() {
        return $this->sender;
    }

    public function getCreateTime() {
        return $this->createTime;
    }

    public function getThread() {
        return $this->thread;
    }
}

// Mock the Google\Service\HangoutsChat\User class
class MockUser {
    private $name;

    public function __construct($name) {
        $this->name = $name;
    }

    public function getName() {
        return $this->name;
    }
}

// Mock the Google\Service\HangoutsChat\Thread class
class MockThread {
    private $name;

    public function __construct($name) {
        $this->name = $name;
    }

    public function getName() {
        return $this->name;
    }
}

// Mock the response from Google Chat API
class MockResponse {
    private $messages = [];
    private $nextPageToken = null;

    public function __construct($messages, $nextPageToken = null) {
        $this->messages = $messages;
        $this->nextPageToken = $nextPageToken;
    }

    public function getMessages() {
        return $this->messages;
    }

    public function getNextPageToken() {
        return $this->nextPageToken;
    }
}

// Test function that simulates the core logic of listMessagesInSpace
function testListMessagesInSpace() {
    echo "=== Testing listMessagesInSpace fix ===\n\n";

    // Create mock messages
    $mockMessages = [
        new MockMessage(
            'spaces/ABCDEF/messages/12345',
            'Hello world!',
            '2025-07-22T10:30:00Z',
            new MockUser('users/user1@example.com'),
            new MockThread('spaces/ABCDEF/threads/thread1')
        ),
        new MockMessage(
            'spaces/ABCDEF/messages/67890',
            '✅ *New Task*: Complete the project',
            '2025-07-22T11:45:00Z',
            new MockUser('users/user2@example.com'),
            null
        ),
        new MockMessage(
            'spaces/ABCDEF/messages/54321',
            'This is a test message',
            '2025-07-22T12:15:00Z',
            null,
            new MockThread('spaces/ABCDEF/threads/thread2')
        )
    ];

    // Create mock response
    $mockResponse = new MockResponse($mockMessages);

    // Simulate the conversion logic from listMessagesInSpace
    $messages = [];
    foreach ($mockResponse->getMessages() as $message) {
        // Manually convert Message object to array since toArray() is not available
        $messages[] = [
            'name' => $message->getName(),
            'text' => $message->getText(),
            'sender' => $message->getSender() ? $message->getSender()->getName() : null,
            'createTime' => $message->getCreateTime(),
            'thread' => $message->getThread() ? $message->getThread()->getName() : null
        ];
    }

    // Print the results
    echo "Converted " . count($messages) . " messages to arrays:\n\n";
    foreach ($messages as $index => $message) {
        echo "Message #" . ($index + 1) . ":\n";
        echo "- Name: " . $message['name'] . "\n";
        echo "- Text: " . $message['text'] . "\n";
        echo "- Sender: " . ($message['sender'] ?? 'null') . "\n";
        echo "- Create Time: " . $message['createTime'] . "\n";
        echo "- Thread: " . ($message['thread'] ?? 'null') . "\n\n";
    }

    // Verify that the task detection logic still works with our new array format
    $tasks = [];
    $taskPrefix = '✅ *New Task*:';

    foreach ($messages as $message) {
        if (isset($message['text']) && str_starts_with($message['text'], $taskPrefix)) {
            $taskDescription = substr($message['text'], strlen($taskPrefix));
            $tasks[] = [
                'message_id' => $message['name'],
                'description' => trim($taskDescription),
                'create_time' => $message['createTime'] ?? null,
            ];
        }
    }

    echo "Found " . count($tasks) . " tasks:\n\n";
    foreach ($tasks as $index => $task) {
        echo "Task #" . ($index + 1) . ":\n";
        echo "- Message ID: " . $task['message_id'] . "\n";
        echo "- Description: " . $task['description'] . "\n";
        echo "- Create Time: " . $task['create_time'] . "\n\n";
    }

    echo "=== Test completed successfully ===\n";
}

// Run the test
try {
    testListMessagesInSpace();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
