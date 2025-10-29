# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

Project overview
- Stack: Laravel 12 (PHP 8.2), Inertia + Vue 3 + Vite, Tailwind, PHPUnit. Queue: database by default. Real-time via Laravel Reverb/Echo. Auth via Sanctum. Activity logging via spatie/laravel-activitylog.
- Purpose: Internal operations app with email approvals, projects/tasks, client dashboard, workflows engine, scheduling, notifications, and Google integrations (Gmail/Drive/Calendar/Chat).

Common commands
- Install deps
  - PHP: composer install
  - JS: npm install
- Env setup
  - cp .env.example .env
  - php artisan key:generate
  - php artisan migrate
- Start full dev stack (Laravel server, queue listener, logs, Vite)
  - composer run dev
- Frontend/Vite only
  - npm run dev
- Build assets
  - npm run build
- Tests
  - All: composer test
  - Single file: php artisan test tests/Unit/Services/StepHandlers/ConditionStepHandlerTest.php
  - Filter by class/method: php artisan test --filter ConditionStepHandlerTest
- PHP lint/format (Laravel Pint)
  - ./vendor/bin/pint -v
- Database
  - Migrate: php artisan migrate
  - Seed: php artisan db:seed
- Queue and logs
  - Queue worker (if not using composer run dev): php artisan queue:work
  - Live application logs: php artisan pail --timeout=0
- Scheduler
  - Run once: php artisan app:run-scheduler
  - Cron (server): * * * * * php /path/to/artisan app:run-scheduler --quiet

Architecture and structure
Backend (Laravel)
- Routing
  - Inertia-powered pages and web flows in routes/web.php. API endpoints under routes/api.php and namespaced controllers in app/Http/Controllers/Api.
- Controllers and middleware
  - Web controllers in app/Http/Controllers and API controllers under app/Http/Controllers/Api. Middleware groups/aliases in app/Http/Kernel.php include permission checks (permission, permissionInAnyProject) and standard web/api stacks.
- Domain models and policies
  - Rich domain in app/Models (Projects, Tasks, Emails, Workflows, Schedules, etc.). Authorization gates/policies registered in app/Providers/AppServiceProvider.php with a Gate::before super-admin override. Resource policies live in app/Policies.
- Events, listeners, observers, notifications
  - Domain events in app/Events and listeners in app/Listeners. Notably, WorkflowTriggerEvent + WorkflowTriggerListener dispatch workflow runs from domain triggers.
  - Model observers (EmailObserver, TransactionObserver, KudoObserver) are registered in AppServiceProvider.
  - Notifications for user- and email-related events in app/Notifications and mail templates under resources/views/emails.
- Jobs and queue
  - Background jobs in app/Jobs (e.g., RunWorkflowJob, GenerateAiContentJob, email processing). Queue connection defaults to database (see .env.example). composer run dev starts a queue listener for local dev.
- Workflows engine (key subsystem)
  - Core service: app/Services/WorkflowEngineService.php orchestrates Workflow and WorkflowStep execution, persisting ExecutionLog, and supporting delayed steps and async continuation via RunWorkflowJob.
  - Step handlers implement app/Services/StepHandlers/StepHandlerContract.php and are registered in WorkflowEngineService for types like AI_PROMPT, CONDITION, ACTION_* (create/update/sync/send email/process email), QUERY_DATA/FETCH_RECORDS, FOR_EACH, TRANSFORM_CONTENT.
  - Delays: Steps with delay_minutes schedule a deferred resume via RunWorkflowJob. Engine supports executeFromStepId for resumption and determines next top-level step ordering.
  - Triggers: WorkflowTriggerEvent is emitted by domain actions; WorkflowTriggerListener finds active workflows by trigger_event and dispatches jobs with enriched context.
- Scheduling module
  - Polymorphic scheduling supports attaching schedules to multiple models (Tasks, Workflows) and queue-based execution of due items. Single entry command app:run-scheduler is intended to run every minute; the Console kernel also schedules it (see app/Console/Kernel.php). README documents usage and UI entry points.
- Services and integrations
  - Extensive services in app/Services for Google APIs (Gmail, Drive, Calendar, Chat), email processing/analysis, bonus/points, currency conversion, ledgering, and presentation generation.

Frontend (Vue 3 + Inertia + Vite)
- Entry: resources/js/app.js initializes Inertia, Pinia, Ziggy, custom directives, and subscribes to private notification channels via Echo for push notifications.
- Pages and components: Inertia pages in resources/js/Pages/** grouped by feature (Admin, Emails, Projects, Presentations, Automation, etc.). Shared UI in resources/js/Components/**. Stores and utilities in resources/js/Stores and resources/js/Utils.
- Vite config: vite.config.js wires laravel-vite-plugin and @vitejs/plugin-vue with alias '@' => '/resources/js'.

Testing
- PHPUnit configuration in phpunit.xml with in-memory sqlite (DB_DATABASE=:memory:), queue sync, and fast bcrypt. Feature and Unit tests under tests/Feature and tests/Unit.

Notes for agents
- Prefer composer run dev during development to run server, queue listener, live logs, and Vite concurrently.
- When adding new workflow step types, implement StepHandlerContract and register in WorkflowEngineService. Consider delayed execution semantics and execution logging.
- For scheduled workflows with empty context, the engine enforces the first non-trigger step to be FETCH_RECORDS (see WorkflowEngineService schedule-run guard).
