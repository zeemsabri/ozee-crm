<?php

return [
    // Comma-separated keys via env, or provide an array directly here
    'keys' => collect(explode(',', (string) env('PUBLIC_API_KEYS', '')))
        ->map(fn ($v) => is_string($v) ? trim($v) : $v)
        ->filter(fn ($v) => is_string($v) && $v !== '')
        ->values()
        ->all(),
];
