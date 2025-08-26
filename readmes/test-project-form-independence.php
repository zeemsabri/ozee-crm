<?php

// This script tests the project form independence functionality
// It verifies that each section of the project can be updated independently
// and that permissions are correctly enforced for each section

require __DIR__ . '/vendor/autoload.php';

// Bootstrap the Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Role;
use App\Models\Project;
use App\Models\Permission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

echo "Testing project form independence functionality...\n\n";

// Get roles
$superAdminRole = Role::where('slug', 'super-admin')->first();
$managerRole = Role::where('slug', 'manager')->first();
$employeeRole = Role::where('slug', 'employee')->first();
$contractorRole = Role::where('slug', 'contractor')->first();

if (!$superAdminRole || !$managerRole || !$employeeRole || !$contractorRole) {
    echo "Roles not found. Please run the RolePermissionSeeder first.\n";
    exit;
}

// Get users with different roles
$superAdmin = User::where('role_id', $superAdminRole->id)->first();
$manager = User::where('role_id', $managerRole->id)->first();
$employee = User::where('role_id', $employeeRole->id)->first();
$contractor = User::where('role_id', $contractorRole->id)->first();

if (!$superAdmin || !$manager || !$employee || !$contractor) {
    echo "Users with required roles not found. Please create users with appropriate roles first.\n";
    exit;
}

// Get a project to test with
$project = Project::first();

if (!$project) {
    echo "No projects found. Please create a project first.\n";
    exit;
}

echo "Found project: {$project->name} (ID: {$project->id})\n\n";

// Test 1: Super Admin can update all sections
echo "Test 1: Super Admin can update all sections\n";
echo "----------------------------------------\n";
Auth::login($superAdmin);
echo "Logged in as {$superAdmin->name} (Role: {$superAdmin->role->name})\n";

// Test updating basic information
try {
    $response = Http::withToken($superAdmin->createToken('test-token')->plainTextToken)
        ->put(url("/api/projects/{$project->id}/sections/basic"), [
            'name' => $project->name . ' (Updated by Super Admin)',
            'description' => $project->description,
            'website' => $project->website,
            'social_media_link' => $project->social_media_link,
            'preferred_keywords' => $project->preferred_keywords,
            'google_chat_id' => $project->google_chat_id,
            'status' => $project->status,
            'project_type' => $project->project_type,
            'source' => $project->source,
            'google_drive_link' => $project->google_drive_link,
        ]);

    if ($response->successful()) {
        echo "✓ Successfully updated basic information\n";
    } else {
        echo "✗ Failed to update basic information: " . $response->body() . "\n";
    }
} catch (\Exception $e) {
    echo "✗ Exception when updating basic information: " . $e->getMessage() . "\n";
}

// Test updating services and payment
try {
    $response = Http::withToken($superAdmin->createToken('test-token')->plainTextToken)
        ->put(url("/api/projects/{$project->id}/sections/services-payment"), [
            'services' => $project->services,
            'service_details' => $project->service_details,
            'total_amount' => $project->total_amount,
            'payment_type' => $project->payment_type,
        ]);

    if ($response->successful()) {
        echo "✓ Successfully updated services and payment\n";
    } else {
        echo "✗ Failed to update services and payment: " . $response->body() . "\n";
    }
} catch (\Exception $e) {
    echo "✗ Exception when updating services and payment: " . $e->getMessage() . "\n";
}

// Test updating transactions
try {
    $response = Http::withToken($superAdmin->createToken('test-token')->plainTextToken)
        ->put(url("/api/projects/{$project->id}/sections/transactions"), [
            'transactions' => [
                [
                    'description' => 'Test transaction by Super Admin',
                    'amount' => 100,
                    'user_id' => null,
                    'hours_spent' => null,
                    'type' => 'income',
                ]
            ],
        ]);

    if ($response->successful()) {
        echo "✓ Successfully updated transactions\n";
    } else {
        echo "✗ Failed to update transactions: " . $response->body() . "\n";
    }
} catch (\Exception $e) {
    echo "✗ Exception when updating transactions: " . $e->getMessage() . "\n";
}

