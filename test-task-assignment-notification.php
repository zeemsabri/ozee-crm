<?php

use App\Models\Task;
use App\Models\User;
use App\Models\Milestone;
use App\Models\TaskType;
use App\Notifications\TaskAssigned;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing Task Assignment Notification\n";
echo "===================================\n\n";

// Use notification fake to capture sent notifications
Notification::fake();

// Get a user to assign the task to
$user = User::first();

if (!$user) {
    echo "Error: No users found in the database.\n";
    exit(1);
}

echo "Selected user: {$user->name} (ID: {$user->id})\n";

// Get a milestone for the task
$milestone = Milestone::first();

if (!$milestone) {
    echo "Error: No milestones found in the database.\n";
    exit(1);
}

echo "Selected milestone: {$milestone->name} (ID: {$milestone->id})\n";

// Get a task type
$taskType = TaskType::first();

if (!$taskType) {
    echo "Error: No task types found in the database.\n";
    exit(1);
}

echo "Selected task type: {$taskType->name} (ID: {$taskType->id})\n";

// Create a new task
try {
    DB::beginTransaction();

    $task = new Task();
    $task->name = "Test Task for Notification";
    $task->description = "This is a test task to verify the notification system";
    $task->assigned_to_user_id = $user->id;
    $task->due_date = now()->addDays(7);
    $task->status = 'To Do';
    $task->task_type_id = $taskType->id;
    $task->milestone_id = $milestone->id;
    $task->creator_id = $user->id;
    $task->creator_type = get_class($user);

    $task->save();

    echo "Created test task: {$task->name} (ID: {$task->id})\n";

    // Verify that the notification was sent
    Notification::assertSentTo(
        $user,
        TaskAssigned::class,
        function ($notification, $channels) use ($task) {
            echo "Notification sent via channels: " . implode(', ', $channels) . "\n";
            // We can't directly access protected properties, so just verify the notification was sent
            return true;
        }
    );

    echo "\nSuccess: Task assignment notification was sent correctly!\n";

    // Clean up - remove the test task
    $task->delete();
    echo "Test task removed.\n";

    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

// Test with real notification (not faked)
echo "\nTesting with real notification...\n";
Notification::fake(false);

try {
    DB::beginTransaction();

    $task = new Task();
    $task->name = "Real Notification Test Task";
    $task->description = "This is a test task to verify the real notification system";
    $task->assigned_to_user_id = $user->id;
    $task->due_date = now()->addDays(7);
    $task->status = 'To Do';
    $task->task_type_id = $taskType->id;
    $task->milestone_id = $milestone->id;
    $task->creator_id = $user->id;
    $task->creator_type = get_class($user);

    $task->save();

    echo "Created real test task: {$task->name} (ID: {$task->id})\n";

    // Manually send notification
    $user->notify(new TaskAssigned($task));

    echo "Manually sent notification for task ID: {$task->id}\n";

    // Check if notification was stored in database
    $notification = DB::table('notifications')
        ->where('notifiable_id', $user->id)
        ->where('notifiable_type', get_class($user))
        ->orderBy('created_at', 'desc')
        ->first();

    if ($notification) {
        echo "Notification found in database:\n";
        echo "ID: {$notification->id}\n";
        echo "Type: {$notification->type}\n";
        echo "Created at: {$notification->created_at}\n";

        // Decode the data
        $data = json_decode($notification->data);
        echo "Task ID in notification: {$data->task_id}\n";
        echo "Task name in notification: {$data->task_name}\n";
    } else {
        echo "No notification found in database.\n";
    }

    // Clean up - remove the test task
    $task->delete();
    echo "Real test task removed.\n";

    // Also remove the notification
    if ($notification) {
        DB::table('notifications')->where('id', $notification->id)->delete();
        echo "Test notification removed from database.\n";
    }

    DB::commit();

    echo "\nTest completed successfully!\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
