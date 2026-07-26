<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AutomationSchemaController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BonusConfigurationController;
use App\Http\Controllers\Api\BonusConfigurationGroupController;
use App\Http\Controllers\Api\BugReportController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\CategorySetController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\Client\SeoReportController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ClientDashboard\ProjectClientAction;
use App\Http\Controllers\Api\ClientDashboard\ProjectClientReader;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\ComponentController;
use App\Http\Controllers\Api\EmailController;
use App\Http\Controllers\Api\EmailTemplateController;
use App\Http\Controllers\Api\ExistingClientEnquiryController;
use App\Http\Controllers\Api\FamifyHub\MailController as FamifyMailController;
use App\Http\Controllers\Api\FileAttachmentController;
use App\Http\Controllers\Api\ImageUploadController;
use App\Http\Controllers\Api\InboxController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\MagicLinkController;
use App\Http\Controllers\Api\MilestoneController;
use App\Http\Controllers\Api\ModelDataController;
use App\Http\Controllers\Api\OptionsController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PlaceholderDefinitionController; // New Import
use App\Http\Controllers\Api\PresentationAIController; // New Import
use App\Http\Controllers\Api\PresentationGeneratorController;
use App\Http\Controllers\Api\ProjectActionController;
use App\Http\Controllers\Api\ProjectDashboard\ProjectDeliverableAction;
use App\Http\Controllers\Api\ProjectNoteController;
use App\Http\Controllers\Api\ProjectReadController;
use App\Http\Controllers\Api\PublicLeadIntakeController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ScheduleApiController;
use App\Http\Controllers\Api\SendEmailController;
use App\Http\Controllers\Api\ShareableResourceController;
use App\Http\Controllers\Api\SubtaskController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskTypeController;
use App\Http\Controllers\Api\UserAttendanceController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\UserWorkspaceController;
use App\Http\Controllers\Api\UserWidgetController;
use App\Http\Controllers\Api\ValueDictionaryController;
use App\Http\Controllers\Api\WireframeController;
use App\Http\Controllers\Api\WorkflowLogController;
use App\Http\Controllers\Api\WorkspaceController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Api\ActivityDataController;
use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\XeroWebhookController;
use App\Http\Controllers\Api\XeroAccountController;
use App\Http\Controllers\Api\XeroPaymentServiceController;
use App\Http\Controllers\Api\XeroReverseSyncController;

Route::post('/telegram/wh', [TelegramWebhookController::class, 'handle']);
Route::post('/telegram/test-telegram', [TelegramWebhookController::class, 'send']);
Route::post('/telegram/test-topic', [TelegramWebhookController::class, 'createTopic']);
Route::post('/telegram/message-thread', [TelegramWebhookController::class, 'sendThreadMessage']);
Route::post('/xero/webhook', [XeroWebhookController::class, 'handle']);

Route::get('/extension/version', function () {
    return response()->json(['version' => config('services.extension.version')]);
});

Route::get('/extension/download', function () {
    return response()->json(['link' => config('services.extension.download_link')]);
});

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

// Client Magic Link Routes (protected by specialized throttling)
Route::middleware(['client.throttle'])->group(function () {
    Route::post('/client-magic-link', [MagicLinkController::class, 'sendClientMagicLink']);
    Route::post('/client-api/check-status', [MagicLinkController::class, 'checkStatus']);
    Route::post('/client-api/verify-pin', [MagicLinkController::class, 'verifyPin']);
    Route::post('/client-api/resend-magic-link', [MagicLinkController::class, 'resendMagicLink']);
});

// Other client endpoints
Route::post('/client-api/verify', [MagicLinkController::class, 'verifyClient']);
Route::post('/client-api/setup-pin', [MagicLinkController::class, 'setupPin']);

Route::middleware('auth.apikey')->group(function () {
    Route::post('/activityData', [ActivityDataController::class, 'store']);
    Route::post('/presence/status', [UserProfileController::class, 'updateOnlineStatus']);
    Route::get('/extension/vault/labels', [\App\Http\Controllers\Api\ExtensionVaultController::class, 'labels']);
    Route::post('/extension/vault/resolve', [\App\Http\Controllers\Api\ExtensionVaultController::class, 'resolve']);

    // External API routes
    Route::get('/activity/projects', [\App\Http\Controllers\Api\ExternalApiController::class, 'getProjects']);
    Route::get('/activity/projects/{project}/tasks', [\App\Http\Controllers\Api\ExternalApiController::class, 'getProjectTasks']);
    Route::get('/activity/tasks', [\App\Http\Controllers\Api\ExternalApiController::class, 'getUserTasks']);
    Route::get('/activity/tasks/activeTask', [\App\Http\Controllers\Api\ExternalApiController::class, 'getActiveTask']);
    Route::post('/activity/tasks/quick', [\App\Http\Controllers\Api\ExternalApiController::class, 'createQuickTask']);
    Route::get('/activity/tasks/{task}', [\App\Http\Controllers\Api\ExternalApiController::class, 'getTaskDetails']);
    Route::post('/activity/tasks/{task}/status', [\App\Http\Controllers\Api\ExternalApiController::class, 'updateTaskStatus']);
    Route::get('/activity/tasks/{task}/notes', [\App\Http\Controllers\Api\ExternalApiController::class, 'getTaskNotes']);
    Route::post('/activity/tasks/{task}/notes', [\App\Http\Controllers\Api\ExternalApiController::class, 'addTaskNote']);
    Route::get('/activity/tasks/{task}/time', [\App\Http\Controllers\Api\ExternalApiController::class, 'getTaskTimeSpent']);
});

