<?php

use App\Http\Controllers\Api\BonusConfigurationGroupController;
use App\Http\Controllers\Api\ClientDashboard\ProjectClientAction;
use App\Http\Controllers\Api\ClientDashboard\ProjectClientReader;
use App\Http\Controllers\Api\ImageUploadController;
use App\Http\Controllers\Api\ProjectDashboard\ProjectDeliverableAction;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\EmailController;
use App\Http\Controllers\Api\ProjectReadController; // New Import
use App\Http\Controllers\Api\ProjectActionController; // New Import
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\SubtaskController;
use App\Http\Controllers\Api\MilestoneController;
use App\Http\Controllers\Api\TaskTypeController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BonusConfigurationController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\MagicLinkController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public Authentication Routes (NO auth:sanctum middleware)
Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware(['guest', 'web']);

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware('guest');

Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware('guest');

// Authenticated API Routes (behind auth:sanctum middleware for internal users)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Tag Management Routes
    Route::get('/tags/search', [\App\Http\Controllers\TagController::class, 'search']);

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

    // Email Verification routes
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1');

    // Google OAuth Routes
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirectToGoogle'])
        ->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);

    // Client Management Routes (CRUD)
    Route::apiResource('clients', ClientController::class);
    Route::get('clients/{client}/email', [ClientController::class, 'getEmail']);
    Route::post('/upload-image', [ImageUploadController::class, 'upload']);

    // Project Management Routes (Split into Read and Action)
    // Read Routes
    Route::get('projects', [ProjectReadController::class, 'index']);
    Route::get('projects/{project}', [ProjectReadController::class, 'show']);
    Route::get('projects-simplified', [ProjectReadController::class, 'getProjectsSimplified']);
    Route::get('projects-for-email', [ProjectReadController::class, 'getProjectsForEmailComposer']);
    Route::get('projects/{project}/notes', [ProjectReadController::class, 'getNotes']); // Handles general project notes
    Route::get('projects/{project}/standups', [ProjectReadController::class, 'getNotes']); // Standups are also notes, filtered by type
    Route::get('projects/{project}/notes/{note}/replies', [ProjectReadController::class, 'getNoteReplies']);
    Route::get('projects/{project}/tasks', [ProjectReadController::class, 'getTasks']);
    Route::get('/projects/{project}/meetings', [ProjectReadController::class, 'getProjectMeetings']);

    // Project Section Read Routes
    Route::get('projects/{project}/sections/basic', [ProjectReadController::class, 'getBasicInfo']);
    Route::get('projects/{project}/sections/clients-users', [ProjectReadController::class, 'getClientsAndUsers']);
    Route::get('projects/{project}/sections/clients', [ProjectReadController::class, 'getClientsAndUsers']);
    Route::get('projects/{project}/sections/users', [ProjectReadController::class, 'getClientsAndUsers']);
    Route::get('projects/{project}/sections/services-payment', [ProjectReadController::class, 'getServicesAndPayment']);
    Route::get('projects/{project}/sections/transactions', [ProjectReadController::class, 'getTransactions']);
    Route::get('projects/{project}/sections/documents', [ProjectReadController::class, 'getDocuments']);
    Route::get('projects/{project}/sections/notes', [ProjectReadController::class, 'getNotes']); // Re-uses getNotes
    Route::get('projects/{project}/users', [ProjectReadController::class, 'getProjectUsers']);
    Route::get('projects/{project}/clients', [ProjectReadController::class, 'getProjectClients']);
    Route::get('projects/{project}/contract-details', [ProjectReadController::class, 'getContractDetails']);

    // Action Routes
    Route::post('projects', [ProjectActionController::class, 'store']);
    Route::put('projects/{project}', [ProjectActionController::class, 'update']);
    Route::delete('projects/{project}', [ProjectActionController::class, 'destroy']);
    Route::post('projects/{project}/attach-users', [ProjectActionController::class, 'attachUsers'])->name('projects.attach-users');
    Route::post('projects/{project}/detach-users', [ProjectActionController::class, 'detachUsers'])->name('projects.detach-users');
    Route::post('projects/{project}/attach-clients', [ProjectActionController::class, 'attachClients'])->name('projects.attach-clients');
    Route::post('projects/{project}/detach-clients', [ProjectActionController::class, 'detach-clients']);
    Route::post('projects/{project}/transactions', [\App\Http\Controllers\Api\TransactionsController::class, 'addTransactions']);
    Route::patch('projects/{project}/transactions/{transaction}/process-payment', [\App\Http\Controllers\Api\TransactionsController::class, 'processPayment']);
    Route::post('projects/{project}/notes', [ProjectActionController::class, 'addNotes']);
    Route::post('projects/{project}/notes/{note}/reply', [ProjectActionController::class, 'replyToNote']);
    Route::post('projects/{project}/document', [ProjectActionController::class, 'uploadDocuments'])->name('singleDocument');
    Route::post('projects/{project}/documents', [ProjectActionController::class, 'uploadDocuments'])->name('multipleDocuments');
    Route::post('projects/{project}/logo', [ProjectActionController::class, 'uploadLogo']);
    Route::post('projects/{project}/standup', [ProjectActionController::class, 'addStandup']);
    Route::post('/projects/{project}/meetings', [ProjectActionController::class, 'createProjectMeeting']);
    Route::delete('/projects/{project}/meetings/{googleEventId}', [ProjectActionController::class, 'deleteProjectMeeting']);
    Route::patch('projects/{project}/convert-payment-type', [ProjectActionController::class, 'convertPaymentType']); // Moved PATCH route

    // Project Section Update Routes
    Route::put('projects/{project}/sections/basic', [ProjectActionController::class, 'updateBasicInfo'])->middleware(['process.tags']);
    Route::put('projects/{project}/sections/services-payment', [ProjectActionController::class, 'updateServicesAndPayment']);
    Route::put('projects/{project}/sections/transactions', [ProjectActionController::class, 'updateTransactions']);
    Route::put('projects/{project}/sections/notes', [ProjectActionController::class, 'updateNotes']);

    // Resource Management Routes
    Route::apiResource('projects/{project}/resources', ResourceController::class);

    // Comment Management Routes
    Route::apiResource('resources.comments', CommentController::class)->except(['index', 'store']); // Use nested resource
    Route::get('resources/{resource}/comments', [CommentController::class, 'index']); // Specific index for comments on a resource
    Route::post('resources/{resource}/comments', [CommentController::class, 'store']); // Specific store for comments on a resource
    Route::post('resources/{resource}/approve', [CommentController::class, 'approveResource']);
    Route::post('resources/{resource}/toggle-visibility', [CommentController::class, 'toggleVisibility']);


    // Email Management & Approval Routes
    Route::get('emails/pending-approval', [EmailController::class, 'pendingApproval']);
    Route::get('emails/pending-approval-simplified', [EmailController::class, 'pendingApprovalSimplified']);
    Route::get('emails/rejected', [EmailController::class, 'rejected']);
    Route::get('emails/rejected-simplified', [EmailController::class, 'rejectedSimplified']);
    Route::get('projects/{project}/emails', [EmailController::class, 'getProjectEmails']);
    Route::get('projects/{project}/emails-simplified', [EmailController::class, 'getProjectEmailsSimplified']);
    Route::post('emails/{email}/approve', [EmailController::class, 'approve']);
    Route::post('emails/{email}/edit-and-approve', [EmailController::class, 'editAndApprove']);
    Route::post('emails/{email}/reject', [EmailController::class, 'reject']);
    Route::post('emails/{email}/update', [EmailController::class, 'update']);
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
    Route::get('projects/{projectId}/due-and-overdue-tasks', [TaskController::class, 'getProjectDueAndOverdueTasks']);

    // Apply ProcessTags middleware to store and update methods
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

    // Bonus Configuration Management Routes
    Route::apiResource('bonus-configurations', BonusConfigurationController::class);

    // Bonus Configuration Group Management Routes
    Route::apiResource('bonus-configuration-groups', BonusConfigurationGroupController::class);
    Route::post('bonus-configuration-groups/{id}/duplicate', [BonusConfigurationGroupController::class, 'duplicate']);
    Route::post('projects/{projectId}/attach-bonus-configuration-group', [BonusConfigurationGroupController::class, 'attachToProject']);
    Route::post('projects/{projectId}/detach-bonus-configuration-group', [BonusConfigurationGroupController::class, 'detachFromProject']);

    // Deliverable Routes
    Route::get('/projects/{project}/deliverables', [ProjectDeliverableAction::class, 'index'])->name('projects.deliverables.index');
    Route::post('/projects/{project}/deliverables', [ProjectDeliverableAction::class, 'store'])->name('projects.deliverables.store');

    Route::get('/projects/{project}/deliverables/{deliverable}', [ProjectDeliverableAction::class, 'show'])->name('projects.deliverables.show');
    Route::post('/projects/{project}/deliverables/{deliverable}/comments', [ProjectDeliverableAction::class, 'addComment'])->name('projects.deliverables.addComment');


    // Magic Link Routes
    Route::post('projects/{projectId}/magic-link', [MagicLinkController::class, 'sendMagicLink']);
    Route::get('currency-rates', [\App\Http\Controllers\Api\CurrencyController::class, 'index']);
});


