<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\BonusConfigurationGroupController;
use App\Http\Controllers\Api\ClientDashboard\ProjectClientAction;
use App\Http\Controllers\Api\ClientDashboard\ProjectClientReader;
use App\Http\Controllers\Api\Client\SeoReportController;
use App\Http\Controllers\Api\ComponentController;
use App\Http\Controllers\Api\EmailTemplateController;
use App\Http\Controllers\Api\ImageUploadController;
use App\Http\Controllers\Api\PlaceholderDefinitionController;
use App\Http\Controllers\Api\ProjectDashboard\ProjectDeliverableAction;
use App\Http\Controllers\Api\SendEmailController;
use App\Http\Controllers\Api\ShareableResourceController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserWorkspaceController;
use App\Http\Controllers\Api\WireframeController;
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
use App\Http\Controllers\Api\InboxController;
use App\Http\Controllers\Api\ProjectReadController; // New Import
use App\Http\Controllers\Api\ProjectActionController; // New Import
use App\Http\Controllers\Api\WorkspaceController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\SubtaskController;
use App\Http\Controllers\Api\MilestoneController;
use App\Http\Controllers\Api\TaskTypeController;
use App\Http\Controllers\Api\FileAttachmentController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BonusConfigurationController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\MagicLinkController;
use App\Http\Controllers\Api\ModelDataController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Api\FamifyHub\MailController as FamifyMailController;
use App\Http\Controllers\Api\BugReportController;
use App\Http\Controllers\Api\PresentationAIController;
use App\Http\Controllers\Api\PresentationGeneratorController;
use App\Http\Controllers\Api\PublicLeadIntakeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\ProjectNoteController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\ScheduleApiController;


Route::post('/loginapp', [AuthenticatedSessionController::class, 'storeapp'])->middleware(['guest', 'web']);
// Public Authentication Routes (NO auth:sanctum middleware)
Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware(['guest', 'web']);

// API Token Authentication for third-party applications
Route::post('/token', [AuthenticatedSessionController::class, 'getToken'])
    ->middleware('guest');

// API Token Logout for third-party applications
Route::post('/logout-token', [AuthenticatedSessionController::class, 'revokeToken'])
    ->middleware('auth:sanctum');

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware('guest');

Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware('guest');

// Client Magic Link Route (accessible without authentication)
Route::post('/client-magic-link', [MagicLinkController::class, 'sendClientMagicLink']);
// Client token verify endpoint
Route::post('/client-api/verify', [MagicLinkController::class, 'verifyClient']);

Route::get('/playground', [\App\Http\Controllers\TestController::class, 'playGourd']);

// Public Bugs Reporting Endpoints
Route::prefix('bugs')->group(function () {
    Route::post('/report', [BugReportController::class, 'report']);
    Route::get('/', [BugReportController::class, 'index']);
    Route::get('/status', [BugReportController::class, 'status']);
});

// Public FamifyHub contact endpoint (no authentication)
Route::post('/famifyhub/contact', [FamifyMailController::class, 'submit']);
Route::post('/famifyhub/contactform', [FamifyMailController::class, 'contactForm']);

// Public Lead Intake (from PublicPresenter)
Route::post('/public/lead-intake', [PublicLeadIntakeController::class, 'store']);