Route::get('/playground', [\App\Http\Controllers\TestController::class, 'playGourd']);
Route::post('/playground', [\App\Http\Controllers\TestController::class, 'playGourd']);
Route::post('/test-reverb', [\App\Http\Controllers\TestController::class, 'sendTestNotification']);
Route::post('/test-email-with-config', [\App\Http\Controllers\TestController::class, 'testEmailWithConfig']);

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
// New Public Lead API (API-key protected)
Route::post('/public/lead/{firefly}', [\App\Http\Controllers\Api\PublicLeadApiController::class, 'store']);

// Authenticated API Routes (behind auth:sanctum middleware for internal users)
Route::middleware('auth:sanctum')->group(function () {
    // Financial Routes
    Route::get('admin/financial-pending-counts', [BillController::class, 'pendingCounts'])->middleware('permission:view_project_bills')->name('api.admin.financial-counts');
    Route::get('admin/bills', [BillController::class, 'all'])->middleware('permission:view_project_bills')->name('api.admin.bills.all');
    Route::get('admin/invoices/stats', [InvoiceController::class, 'stats'])->middleware('permission:view_project_invoices')->name('api.admin.invoices.stats');
    Route::get('admin/invoices', [InvoiceController::class, 'all'])->middleware('permission:view_project_invoices')->name('api.admin.invoices.all');
    Route::get('xero/accounts', [XeroAccountController::class, 'index'])->name('api.xero.accounts');
    Route::post('xero/accounts', [XeroAccountController::class, 'storeAccount'])->name('api.xero.accounts.store');
    Route::get('xero/items', [XeroAccountController::class, 'items'])->name('api.xero.items');
    Route::post('xero/items', [XeroAccountController::class, 'storeItem'])->name('api.xero.items.store');
    Route::get('xero/payment-services', [XeroPaymentServiceController::class, 'index'])->name('api.xero.payment-services.index');
    Route::post('xero/payment-services/refresh', [XeroPaymentServiceController::class, 'refresh'])->name('api.xero.payment-services.refresh');
    Route::get('admin/xero/invoices', [XeroReverseSyncController::class, 'listXeroInvoices'])->middleware('permission:create_project_invoices')->name('api.admin.xero.invoices');
    Route::get('admin/xero/invoices/{xero_invoice_id}', [XeroReverseSyncController::class, 'showXeroInvoice'])->middleware('permission:create_project_invoices')->name('api.admin.xero.invoices.show');
    Route::post('admin/xero/invoices/sync', [XeroReverseSyncController::class, 'syncInvoice'])->middleware('permission:create_project_invoices')->name('api.admin.xero.invoices.sync');
    Route::post('admin/xero/contacts/create-client', [XeroReverseSyncController::class, 'createClientFromXeroContact'])->middleware('permission:create_clients');
    Route::get('projects/{project}/services-quick-list', [XeroReverseSyncController::class, 'getProjectServices'])->middleware('permission:view_project_invoices');
    Route::post('projects/{project}/services/quick-add', [XeroReverseSyncController::class, 'quickAddProjectService'])->middleware('permission:create_project_invoices')->name('api.projects.services.quick-add');
    
    Route::get('projects/{project}/bills', [BillController::class, 'index'])->middleware('permission:view_project_bills')->name('api.bills.index');
    Route::post('projects/{project}/bills', [BillController::class, 'store'])->middleware('permission:create_project_bills')->name('api.bills.store');
    Route::put('projects/{project}/bills/{bill}', [BillController::class, 'update'])->middleware('permission:edit_project_bills')->name('api.bills.update');
    Route::post('bills/{bill}/approve', [BillController::class, 'approve'])->middleware('permission:approve_project_bills')->name('api.bills.approve');
    Route::post('bills/{bill}/void', [BillController::class, 'void'])->middleware('permission:void_project_bills')->name('api.bills.void');
    Route::delete('bills/{bill}', [BillController::class, 'destroy'])->middleware('permission:delete_project_bills')->name('api.bills.destroy');
    Route::post('bills/{bill}/restore', [BillController::class, 'restore'])->middleware('permission:restore_project_bills')->name('api.bills.restore');
    Route::post('bills/{bill}/attachments', [BillController::class, 'uploadAttachment'])->middleware('permission:edit_project_bills')->name('api.bills.attachments.store');

    Route::get('projects/{project}/invoices', [InvoiceController::class, 'index'])->middleware('permission:view_project_invoices')->name('api.invoices.index');
    Route::post('projects/{project}/invoices', [InvoiceController::class, 'store'])->middleware('permission:create_project_invoices')->name('api.invoices.store');
    Route::put('projects/{project}/invoices/{invoice}', [InvoiceController::class, 'update'])->middleware('permission:edit_project_invoices')->name('api.invoices.update');
    Route::get('projects/{project}/invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:view_project_invoices')->name('api.invoices.show');
    Route::get('projects/{project}/invoices/{invoice}/notes', [InvoiceController::class, 'notes'])->middleware('permission:view_project_invoices')->name('api.invoices.notes');
    Route::post('projects/{project}/invoices/{invoice}/notes', [InvoiceController::class, 'addNote'])->middleware('permission:view_project_invoices')->name('api.invoices.addNote');
    Route::post('projects/{project}/invoices/{invoice}/approve', [InvoiceController::class, 'approve'])->middleware('permission:approve_project_invoices')->name('api.invoices.approve');
    Route::post('projects/{project}/invoices/{invoice}/reject', [InvoiceController::class, 'reject'])->middleware('permission:approve_project_invoices')->name('api.invoices.reject');
    Route::post('projects/{project}/invoices/{invoice}/comment', [InvoiceController::class, 'comment'])->middleware('permission:view_project_invoices')->name('api.invoices.comment');
    Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->middleware('permission:void_project_invoices')->name('api.invoices.void');

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/me/status', [UserProfileController::class, 'status']);
    Route::get('/me/attendance', [UserAttendanceController::class, 'index']);

    // Centralized options endpoint
    Route::get('options/{key}', [OptionsController::class, 'show']);

    // BugHerd Routes
    Route::get('bugherd/projects', [\App\Http\Controllers\Api\BugHerdController::class, 'index']);

    // Category Sets & Categories
    Route::get('category-sets', [CategorySetController::class, 'index']);
    Route::post('category-sets', [CategorySetController::class, 'store']);
    Route::put('category-sets/{categorySet}', [CategorySetController::class, 'update']);
    Route::delete('category-sets/{categorySet}', [CategorySetController::class, 'destroy']);
    Route::get('category-sets/{categorySet}/categories', [AdminCategoryController::class, 'index']);
    Route::middleware('process.tags')->group(function () {
        Route::post('categories', [AdminCategoryController::class, 'store']);
        Route::put('categories/{category}', [AdminCategoryController::class, 'update']);
        Route::patch('categories/{category}', [AdminCategoryController::class, 'update']);
    });
    Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy']);

    // Available models for bindings
    Route::get('models/available', [ModelDataController::class, 'availableModels']);

    // Shareable Resource Copy endpoint
    Route::post('shareable-resources/{resource}/copy-to-project', [\App\Http\Controllers\Api\ShareableResourceCopyController::class, 'copyToProject']);

    // Test Form Route for BaseFormModal testing
    Route::post('/test-form', [\App\Http\Controllers\Api\TestFormController::class, 'store']);

    // Notifications Routes
    // Other routes...
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/{viewId}/read', [NotificationController::class, 'markAsReadByViewId']);
    Route::delete('/notifications/{notificationId}', [NotificationController::class, 'destroy']);

    // User Workspace (checklist and notes)
    Route::get('user/workspace', [UserWorkspaceController::class, 'workspace']);
    Route::put('user/checklist', [UserWorkspaceController::class, 'updateChecklist']);
    Route::put('user/notes', [UserWorkspaceController::class, 'updateNotes']);

    // Tag Management Routes
    Route::get('/tags/search', [\App\Http\Controllers\TagController::class, 'search']);
    Route::get('/global-search', [\App\Http\Controllers\GlobalSearchController::class, 'search']);

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
    Route::get('/existing-client-enquiries/supporting-data', [ExistingClientEnquiryController::class, 'supportingData']);
    Route::get('/existing-client-enquiries', [ExistingClientEnquiryController::class, 'index']);
    Route::post('/existing-client-enquiries', [ExistingClientEnquiryController::class, 'store']);
    Route::put('/existing-client-enquiries/{enquiryId}', [ExistingClientEnquiryController::class, 'update']);
    Route::delete('/existing-client-enquiries/{enquiryId}', [ExistingClientEnquiryController::class, 'destroy']);
    Route::post('/existing-client-enquiries/{enquiryId}/convert', [ExistingClientEnquiryController::class, 'convert']);
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
    Route::get('clients/{client}/xero-contact-candidates', [ClientController::class, 'xeroContactCandidates']);
    Route::post('clients/{client}/xero-contact-sync', [ClientController::class, 'syncXeroContact']);
    Route::post('clients/{client}/xero-contact-create', [ClientController::class, 'createXeroContact']);

    // Generic project notes endpoints (polymorphic)
    Route::get('/project_notes', [ProjectNoteController::class, 'index']);
    Route::post('/project_notes', [ProjectNoteController::class, 'store']);
    Route::post('/upload-image', [ImageUploadController::class, 'upload']);

    // Project Management Routes (Split into Read and Action)

    // Automation: Workflows & Logs
    Route::get('workflows', [\App\Http\Controllers\Api\WorkflowController::class, 'index']);
    Route::get('workflows/{workflow}', [\App\Http\Controllers\Api\WorkflowController::class, 'show']);
    Route::post('workflows', [\App\Http\Controllers\Api\WorkflowController::class, 'store']);
    Route::put('workflows/{workflow}', [\App\Http\Controllers\Api\WorkflowController::class, 'update']);
    Route::delete('workflows/{workflow}', [\App\Http\Controllers\Api\WorkflowController::class, 'destroy']);
    Route::post('workflows/{workflow}/run', [\App\Http\Controllers\Api\WorkflowController::class, 'run']);
    Route::get('workflows/{workflow}/logs', [WorkflowLogController::class, 'index']);
    // Read Routes
    Route::get('projects', [ProjectReadController::class, 'index']);
    Route::get('projects/with-wireframes', [ProjectReadController::class, 'wireframe']);
    Route::get('projects/{project}', [ProjectReadController::class, 'show']);
    Route::get('projects-simplified', [ProjectReadController::class, 'getProjectsSimplified']);
    Route::get('projects-for-email', [ProjectReadController::class, 'getProjectsForEmailComposer']);
    Route::get('projects-for-invoicing', [ProjectReadController::class, 'getProjectsForInvoicing'])->middleware('permission:create_project_invoices');
    Route::get('projects/{project}/notes', [ProjectReadController::class, 'getNotes']); // Handles general project notes
    Route::get('projects/{project}/standups', [ProjectReadController::class, 'getNotes']); // Standups are also notes, filtered by type
    Route::get('projects/{project}/notes/{note}/replies', [ProjectReadController::class, 'getNoteReplies']);
    Route::get('projects/{project}/tasks', [ProjectReadController::class, 'getTasks']);
    Route::get('/projects/{project}/meetings', [ProjectReadController::class, 'getProjectMeetings']);
    Route::get('/projects/{project}/contexts', [ProjectReadController::class, 'getProjectContexts']);
    Route::get('/user/meetings', [ProjectReadController::class, 'getUserMeetings']);
    Route::get('/user/standups', [ProjectReadController::class, 'getUserStandups']);

    // Project Section Read Routes
    Route::get('projects/{project}/sections/basic', [ProjectReadController::class, 'getBasicInfo']);
    // Project Expendables
    Route::get('projects/{project}/expendables', [\App\Http\Controllers\Api\ProjectExpendableController::class, 'index']);
    Route::get('projects/{project}/sections/clients-users', [ProjectReadController::class, 'getClientsAndUsers']);
    Route::get('projects/{project}/sections/meeting-attendees', [ProjectReadController::class, 'getMeetingAttendees']);
    Route::get('projects/{project}/sections/clients', [ProjectReadController::class, 'getClientsAndUsers']);
    Route::get('projects/{project}/sections/users', [ProjectReadController::class, 'getClientsAndUsers']);
    Route::get('projects/{project}/sections/services-payment', [ProjectReadController::class, 'getServicesAndPayment']);
    Route::get('projects/{project}/expendable-budget', [ProjectReadController::class, 'getExpendableBudget']);
    Route::get('projects/{project}/sections/transactions', [ProjectReadController::class, 'getTransactions']);
    Route::get('projects/{project}/sections/documents', [ProjectReadController::class, 'getDocuments']);
    Route::get('projects/{project}/sections/notes', [ProjectReadController::class, 'getNotes']); // Re-uses getNotes
    Route::get('projects/{project}/users', [ProjectReadController::class, 'getProjectUsers']);
    Route::get('projects/{project}/clients', [ProjectReadController::class, 'getProjectClients']);
    Route::get('projects/{project}/google-chat-members', [ProjectReadController::class, 'getGoogleChatMembers']);
    Route::get('projects/{project}/contract-details', [ProjectReadController::class, 'getContractDetails']);

    // Chat Routes
    Route::get('projects/{project}/chat', [ChatController::class, 'index']);
    Route::post('projects/{project}/chat', [ChatController::class, 'store']);
    Route::post('projects/{project}/chat/attachments', [ChatController::class, 'storeAttachments']);
    Route::post('projects/{project}/chat/drive-documents/create', [ChatController::class, 'createDriveDocument']);
    Route::post('projects/{project}/chat/drive-documents/reference', [ChatController::class, 'referenceDriveFile']);
    Route::get('projects/{project}/chat/drive-documents/browse', [ChatController::class, 'browseDriveFolder']);
    Route::delete('projects/{project}/chat/{chat_message}', [ChatController::class, 'destroy']);
    Route::post('projects/{project}/chat/mark-read', [ChatController::class, 'markRead']);
    Route::get('chat/unread-counts', [ChatController::class, 'unreadCounts']);

    // Telegram Topic Routes
    Route::get('projects/{project}/topics', [\App\Http\Controllers\Api\TelegramTopicController::class, 'index']);
    Route::post('projects/{project}/topics', [\App\Http\Controllers\Api\TelegramTopicController::class, 'store']);

    // Action Routes
    Route::post('projects', [ProjectActionController::class, 'store']);
    Route::put('projects/{project}', [ProjectActionController::class, 'update']);
    Route::put('projects/{project}/update-data', [ProjectActionController::class, 'updateProjectData'])->name('projects.update-data');
    Route::delete('projects/{project}', [ProjectActionController::class, 'destroy']);
    Route::post('projects/{project}/attach-users', [ProjectActionController::class, 'attachUsers'])->name('projects.attach-users');
    Route::post('projects/{project}/detach-users', [ProjectActionController::class, 'detachUsers'])->name('projects.detach-users');
    Route::post('projects/{project}/attach-clients', [ProjectActionController::class, 'attachClients'])->name('projects.attach-clients');
    Route::post('projects/{project}/detach-clients', [ProjectActionController::class, 'detach-clients']);
    Route::post('projects/{project}/attach-google-chat-members', [ProjectActionController::class, 'attachGoogleChatMembers'])->name('projects.attach-google-chat-members');
    Route::post('projects/{project}/detach-google-chat-members', [ProjectActionController::class, 'detachGoogleChatMembers'])->name('projects.detach-google-chat-members');
    Route::post('projects/{project}/transactions', [\App\Http\Controllers\Api\TransactionsController::class, 'addTransactions'])->middleware('process.basic:transaction_type,App\\Models\\TransactionType');
    Route::patch('projects/{project}/transactions/{transaction}/process-payment', [\App\Http\Controllers\Api\TransactionsController::class, 'processPayment']);
    Route::post('projects/{project}/notes', [ProjectActionController::class, 'addNotes']);
    Route::post('projects/{project}/notes/{note}/reply', [ProjectActionController::class, 'replyToNote']);
    Route::post('projects/{project}/document', [ProjectActionController::class, 'uploadDocuments'])->name('singleDocument');
    Route::post('projects/{project}/documents', [ProjectActionController::class, 'uploadDocuments'])->name('multipleDocuments');
    Route::post('projects/{project}/logo', [ProjectActionController::class, 'uploadLogo']);
    Route::post('projects/{project}/standup', [ProjectActionController::class, 'addStandup']);
    Route::post('projects/{project}/meeting-minutes', [ProjectActionController::class, 'addMeetingMinutes']);
    Route::post('/projects/{project}/meetings', [ProjectActionController::class, 'createProjectMeeting']);
    Route::delete('/projects/{project}/meetings/{googleEventId}', [ProjectActionController::class, 'deleteProjectMeeting']);
    Route::patch('projects/{project}/convert-payment-type', [ProjectActionController::class, 'convertPaymentType']); // Moved PATCH route
    Route::patch('projects/{project}/expendable-budget', [ProjectActionController::class, 'updateExpendableBudget']);
    Route::post('projects/{project}/archive', [ProjectActionController::class, 'archive']);
    Route::patch('projects/{project}/assign-leads', [ProjectActionController::class, 'assignLeads'])->middleware('permission:manage_projects');
    Route::post('projects/{project}/generate-telegram-code', 'App\Http\Controllers\Api\ProjectActionController@generateTelegramLinkCode');
    Route::post('users/{user}/generate-telegram-code', 'App\Http\Controllers\Api\UserController@generateTelegramLinkCode');
    Route::post('clients/{client}/generate-telegram-code', 'App\Http\Controllers\Api\ClientController@generateTelegramLinkCode');
    Route::post('projects/{project}/expendables', [\App\Http\Controllers\Api\ProjectExpendableController::class, 'store']);
    Route::put('projects/{project}/expendables/{expendable}', [\App\Http\Controllers\Api\ProjectExpendableController::class, 'update']);
    Route::post('projects/{project}/expendables/{expendable}/accept', [\App\Http\Controllers\Api\ProjectExpendableController::class, 'accept']);
    Route::post('projects/{project}/expendables/{expendable}/reject', [\App\Http\Controllers\Api\ProjectExpendableController::class, 'reject']);
    Route::post('projects/{project}/expendables/{expendable}/shortlist', [\App\Http\Controllers\Api\ProjectExpendableController::class, 'shortlist']);
    Route::delete('projects/{project}/expendables/{expendable}', [\App\Http\Controllers\Api\ProjectExpendableController::class, 'destroy']);

    // Project Public Share Routes
    Route::get('projects/{project}/share', [\App\Http\Controllers\Api\ProjectShareController::class, 'getShareInfo']);
    Route::get('projects/{project}/share/recipients', [\App\Http\Controllers\Api\ProjectShareController::class, 'recipients']);
    Route::get('projects/{project}/share/tracking', [\App\Http\Controllers\Api\ProjectShareController::class, 'tracking']);
    Route::post('projects/{project}/share/token', [\App\Http\Controllers\Api\ProjectShareController::class, 'generateToken']);
    Route::post('projects/{project}/share/regenerate', [\App\Http\Controllers\Api\ProjectShareController::class, 'regenerateToken']);
    Route::post('projects/{project}/share/email', [\App\Http\Controllers\Api\ProjectShareController::class, 'sendEmail'])
        ->middleware('throttle:20,1');

    Route::post('projects/{id}/restore', [ProjectActionController::class, 'restore']);

    // Project Section Update Routes
    Route::put('projects/{project}/sections/basic', [ProjectActionController::class, 'updateBasicInfo'])->middleware(['process.tags']);

    // Workspace API
    Route::get('workspace/projects', [WorkspaceController::class, 'projects']);
    Route::get('workspace/projects/{project}/completed-tasks', [WorkspaceController::class, 'completedTasks']);

    // Daily Work Log API
    Route::get('daily-tasks', [\App\Http\Controllers\Api\DailyTaskController::class, 'index']);
    Route::get('daily-tasks/history', [\App\Http\Controllers\Api\DailyTaskController::class, 'history']);
    Route::post('daily-tasks', [\App\Http\Controllers\Api\DailyTaskController::class, 'store']);
    Route::post('daily-tasks/reorder', [\App\Http\Controllers\Api\DailyTaskController::class, 'reorder']);
    Route::patch('daily-tasks/{dailyTask}', [\App\Http\Controllers\Api\DailyTaskController::class, 'update']);
    Route::post('daily-tasks/{dailyTask}/push-to-tomorrow', [\App\Http\Controllers\Api\DailyTaskController::class, 'pushToTomorrow']);
    Route::delete('daily-tasks/{dailyTask}', [\App\Http\Controllers\Api\DailyTaskController::class, 'destroy']);
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

    // Admin media files listing endpoint
    Route::get('admin/media-files/list', [\App\Http\Controllers\Admin\MediaFileController::class, 'list'])
        ->middleware(['permission:manage_projects'])
        ->name('admin.media-files.list');

    // Admin media files view URL endpoint
    Route::get('admin/media-files/{file}/view-url', [\App\Http\Controllers\Admin\MediaFileController::class, 'viewUrl'])
        ->middleware(['permission:manage_projects'])
        ->name('admin.media-files.view-url');

    // Admin media files bulk delete endpoint
    Route::post('admin/media-files/bulk-delete', [\App\Http\Controllers\Admin\MediaFileController::class, 'bulkDelete'])
        ->middleware(['permission:manage_projects'])
        ->name('admin.media-files.bulk-delete');

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
    Route::patch('conversations/{conversation}/project', [EmailController::class, 'updateConversationProject']);
    Route::patch('emails/{email}/privacy', [EmailController::class, 'togglePrivacy']);
    Route::delete('emails/{email}', [EmailController::class, 'destroy']);

    // Inbox Routes
    Route::get('inbox/new-emails', [InboxController::class, 'newEmails']);
    Route::get('inbox/all-emails', [InboxController::class, 'allEmails']);
    Route::get('inbox/waiting-approval', [InboxController::class, 'waitingApproval']);
    Route::get('inbox/counts', [InboxController::class, 'counts']);
    Route::get('inbox/category-stats', [InboxController::class, 'categoryStats']);

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
    Route::post('users/{user}/generate-api-key', [UserController::class, 'generateApiKey'])->name('users.generate-api-key');
    Route::get('users/{user}/xero-contact-candidates', [UserController::class, 'xeroContactCandidates']);
    Route::post('users/{user}/xero-contact-sync', [UserController::class, 'syncXeroContact']);
    Route::post('users/{user}/xero-contact-create', [UserController::class, 'createXeroContact']);
    Route::get('users/{user}/emails', [UserController::class, 'emails']);
    Route::get('users/{user}/metadata', [UserWidgetController::class, 'getMetadata']);
    Route::post('users/{user}/metadata', [UserWidgetController::class, 'updateMetadata']);
    Route::get('users/{user}/notes', [UserWidgetController::class, 'getNotes']);
    Route::post('users/{user}/notes', [UserWidgetController::class, 'addNote']);
    Route::get('user-metadata-keys', [UserWidgetController::class, 'getKeys']);
    Route::post('user-metadata-keys', [UserWidgetController::class, 'addKey']);
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

    // Email App Management Routes
    Route::apiResource('email-apps', \App\Http\Controllers\Api\EmailAppController::class)->names('api.email-apps');
    Route::get('email-apps/{emailApp}/logs', [\App\Http\Controllers\Api\EmailAppController::class, 'logs'])->name('api.email-apps.logs');

    // Task Management Routes
    // Polymorphic schedule creation for existing items
    Route::post('schedules', [ScheduleApiController::class, 'store'])->name('api.schedules.store');
    Route::get('task-statistics', [TaskController::class, 'getTaskStatistics']);
    Route::get('assigned-tasks', [TaskController::class, 'getAssignedTasks']);
    Route::get('projects/{projectId}/due-and-overdue-tasks', [TaskController::class, 'getProjectDueAndOverdueTasks']);

    // Activity Log Routes
    Route::get('activities', [ActivityController::class, 'index']);

    // Productivity Report API
    Route::get('productivity/report', [\App\Http\Controllers\Api\ProductivityReportController::class, 'index'])
        ->middleware('permission:manage_projects');

    Route::get('productivity/project-report', [\App\Http\Controllers\Api\ProjectProductivityReportController::class, 'index'])
        ->middleware('permission:manage_projects');

    Route::get('productivity/projects/{project}/messages', [\App\Http\Controllers\Api\ProjectProductivityReportController::class, 'messages'])
        ->middleware('permission:manage_projects')
        ->name('productivity.projects.messages');

    // User Productivity Snapshots API
    Route::get('productivity/snapshots', [\App\Http\Controllers\Api\UserProductivityController::class, 'index'])
        ->middleware('permission:manage_projects');
    Route::post('productivity/snapshots', [\App\Http\Controllers\Api\UserProductivityController::class, 'store'])
        ->middleware('permission:manage_projects');
    Route::get('productivity/snapshots/{userProductivity}', [\App\Http\Controllers\Api\UserProductivityController::class, 'show'])
        ->middleware('permission:manage_projects');
    Route::delete('productivity/snapshots/{userProductivity}', [\App\Http\Controllers\Api\UserProductivityController::class, 'destroy'])
        ->middleware('permission:manage_projects');
    Route::post('productivity/snapshots/{userProductivity}/feedback', [\App\Http\Controllers\Api\UserProductivityController::class, 'updateFeedback'])
        ->middleware('permission:manage_projects');

    // Activity Report API
    Route::get('activity-report', [\App\Http\Controllers\Api\ActivityReportController::class, 'index'])
        ->middleware('permission:manage_projects');
    Route::patch('activities/{id}/category', [ActivityDataController::class, 'updateCategory']);

    Route::post('tasks/{task}/productivity-meta', [\App\Http\Controllers\Api\ProductivityReportController::class, 'updateTaskMeta'])
        ->middleware('permission:manage_projects');
    Route::post('tasks/manual-effort', [\App\Http\Controllers\Api\ProductivityReportController::class, 'storeManualTask'])
        ->middleware('permission:manage_projects');


    // Apply ProcessTags middleware to store and update methods
    Route::post('tasks/bulk', [TaskController::class, 'bulk']);
    Route::post('tasks/bulk-workspace', [TaskController::class, 'bulkWorkspace']);
    Route::post('tasks/quick', [TaskController::class, 'quickStore']);
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
    Route::post('milestones/{milestone}/update-due-date', [MilestoneController::class, 'updateDueDate']);
    Route::get('milestones/{milestone}/reasons', [MilestoneController::class, 'reasons']);

    // Project-specific Task Management Routes
    Route::get('projects/{project}/milestones', [MilestoneController::class, 'index']);
    Route::get('projects/{project}/milestones-with-expendables', [MilestoneController::class, 'milestonesWithExpendables']);
    Route::post('projects/{project}/milestones', [MilestoneController::class, 'store']);

    // Task Type Routes
    Route::apiResource('task-types', TaskTypeController::class);

    // CRM Services Routes
    Route::get('crm-services', [\App\Http\Controllers\Api\CrmServiceController::class, 'index']);
    Route::post('crm-services', [\App\Http\Controllers\Api\CrmServiceController::class, 'store']);
    Route::put('crm-services/bulk', [\App\Http\Controllers\Api\CrmServiceController::class, 'bulkUpdate']);
    Route::put('crm-services/{crmService}', [\App\Http\Controllers\Api\CrmServiceController::class, 'update']);
    Route::post('crm-services/{crmService}/merge', [\App\Http\Controllers\Api\CrmServiceController::class, 'merge']);

    // Transaction Types Routes (index, store, update, search)
    Route::get('transaction-types', [\App\Http\Controllers\Api\TransactionTypeController::class, 'index']);
    Route::post('transaction-types', [\App\Http\Controllers\Api\TransactionTypeController::class, 'store']);
    Route::put('transaction-types/{transactionType}', [\App\Http\Controllers\Api\TransactionTypeController::class, 'update']);
    Route::get('transaction-types/search', [\App\Http\Controllers\Api\TransactionTypeController::class, 'search']);

    // Bill Management Routes
    Route::get('projects/{project}/bills', [\App\Http\Controllers\Api\BillController::class, 'index'])->middleware('permission:view_project_bills');
    Route::post('projects/{project}/bills', [\App\Http\Controllers\Api\BillController::class, 'store'])->middleware('permission:create_project_bills');
    Route::put('projects/{project}/bills/{bill}', [\App\Http\Controllers\Api\BillController::class, 'update'])->middleware('permission:edit_project_bills')->name('api.bills.update');
    Route::post('projects/{project}/bills/{bill}/approve', [\App\Http\Controllers\Api\BillController::class, 'approve'])->middleware('permission:approve_project_bills');
    Route::post('projects/{project}/bills/{bill}/void', [\App\Http\Controllers\Api\BillController::class, 'void'])->middleware('permission:void_project_bills');

    // Invoice Management Routes (Basic)
    Route::get('projects/{project}/invoices', [\App\Http\Controllers\Api\InvoiceController::class, 'index']);
    Route::post('projects/{project}/invoices', [\App\Http\Controllers\Api\InvoiceController::class, 'store']);
    Route::put('projects/{project}/invoices/{invoice}', [\App\Http\Controllers\Api\InvoiceController::class, 'update']);
    Route::get('projects/{project}/invoices/{invoice}', [\App\Http\Controllers\Api\InvoiceController::class, 'show']);
    Route::get('projects/{project}/invoices/{invoice}/notes', [\App\Http\Controllers\Api\InvoiceController::class, 'notes']);
    Route::post('projects/{project}/invoices/{invoice}/notes', [\App\Http\Controllers\Api\InvoiceController::class, 'addNote']);
    Route::post('projects/{project}/invoices/{invoice}/approve', [\App\Http\Controllers\Api\InvoiceController::class, 'approve']);
    Route::post('projects/{project}/invoices/{invoice}/reject', [\App\Http\Controllers\Api\InvoiceController::class, 'reject']);
    Route::post('projects/{project}/invoices/{invoice}/comment', [\App\Http\Controllers\Api\InvoiceController::class, 'comment']);

    // Availability Management Routes
    Route::get('availabilities/reason-options', [AvailabilityController::class, 'reasonOptions']);
    Route::apiResource('availabilities', AvailabilityController::class);
    Route::post('availabilities/batch', [AvailabilityController::class, 'batch']);
    Route::get('weekly-availabilities', [AvailabilityController::class, 'getWeeklyAvailabilities']);
    Route::get('productivity/yesterday-report', [\App\Http\Controllers\Api\UserProductivityController::class, 'getYesterdayReport']);
    Route::post('productivity/yesterday-feedback', [\App\Http\Controllers\Api\UserProductivityController::class, 'saveYesterdayFeedback']);
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

    // SEO Report
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
    Route::get('/automation/schema', [AutomationSchemaController::class, 'getSchema']);

    // Value Dictionaries (Allowed Values/Enums)
    Route::get('value-dictionaries', [ValueDictionaryController::class, 'index'])->middleware('permission:manage_placeholder_definitions');
    Route::get('value-dictionaries/{model}/{field}', [ValueDictionaryController::class, 'show'])->middleware('permission:manage_placeholder_definitions');

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

    // Standup Analytics Routes
    Route::prefix('standups/analytics')->group(function () {
        Route::get('/filters', [App\Http\Controllers\Api\StandupAnalyticsController::class, 'getFilters']);
        Route::get('/matrix', [App\Http\Controllers\Api\StandupAnalyticsController::class, 'getComplianceMatrix']);
        Route::get('/feed', [App\Http\Controllers\Api\StandupAnalyticsController::class, 'getFeed']);
        Route::get('/stats', [App\Http\Controllers\Api\StandupAnalyticsController::class, 'getStats']);
    });

    Route::get('clients/{client}/vault', [\App\Http\Controllers\Admin\VaultController::class, 'index']);
    Route::get('projects/{project}/vault-credentials', [\App\Http\Controllers\Admin\VaultController::class, 'indexByProject']);
    Route::post('projects/{project}/vault-credentials', [\App\Http\Controllers\Admin\VaultController::class, 'storeForProject']);
    Route::post('vault/unlock', [\App\Http\Controllers\Admin\VaultController::class, 'unlock']);
    Route::put('vault/{credential}', [\App\Http\Controllers\Admin\VaultController::class, 'update']);
    Route::get('vault/{credential}/logs', [\App\Http\Controllers\Admin\VaultController::class, 'logs']);
    Route::get('vault/{credential}/shared-users', [\App\Http\Controllers\Admin\VaultController::class, 'sharedUsers']);
    Route::post('vault/{credential}/share', [\App\Http\Controllers\Admin\VaultController::class, 'share']);
    Route::delete('vault/{credential}/share/{user}', [\App\Http\Controllers\Admin\VaultController::class, 'revoke']);
    Route::delete('vault/{credential}', [\App\Http\Controllers\Admin\VaultController::class, 'destroy']);

});

