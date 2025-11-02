<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "Testing UserController changes...\n";

// Get roles
$superAdminRole = Role::where('slug', 'super-admin')->first();
$managerRole = Role::where('slug', 'manager')->first();
$employeeRole = Role::where('slug', 'employee')->first();
$contractorRole = Role::where('slug', 'contractor')->first();

if (! $superAdminRole || ! $managerRole || ! $employeeRole || ! $contractorRole) {
    echo "ERROR: Required roles not found. Please ensure RolePermissionSeeder has been run.\n";
    exit(1);
}

// Test user creation with role_id
echo "\nTesting user creation with role_id...\n";
$testEmail = 'test-user-'.time().'@example.com';

try {
    $user = User::create([
        'name' => 'Test User',
        'email' => $testEmail,
        'password' => Hash::make('password'),
        'role_id' => $employeeRole->id,
    ]);

    echo "User created successfully with ID: {$user->id}\n";

    // Verify role_id was set correctly
    $user = User::where('email', $testEmail)->first();
    if ($user->role_id === $employeeRole->id) {
        echo "PASS: role_id set correctly to {$employeeRole->id} ({$employeeRole->name})\n";
    } else {
        echo "FAIL: role_id not set correctly. Expected {$employeeRole->id}, got {$user->role_id}\n";
    }

    // Test role relationship
    if ($user->role && $user->role->id === $employeeRole->id) {
        echo "PASS: role relationship works correctly\n";
    } else {
        echo "FAIL: role relationship not working correctly\n";
    }

    // Test role helper methods
    if ($user->isEmployee()) {
        echo "PASS: isEmployee() method works correctly\n";
    } else {
        echo "FAIL: isEmployee() method not working correctly\n";
    }

    if (! $user->isSuperAdmin()) {
        echo "PASS: isSuperAdmin() method works correctly\n";
    } else {
        echo "FAIL: isSuperAdmin() method not working correctly\n";
    }

    // Test updating role_id
    echo "\nTesting user role_id update...\n";
    $user->role_id = $contractorRole->id;
    $user->save();

    // Refresh user from database
    $user = User::where('email', $testEmail)->first();

    if ($user->role_id === $contractorRole->id) {
        echo "PASS: role_id updated correctly to {$contractorRole->id} ({$contractorRole->name})\n";
    } else {
        echo "FAIL: role_id not updated correctly. Expected {$contractorRole->id}, got {$user->role_id}\n";
    }

    // Test role helper methods after update
    if ($user->isContractor()) {
        echo "PASS: isContractor() method works correctly after update\n";
    } else {
        echo "FAIL: isContractor() method not working correctly after update\n";
    }

    if (! $user->isEmployee()) {
        echo "PASS: isEmployee() method works correctly after update\n";
    } else {
        echo "FAIL: isEmployee() method not working correctly after update\n";
    }

    // Clean up
    echo "\nCleaning up test user...\n";
    $user->delete();
    echo "Test user deleted\n";

} catch (\Exception $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
}

echo "\nTest completed.\n";
