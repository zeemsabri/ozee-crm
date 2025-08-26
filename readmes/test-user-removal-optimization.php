<?php

// Test script to verify the optimization of user removal from Google Chat spaces
// This script simulates the flow of data between ProjectController and GoogleChatService

class MockUser {
    public $id;
    public $email;
    public $chat_name;

    public function __construct($id, $email, $chat_name = null) {
        $this->id = $id;
        $this->email = $email;
        $this->chat_name = $chat_name;
    }
}

class MockCollection {
    private $items = [];

    public function __construct($items = []) {
        $this->items = $items;
    }

    public function count() {
        return count($this->items);
    }

    public function all() {
        return $this->items;
    }

    public function pluck($key) {
        $result = [];
        foreach ($this->items as $item) {
            if (property_exists($item, $key)) {
                $result[] = $item->{$key};
            }
        }
        return $result;
    }
}

class MockGoogleChatService {
    public function removeMembersFromSpace($spaceName, $users) {
        echo "Removing members from space: $spaceName\n";
        echo "Users received by GoogleChatService:\n";

        foreach ($users as $user) {
            echo "- ID: {$user->id}, Email: {$user->email}, Chat Name: " .
                 ($user->chat_name ? $user->chat_name : "N/A") . "\n";
        }

        // Simulate the processing that would happen in the real service
        echo "\nProcessing users in GoogleChatService...\n";
        foreach ($users as $user) {
            echo "- Looking up user {$user->email} ";
            if ($user->chat_name) {
                echo "using chat_name: {$user->chat_name}\n";
                // In the real service, this would use the chat_name to find the membership
            } else {
                echo "without chat_name (fallback to email matching)\n";
                // In the real service, this would fall back to email matching
            }
        }

        return true;
    }
}

// Simulate the ProjectController's detachUsers method
function simulateDetachUsers($userIds) {
    echo "=== Simulating ProjectController::detachUsers ===\n";

    // Create mock users with different combinations of properties
    $users = [
        new MockUser(1, "user1@example.com", "users/user1@example.com"),
        new MockUser(2, "user2@example.com", null),
        new MockUser(3, "user3@example.com", "users/different_format")
    ];

    // Filter users based on the provided IDs
    $usersToDetach = [];
    foreach ($users as $user) {
        if (in_array($user->id, $userIds)) {
            $usersToDetach[] = $user;
        }
    }
    $usersCollection = new MockCollection($usersToDetach);

    echo "Users to detach:\n";
    foreach ($usersToDetach as $user) {
        echo "- ID: {$user->id}, Email: {$user->email}, Chat Name: " .
             ($user->chat_name ? $user->chat_name : "N/A") . "\n";
    }

    // Simulate detaching users from project
    echo "\nDetaching users from project...\n";

    // Simulate calling GoogleChatService
    $googleChatService = new MockGoogleChatService();
    $spaceName = "spaces/ABCDEF";

    echo "\nCalling GoogleChatService::removeMembersFromSpace...\n";
    $googleChatService->removeMembersFromSpace($spaceName, $usersCollection->all());

    echo "\n=== Simulation completed ===\n";
}

// Run the simulation with user IDs 1 and 3
echo "Running test with user IDs [1, 3]...\n\n";
simulateDetachUsers([1, 3]);