// === Client-Specific API Routes (Protected by Magic Link Token) ===
// These routes will be used by the Vue client dashboard, authenticated via magic link.
// Client Dashboard API Routes (protected by magiclink middleware)
Route::prefix('client-api')
    ->middleware(['auth.magiclink'])
    ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class])
    ->group(function () {

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
    Route::post('switch-project', [MagicLinkController::class, 'switchProject']);
    Route::get('me/status', [ProjectClientReader::class, 'getClientStatus']);
    Route::post('me/generate-telegram-code', [ProjectClientReader::class, 'generateTelegramCode']);
    Route::post('vault', [\App\Http\Controllers\Client\VaultController::class, 'store']);
    Route::get('vault', [\App\Http\Controllers\Client\VaultController::class, 'index']);
    Route::delete('vault/{id}', [\App\Http\Controllers\Client\VaultController::class, 'destroy']);
    Route::get('vault/{id}/logs', [\App\Http\Controllers\Client\VaultController::class, 'logs']);

});

// === Native App API Routes ===
// Dedicated API group for the standalone desktop application
Route::prefix('native-app')->middleware(['auth:sanctum'])->group(function () {
    // Projects
    Route::get('projects', [\App\Http\Controllers\Api\ProjectReadController::class, 'getProjectsSimplified']);
    
    // Topics
    Route::get('projects/{project}/topics', [\App\Http\Controllers\Api\TelegramTopicController::class, 'index']);
    Route::post('projects/{project}/topics', [\App\Http\Controllers\Api\TelegramTopicController::class, 'store']);
    
    // Chat & Messages
    Route::get('projects/{project}/chat', [\App\Http\Controllers\Api\ChatController::class, 'indexNative']);
    Route::post('projects/{project}/chat', [\App\Http\Controllers\Api\ChatController::class, 'store']);
    Route::post('projects/{project}/chat/attachments', [\App\Http\Controllers\Api\ChatController::class, 'storeAttachments']);
    Route::post('projects/{project}/chat/drive-documents/create', [\App\Http\Controllers\Api\ChatController::class, 'createDriveDocument']);
    Route::post('projects/{project}/chat/drive-documents/reference', [\App\Http\Controllers\Api\ChatController::class, 'referenceDriveFile']);
    Route::get('projects/{project}/chat/drive-documents/browse', [\App\Http\Controllers\Api\ChatController::class, 'browseDriveFolder']);
    Route::delete('projects/{project}/chat/{chat_message}', [\App\Http\Controllers\Api\ChatController::class, 'destroy']);
    Route::post('projects/{project}/chat/mark-read', [\App\Http\Controllers\Api\ChatController::class, 'markRead']);
    Route::get('chat/unread-counts', [\App\Http\Controllers\Api\ChatController::class, 'unreadCounts']);

    // Users
    Route::get('users', [\App\Http\Controllers\Api\UserController::class, 'indexSimplified']);
    Route::get('projects/{project}/users', [\App\Http\Controllers\Api\UserController::class, 'projectUsersSimplified']);
});

