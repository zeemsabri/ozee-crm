<?php

return [
    // Toggle the global model event listener
    'global_model_events' => [
        'enabled' => false,
        // Which Eloquent verbs to listen for. Supported: created, updated, saved, deleted
        'verbs' => ['created', 'updated'],
    ],

    // When true, triggers execute immediately in-process without relying on a queue worker.
    // Set AUTMATION_RUN_SYNC=false (or this value false) to use queued jobs instead.
    'run_synchronously' => env('AUTOMATION_RUN_SYNC', false),

    'models' => [
        // If empty, all models are allowed (except those in deny).
        // Example: ['Lead', 'Task']
        'allow' => ['Task', 'Project', 'Email', 'Campaign', 'Lead'],
        // Models that will never trigger automations
        'deny' => ['ExecutionLog'],
    ],
];
