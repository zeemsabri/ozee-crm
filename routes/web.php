<?php

use App\Http\Controllers\Api\EmailController;
use App\Http\Controllers\ClientDashboardController;
use App\Http\Controllers\EmailPreviewController;
use App\Http\Controllers\EmailTestController;
use App\Http\Controllers\EmailTrackingController;
use App\Http\Controllers\ProfileController;
use App\Models\Project;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\GoogleAuthController; // Import our Google Auth controller (for web routes)
use App\Http\Controllers\Api\MagicLinkController; // Import for magic link functionality
use App\Http\Controllers\GoogleUserAuthController;
use App\Http\Controllers\NoticeBoardController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
$sourceOptions = [
    [
        'label'  =>  'UpWork',
        'value' =>  'UpWork'
    ],
    [
        'label'  =>  'AirTasker',
        'value' =>  'AirTasker'
    ],
    [
        'label'  =>  'Direct',
        'value' =>  'Direct'
    ],
    [
        'label'  =>  'Agency',
        'value' =>  'Agency'
    ],
    [
        'label'  =>  'Reference',
        'value' =>  'Reference'
    ],
    [
        'label'  =>  'Wix Marketplace',
        'value' =>  'Wix Marketplace'
    ],
    [
        'label'  =>  'Fiver',
        'value' =>  'Fiver'
    ],
    [
        'label'  =>  'Social Media',
        'value' =>  'Social Media'
    ],
    [
        'label'  =>  'Advertising',
        'value' =>  'Advertising'
    ],
    [
        'label'  =>  'Website',
        'value' =>  'Website'
    ],
    [
        'label'  =>  'Other',
        'value' =>  'Other'
    ]
];
// Default welcome page or client dashboard if token is present
Route::get('/', function (Request $request) {
//    // Check if token parameter is present in the URL
//    $token = $request->query('token');
//    if ($token) {
//        // If token is present, serve the client dashboard view with the token
//        return view('client_dashboard', ['token' => $token]);
//    }

    // Otherwise, serve the default welcome page
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        // 'canRegister' removed - this is a closed system where only administrators can add users
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

//Route::get('/wireframe', function () {
//    return Inertia::render('Wireframe', [
//        'message' => 'An unexpected error occurred.'
//    ]);
//})->name('wireframe');

// Public route for handling the magic link (this is the new client dashboard route)
// This route will render the ClientDashboard.vue component
Route::get('/client/dashboard/{token}', [MagicLinkController::class, 'handleMagicLink'])
    ->name('client.magic-link-login') // Consistent name with controller
    ->middleware(['signed']); // Ensure the URL is signed

// You might also want a dedicated error page for magic links
Route::get('/magic-link-error', function () {
    return Inertia::render('Errors/MagicLinkError', [
        'message' => 'An unexpected error occurred.'
    ]);
})->name('magic-link.error');

// --- NEW: Public Email Preview Route for Development ---
Route::get('/email-preview/{slug?}', [EmailPreviewController::class, 'preview'])->name('email.preview');

// Privacy Policy Route - publicly accessible
Route::get('/privacy-policy', function () {
    return view('privacy-policy');
})->name('privacy.policy');

// Test Google Auth Flow Route - for development only
Route::get('/test-google-auth', function () {
    return view('test-google-auth');
});

Route::get('/emails/{email}/preview', [EmailController::class, 'reviewEmail'])
    ->middleware(['auth']) // Add any necessary middleware for access control
    ->name('emails.preview');

// --- Google OAuth Routes (for initial authorization by Super Admin) ---
// These need to be accessible via web, but you might want to restrict access to them
// via a Gate/Policy if only super_admin should see the button in the UI.
// The actual storage of tokens is protected by `auth:sanctum` on the API route.
Route::get('/google/redirect', [GoogleAuthController::class, 'redirectToGoogle'])->name('google.redirect');
// The callback also needs to be in web.php as Google redirects the browser here.
// No auth middleware on callback because the user might not be logged into Laravel yet,
// but the controller handles associating with a logged-in user or storing generally.
// For this MVP, we are storing to file, so no direct user login needed for this specific route.
Route::get('/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('google.callback');

// --- Google User OAuth Routes (for user-based authentication) ---

Route::get('/user/google/redirect', [GoogleUserAuthController::class, 'redirectToGoogle'])->name('user.google.redirect');
Route::get('/google/usercallback', [GoogleUserAuthController::class, 'handleCallback'])->name('user.google.callback');
Route::get('/user/google/check', [GoogleUserAuthController::class, 'checkGoogleConnection'])->name('user.google.check');
Route::post('/user/google/disconnect', [GoogleUserAuthController::class, 'disconnectGoogle'])->name('user.google.disconnect');

Route::get('/receive-test-emails', [EmailTestController::class, 'receiveTestEmails'])->name('receive-test-email');

// Magic Link Route - accessible without authentication
Route::get('/magic-link', [MagicLinkController::class, 'handleMagicLink'])->name('client.magic-link');

// Client Dashboard Route - accessible without authentication
Route::get('/client/dashboard', [ClientDashboardController::class, 'index'])->name('client.dashboard');

// Route for the email tracking pixel
Route::get('/email/track/{id}', [EmailTrackingController::class, 'track'])->name('email.track');
Route::get('/notice/track/{id}/{email?}', [EmailTrackingController::class, 'notice'])->name('notice.track');
// Authenticated routes group for Inertia pages that require a logged-in user
// The 'verified' middleware ensures the user's email is verified (optional, remove if not needed for MVP)
Route::middleware(['auth', 'verified'])->group(function () use ($sourceOptions) {

    // Test route for BaseFormModal demonstration
    Route::get('/test/form-modal', function () {
        return Inertia::render('Test/FormModalTest');
    })->name('test.form-modal');

    // Project Expendables Page
    Route::get('/project-expendables', function () {
        return Inertia::render('Admin/ProjectExpendables/Index');
    })->name('project-expendables.index')->middleware('permissionInAnyProject:add_expendables');

    // Admin routes
    Route::prefix('admin')->name('admin.')->group(function () {
        // Project Tier management routes - requires view_project_tiers permission
        Route::get('/project-tiers', [\App\Http\Controllers\Admin\ProjectTierController::class, 'index'])
            ->middleware(['permission:view_project_tiers'])
            ->name('project-tiers.index');

        // The following routes require create/edit/delete permissions
        Route::post('/project-tiers', [\App\Http\Controllers\Admin\ProjectTierController::class, 'store'])
            ->middleware(['permission:create_project_tiers'])
            ->name('project-tiers.store');

        Route::put('/project-tiers/{projectTier}', [\App\Http\Controllers\Admin\ProjectTierController::class, 'update'])
            ->middleware(['permission:edit_project_tiers'])
            ->name('project-tiers.update');

        Route::delete('/project-tiers/{projectTier}', [\App\Http\Controllers\Admin\ProjectTierController::class, 'destroy'])
            ->middleware(['permission:delete_project_tiers'])
            ->name('project-tiers.destroy');

        // Monthly Budget management routes - requires view_monthly_budgets permission
        Route::get('/monthly-budgets', [\App\Http\Controllers\Admin\MonthlyBudgetController::class, 'index'])
            ->middleware(['permission:view_monthly_budgets'])
            ->name('monthly-budgets.index');

        // Notice Board Admin Page
        Route::get('/notice-board', function () {
            return Inertia::render('Admin/NoticeBoard/Index');
        })->name('notice-board.index')->middleware('permission:manage_notices');

        // The following routes require manage_monthly_budgets permission
        Route::post('/monthly-budgets', [\App\Http\Controllers\Admin\MonthlyBudgetController::class, 'store'])
            ->middleware(['permission:manage_monthly_budgets'])
            ->name('monthly-budgets.store');

        Route::put('/monthly-budgets/{monthlyBudget}', [\App\Http\Controllers\Admin\MonthlyBudgetController::class, 'update'])
            ->middleware(['permission:manage_monthly_budgets'])
            ->name('monthly-budgets.update');

        Route::delete('/monthly-budgets/{monthlyBudget}', [\App\Http\Controllers\Admin\MonthlyBudgetController::class, 'destroy'])
            ->middleware(['permission:manage_monthly_budgets'])
            ->name('monthly-budgets.destroy');

        // API routes for Monthly Budget management
        Route::get('/monthly-budgets/all', [\App\Http\Controllers\Admin\MonthlyBudgetController::class, 'getAllBudgets'])
            ->middleware(['permission:view_monthly_budgets'])
            ->name('monthly-budgets.all');

        // Bonus Calculator routes
        Route::get('/bonus-calculator', [\App\Http\Controllers\Admin\BonusCalculatorController::class, 'index'])
            ->middleware(['permission:view_monthly_budgets'])
            ->name('bonus-calculator.index');
        Route::get('/bonus-calculator/calculate', [\App\Http\Controllers\Admin\BonusCalculatorController::class, 'calculate'])
            ->middleware(['permission:view_monthly_budgets'])
            ->name('bonus-calculator.calculate');

        // PM Payout Calculator - blank page (Inertia)
        Route::get('/pm-payout-calculator', function () {
            return Inertia::render('Admin/PMPayoutCalculator/Index');
        })->middleware(['permission:view_monthly_budgets'])
          ->name('pm-payout-calculator.index');

        Route::get('/monthly-budgets/current', [\App\Http\Controllers\Admin\MonthlyBudgetController::class, 'getCurrentBudget'])
            ->middleware(['permission:view_monthly_budgets'])
            ->name('monthly-budgets.current');

        Route::get('/monthly-budgets/{monthlyBudget}', [\App\Http\Controllers\Admin\MonthlyBudgetController::class, 'show'])
            ->middleware(['permission:view_monthly_budgets'])
            ->name('monthly-budgets.show');

        // Role management routes - requires manage_roles permission
        Route::middleware(['permission:manage_roles'])->group(function () {
        // Role management routes
        Route::get('/roles', function () {
            return Inertia::render('Admin/Roles/Index', [
                'roles' => \App\Models\Role::with('permissions')->get()
            ]);
        })->name('roles.index');

        Route::get('/roles/create', function () {
            return Inertia::render('Admin/Roles/Create', [
                'permissions' => \App\Models\Permission::orderBy('category')->get()->groupBy('category')
            ]);
        })->name('roles.create');

        Route::post('/roles', [\App\Http\Controllers\Api\RoleController::class, 'store'])->name('roles.store');

        Route::get('/roles/{role}', function (\App\Models\Role $role) {
            $role->load('permissions');

            // Get users with this role as their application role
            $applicationUsers = \App\Models\User::where('role_id', $role->id)->get();

            // Get users with this role as their project role
            $projectUsers = [];
            if ($role->type === 'project') {
                $projectUsers = \Illuminate\Support\Facades\DB::table('users')
                    ->join('project_user', 'users.id', '=', 'project_user.user_id')
                    ->join('projects', 'project_user.project_id', '=', 'projects.id')
                    ->where('project_user.role_id', $role->id)
                    ->select('users.*', 'projects.name as project_name', 'projects.id as project_id')
                    ->get();
            }

            return Inertia::render('Admin/Roles/Show', [
                'role' => $role,
                'applicationUsers' => $applicationUsers,
                'projectUsers' => $projectUsers
            ]);
        })->name('roles.show');

        Route::get('/roles/{role}/edit', function (\App\Models\Role $role) {
            $role->load('permissions');

            // Get users with this role as their application role
            $applicationUsers = \App\Models\User::where('role_id', $role->id)->get();

            // Get users with this role as their project role
            $projectUsers = [];
            if ($role->type === 'project') {
                $projectUsers = \Illuminate\Support\Facades\DB::table('users')
                    ->join('project_user', 'users.id', '=', 'project_user.user_id')
                    ->join('projects', 'project_user.project_id', '=', 'projects.id')
                    ->where('project_user.role_id', $role->id)
                    ->select('users.*', 'projects.name as project_name', 'projects.id as project_id')
                    ->get();
            }

            return Inertia::render('Admin/Roles/Edit', [
                'role' => $role,
                'permissions' => \App\Models\Permission::orderBy('category')->get()->groupBy('category'),
                'rolePermissions' => $role->permissions->pluck('id')->toArray(),
                'applicationUsers' => $applicationUsers,
                'projectUsers' => $projectUsers
            ]);
        })->name('roles.edit');

        Route::put('/roles/{role}', [\App\Http\Controllers\Api\RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [\App\Http\Controllers\Api\RoleController::class, 'destroy'])->name('roles.destroy');

        Route::get('/roles/{role}/permissions', function (\App\Models\Role $role) {
            return Inertia::render('Admin/Roles/Permissions', [
                'role' => $role,
                'permissions' => \App\Models\Permission::orderBy('category')->get()->groupBy('category'),
                'rolePermissions' => $role->permissions->pluck('id')->toArray()
            ]);
        })->name('roles.permissions');

        Route::post('/roles/{role}/permissions', [\App\Http\Controllers\Api\RoleController::class, 'updatePermissions'])->name('roles.updatePermissions');

        // Route for revoking a role from a user
        Route::post('/roles/revoke-user', [\App\Http\Controllers\Admin\RoleController::class, 'revokeUser'])->name('roles.revoke-user');

        // Route for revoking a permission from a user
        Route::post('/permissions/revoke-user', [\App\Http\Controllers\Admin\PermissionController::class, 'revokeUser'])->name('permissions.revoke-user');

        // Permission management routes
        Route::get('/permissions', function () {
            return Inertia::render('Admin/Permissions/Index', [
                'permissions' => \App\Models\Permission::with('roles')->orderBy('category')->get()->groupBy('category')
            ]);
        })->name('permissions.index');

        Route::get('/permissions/create', function () {
            return Inertia::render('Admin/Permissions/Create', [
                'categories' => \App\Models\Permission::select('category')->distinct()->pluck('category'),
                'roles' => \App\Models\Role::all()
            ]);
        })->name('permissions.create');

        Route::post('/permissions', [\App\Http\Controllers\Admin\PermissionController::class, 'store'])->name('permissions.store');

        Route::get('/permissions/bulk-create', function () {
            return Inertia::render('Admin/Permissions/BulkCreate', [
                'categories' => \App\Models\Permission::select('category')->distinct()->pluck('category'),
                'roles' => \App\Models\Role::all()
            ]);
        })->name('permissions.bulk-create');

        Route::post('/permissions/bulk', [\App\Http\Controllers\Admin\PermissionController::class, 'bulkStore'])->name('permissions.bulk-store');

        Route::get('/permissions/{permission}/edit', function (\App\Models\Permission $permission) {
            $permission->load('roles');

            // Get all roles for selection
            $roles = \App\Models\Role::all();
            $permissionRoles = $permission->roles->pluck('id')->toArray();

            // Get all roles that have this permission
            $rolesWithPermission = $permission->roles;

            // Get users with application roles that have this permission
            $applicationUsers = \App\Models\User::whereIn('role_id', $rolesWithPermission->where('type', 'application')->pluck('id'))->get();

            // Get users with project roles that have this permission
            $projectUsers = [];
            $projectRoleIds = $rolesWithPermission->where('type', 'project')->pluck('id')->toArray();

            if (!empty($projectRoleIds)) {
                $projectUsers = \Illuminate\Support\Facades\DB::table('users')
                    ->join('project_user', 'users.id', '=', 'project_user.user_id')
                    ->join('projects', 'project_user.project_id', '=', 'projects.id')
                    ->whereIn('project_user.role_id', $projectRoleIds)
                    ->select('users.*', 'projects.name as project_name', 'projects.id as project_id')
                    ->get();
            }

            return Inertia::render('Admin/Permissions/Edit', [
                'permission' => $permission,
                'categories' => \App\Models\Permission::select('category')->distinct()->pluck('category'),
                'roles' => $roles,
                'permissionRoles' => $permissionRoles,
                'applicationUsers' => $applicationUsers,
                'projectUsers' => $projectUsers
            ]);
        })->name('permissions.edit');

        Route::put('/permissions/{permission}', [\App\Http\Controllers\Admin\PermissionController::class, 'update'])->name('permissions.update');
        Route::delete('/permissions/{permission}', [\App\Http\Controllers\Admin\PermissionController::class, 'destroy'])->name('permissions.destroy');
    });

});

    Route::get('/bonus_system', function () {
        return Inertia::render('BonusSystem/Index');
    });

    // Your existing dashboard route
    Route::get('/dashboard', function () {
        $user = auth()->user();
        $projectCount = $user->projects()->count();

        return Inertia::render('Dashboard', [
            'projectCount' => $projectCount,
        ]);
    })->name('dashboard');

    // Your existing profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // --- NEW: Inertia Routes for our custom application features ---

    // Clients Index Page
    Route::get('/clients', function () {
        return Inertia::render('Clients/Index');
    })->name('clients.page')->middleware('permission:view_clients');

    // Projects Index Page
    Route::get('/projects', function () {
        return Inertia::render('Projects/Index');
    })->name('projects.index')->middleware('permission:view_projects');

    // Projects Create Page
    Route::get('/projects/create', function () use ($sourceOptions) {
        return Inertia::render('Projects/Create', [
            'sourceOptions' => $sourceOptions,
        ]);
    })->name('projects.create')->middleware('permission:create_projects');

    // Projects Edit Page
    Route::get('/projects/{project}/edit', function (Project $project) use ($sourceOptions) {
        return Inertia::render('Projects/Edit', [
            'project' => $project,
            'sourceOptions' => $sourceOptions,
        ]);
    })->name('projects.edit')->middleware('permission:create_projects');

    // Project Detail Page
    Route::get('/projects/{id}', function ($id) {
        return Inertia::render('Projects/Show', [
            'id' => $id,
        ]);
    })->name('projects.show')->middleware('permission:view_projects');

    // Project Wireframe Page
    Route::get('/projects/{project}/wireframe', function ($project) {
        return Inertia::render('Projects/Show', [
            'id' => $project,
            'showWireframe' => true,
        ]);
    })->name('projects.wireframe')->middleware('permission:view_projects');

    // Project Wireframe with Version Page
    Route::get('/projects/{project}/wireframe/{wireframe}', function ($project, $wireframe) {
        return Inertia::render('Projects/Show', [
            'id' => $project,
            'showWireframe' => true,
            'wireframeId' => $wireframe,
        ]);
    })->name('projects.wireframe.show')->middleware('permission:view_projects');

    // Project Wireframe with Version Page
    Route::get('/projects/{project}/wireframe/{wireframe}/version/{version}', function ($project, $wireframe, $version) {
        return Inertia::render('Projects/Show', [
            'id' => $project,
            'showWireframe' => true,
            'wireframeId' => $wireframe,
            'versionNumber' => $version,
        ]);
    })->name('projects.wireframe.version')->middleware('permission:view_projects');

    // Task Types Management Page
    Route::get('/task-types', function () {
        return Inertia::render('TaskTypes/Index');
    })->name('task-types.page')->middleware('permission:manage_projects');

    // Email Composer Page
    Route::get('/emails/compose', function () {
        return Inertia::render('Emails/Composer');
    })->name('emails.compose')->middleware('permission:compose_emails');

    // Pending Approvals Page
    Route::get('/emails/pending', function () {
        return Inertia::render('Emails/PendingApprovals');
    })->name('emails.pending')->middleware('permission:approve_emails');

    Route::get('/emails/rejected', function () {
        return Inertia::render('Emails/Rejected');
    })->name('emails.rejected')->middleware('permission:compose_emails'); // New route for rejected emails

    // Inbox Page
    Route::get('/inbox', function () {
        return Inertia::render('Emails/Inbox/Index');
    })->name('inbox')->middleware('permission:view_emails');

    // Kudos Page (approvals or user's approved kudos)
    Route::get('/kudos', function () {
        return Inertia::render('Kudos/Index');
    })->name('kudos.index')->middleware('permission:view_kudos');

    Route::get('/users', function () {
        return Inertia::render('Users/Index');
    })->name('users.page')->middleware('permission:create_users');

    // Test route for User Project Role functionality
    Route::get('/test/user-project-role', [\App\Http\Controllers\TestController::class, 'testUserProjectRole'])
        ->name('test.user-project-role');

    // Availability Calendar Page
    Route::get('/availability', function () {
        return Inertia::render('Availability/Index');
    })->name('availability.index')->middleware('permission:create_users');

    // Bonus Configuration Page
//    Route::get('/bonus-configuration', function () {
//        return Inertia::render('BonusConfiguration/Index');
//    })->name('bonus-configuration.index')->middleware('permission:manage_bonus_configuration');

    // Bonus System Page
    Route::get('/bonus-system', function () {
        return Inertia::render('BonusSystem/Index');
    })->name('bonus-system.index')->middleware('permission:view_own_points');

    // Leaderboard Page
    Route::get('/leaderboard', function () {
        return Inertia::render('Leaderboard/Index');
    })->name('leaderboard.index')->middleware('permission:view_own_points');

    Route::get('/shareable-resources', function () {
        return Inertia::render('ShareableResources/Index');
    })->name('shareable-resources.page')->middleware('permission:view_shareable_resources');

    // --- NEW: Email Templates Web Routes ---
    Route::get('/email-templates', function () {
        return Inertia::render('EmailTemplates/Index');
    })->name('email-templates.page')->middleware('permission:manage_email_templates');

    // Route to the create form for a new template
    Route::get('/email-templates/create', function () {
        return Inertia::render('EmailTemplates/Create');
    })->name('email-templates.create')->middleware('permission:manage_email_templates');

    // --- NEW: Placeholder Definitions Web Routes ---
    Route::get('/placeholder-definitions', function () {
        return Inertia::render('PlaceholderDefinitions/Index');
    })->name('placeholder-definitions.page')->middleware('permission:manage_placeholder_definitions');

});

// Require your existing authentication routes (login, register, logout, etc.)
require __DIR__.'/auth.php';

// Include admin routes
require __DIR__.'/admin.php';


// Notice redirect route to log interactions and redirect to destination
Route::get('/notices/{notice}/redirect', [NoticeBoardController::class, 'redirect'])
    ->middleware(['auth', 'verified'])
    ->name('notices.redirect');