// === External API Routes (Protected by External Magic Link Token) ===
Route::prefix('external')->middleware(['auth.magiclink.external'])->group(function () {
    Route::post('/email/send', [\App\Http\Controllers\Api\External\ExternalEmailController::class, 'send']);
    Route::post('/payment/create-session', [\App\Http\Controllers\Api\External\ExternalPaymentController::class, 'createSession']);
    Route::post('/payment/create-price', [\App\Http\Controllers\Api\External\ExternalPaymentController::class, 'createPrice']);
    Route::post('/payment/update-configuration', [\App\Http\Controllers\Api\External\ExternalPaymentController::class, 'updateConfiguration']);
    Route::get('/payment/status/{activityId}', [\App\Http\Controllers\Api\External\ExternalPaymentController::class, 'getStatus']);
    Route::get('/payment/subscriptions/{appId}', [\App\Http\Controllers\Api\External\ExternalPaymentController::class, 'getSubscriptions']);
    Route::post('/payment/cancel-subscription', [\App\Http\Controllers\Api\External\ExternalPaymentController::class, 'cancelSubscription']);
    Route::get('/activities/{appId}', [\App\Http\Controllers\Api\External\ExternalPaymentController::class, 'getActivities']);
});

// Public webhook route (must exclude from CSRF if using web middleware, but it's in api.php)
Route::post('/external/stripe/webhook/{app_id}', [\App\Http\Controllers\Api\External\StripeWebhookController::class, 'handle']);

