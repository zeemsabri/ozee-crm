<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

echo "Testing UserController relationship loading fixes...\n";

// Get a user
$user = User::first();
if (!$user) {
    echo "No users found in the database\n";
    exit;
}

echo "User found: {$user->name}\n";

// Test role relationship
echo "Testing role relationship...\n";
$role = $user->role;
if ($role) {
    echo "Role relationship works: {$role->name} (ID: {$role->id})\n";
} else {
    echo "Role relationship returned null\n";
}

// Test roles relationship (legacy)
echo "Testing roles relationship (legacy)...\n";
$roles = $user->roles;
if ($roles && $roles->count() > 0) {
    echo "Roles relationship works: {$roles->count()} roles found\n";
    echo "First role: {$roles->first()->name}\n";
} else {
    echo "Roles relationship returned empty collection or null\n";
}

// Test app_role attribute
echo "Testing app_role attribute...\n";
$appRole = $user->app_role;
echo "app_role value: " . $appRole . "\n";

// Test role_data attribute
echo "Testing role_data attribute...\n";
$roleData = $user->role_data;
if (is_array($roleData)) {
    echo "role_data is an array with keys: " . implode(", ", array_keys($roleData)) . "\n";
    echo "role_data values: id={$roleData['id']}, name={$roleData['name']}, slug={$roleData['slug']}\n";
} else {
    echo "role_data is not an array or is null\n";
}

// Test loading relationships
echo "\nTesting relationship loading...\n";

// Test loading 'role'
echo "Loading 'role' relationship...\n";
$userWithRole = $user->load('role');
if ($userWithRole->relationLoaded('role')) {
    echo "PASS: 'role' relationship loaded successfully\n";
    echo "Role: {$userWithRole->role->name}\n";
} else {
    echo "FAIL: 'role' relationship not loaded\n";
}

// Test loading 'roles' (legacy)
echo "Loading 'roles' relationship...\n";
try {
    $userWithRoles = $user->load('roles');
    if ($userWithRoles->relationLoaded('roles')) {
        echo "PASS: 'roles' relationship loaded successfully\n";
        echo "Roles count: {$userWithRoles->roles->count()}\n";
    } else {
        echo "FAIL: 'roles' relationship not loaded\n";
    }
} catch (\Exception $e) {
    echo "ERROR: Could not load 'roles' relationship: " . $e->getMessage() . "\n";
}

// Test loading both 'projects' and 'role'
echo "Loading 'projects' and 'role' relationships...\n";
try {
    $userWithProjectsAndRole = $user->load(['projects', 'role']);
    if ($userWithProjectsAndRole->relationLoaded('projects') && $userWithProjectsAndRole->relationLoaded('role')) {
        echo "PASS: Both 'projects' and 'role' relationships loaded successfully\n";
    } else {
        echo "FAIL: Not all relationships loaded\n";
    }
} catch (\Exception $e) {
    echo "ERROR: Could not load relationships: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";
