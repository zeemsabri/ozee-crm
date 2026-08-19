<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Redesigned inbox (beta)
    |--------------------------------------------------------------------------
    |
    | The React inbox lives at /inbox/beta alongside the original Vue page at
    | /inbox, which stays the default. Setting this to false unregisters the beta
    | routes AND stops the Vue page rendering its "Try the new inbox" link, so the
    | link and its destination disappear together and can never point at a 404.
    |
    | Routes are registered from this value, so run `php artisan route:clear`
    | after changing it — a cached route file has the old choice baked in.
    |
    */

    'beta' => (bool) env('INBOX_BETA', true),

    /*
    |--------------------------------------------------------------------------
    | Reply rule
    |--------------------------------------------------------------------------
    |
    | How long the team has to answer a client email before the thread counts as
    | breaching. Drives the "Needs reply" view, the countdown pills, the breach
    | banner and the "closest to breaching first" sort.
    |
    | A thread is answered when its newest outbound email is newer than its newest
    | inbound one. Both are derived per query from the emails table — there is no
    | denormalised column to drift out of sync.
    |
    */

    'sla_minutes' => (int) env('INBOX_SLA_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Business hours
    |--------------------------------------------------------------------------
    |
    | The clock is wall-clock, not business-hours-aware — a mail arriving at 11pm
    | is "overdue" by morning. This flag exists so that assumption is visible in
    | config rather than buried in a service; honouring it is not implemented yet.
    |
    */

    'timezone' => env('INBOX_TIMEZONE', config('app.timezone')),

    /*
    |--------------------------------------------------------------------------
    | AI
    |--------------------------------------------------------------------------
    |
    | All AI features are OFF by default and each is separately switchable,
    | because they are not equally reversible:
    |
    | - `summarise` and `draft_replies` only ADD text to the UI. Safe to enable.
    | - `check_outbound` CHANGES WHAT HAPPENS TO OUTGOING MAIL: a submitted draft
    |   is held at ai_status=checking until Gemini clears it, and auto-sends if it
    |   does. Do not turn this on until you have watched the queue drain reliably.
    |
    | Jobs default to the `emails` queue because routes/console.php already drains
    | that one every minute; a new queue name needs its own scheduled worker.
    |
    */

    'ai' => [
        'enabled' => (bool) env('INBOX_AI', false),
        'summarise' => (bool) env('INBOX_AI_SUMMARISE', true),
        'draft_replies' => (bool) env('INBOX_AI_DRAFTS', true),
        'check_outbound' => (bool) env('INBOX_AI_CHECK_OUTBOUND', false),
        'queue' => env('INBOX_AI_QUEUE', 'emails'),

        // How long a check may sit at `checking` before the UI calls it stalled and
        // offers "Send to AI again". Matches the mock's 5-minute threshold.
        'stall_minutes' => (int) env('INBOX_AI_STALL_MINUTES', 5),

        // Trim message bodies before they go to the model — long quoted-reply chains
        // otherwise dominate the prompt and cost.
        'max_chars_per_message' => (int) env('INBOX_AI_MAX_CHARS', 4000),
        'max_messages_per_thread' => (int) env('INBOX_AI_MAX_MESSAGES', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Listing
    |--------------------------------------------------------------------------
    */

    'per_page' => (int) env('INBOX_PER_PAGE', 25),

];