// Authenticated API Routes (behind auth:sanctum middleware for internal users)
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Test Form Route for BaseFormModal testing
    Route::post('/test-form', [\App\Http\Controllers\Api\TestFormController::class, 'store']);

    // Notifications Routes
    // Other routes...
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{viewId}/read', [NotificationController::class, 'markAsReadByViewId']);
    Route::delete('/notifications/{notificationId}', [NotificationController::class, 'destroy']);

    // User Workspace (checklist and notes)
    Route::get('user/workspace', [UserWorkspaceController::class, 'workspace']);
    Route::put('user/checklist', [UserWorkspaceController::class, 'updateChecklist']);
    Route::put('user/notes', [UserWorkspaceController::class, 'updateNotes']);

    // Tag Management Routes
    Route::get('/tags/search', [\App\Http\Controllers\TagController::class, 'search']);

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

    // Email Verification routes
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('api.verification.verify');

    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1');

    // Google OAuth Routes
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirectToGoogle'])
        ->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);

    // Client Management Routes (CRUD)
    Route::apiResource('clients', ClientController::class)->names('api.clients');
    Route::get('/leads/search', [LeadController::class, 'search']);
    Route::apiResource('leads', LeadController::class)->names('api.leads');
    Route::post('/leads/{lead}/contexts', [LeadController::class, 'addContext']);
    Route::get('leads/{lead}/emails', [LeadController::class, 'emails']);

    // Campaigns
    Route::apiResource('campaigns', CampaignController::class);
    Route::get('/campaigns/{campaign}/leads', [CampaignController::class, 'leads']);
    Route::post('/campaigns/{campaign}/leads', [CampaignController::class, 'attachLead']);
    Route::delete('/campaigns/{campaign}/leads/{lead}', [CampaignController::class, 'detachLead']);
    Route::get('leads/{lead}/presentations', [LeadController::class, 'presentations']);
    Route::post('leads/{lead}/convert', [LeadController::class, 'convert']);
    Route::get('clients/{client}/email', [ClientController::class, 'getEmail']);
    Route::get('clients/{client}/emails', [ClientController::class, 'emails']);
    Route::get('clients/{client}/details', [ClientController::class, 'details']);

    // Generic project notes endpoints (polymorphic)
    Route::get('/project_notes', [ProjectNoteController::class, 'index']);
    Route::post('/project_notes', [ProjectNoteController::class, 'store']);
    Route::post('/upload-image', [ImageUploadController::class, 'upload']);

    // Project Management Routes (Split into Read and Action)
    // Read Routes
    Route::get('projects', [ProjectReadController::class, 'index']);
    Route::get('projects/with-wireframes', [ProjectReadController::class, 'wireframe']);
    Route::get('projects/{project}', [ProjectReadController::class, 'show']);
    Route::get('projects-simplified', [ProjectReadController::class, 'getProjectsSimplified']);
    Route::get('projects-for-email', [ProjectReadController::class, 'getProjectsForEmailComposer']);
    Route::get('projects/{project}/notes', [ProjectReadController::class, 'getNotes']); // Handles general project notes
    Route::get('projects/{project}/standups', [ProjectReadController::class, 'getNotes']); // Standups are also notes, filtered by type
    Route::get('projects/{project}/notes/{note}/replies', [ProjectReadController::class, 'getNoteReplies']);
    Route::get('projects/{project}/tasks', [ProjectReadController::class, 'getTasks']);
    Route::get('/projects/{project}/meetings', [ProjectReadController::class, 'getProjectMeetings']);
    Route::get('/user/meetings', [ProjectReadController::class, 'getUserMeetings']);
    Route::get('/user/standups', [ProjectReadController::class, 'getUserStandups']);

    // Project Section Read Routes
    Route::get('projects/{project}/sections/basic', [ProjectReadController::class, 'getBasicInfo']);
    // Project Expendables
    Route::get('projects/{project}/expendables', [\App\Http\Controllers\Api\ProjectExpendableController::class, 'index']);
    Route::get('projects/{project}/sections/clients-users', [ProjectReadController::class, 'getClientsAndUsers']);
    Route::get('projects/{project}/sections/clients', [ProjectReadController::class, 'getClientsAndUsers']);
    Route::get('projects/{project}/sections/users', [ProjectReadController::class, 'getClientsAndUsers']);
    Route::get('projects/{project}/sections/services-payment', [ProjectReadController::class, 'getServicesAndPayment']);
    Route::get('projects/{project}/expendable-budget', [ProjectReadController::class, 'getExpendableBudget']);
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
    Route::post('projects/{project}/transactions', [\App\Http\Controllers\Api\TransactionsController::class, 'addTransactions'])->middleware('process.basic:transaction_type,App\\Models\\TransactionType');
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
    Route::patch('projects/{project}/expendable-budget', [ProjectActionController::class, 'updateExpendableBudget']);
    Route::post('projects/{project}/archive', [ProjectActionController::class, 'archive']);
    Route::patch('projects/{project}/assign-leads', [ProjectActionController::class, 'assignLeads'])->middleware('permission:manage_projects');
    Route::post('projects/{project}/expendables', [\App\Http\Controllers\Api\ProjectExpendableController::class, 'store']);
    Route::put('projects/{project}/expendables/{expendable}', [\App\Http\Controllers\Api\ProjectExpendableController::class, 'update']);
    Route::post('projects/{project}/expendables/{expendable}/accept', [\App\Http\Controllers\Api\ProjectExpendableController::class, 'accept']);
    Route::post('projects/{project}/expendables/{expendable}/reject', [\App\Http\Controllers\Api\ProjectExpendableController::class, 'reject']);
    Route::delete('projects/{project}/expendables/{expendable}', [\App\Http\Controllers\Api\ProjectExpendableController::class, 'destroy']);
    Route::post('projects/{id}/restore', [ProjectActionController::class, 'restore']);

    // Project Section Update Routes
    Route::put('projects/{project}/sections/basic', [ProjectActionController::class, 'updateBasicInfo'])->middleware(['process.tags']);

    // Workspace API
    Route::get('workspace/projects', [WorkspaceController::class, 'projects']);
        Route::get('workspace/projects/{project}/completed-tasks', [WorkspaceController::class, 'completedTasks']);
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

    // Generic file attachments (polymorphic: Task, etc.)
    Route::get('files', [FileAttachmentController::class, 'index']);
    Route::post('files', [FileAttachmentController::class, 'store']);
    Route::delete('files/{file}', [FileAttachmentController::class, 'destroy']);


    // Email Management & Approval Routes
    Route::get('emails/pending-approval', [EmailController::class, 'pendingApproval']);
    Route::get('emails/pending-approval-simplified', [EmailController::class, 'pendingApprovalSimplified']);
    Route::get('emails/rejected', [EmailController::class, 'rejected']);
    Route::get('emails/rejected-simplified', [EmailController::class, 'rejectedSimplified']);
    Route::get('projects/{project}/emails', [EmailController::class, 'getProjectEmails']);
    Route::get('projects/{project}/emails-simplified', [EmailController::class, 'getProjectEmailsSimplified']);
    Route::get('emails/{email}/edit-content', [EmailController::class, 'getEmailContent']);
    Route::post('emails/{email}/approve', [EmailController::class, 'approve']);
    Route::post('emails/{email}/edit-and-approve', [EmailController::class, 'editAndApprove']);
    Route::post('emails/{email}/reject', [EmailController::class, 'reject']);
    Route::post('emails/{email}/update', [EmailController::class, 'update']);
    Route::post('emails/{email}/resubmit', [EmailController::class, 'resubmit']);
    Route::post('emails/{email}/tasks/bulk', [EmailController::class, 'bulkTasksFromEmail']);
    Route::apiResource('emails', EmailController::class)->except(['destroy']);
    Route::patch('emails/{email}/privacy', [EmailController::class, 'togglePrivacy']);
    Route::delete('emails/{email}', [EmailController::class, 'destroy']);

    // Inbox Routes
    Route::get('inbox/new-emails', [InboxController::class, 'newEmails']);
    Route::get('inbox/all-emails', [InboxController::class, 'allEmails']);
    Route::get('inbox/waiting-approval', [InboxController::class, 'waitingApproval']);
    Route::get('inbox/counts', [InboxController::class, 'counts']);

    Route::post('inbox/emails/{email}/mark-as-read', [InboxController::class, 'markAsRead']);

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

    // Google User Chat Routes
    Route::prefix('user/google-chat')->group(function () {
        Route::get('/check-credentials', [\App\Http\Controllers\GoogleChatUserController::class, 'checkGoogleCredentials']);
        Route::post('/spaces', [\App\Http\Controllers\GoogleChatUserController::class, 'createSpace']);
        Route::post('/spaces/members', [\App\Http\Controllers\GoogleChatUserController::class, 'addMembers']);
        Route::post('/messages', [\App\Http\Controllers\GoogleChatUserController::class, 'sendMessage']);
        Route::post('/standups', [\App\Http\Controllers\GoogleChatUserController::class, 'sendStandup']);
        Route::post('/notes', [\App\Http\Controllers\GoogleChatUserController::class, 'sendNote']);
    });

    // Leaderboard Routes
    Route::get('leaderboard/monthly', [\App\Http\Controllers\Api\LeaderboardController::class, 'monthly']);
    Route::get('leaderboard/stats', [\App\Http\Controllers\Api\LeaderboardController::class, 'stats']);

    // Points Ledger Routes
    Route::get('points-ledger', [\App\Http\Controllers\Api\PointsLedgerController::class, 'mine']);
    Route::get('points-ledger/total', [\App\Http\Controllers\Api\PointsLedgerController::class, 'total']);

    Route::post('users/{user}/restore', [UserController::class, 'restore'])->name('users.restore');
    Route::get('users/{user}/emails', [UserController::class, 'emails']);
    Route::apiResource('users', UserController::class)->names('api.users');

    // Permission Management Routes
    Route::get('/permissions', [PermissionController::class, 'getAllPermissions'])->middleware('permission:view_permissions');
    Route::get('/user/permissions', [PermissionController::class, 'getUserPermissions']);
    Route::get('/projects/{project}/permissions', [PermissionController::class, 'getUserProjectPermissions'])->name('projects.permissions');


    // Role Management Routes (CRUD)
    Route::apiResource('roles', RoleController::class)->middleware('permission:manage_roles');
    Route::post('roles/{role}/permissions', [RoleController::class, 'updatePermissions'])
        ->middleware('permission:manage_permissions')
        ->name('roles.updatePermissions');

    // Project Tier Management Routes
    Route::apiResource('project-tiers', \App\Http\Controllers\Api\ProjectTierController::class);

    // Task Management Routes
        // Polymorphic schedule creation for existing items
        Route::post('schedules', [ScheduleApiController::class, 'store'])->name('api.schedules.store');
    Route::get('task-statistics', [TaskController::class, 'getTaskStatistics']);
    Route::get('assigned-tasks', [TaskController::class, 'getAssignedTasks']);
    Route::get('projects/{projectId}/due-and-overdue-tasks', [TaskController::class, 'getProjectDueAndOverdueTasks']);

    // Activity Log Routes
    Route::get('activities', [ActivityController::class, 'index']);

    // Apply ProcessTags middleware to store and update methods
    Route::post('tasks/bulk', [TaskController::class, 'bulk']);
    Route::apiResource('tasks', TaskController::class)->middleware(['process.tags']);
    Route::post('tasks/{task}/notes', [TaskController::class, 'addNote']);
    Route::patch('tasks/{task}/complete', [TaskController::class, 'markAsCompleted']);
    Route::post('tasks/{task}/start', [TaskController::class, 'start']);
    Route::post('tasks/{task}/pause', [TaskController::class, 'pause']);
    Route::post('tasks/{task}/resume', [TaskController::class, 'resume']);
    Route::post('tasks/{task}/block', [TaskController::class, 'block']);
    Route::post('tasks/{task}/unblock', [TaskController::class, 'unblock']);
    Route::post('tasks/{task}/archive', [TaskController::class, 'archive']);
    Route::post('tasks/{task}/revise', [TaskController::class, 'revise']);

    // Subtask Management Routes
    Route::apiResource('subtasks', SubtaskController::class);
    Route::post('subtasks/{subtask}/notes', [SubtaskController::class, 'addNote']);
    Route::post('subtasks/{subtask}/complete', [SubtaskController::class, 'markAsCompleted']);
    Route::post('subtasks/{subtask}/start', [SubtaskController::class, 'start']);
    Route::post('subtasks/{subtask}/block', [SubtaskController::class, 'block']);

    // Milestone Management Routes
    Route::apiResource('milestones', MilestoneController::class);
    Route::post('milestones/{milestone}/complete', [MilestoneController::class, 'markAsCompleted']);
    Route::post('milestones/{milestone}/approve', [MilestoneController::class, 'approve']);
    Route::post('milestones/{milestone}/reject', [MilestoneController::class, 'reject']);
    Route::post('milestones/{milestone}/reopen', [MilestoneController::class, 'reopen']);
    Route::post('milestones/{milestone}/start', [MilestoneController::class, 'start']);
    Route::get('milestones/{milestone}/reasons', [MilestoneController::class, 'reasons']);

    // Project-specific Task Management Routes
    Route::get('projects/{project}/milestones', [MilestoneController::class, 'index']);
    Route::get('projects/{project}/milestones-with-expendables', [MilestoneController::class, 'milestonesWithExpendables']);
    Route::post('projects/{project}/milestones', [MilestoneController::class, 'store']);

    // Task Type Routes
    Route::apiResource('task-types', TaskTypeController::class);

    // Transaction Types Routes (index, store, search)
    Route::get('transaction-types', [\App\Http\Controllers\Api\TransactionTypeController::class, 'index']);
    Route::post('transaction-types', [\App\Http\Controllers\Api\TransactionTypeController::class, 'store']);
    Route::get('transaction-types/search', [\App\Http\Controllers\Api\TransactionTypeController::class, 'search']);

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

    // Shareable Resource Management Routes
    Route::apiResource('shareable-resources', ShareableResourceController::class)->middleware(['process.tags']);

    // Profile Field Update (generic)
    Route::post('user/update-profile-field', [UserProfileController::class, 'updateField']);

    // Notice Board Routes
    Route::get('notices', [\App\Http\Controllers\Api\NoticeBoardController::class, 'index'])->middleware('permission:manage_notices');
    Route::post('notices', [\App\Http\Controllers\Api\NoticeBoardController::class, 'store'])->middleware('permission:manage_notices');
    Route::get('notices/unread', [\App\Http\Controllers\Api\NoticeBoardController::class, 'unread']);
    Route::post('notices/acknowledge', [\App\Http\Controllers\Api\NoticeBoardController::class, 'acknowledge']);
    Route::get('notices/{notice}/redirect', [\App\Http\Controllers\Api\NoticeBoardController::class, 'redirect'])->name('api.notices.redirect');

    // Deliverable Routes
    Route::get('/projects/{project}/deliverables', [ProjectDeliverableAction::class, 'index'])->name('projects.deliverables.index');
    Route::post('/projects/{project}/deliverables', [ProjectDeliverableAction::class, 'store'])->name('projects.deliverables.store');

    Route::get('/projects/{project}/deliverables/{deliverable}', [ProjectDeliverableAction::class, 'show'])->name('projects.deliverables.show');
    Route::post('/projects/{project}/deliverables/{deliverable}/comments', [ProjectDeliverableAction::class, 'addComment'])->name('projects.deliverables.addComment');

    //SEO Report
    Route::post('/projects/{project}/seo-reports', [SeoReportController::class, 'store']);
    Route::get('/projects/{project}/seo-reports/available-months', [SeoReportController::class, 'getAvailableMonths']);
    Route::get('/projects/{project}/seo-reports/{yearMonth}', [SeoReportController::class, 'show']);

    // Magic Link Routes
    Route::post('projects/{projectId}/magic-link', [MagicLinkController::class, 'sendMagicLink']);
    Route::get('currency-rates', [\App\Http\Controllers\Api\CurrencyController::class, 'index']);

    // --- NEW: Email Templates API Routes ---
    // Protect these routes with a new permission: 'manage_email_templates'
