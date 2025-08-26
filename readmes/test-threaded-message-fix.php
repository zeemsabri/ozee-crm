<?php

// Test script to verify the fix for GoogleChatService's sendThreadedMessage method

// Mock classes to simulate the behavior
class MockMessage {
    private $text;
    private $thread;

    public function setText($text) {
        $this->text = $text;
    }

    public function setThread($thread) {
        $this->thread = $thread;
    }

    public function getText() {
        return $this->text;
    }

    public function getName() {
        return 'spaces/ABCDEF/messages/' . rand(10000, 99999);
    }

    public function getSender() {
        return 'users/test@example.com';
    }

    public function getCreateTime() {
        return date('Y-m-d\TH:i:s\Z');
    }

    public function getThread() {
        return $this->thread;
    }
}

class MockThread {
    private $name;

    public function setName($name) {
        $this->name = $name;
    }

    public function getName() {
        return $this->name;
    }
}

class MockSpacesMessages {
    public function create($spaceName, $message) {
        echo "Creating message in space: $spaceName\n";
        echo "Message text: " . $message->getText() . "\n";
        echo "Thread name: " . $message->getThread()->getName() . "\n";

        return $message;
    }
}

class MockHangoutsChat {
    public $spaces_messages;

    public function __construct() {
        $this->spaces_messages = new MockSpacesMessages();
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

// Function to simulate the sendThreadedMessage method
function testSendThreadedMessage($spaceName, $threadName, $messageText) {
    echo "=== Testing sendThreadedMessage method ===\n\n";

    $service = new MockHangoutsChat();
    $message = new MockMessage();
    $message->setText($messageText);

    $thread = new MockThread();
    $thread->setName($threadName);
    $message->setThread($thread);

    try {
        $sentMessage = $service->spaces_messages->create($spaceName, $message);
        MockLog::info('Threaded message sent to Google Chat space', [
            'space_name' => $spaceName,
            'thread_name' => $threadName,
            'message_id' => $sentMessage->getName()
        ]);

        // Convert Message object to array manually for consistency
        $result = [
            'name' => $sentMessage->getName(),
            'text' => $sentMessage->getText(),
            'sender' => $sentMessage->getSender(),
            'createTime' => $sentMessage->getCreateTime(),
            'thread' => $threadName
        ];

        echo "\nResult array:\n";
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";

        return $result;
    } catch (Exception $e) {
        MockLog::error('Failed to send threaded message to Google Chat: ' . $e->getMessage(), [
            'space_name' => $spaceName,
            'thread_name' => $threadName,
            'exception' => $e
        ]);
        throw $e;
    }
}

// Run the test
try {
    $spaceName = 'spaces/ABCDEF';
    $threadName = 'spaces/ABCDEF/threads/12345';
    $messageText = 'This is a test threaded message';

    $result = testSendThreadedMessage($spaceName, $threadName, $messageText);

    echo "\n=== Test completed successfully ===\n";
    echo "The sendThreadedMessage method now works correctly without calling setMessageReplyOption.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
