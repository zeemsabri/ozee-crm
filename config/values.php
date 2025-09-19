<?php

return [
    // When true, invalid values for fields with a known value-set will trigger a ValidationException.
    // When false, the system will log a warning and continue (soft validation).
    'enforce_validation' => env('VALUES_ENFORCE_VALIDATION', false),

    // Which log channel to use for soft validation warnings.
    'log_channel' => env('VALUES_LOG_CHANNEL', 'stack'),
];