//    Route::get('email-templates', [EmailTemplateController::class, 'index']);
    Route::apiResource('email-templates', EmailTemplateController::class);
    Route::post('email-templates/{emailTemplate}/placeholders', [EmailTemplateController::class, 'syncPlaceholders']);
    // We can also add a route to get a preview of the rendered template.
    Route::post('email-templates/{emailTemplate}/preview', [EmailTemplateController::class, 'preview']);

    // --- NEW: Placeholder Definitions API Routes ---
    // Protected by 'manage_placeholder_definitions' permission
    Route::get('placeholder-definitions/models-and-columns', [PlaceholderDefinitionController::class, 'getModelsAndColumns'])->middleware('permission:manage_placeholder_definitions');
    Route::apiResource('placeholder-definitions', PlaceholderDefinitionController::class)->middleware('permission:manage_placeholder_definitions');


    Route::post('projects/{project}/email-preview', [SendEmailController::class, 'preview']);
    Route::post('emails/templated', [EmailController::class, 'storeTemplatedEmail']);
    Route::get('projects/{project}/model-data/{shortModelName}', [\App\Http\Controllers\Api\ModelDataController::class, 'index']);

    // Route for fetching source model data for email templates
    Route::get('source-models/{modelName}', [\App\Http\Controllers\Api\ModelDataController::class, 'getSourceModelData']);

    // Project Deliverables Routes
    Route::get('projects/{projectId}/project-deliverables', [\App\Http\Controllers\Api\ProjectDeliverableController::class, 'index']);
    Route::post('projects/{projectId}/project-deliverables', [\App\Http\Controllers\Api\ProjectDeliverableController::class, 'store']);
    Route::get('project-deliverables/{id}', [\App\Http\Controllers\Api\ProjectDeliverableController::class, 'show']);
    Route::put('project-deliverables/{id}', [\App\Http\Controllers\Api\ProjectDeliverableController::class, 'update']);
    Route::delete('project-deliverables/{id}', [\App\Http\Controllers\Api\ProjectDeliverableController::class, 'destroy']);
    Route::get('project-deliverable-types', function () {
       return config('project_deliverable_types');
    });

    // Wireframe Routes
    Route::prefix('projects/{projectId}/wireframes')->group(function () {
        Route::get('/', [WireframeController::class, 'index']);
        Route::get('latest', [WireframeController::class, 'latest']);
        Route::get('{id}', [WireframeController::class, 'show']);
        Route::post('/', [WireframeController::class, 'store']);
        Route::put('{id}', [WireframeController::class, 'update']);

        Route::get('{id}/comments', [\App\Http\Controllers\Api\ProjectReadController::class, 'getWireframeComments']);
        Route::post('{id}/comments', [\App\Http\Controllers\Api\ProjectActionController::class, 'addWireframeComment']);
        Route::post('{id}/comments/{commentId}/resolved_comment', [\App\Http\Controllers\Api\ProjectActionController::class, 'resolveWireframeComment']);

        Route::post('{id}/{publish}', [WireframeController::class, 'publish']);
        Route::post('{id}/versions', [WireframeController::class, 'newVersion']);
        Route::get('{id}/versions', [WireframeController::class, 'versions']);
        Route::put('{id}/versions/{versionNumber}', [WireframeController::class, 'updateVersion']);
        Route::delete('{id}', [WireframeController::class, 'destroy']);
        Route::get('{id}/logs', [WireframeController::class, 'logs']);
        // New: Internal (sanctum) wireframe comments endpoints
    });

    // Presentations API v1
    Route::prefix('v1')->group(function () {
        Route::get('presentations', [\App\Http\Controllers\Api\PresentationController::class, 'index']);
        Route::post('presentations', [\App\Http\Controllers\Api\PresentationController::class, 'store']);
        Route::get('presentations/{id}', [\App\Http\Controllers\Api\PresentationController::class, 'show']);
        Route::put('presentations/{id}', [\App\Http\Controllers\Api\PresentationController::class, 'update']);
        Route::delete('presentations/{id}', [\App\Http\Controllers\Api\PresentationController::class, 'destroy']);
        Route::post('presentations/{id}/invite', [\App\Http\Controllers\Api\PresentationController::class, 'invite']);
        Route::post('presentations/{id}/collaborators', [\App\Http\Controllers\Api\PresentationController::class, 'syncCollaborators']);

        Route::post('presentations/{presentationId}/slides', [\App\Http\Controllers\Api\PresentationController::class, 'storeSlide']);
        Route::put('slides/{id}', [\App\Http\Controllers\Api\PresentationController::class, 'updateSlide']);
        Route::post('slides/reorder', [\App\Http\Controllers\Api\PresentationController::class, 'reorderSlides']);
        Route::delete('slides/{id}', [\App\Http\Controllers\Api\PresentationController::class, 'destroySlide']);

        Route::post('slides/{slideId}/content_blocks', [\App\Http\Controllers\Api\PresentationController::class, 'storeContentBlock']);
        Route::put('content_blocks/{id}', [\App\Http\Controllers\Api\PresentationController::class, 'updateContentBlock']);
        Route::post('content_blocks/reorder', [\App\Http\Controllers\Api\PresentationController::class, 'reorderContentBlocks']);
        Route::delete('content_blocks/{id}', [\App\Http\Controllers\Api\PresentationController::class, 'destroyContentBlock']);

        // Templates & Duplication
        Route::get('templates', [\App\Http\Controllers\Api\PresentationController::class, 'templates']);
        Route::post('presentations/{id}/duplicate', [\App\Http\Controllers\Api\PresentationController::class, 'duplicate']);
        Route::post('presentations/{id}/save-as-template', [\App\Http\Controllers\Api\PresentationController::class, 'saveAsTemplate']);
        Route::post('presentations/{targetId}/copy-slides', [\App\Http\Controllers\Api\PresentationController::class, 'copySlides']);
    });

    Route::post('/presentations/{presentation}/generate-slide', [PresentationAIController::class, 'generateSlide']);
    Route::post('/presentations/{presentation}/create-slide-from-ai', [PresentationAIController::class, 'createSlideFromAI']);

    // Surprise Me: Generate a full presentation
    Route::post('/presentations/generate', [PresentationGeneratorController::class, 'generate']);

    // Non-versioned Presentations Template & Duplication routes for compatibility with spec
    Route::get('templates', [\App\Http\Controllers\Api\PresentationController::class, 'templates']);
    Route::post('presentations/{id}/duplicate', [\App\Http\Controllers\Api\PresentationController::class, 'duplicate']);
    Route::post('presentations/{id}/save-as-template', [\App\Http\Controllers\Api\PresentationController::class, 'saveAsTemplate']);
    Route::post('presentations/{targetId}/copy-slides', [\App\Http\Controllers\Api\PresentationController::class, 'copySlides']);

    // Kudos Routes
    Route::get('kudos/pending', [\App\Http\Controllers\Api\KudoController::class, 'pending']);
    Route::get('kudos/mine', [\App\Http\Controllers\Api\KudoController::class, 'mine']);
    Route::post('kudos', [\App\Http\Controllers\Api\KudoController::class, 'store']);
    Route::post('kudos/{kudo}/approve', [\App\Http\Controllers\Api\KudoController::class, 'approve']);
    Route::post('kudos/{kudo}/reject', [\App\Http\Controllers\Api\KudoController::class, 'reject']);

    // Component Routes
    Route::prefix('components')->group(function () {
        Route::get('/', [ComponentController::class, 'index']);
        Route::post('/', [ComponentController::class, 'store']);
        Route::get('{id}', [ComponentController::class, 'show']);
        Route::put('{id}', [ComponentController::class, 'update']);
        Route::delete('{id}', [ComponentController::class, 'destroy']);
    });

    // AI Automation Engine routes
    Route::apiResource('workflows', \App\Http\Controllers\Api\WorkflowController::class);
    Route::post('workflows/{workflow}/run', [\App\Http\Controllers\Api\WorkflowController::class, 'run']);
    // Endpoint to manually fire automation triggers (useful for testing)
    Route::post('workflows/triggers/{event}', [\App\Http\Controllers\Api\AutomationTriggerController::class, 'trigger']);
    Route::apiResource('prompts', \App\Http\Controllers\Api\PromptController::class);
    Route::apiResource('workflow-steps', \App\Http\Controllers\Api\WorkflowStepController::class);

});