// === Client-Specific API Routes (Protected by Magic Link Token) ===
// These routes will be used by the Vue client dashboard, authenticated via magic link.
// Client Dashboard API Routes (protected by magiclink middleware)
Route::prefix('client-api')->middleware(['auth.magiclink'])->group(function () {

    Route::get('project/{project}', [ProjectClientReader::class, 'getProject']);
    // Project Client Reader Routes (GET)
    Route::get('project/{project}/tasks', [ProjectClientReader::class, 'getProjectTasks']);
    Route::get('project/{project}/deliverables', [ProjectClientReader::class, 'getProjectDeliverables']);
    Route::get('project/{project}/documents', [ProjectClientReader::class, 'getProjectDocuments']);
    // TODO: Add more reader endpoints as needed (e.g., announcements, invoices, comments for a deliverable)

    // Project Client Action Routes (POST/PATCH)
    Route::post('deliverables/{deliverable}/mark-read', [ProjectClientAction::class, 'markDeliverableAsRead']);
    Route::post('deliverables/{deliverable}/approve', [ProjectClientAction::class, 'approveDeliverable']);
    Route::post('deliverables/{deliverable}/request-revisions', [ProjectClientAction::class, 'requestDeliverableRevisions']);
    Route::post('deliverables/{deliverable}/comments', [ProjectClientAction::class, 'addDeliverableComment']);

    Route::post('tasks/{task}/notes', [ProjectClientAction::class, 'addNoteToTask']);
    Route::post('tasks', [ProjectClientAction::class, 'createTask']);
    Route::post('documents', [ProjectClientAction::class, 'uploadClientDocuments']);
    Route::post('document/{document}/notes', [ProjectClientAction::class, 'addNoteToDocument']);
});
