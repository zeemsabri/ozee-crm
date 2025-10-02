<?php

return [
    // Centralized options for various front-end dropdowns

    // Shareable Resource types
    'shareable_resource_types' => [
        ['value' => 'website', 'label' => 'Website'],
        ['value' => 'youtube', 'label' => 'YouTube Video'],
        ['value' => 'document', 'label' => 'Document'],
        ['value' => 'google_doc', 'label' => 'Google Document', 'allow' => ['copy']],
        ['value' => 'image', 'label' => 'Image'],
        ['value' => 'other', 'label' => 'Other'],
    ],
];