// === Client-Specific API Routes (Protected by Magic Link Token) ===
// These routes will be used by the Vue client dashboard, authenticated via magic link.
// Client Dashboard API Routes (protected by magiclink middleware)
Route::prefix('client-api')->middleware(['auth.magiclink'])->group(function () {

    Route::get('project/{project}', [ProjectClientReader::class, 'getProject']);
    Route::get('project/{project}/wireframes', [ProjectClientReader::class, 'getWireframes']);
    Route::get('project/{project}/wireframe/{wireframeId}', [ProjectClientReader::class, 'showWireframe']);
    Route::get('project/{project}/wireframe/{wireframeId}/comments', [ProjectClientReader::class, 'getWireframeComments']);
    Route::post('project/{project}/wireframe/{wireframeId}/comments', [ProjectClientAction::class, 'addWireframeComment']);
    Route::post('project/{project}/wireframe/{wireframeId}/comments/{commentId}/{status}', [ProjectClientAction::class, 'resolveWireframeComment']);

    // Project Client Reader Routes (GET)
    Route::get('project/{project}/tasks', [ProjectClientReader::class, 'getProjectTasks']);
    Route::get('project/{project}/deliverables', [ProjectClientReader::class, 'getProjectDeliverables']);
    Route::get('project/{project}/documents', [ProjectClientReader::class, 'getProjectDocuments']);
    Route::get('project/{project}/shareable-resources', [ProjectClientReader::class, 'getShareableResources']);
    Route::get('/project/{projectId}/seo-report/{month}', [ProjectClientReader::class, 'getReportData']);

    // SEO Reports API Routes
    Route::get('/projects/{project}/seo-reports/available-months', [SeoReportController::class, 'getAvailableMonths']);
    Route::get('/projects/{project}/seo-reports/count', [SeoReportController::class, 'getCount']);
    Route::get('/projects/{project}/seo-reports/{yearMonth}', [SeoReportController::class, 'show']);
    // TODO: Add more reader endpoints as needed (e.g., announcements, invoices, comments for a deliverable)

    // Project Client Action Routes (POST/PATCH)
    Route::post('deliverables/{deliverable}/mark-read', [ProjectClientAction::class, 'markDeliverableAsRead']);
    Route::post('deliverables/{deliverable}/approve', [ProjectClientAction::class, 'approveDeliverable']);
    Route::post('deliverables/{deliverable}/request-revisions', [ProjectClientAction::class, 'requestDeliverableRevisions']);
    Route::post('deliverables/{deliverable}/comments', [ProjectClientAction::class, 'addDeliverableComment']);

    Route::post('tasks/{task}/notes', [ProjectClientAction::class, 'addNoteToTask']);
    Route::post('tasks', [ProjectClientAction::class, 'createTask']);
    Route::post('documents', [ProjectClientAction::class, 'uploadClientDocuments']);
    Route::post('documents/{document}/notes', [ProjectClientAction::class, 'addNoteToDocument']);

});
