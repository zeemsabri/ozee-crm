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
        // Initial adoption: Task.status via PHP enum, mirroring Task model constants.
        'Task' => [
            'status' => [
                'source' => 'php_enum',
                'enum' => \App\Enums\TaskStatus::class,
            ],
        ],
        'Milestone' => [
            'status' => [
                'source' => 'php_enum',
                'enum' => \App\Enums\MilestoneStatus::class,
            ],
        ],
        'Project' => [
            'status' => [
                'source' => 'php_enum',
                'enum' => \App\Enums\ProjectStatus::class,
            ],
        ],
        'Email' => [
            'status' => [
                'source' => 'php_enum',
                'enum' => \App\Enums\EmailStatus::class,
            ],
            'type' => [
                'source' => 'php_enum',
                'enum' => \App\Enums\EmailType::class,
            ],
        ],
        'ProjectExpendable' => [
                'status' => [
                    'source' => 'php_enum',
                    'enum' => \App\Enums\ProjectExpendableStatus::class,
                ],
            ],
            'BonusTransaction' => [
                'status' => [
                    'source' => 'php_enum',
                    'enum' => \App\Enums\BonusTransactionStatus::class,
                ],
            ],
            'Lead' => [
                'status' => [
                    'source' => 'php_enum',
                    'enum' => \App\Enums\LeadStatus::class,
                ],
            ],
    ],

    // Cache TTL in seconds for the aggregated dictionary
    'cache_ttl' => 300,

    // Validation behavior
    'enforce_validation' => false, // when true, invalid values cause ValidationException
    'log_channel' => 'stack', // logging channel for invalid value warnings
];
