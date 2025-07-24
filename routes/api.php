<?php

use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\GoogleAuthController; // Our Google Auth controller
use App\Http\Controllers\Api\ClientController; // Import
use App\Http\Controllers\Api\EmailController;
use App\Http\Controllers\Api\ProjectController; // Import
use App\Http\Controllers\Api\ProjectSectionController; // Import for section-based project data
use App\Http\Controllers\Api\RoleController; // Import for role management
use App\Http\Controllers\Api\PermissionController; // Import for permission management
use App\Http\Controllers\Api\TaskController; // Import for task management
use App\Http\Controllers\Api\SubtaskController; // Import for subtask management
use App\Http\Controllers\Api\MilestoneController; // Import for milestone management
use App\Http\Controllers\Api\TaskTypeController; // Import for task type management
use App\Http\Controllers\Api\AvailabilityController; // Import for availability management
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// --- Public Authentication Routes (NO auth:sanctum middleware) ---
// These routes must be accessible to unauthenticated users to perform login
// Registration route removed - this is a closed system where only administrators can add users
// Route::post('/register', [RegisteredUserController::class, 'store'])
//     ->middleware('guest');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware(['guest', 'web']); // THIS IS THE CRUCIAL ROUTE FOR YOUR LOGIN.VUE

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware('guest');

Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware('guest');

