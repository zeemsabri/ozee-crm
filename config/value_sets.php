<?php

return [
    // Hints for the ValueDictionaryRegistry to discover allowed values for fields.
    // You can fill this out incrementally. The registry also attempts limited auto-detection
    // from PHP enum casts on model attributes.
    //
    // Example structure:
    // 'models' => [
    //     'Task' => [
    //         'status' => [
    //             'source' => 'php_enum', // php_enum | model_const | config | db
    //             'enum' => \App\Enums\TaskStatus::class,
    //         ],
    //         'priority' => [
    //             'source' => 'config',
    //             'path' => 'enums.task.priority', // returns [value=>label] or [values]
    //         ],
    //     ],
    //     'Milestone' => [
    //         'status' => [
    //             'source' => 'db',
    //             'table' => 'milestone_statuses',
    //             'value_column' => 'code',
    //             'label_column' => 'name',
    //             'active_column' => 'is_active', // optional filter
    //         ],
    //     ],
    // ],

    'models' => [
        // Start empty; populate per model/field as you adopt enums/value sets.
    ],

    // Cache TTL in seconds for the aggregated dictionary
    'cache_ttl' => 300,
];
