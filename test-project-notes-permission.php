<?php

// Test script to verify that users with add_project_notes permission can add notes to projects

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Project;
use App\Policies\ProjectPolicy;

// Mock classes for testing
class MockUser {
    public $id;
    public $name;
    private $permissions = [];

    public function __construct($id, $name, $permissions = []) {
        $this->id = $id;
        $this->name = $name;
        $this->permissions = $permissions;
    }

    public function hasPermission($permission) {
        return in_array($permission, $this->permissions);
    }

    public function isContractor() {
        return false;
    }
}

class MockProject {
    public $id;
    public $name;
    public $users;

    public function __construct($id, $name, $users = []) {
        $this->id = $id;
        $this->name = $name;
        $this->users = collect($users);
    }
}

// Test the ProjectPolicy's addNotes method
function testAddNotesPolicy() {
    echo "=== Testing ProjectPolicy::addNotes ===\n\n";

    // Create a policy instance
    $policy = new class extends ProjectPolicy {
        // Override the userHasProjectPermission method for testing
        protected function userHasProjectPermission($user, $permission, $projectId) {
            // For testing purposes, we'll simulate that user with ID 3 has project-specific permission
            return $user->id === 3 && $permission === 'add_project_notes';
        }
    };

    // Test cases
    $testCases = [
        [
            'description' => 'Super admin with global add_project_notes permission',
            'user' => new MockUser(1, 'Super Admin', ['add_project_notes']),
            'project' => new MockProject(1, 'Test Project'),
            'expected' => true
        ],
        [
            'description' => 'Regular user without add_project_notes permission',
            'user' => new MockUser(2, 'Regular User', []),
            'project' => new MockProject(1, 'Test Project'),
            'expected' => false
        ],
        [
            'description' => 'User with project-specific add_project_notes permission',
            'user' => new MockUser(3, 'Project Member', []),
            'project' => new MockProject(1, 'Test Project'),
            'expected' => true
        ]
    ];

    // Run the tests
    foreach ($testCases as $index => $testCase) {
        echo "Test Case #" . ($index + 1) . ": " . $testCase['description'] . "\n";

        $result = $policy->addNotes($testCase['user'], $testCase['project']);

        echo "Expected: " . ($testCase['expected'] ? 'true' : 'false') . "\n";
        echo "Actual: " . ($result ? 'true' : 'false') . "\n";
        echo "Result: " . ($result === $testCase['expected'] ? "PASS" : "FAIL") . "\n\n";
    }

    echo "=== All tests completed ===\n";
}

// Run the tests
try {
    testAddNotesPolicy();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