// --- Authenticated API Routes (behind auth:sanctum middleware) ---
// All routes within this group require a valid Sanctum token
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']); // Logout needs auth

    // Email Verification routes (often here if API-only)
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1');

    // Our Google OAuth Routes (for admin to link Google Workspace)
    // These should also be protected by auth:sanctum as only a logged-in admin links their account
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirectToGoogle'])
        ->name('auth.google.redirect'); // This will redirect to Google's OAuth consent screen

    Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']); // Google redirects here after consent

    // Client Management Routes (CRUD)
    Route::apiResource('clients', ClientController::class);
    Route::get('clients/{client}/email', [ClientController::class, 'getEmail']);

    // Project Management Routes (CRUD)
    Route::apiResource('projects', ProjectController::class);
    Route::get('projects-simplified', [ProjectController::class, 'getProjectsSimplified']); // New route with limited information for dashboard
    Route::get('projects-for-email', [ProjectController::class, 'getProjectsForEmailComposer']);
    Route::post('projects/{project}/attach-users', [ProjectController::class, 'attachUsers'])->name('projects.attach-users');
    Route::post('projects/{project}/detach-users', [ProjectController::class, 'detachUsers'])->name('projects.detach-users');
    Route::post('projects/{project}/attach-clients', [ProjectController::class, 'attachClients'])->name('projects.attach-clients');
    Route::post('projects/{project}/detach-clients', [ProjectController::class, 'detachClients'])->name('projects.detach-clients');
    Route::post('projects/{project}/expenses', [ProjectController::class, 'addTransactions']);
    Route::post('projects/{project}/notes', [ProjectController::class, 'addNotes']);
    Route::get('projects/{project}/notes', [ProjectController::class, 'getNotes']);
    Route::post('projects/{project}/notes/{note}/reply', [ProjectController::class, 'replyToNote']);
    Route::get('projects/{project}/notes/{note}/replies', [ProjectController::class, 'getNoteReplies']);
    Route::get('projects/{project}/tasks', [ProjectController::class, 'getTasks']);
    Route::post('projects/{project}/documents', [ProjectController::class, 'uploadDocuments']);

    // Project Section Routes (for permission-based data fetching)
    Route::get('projects/{project}/sections/basic', [ProjectSectionController::class, 'getBasicInfo']);
    Route::get('projects/{project}/sections/clients-users', [ProjectSectionController::class, 'getClientsAndUsers']);
    Route::get('projects/{project}/sections/services-payment', [ProjectSectionController::class, 'getServicesAndPayment']);
    Route::get('projects/{project}/sections/transactions', [ProjectSectionController::class, 'getTransactions']);
    Route::get('projects/{project}/sections/documents', [ProjectSectionController::class, 'getDocuments']);
    Route::get('projects/{project}/sections/notes', [ProjectSectionController::class, 'getNotes']);
    Route::post('projects/{project}/standup', [ProjectSectionController::class, 'addStandup']);
    Route::get('projects/{project}/users', [ProjectSectionController::class, 'getProjectUsers']);
    Route::get('projects/{project}/clients', [ProjectSectionController::class, 'getProjectClients']);
    Route::get('projects/{project}/contract-details', [ProjectSectionController::class, 'getContractDetails']);
    Route::get('/projects/{project}/meetings', [ProjectController::class, 'getProjectMeetings']);
    Route::post('/projects/{project}/meetings', [ProjectController::class, 'createProjectMeeting']);
    Route::delete('/projects/{project}/meetings/{googleEventId}', [ProjectController::class, 'deleteProjectMeeting']);

    // Project Section Update Routes
    Route::put('projects/{project}/sections/basic', [ProjectSectionController::class, 'updateBasicInfo']);
    Route::put('projects/{project}/sections/services-payment', [ProjectSectionController::class, 'updateServicesAndPayment']);
    Route::put('projects/{project}/sections/transactions', [ProjectSectionController::class, 'updateTransactions']);
    Route::put('projects/{project}/sections/notes', [ProjectSectionController::class, 'updateNotes']);

    // Email Management & Approval Routes

    Route::get('emails/pending-approval', [EmailController::class, 'pendingApproval']); // Legacy route with full details
    Route::get('emails/pending-approval-simplified', [EmailController::class, 'pendingApprovalSimplified']); // New route with limited information
    Route::get('emails/rejected', [EmailController::class, 'rejected']); // Legacy route with full details
    Route::get('emails/rejected-simplified', [EmailController::class, 'rejectedSimplified']); // New route with limited information
    Route::get('projects/{project}/emails', [EmailController::class, 'getProjectEmails']); // Get all emails for a project (legacy endpoint)
    Route::get('projects/{project}/emails-simplified', [EmailController::class, 'getProjectEmailsSimplified']); // Get simplified emails for a project
    Route::post('emails/{email}/approve', [EmailController::class, 'approve']);
    Route::post('emails/{email}/edit-and-approve', [EmailController::class, 'editAndApprove']);
    Route::post('emails/{email}/reject', [EmailController::class, 'reject']);
    Route::post('emails/{email}/resubmit', [EmailController::class, 'resubmit']);
    Route::apiResource('emails', EmailController::class)->except(['destroy']);

    // Google Auth Status Endpoint
    Route::get('/google/status', function () {
        try {
            $gmailService = app(\App\Services\GmailService::class);
            return response()->json([
                'status' => 'authorized',
                'authorized_email' => $gmailService->getAuthorizedEmail(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unauthorized',
                'authorized_email' => null,
                'error' => $e->getMessage(),
            ]);
        }
    });

    Route::apiResource('users', UserController::class);

    // Permission Management Routes
    Route::get('/permissions', [PermissionController::class, 'getAllPermissions'])->middleware('permission:view_permissions');
    Route::get('/user/permissions', [PermissionController::class, 'getUserPermissions']);
    Route::get('/projects/{project}/permissions', [PermissionController::class, 'getUserProjectPermissions'])->name('projects.permissions');

    // Role Management Routes (CRUD)
    Route::apiResource('roles', RoleController::class)->middleware('permission:manage_roles');
    Route::post('roles/{role}/permissions', [RoleController::class, 'updatePermissions'])
        ->middleware('permission:manage_permissions')
        ->name('roles.updatePermissions');

    // Task Management Routes
    Route::get('task-statistics', [TaskController::class, 'getTaskStatistics']);
    Route::apiResource('tasks', TaskController::class);
    Route::post('tasks/{task}/notes', [TaskController::class, 'addNote']);
    Route::post('tasks/{task}/complete', [TaskController::class, 'markAsCompleted']);
    Route::post('tasks/{task}/start', [TaskController::class, 'start']);
    Route::post('tasks/{task}/block', [TaskController::class, 'block']);
    Route::post('tasks/{task}/archive', [TaskController::class, 'archive']);

    // Subtask Management Routes
    Route::apiResource('subtasks', SubtaskController::class);
    Route::post('subtasks/{subtask}/notes', [SubtaskController::class, 'addNote']);
    Route::post('subtasks/{subtask}/complete', [SubtaskController::class, 'markAsCompleted']);
    Route::post('subtasks/{subtask}/start', [SubtaskController::class, 'start']);
    Route::post('subtasks/{subtask}/block', [SubtaskController::class, 'block']);

    // Milestone Management Routes
    Route::apiResource('milestones', MilestoneController::class);
    Route::post('milestones/{milestone}/complete', [MilestoneController::class, 'markAsCompleted']);
    Route::post('milestones/{milestone}/start', [MilestoneController::class, 'start']);

    // Project-specific Task Management Routes
    Route::get('projects/{project}/milestones', [MilestoneController::class, 'index']);
    Route::post('projects/{project}/milestones', [MilestoneController::class, 'store']);

    // Task Type Routes
    Route::apiResource('task-types', TaskTypeController::class);

    // Availability Management Routes
    Route::apiResource('availabilities', AvailabilityController::class);
    Route::post('availabilities/batch', [AvailabilityController::class, 'batch']);
    Route::get('weekly-availabilities', [AvailabilityController::class, 'getWeeklyAvailabilities']);
    Route::get('availability-prompt', [AvailabilityController::class, 'shouldShowPrompt']);

});
