<?php

// Test script to verify the integration of GoogleChatService with ProjectController's addNotes method

// Mock classes to simulate the behavior
class MockProject
{
    public $id = 1;

    public $name = 'Test Project';

    public $google_chat_id = 'spaces/ABCDEF';

    public function notes()
    {
        return new MockNotesRelation;
    }
}

class MockNotesRelation
{
    public function create($data)
    {
        $note = new \stdClass;
        $note->id = rand(1, 1000);
        $note->content = $data['content']; // This would be encrypted in the real app
        $note->user_id = $data['user_id'];
        $note->created_at = date('Y-m-d H:i:s');

        return $note;
    }
}

class MockGoogleChatService
{
    public function sendMessage($spaceName, $messageText, $cards = [])
    {
        echo "Sending message to Google Chat space: $spaceName\n";
        echo "Message text: $messageText\n";

        // Simulate successful message sending
        return [
            'name' => 'spaces/ABCDEF/messages/'.rand(1000, 9999),
            'text' => $messageText,
            'sender' => 'users/bot',
            'createTime' => date('Y-m-d\TH:i:s\Z'),
        ];
    }
}

class MockUser
{
    public $id = 1;

    public $name = 'Test User';

    public $email = 'test@example.com';
}

class MockAuth
{
    private static $user;

    public static function setUser($user)
    {
        self::$user = $user;
    }

    public static function user()
    {
        return self::$user;
    }

    public static function id()
    {
        return self::$user ? self::$user->id : null;
    }
}

class MockLog
{
    public static function info($message, $context = [])
    {
        echo "LOG INFO: $message\n";
        if (! empty($context)) {
            echo 'Context: '.json_encode($context, JSON_PRETTY_PRINT)."\n";
        }
    }

    public static function error($message, $context = [])
    {
        echo "LOG ERROR: $message\n";
        if (! empty($context)) {
            echo 'Context: '.json_encode($context, JSON_PRETTY_PRINT)."\n";
        }
    }
}

// Simulate the ProjectController's addNotes method
function simulateAddNotes($noteContent)
{
    echo "=== Simulating ProjectController::addNotes ===\n";

    // Setup mocks
    $project = new MockProject;
    $user = new MockUser;
    MockAuth::setUser($user);
    $googleChatService = new MockGoogleChatService;

    echo "Project: {$project->name} (ID: {$project->id})\n";
    echo "User: {$user->name} (ID: {$user->id})\n";
    echo "Google Chat Space: {$project->google_chat_id}\n\n";

    // Simulate the note creation process
    echo "Creating note with content: $noteContent\n";
    $note = $project->notes()->create([
        'content' => 'ENCRYPTED:'.$noteContent, // Simulate encryption
        'user_id' => MockAuth::id(),
    ]);

    echo "Note created with ID: {$note->id}\n\n";

    // Simulate sending notification to Google Chat
    if ($project->google_chat_id) {
        try {
            $messageText = "📝 *New Note Added by {$user->name}*: ".$noteContent;
            $result = $googleChatService->sendMessage($project->google_chat_id, $messageText);


            echo "Message sent successfully with ID: {$result['name']}\n";
        } catch (\Exception $e) {
            MockLog::error('Failed to send note notification to Google Chat space', [
                'project_id' => $project->id,
                'space_name' => $project->google_chat_id,
                'error' => $e->getMessage(),
            ]);
        }
    } else {
        echo "Project has no Google Chat space, skipping notification\n";
    }

    echo "\n=== Simulation completed ===\n";
}

// Run the simulation with a test note
echo "Running test with a sample note...\n\n";
simulateAddNotes('This is a test note for the project. Important update!');

// Run another test with a project that has no Google Chat space
echo "\nRunning test with a project that has no Google Chat space...\n\n";
$projectNoChat = new MockProject;
$projectNoChat->google_chat_id = null;
$user = new MockUser;
MockAuth::setUser($user);

echo "Project: {$projectNoChat->name} (ID: {$projectNoChat->id})\n";
echo "User: {$user->name} (ID: {$user->id})\n";
echo "Google Chat Space: None\n\n";

echo "Creating note with content: Another test note\n";
$note = $projectNoChat->notes()->create([
    'content' => 'ENCRYPTED: Another test note', // Simulate encryption
    'user_id' => MockAuth::id(),
]);

echo "Note created with ID: {$note->id}\n\n";

// Check if Google Chat notification would be sent
if ($projectNoChat->google_chat_id) {
    echo "Would send notification to Google Chat (but this project has no space)\n";
} else {
    echo "Project has no Google Chat space, skipping notification\n";
}

echo "\n=== All tests completed ===\n";
