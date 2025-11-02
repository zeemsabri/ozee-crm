<?php

// Test script to verify the integration of Google Chat tasks with the Project controller

// Mock classes to simulate the behavior
class MockProject
{
    public $id = 1;

    public $name = 'Test Project';

    public $google_chat_id = 'spaces/ABCDEF';

    public function __construct($hasGoogleChat = true)
    {
        if (! $hasGoogleChat) {
            $this->google_chat_id = null;
        }
    }
}

class MockGoogleChatService
{
    public function getTasksFromSpace($spaceName)
    {
        echo "Fetching tasks from Google Chat space: $spaceName\n";

        // Simulate tasks from Google Chat
        return [
            [
                'message_id' => 'spaces/ABCDEF/messages/12345',
                'description' => 'Complete the homepage design',
                'assignee_email' => 'designer@example.com',
                'create_time' => '2025-07-20T10:30:00Z',
            ],
            [
                'message_id' => 'spaces/ABCDEF/messages/67890',
                'description' => 'Implement API endpoints',
                'assignee_email' => 'developer@example.com',
                'create_time' => '2025-07-21T09:15:00Z',
            ],
            [
                'message_id' => 'spaces/ABCDEF/messages/54321',
                'description' => 'Write documentation',
                'assignee_email' => '',
                'create_time' => '2025-07-22T14:45:00Z',
            ],
        ];
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

// Simulate the ProjectController's getTasks method
function simulateGetTasks($project)
{
    echo "=== Simulating ProjectController::getTasks ===\n";

    echo "Project: {$project->name} (ID: {$project->id})\n";
    echo 'Google Chat Space: '.($project->google_chat_id ?? 'None')."\n\n";

    // Check if the project has a Google Chat space
    if (! $project->google_chat_id) {
        echo "This project does not have a Google Chat space.\n";

        return [
            'message' => 'This project does not have a Google Chat space.',
            'tasks' => [],
        ];
    }

    try {
        // Get tasks from Google Chat space
        $googleChatService = new MockGoogleChatService;
        $tasks = $googleChatService->getTasksFromSpace($project->google_chat_id);

        echo 'Retrieved '.count($tasks)." tasks from Google Chat\n\n";

        // Map tasks to a more frontend-friendly format
        $formattedTasks = array_map(function ($task) {
            return [
                'id' => $task['message_id'], // Use message_id as unique identifier
                'title' => $task['description'],
                'status' => 'To Do', // Default status since Google Chat doesn't track status
                'assigned_to' => $task['assignee_email'] ?: 'Unassigned',
                'due_date' => null, // Google Chat doesn't track due dates
                'create_time' => $task['create_time'],
            ];
        }, $tasks);

        echo "Formatted tasks for frontend:\n";
        foreach ($formattedTasks as $index => $task) {
            echo 'Task #'.($index + 1).":\n";
            echo '- ID: '.$task['id']."\n";
            echo '- Title: '.$task['title']."\n";
            echo '- Status: '.$task['status']."\n";
            echo '- Assigned To: '.$task['assigned_to']."\n";
            echo '- Created: '.$task['create_time']."\n\n";
        }

        return $formattedTasks;
    } catch (\Exception $e) {
        MockLog::error('Failed to fetch tasks from Google Chat', [
            'project_id' => $project->id,
            'space_name' => $project->google_chat_id,
            'error' => $e->getMessage(),
        ]);

        return [
            'message' => 'Failed to fetch tasks from Google Chat: '.$e->getMessage(),
            'tasks' => [],
        ];
    }
}

// Run the test with a project that has a Google Chat space
echo "Running test with a project that has a Google Chat space...\n\n";
$projectWithChat = new MockProject;
$result1 = simulateGetTasks($projectWithChat);

// Run another test with a project that has no Google Chat space
echo "\nRunning test with a project that has no Google Chat space...\n\n";
$projectNoChat = new MockProject(false);
$result2 = simulateGetTasks($projectNoChat);

echo "\n=== All tests completed ===\n";
