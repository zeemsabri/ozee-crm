Prompt for PhpStorm AI (Junie)
ROLE: You are a senior Laravel developer tasked with building the backend for a new "AI Automation Engine."

CONTEXT: We are building a system to replace hardcoded AI logic and scattered text files with a centralized, database-driven solution. The ultimate goal is to create a visual "AI Automation Studio" where non-technical users can build, manage, and monitor complex workflows that chain together both AI-powered actions (like generating emails) and internal CRM actions (like sending notifications or updating fields).

YOUR TASK: Your current task is to create the foundational backend structure. This includes the database migrations, Eloquent models with all relationships defined, and the basic CRUD API controllers and routes. You are to build the "skeleton" of the application. Do not implement the complex "brain" logic (the WorkflowEngineService or AIGenerationService) at this stage. Focus only on the database and API layer.

INSTRUCTIONS:

Please generate the following files based on the detailed specifications below.

Step 1: Create the Database Migrations
Generate four migration files. Ensure all foreign key constraints are correctly defined with cascading deletes where appropriate to maintain data integrity.

create_workflows_table:

id (primary key)

name (string, not nullable)

description (text, nullable)

trigger_event (string, not nullable, e.g., "lead.created")

is_active (boolean, default true)

timestamps

create_prompts_table:

id (primary key)

name (string, not nullable)

category (string, nullable, for organization)

version (integer, not nullable, default 1)

system_prompt_text (text, not nullable)

model_name (string, default 'gemini-2.5-flash-preview-05-20')

generation_config (json, nullable)

template_variables (json, nullable, to document expected variables)

status (string, default 'active', e.g., 'draft', 'active', 'archived')

timestamps

Constraint: Add a unique constraint on the combination of name and version.

create_workflow_steps_table:

id (primary key)

workflow_id (foreign key to workflows table, cascade on delete)

step_order (integer, not nullable, for sequencing)

name (string, not nullable)

step_type (string, not nullable, default 'AI_PROMPT', e.g., 'UPDATE_CRM_FIELD')

prompt_id (foreign key to prompts table, nullable)

step_config (json, nullable, for non-AI step settings)

condition_rules (json, nullable, for branching logic)

delay_minutes (integer, default 0)

create_execution_logs_table:

id (bigIncrements, primary key)

workflow_id (foreign key to workflows table)

step_id (foreign key to workflow_steps table)

triggering_object_id (string, nullable)

parent_execution_log_id (foreign key to its own table, nullable, for chaining)

status (string, not nullable, e.g., 'SUCCESS', 'FAILURE')

input_context (json, nullable)

raw_output (json, nullable)

parsed_output (json, nullable)

error_message (text, nullable)

duration_ms (integer, nullable)

token_usage (integer, nullable)

cost (decimal, 10, 6, nullable)

executed_at (timestamp, default current)

Step 2: Create the Eloquent Models
Generate four Eloquent models. For each model, define the $fillable array for mass assignment, the $casts for JSON and boolean fields, and all necessary relationship methods.

App\Models\Workflow.php

$fillable: ['name', 'description', 'trigger_event', 'is_active']

$casts: ['is_active' => 'boolean']

Relationships:

steps(): hasMany(WorkflowStep::class) -> ordered by step_order.

logs(): hasMany(ExecutionLog::class)

App\Models\Prompt.php

$fillable: ['name', 'category', 'version', 'system_prompt_text', 'model_name', 'generation_config', 'template_variables', 'status']

$casts: ['generation_config' => 'array', 'template_variables' => 'array']

Relationships:

steps(): hasMany(WorkflowStep::class)

App\Models\WorkflowStep.php

$fillable: ['workflow_id', 'step_order', 'name', 'step_type', 'prompt_id', 'step_config', 'condition_rules', 'delay_minutes']

$casts: ['step_config' => 'array', 'condition_rules' => 'array']

Relationships:

workflow(): belongsTo(Workflow::class)

prompt(): belongsTo(Prompt::class)

App\Models\ExecutionLog.php

$fillable: ['workflow_id', 'step_id', ... all other fields except timestamps]

$casts: ['input_context' => 'array', 'raw_output' => 'array', 'parsed_output' => 'array']

Relationships:

workflow(): belongsTo(Workflow::class)

step(): belongsTo(WorkflowStep::class)

parentLog(): belongsTo(ExecutionLog::class, 'parent_execution_log_id')

childLogs(): hasMany(ExecutionLog::class, 'parent_execution_log_id')

Step 3: Create the API Controllers and Routes
Generate API resource controllers for the main models. The methods should contain basic validation and CRUD logic. Do not implement any business logic beyond creating, reading, updating, or deleting the records.

Controllers:

App\Http\Controllers\Api\WorkflowController

App\Http\Controllers\Api\PromptController

App\Http\Controllers\Api\WorkflowStepController

Implement the standard index, store, show, update, and destroy methods for each. Use basic request validation in the store and update methods.

API Routes (routes/api.php):

Use Route::apiResource() to create the RESTful endpoints for all four controllers.

Group these routes under a relevant middleware group, such as auth:sanctum.