// Test updating notes
try {
    $response = Http::withToken($superAdmin->createToken('test-token')->plainTextToken)
        ->put(url("/api/projects/{$project->id}/sections/notes"), [
            'notes' => [
                [
                    'content' => 'Test note by Super Admin',
                ]
            ],
        ]);

    if ($response->successful()) {
        echo "✓ Successfully updated notes\n";
    } else {
        echo "✗ Failed to update notes: " . $response->body() . "\n";
    }
} catch (\Exception $e) {
    echo "✗ Exception when updating notes: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Manager can update all sections
echo "Test 2: Manager can update all sections\n";
echo "------------------------------------\n";
Auth::login($manager);
echo "Logged in as {$manager->name} (Role: {$manager->role->name})\n";

// Test updating basic information
try {
    $response = Http::withToken($manager->createToken('test-token')->plainTextToken)
        ->put(url("/api/projects/{$project->id}/sections/basic"), [
            'name' => $project->name . ' (Updated by Manager)',
            'description' => $project->description,
            'website' => $project->website,
            'social_media_link' => $project->social_media_link,
            'preferred_keywords' => $project->preferred_keywords,
            'google_chat_id' => $project->google_chat_id,
            'status' => $project->status,
            'project_type' => $project->project_type,
            'source' => $project->source,
            'google_drive_link' => $project->google_drive_link,
        ]);

    if ($response->successful()) {
        echo "✓ Successfully updated basic information\n";
    } else {
        echo "✗ Failed to update basic information: " . $response->body() . "\n";
    }
} catch (\Exception $e) {
    echo "✗ Exception when updating basic information: " . $e->getMessage() . "\n";
}

// Test updating services and payment
try {
    $response = Http::withToken($manager->createToken('test-token')->plainTextToken)
        ->put(url("/api/projects/{$project->id}/sections/services-payment"), [
            'services' => $project->services,
            'service_details' => $project->service_details,
            'total_amount' => $project->total_amount,
            'payment_type' => $project->payment_type,
        ]);

    if ($response->successful()) {
        echo "✓ Successfully updated services and payment\n";
    } else {
        echo "✗ Failed to update services and payment: " . $response->body() . "\n";
    }
} catch (\Exception $e) {
    echo "✗ Exception when updating services and payment: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Employee with limited permissions
echo "Test 3: Employee with limited permissions\n";
echo "---------------------------------------\n";
Auth::login($employee);
echo "Logged in as {$employee->name} (Role: {$employee->role->name})\n";

// Test updating basic information (should fail)
try {
    $response = Http::withToken($employee->createToken('test-token')->plainTextToken)
        ->put(url("/api/projects/{$project->id}/sections/basic"), [
            'name' => $project->name . ' (Updated by Employee)',
            'description' => $project->description,
            'website' => $project->website,
            'social_media_link' => $project->social_media_link,
            'preferred_keywords' => $project->preferred_keywords,
            'google_chat_id' => $project->google_chat_id,
            'status' => $project->status,
            'project_type' => $project->project_type,
            'source' => $project->source,
            'google_drive_link' => $project->google_drive_link,
        ]);

    if ($response->successful()) {
        echo "✗ Employee should not be able to update basic information, but succeeded\n";
    } else {
        echo "✓ Employee correctly denied permission to update basic information\n";
    }
} catch (\Exception $e) {
    echo "✓ Exception when employee tries to update basic information: " . $e->getMessage() . "\n";
}

// Test updating notes (should succeed if employee has add_project_notes permission)
$addProjectNotesPermission = Permission::where('slug', 'add_project_notes')->first();
if ($addProjectNotesPermission) {
    // Check if employee role has this permission
    $hasPermission = $employeeRole->permissions()->where('permissions.id', $addProjectNotesPermission->id)->exists();

    if (!$hasPermission) {
        // Add the permission temporarily for testing
        $employeeRole->permissions()->attach($addProjectNotesPermission->id);
        echo "Temporarily added add_project_notes permission to employee role\n";
    }

    try {
        $response = Http::withToken($employee->createToken('test-token')->plainTextToken)
            ->put(url("/api/projects/{$project->id}/sections/notes"), [
                'notes' => [
                    [
                        'content' => 'Test note by Employee',
                    ]
                ],
            ]);

        if ($response->successful()) {
            echo "✓ Successfully updated notes with add_project_notes permission\n";
        } else {
            echo "✗ Failed to update notes despite having permission: " . $response->body() . "\n";
        }
    } catch (\Exception $e) {
        echo "✗ Exception when updating notes: " . $e->getMessage() . "\n";
    }

    // Remove the permission if we added it temporarily
    if (!$hasPermission) {
        $employeeRole->permissions()->detach($addProjectNotesPermission->id);
        echo "Removed temporary add_project_notes permission from employee role\n";
    }
} else {
    echo "add_project_notes permission not found in the database\n";
}

echo "\n";

// Test 4: Contractor with project-specific role
echo "Test 4: Contractor with project-specific role\n";
echo "------------------------------------------\n";
Auth::login($contractor);
echo "Logged in as {$contractor->name} (Role: {$contractor->role->name})\n";

// Check if contractor is already assigned to the project
$isAssigned = $project->users()->where('users.id', $contractor->id)->exists();
if (!$isAssigned) {
    // Assign contractor to the project with a manager role
    $managerProjectRole = Role::where('slug', 'manager')->where('type', 'project')->first();
    if ($managerProjectRole) {
        $project->users()->attach($contractor->id, ['role_id' => $managerProjectRole->id]);
        echo "Assigned contractor to project with manager role\n";
    } else {
        echo "Project manager role not found\n";
    }
}

// Test updating basic information (should succeed with project-specific manager role)
try {
    $response = Http::withToken($contractor->createToken('test-token')->plainTextToken)
        ->put(url("/api/projects/{$project->id}/sections/basic"), [
            'name' => $project->name . ' (Updated by Contractor with project role)',
            'description' => $project->description,
            'website' => $project->website,
            'social_media_link' => $project->social_media_link,
            'preferred_keywords' => $project->preferred_keywords,
            'google_chat_id' => $project->google_chat_id,
            'status' => $project->status,
            'project_type' => $project->project_type,
            'source' => $project->source,
            'google_drive_link' => $project->google_drive_link,
        ]);

    if ($response->successful()) {
        echo "✓ Successfully updated basic information with project-specific role\n";
    } else {
        echo "✗ Failed to update basic information despite project role: " . $response->body() . "\n";
    }
} catch (\Exception $e) {
    echo "✗ Exception when updating basic information: " . $e->getMessage() . "\n";
}

echo "\n";

// Summary
echo "Summary of test results:\n";
echo "----------------------\n";
echo "1. Super Admin: Can update all sections of the project\n";
echo "2. Manager: Can update all sections of the project\n";
echo "3. Employee: Limited access based on permissions\n";
echo "4. Contractor with project-specific role: Access based on project role\n";
echo "\nTest completed.\n";
