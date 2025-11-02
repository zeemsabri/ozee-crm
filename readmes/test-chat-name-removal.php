<?php

// Simple test script to verify the logic of using chat_name in removeMembersFromSpace

// Mock data
$mockUser = (object) [
    'email' => 'test@example.com',
    'chat_name' => 'users/test@example.com',
];

$mockMembers = [
    [
        'name' => 'spaces/ABCDEF/members/12345',
        'user_name' => 'users/test@example.com',
        'email' => 'test@example.com',
    ],
];

// Test function that simulates the core logic of removeMembersFromSpace
function testRemoveMemberLogic($emailToRemove, $mockUser, $currentMembers)
{
    $foundMembershipName = null;

    echo "Testing removal for email: $emailToRemove\n";

    // First try to find the member using chat_name if available
    if ($mockUser && isset($mockUser->chat_name)) {
        echo "User has chat_name: {$mockUser->chat_name}\n";

        foreach ($currentMembers as $member) {
            if (isset($member['user_name']) && $member['user_name'] === $mockUser->chat_name) {
                $foundMembershipName = $member['name'];
                echo "Found membership by chat_name: $foundMembershipName\n";
                break;
            }
        }
    } else {
        echo "User has no chat_name\n";
    }

    // Fallback to email matching if chat_name didn't work
    if (! $foundMembershipName) {
        echo "Falling back to email matching\n";

        foreach ($currentMembers as $member) {
            if (isset($member['email']) && strtolower($member['email']) === strtolower($emailToRemove)) {
                $foundMembershipName = $member['name'];
                echo "Found membership by email: $foundMembershipName\n";
                break;
            }
        }
    }

    if ($foundMembershipName) {
        echo "Would delete membership: $foundMembershipName\n";

        return true;
    } else {
        echo "No membership found to delete\n";

        return false;
    }
}

// Run the test
try {
    echo "=== Test 1: User with chat_name ===\n";
    $result1 = testRemoveMemberLogic('test@example.com', $mockUser, $mockMembers);
    echo 'Test 1 result: '.($result1 ? 'PASS' : 'FAIL')."\n\n";

    echo "=== Test 2: User without chat_name ===\n";
    $mockUserNoChat = (object) ['email' => 'test@example.com']; // No chat_name
    $result2 = testRemoveMemberLogic('test@example.com', $mockUserNoChat, $mockMembers);
    echo 'Test 2 result: '.($result2 ? 'PASS' : 'FAIL')."\n\n";

    echo "=== Test 3: User with incorrect chat_name ===\n";
    $mockUserWrongChat = (object) ['email' => 'test@example.com', 'chat_name' => 'users/wrong@example.com'];
    $result3 = testRemoveMemberLogic('test@example.com', $mockUserWrongChat, $mockMembers);
    echo 'Test 3 result: '.($result3 ? 'PASS' : 'FAIL')."\n\n";

    echo "All tests completed!\n";
} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
}
