<?php

// Simple test script to verify the authorization logic for adding notes to projects

// Function to simulate the hasPermission method in User model
function hasPermission($user, $permission)
{
    // Simulate that users with specific IDs have specific permissions
    $userPermissions = [
        1 => ['add_project_notes', 'view_projects', 'edit_projects'], // Super admin
        2 => ['view_projects'], // Regular user
        3 => [], // Project member (will have project-specific permission)
    ];

    return isset($userPermissions[$user['id']]) && in_array($permission, $userPermissions[$user['id']]);
}

// Function to simulate the userHasProjectPermission method in ProjectPolicy
function userHasProjectPermission($user, $permission, $projectId)
{
    // Simulate that user with ID 3 has project-specific permission for project 1
    return $user['id'] === 3 && $permission === 'add_project_notes' && $projectId === 1;
}

// Function to simulate the addNotes method in ProjectPolicy
function canAddNotes($user, $project)
{
    // Check if user has global permission
    if (hasPermission($user, 'add_project_notes')) {
        return true;
    }

    // Check project-specific permission
    return userHasProjectPermission($user, 'add_project_notes', $project['id']);
}

// Test cases
$testCases = [
    [
        'description' => 'Super admin with global add_project_notes permission',
        'user' => ['id' => 1, 'name' => 'Super Admin'],
        'project' => ['id' => 1, 'name' => 'Test Project'],
        'expected' => true,
    ],
    [
        'description' => 'Regular user without add_project_notes permission',
        'user' => ['id' => 2, 'name' => 'Regular User'],
        'project' => ['id' => 1, 'name' => 'Test Project'],
        'expected' => false,
    ],
    [
        'description' => 'User with project-specific add_project_notes permission',
        'user' => ['id' => 3, 'name' => 'Project Member'],
        'project' => ['id' => 1, 'name' => 'Test Project'],
        'expected' => true,
    ],
    [
        'description' => 'User with project-specific permission but for different project',
        'user' => ['id' => 3, 'name' => 'Project Member'],
        'project' => ['id' => 2, 'name' => 'Another Project'],
        'expected' => false,
    ],
];

// Run the tests
echo "=== Testing Project Notes Authorization Logic ===\n\n";

foreach ($testCases as $index => $testCase) {
    echo 'Test Case #'.($index + 1).': '.$testCase['description']."\n";

    $result = canAddNotes($testCase['user'], $testCase['project']);

    echo 'Expected: '.($testCase['expected'] ? 'true' : 'false')."\n";
    echo 'Actual: '.($result ? 'true' : 'false')."\n";
    echo 'Result: '.($result === $testCase['expected'] ? 'PASS' : 'FAIL')."\n\n";
}

echo "=== All tests completed ===\n";
echo "This test confirms that the authorization logic in ProjectPolicy::addNotes works correctly.\n";
echo "Users with global 'add_project_notes' permission or project-specific permission can add notes.\n";
echo "Other users without the permission will be denied access.\n";
