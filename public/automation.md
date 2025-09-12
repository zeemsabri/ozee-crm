Guided Instructions for Junie to Create the Backend
This guide provides a comprehensive, step-by-step plan for implementing the entire backend of the CRM automation system. The instructions are organized into logical phases, from the foundational models to the core execution engine.

Phase 1: Models and Relationships (The Foundation)
Goal: Create the Eloquent models and define their relationships based on the provided database schema.

Instructions for Junie:

Create all Models: Create the four main Eloquent models based on the database schema: Workflow, WorkflowStep, Prompt, and ExecutionLog. Ensure each model is correctly mapped to its corresponding table (e.g., ai_workflows).

Define Primary Keys and Timestamps: For each model, ensure the primary key and timestamp columns are correctly defined.

Cast JSON Attributes: Use the $casts property in each model to automatically handle the JSON columns. This is a critical step for a clean implementation.

For WorkflowStep, cast step_config and condition_rules to array.

For Prompt, cast generation_config and template_variables to array.

For ExecutionLog, cast input_context, raw_output, and parsed_output to array.

Create Relationships: Define the following Eloquent relationships:

In the Workflow model, define a hasMany relationship to WorkflowStep.

In the WorkflowStep model, define a belongsTo relationship to Workflow and a belongsTo relationship to Prompt.

In the ExecutionLog model, define a belongsTo relationship to Workflow, WorkflowStep, Prompt, and a self-referencing parent relationship to ExecutionLog.

Phase 2: API Controllers (The Entry Points)
Goal: Build the RESTful API endpoints for the frontend.

Instructions for Junie:

Create Resource Controllers: Create four resource controllers: WorkflowController, WorkflowStepController, PromptController, and SchemaController.

Implement WorkflowController:

index(): Return a paginated list of all workflows.

show(Workflow $workflow): Return a single workflow, including all its steps. Use $workflow->load('steps').

store(Request $request): Create a new workflow.

update(Request $request, Workflow $workflow): Update an existing workflow.

destroy(Workflow $workflow): Delete a workflow.

Implement WorkflowStepController:

store(Request $request): Create a new workflow step.

update(Request $request, WorkflowStep $step): Update an existing step.

destroy(WorkflowStep $step): Delete a step.

Implement PromptController:

index(): Return a paginated list of all prompts.

store(Request $request): Create a new prompt.

update(Request $request, Prompt $prompt): Update an existing prompt.

Implement SchemaController:

index(): This method should return a data dictionary of your CRM's core models and their columns. This data powers the dropdowns in the frontend.

Phase 3: The Workflow Engine (The Heart)
Goal: Implement the central business logic for running the automations.

Instructions for Junie:

Create the StepHandlerContract Interface: In app/Services/StepHandlers, create an interface named StepHandlerContract.php with a single method: handle(array $context, WorkflowStep $step).

Create the WorkflowEngineService: As you have already started this file, complete it by ensuring the execute() method iterates over the sorted steps and uses the $handlers map to delegate to the correct handler class. It should log execution details and create an ExecutionLog entry for any failures.

Create StepHandler Classes: In app/Services/StepHandlers, create the following classes, ensuring each one implements the StepHandlerContract interface. These classes should be responsible for their specific logic and nothing more.

AiPromptStepHandler: Handles the AI prompt steps. It will use the prompt_id to get the correct prompt and send a request to a dedicated AI service.

ConditionStepHandler: Evaluates the condition_rules and determines which branch to follow. It is responsible for recursively calling the WorkflowEngineService for the nested steps.

CreateRecordStepHandler: Reads the step_config to create a new record in the specified target model.

UpdateRecordStepHandler: Reads the step_config to update an existing record based on the record_id.

SendEmailStepHandler: Reads the step_config to send an email using your internal EmailService.

Phase 4: AI and Event Services
Goal: Implement the services that the WorkflowEngineService will depend on.

Instructions for Junie:

Create an AIGenerationService: This class will act as a wrapper for the AI API. Its primary method, generate(Prompt $prompt, array $variables), will be responsible for:

Templating the system_prompt_text by replacing placeholders with data from $variables.

Making the actual API call to the Gemini API using the payload you have discussed.

Handling the raw response, logging token usage and cost, and returning a parsed output.

Create a WorkflowTriggerListener: This listener will be responsible for starting the workflow. It will listen for events like LeadWasCreated and check the workflows table to see if any are triggered by lead.created. If a match is found, it will dispatch a new job to the queue.

Create a RunWorkflowJob: This is the queued job. It will have a constructor that accepts a Workflow instance and a $context array. The handle() method of this job will simply call $this->workflowEngineService->execute($this->workflow, $this->context).
