<?php

return [
    // Toggle the global model event listener
    'global_model_events' => [
        'enabled' => true,
        // Which Eloquent verbs to listen for. Supported: created, updated, saved
        'verbs' => ['created', 'updated'],
    ],

    'models' => [
        // If empty, all models are allowed (except those in deny).
        // Example: ['Lead', 'Task']
        'allow' => [],
        // Models that will never trigger automations
        'deny' => ['ExecutionLog'],
    ],
];
