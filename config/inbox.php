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
    | What we call ourselves
    |--------------------------------------------------------------------------
    |
    | Shown wherever the team is the sender or the recipient of a message —
    | "Priya Nair to the OZee Team". Never an email address: client mail is
    | routed through one mailbox precisely so that individual staff neither hold
    | nor are shown client contact details, and the timeline printing raw
    | addresses walked around the permission that enforces it.
    |
    */

    'team_label' => env('INBOX_TEAM_LABEL', 'OZee Team'),

    /*
    |--------------------------------------------------------------------------
    | When a person may approve a draft by hand
    |--------------------------------------------------------------------------
    |
    | A reply submitted from /inbox/beta is written as `status = draft`, and that
    | row IS the submission: the automation picks it up and either sends it or
    | hands it back as `pending_approval` for a person. See the docblock on
    | Api\InboxReplyController.
    |
    | While it is still a draft the automation owns it, so the thread view shows
    | "Approve & send" DISABLED — approving by hand inside that window races the
    | machine, and both of them send.
    |
    | Two things unlock the button:
    |
    |  - the automation hands the email back (`pending_approval`), which is the
    |    normal path and is immediate; or
    |  - the draft has sat here longer than the window below, which means the
    |    automation never ran — a stuck queue, a workflow that no longer matches,
    |    an AI call that failed without writing a verdict. Without this, a stuck
    |    draft is unsendable forever with nothing on screen explaining why.
    |
    | Measured from the email's created_at. This is a BETA-INBOX rule: it gates
    | inbox/emails/{email}/approve, and the classic inbox's own
    | emails/{email}/edit-and-approve keeps the rules it has today.
    |
    */

    'manual_approval_after_minutes' => (int) env('INBOX_MANUAL_APPROVAL_AFTER_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Reply clock scope
    |--------------------------------------------------------------------------
    |
    | Two knobs that decide which threads the reply clock is allowed to judge.
    | Both exist because the clock asks a question the historical data cannot
    | answer, and answering it wrongly is worse than declining to answer.
    |
    | `since` — the cutover. Threads whose newest CLIENT message predates this
    | are excluded from "Needs reply", the overdue count and the breach sort.
    |
    |   Set this. Without it every thread the team ever answered from the Gmail
    |   web UI reads as unanswered, because a reply typed into Gmail never became
    |   a row here — the poller only ever asked Gmail for `is:inbox`. That is not
    |   a bug in the clock; it is the clock correctly reporting that this system
    |   has no record of a reply, which is a different claim from "nobody
    |   replied". The cutover is how you say "do not judge what you cannot see".
    |
    |   Null means no cutover: judge everything, including mail from 2023.
    |   Format: anything strtotime understands — '2026-08-01', or '-30 days'.
    |
    |   Nothing is written to the database by this. It is a filter, so widening
    |   or removing it later brings the older threads straight back.
    |
    | `delivered_statuses` — which outbound statuses mean "the client got this".
    |
    |   `sent` is what both live send paths write. `approved` is legacy: it has
    |   been in the enum since 2023, no current code writes it, and two API
    |   endpoints plus a Vue filter still treat it as a synonym for delivered —
    |   so real rows almost certainly carry it. Leaving it out silently reclassed
    |   every one of those as an unanswered thread.
    |
    |   Run `php artisan inbox:audit-reply-clock` to see the real distribution
    |   before adding anything else here.
    |
    */

    'reply_clock' => [
        'since' => env('INBOX_REPLY_CLOCK_SINCE'),

        'delivered_statuses' => [
            \App\Enums\EmailStatus::Sent->value,
            \App\Enums\EmailStatus::Approved->value,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ingesting mail sent from Gmail
    |--------------------------------------------------------------------------
    |
    | The poller has only ever asked Gmail for `is:inbox`, so a reply somebody
    | typed into the Gmail web UI never became a row here and the thread reads as
    | unanswered forever. `inbox:fetch-sent` closes that.
    |
    | `first_run_since` is where the very first pass starts, and the default of
    | "now" is deliberate. Emails the CRM sent before this feature existed have no
    | rfc_message_id — the column did not exist — so the ingester cannot recognise
    | them as ours and would ingest every one a second time. Reaching further back
    | needs a fuzzier matcher and a dry run you have actually read.
    |
    | `enabled` unregisters the scheduled pass without touching the command, so it
    | can still be run by hand.
    |
    */

    'sent_ingest' => [
        'enabled' => (bool) env('INBOX_INGEST_SENT', true),
        'first_run_since' => env('INBOX_SENT_INGEST_SINCE'),
        'limit' => (int) env('INBOX_SENT_INGEST_LIMIT', 50),
    ],

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
    | Block builder
    |--------------------------------------------------------------------------
    |
    | The "build it in blocks" composer. Images are EMBEDDED in the outgoing message as
    | multipart/related CID parts, so the client's copy is permanent and works offline —
    | which is what makes it safe for our own copy to expire.
    |
    | `image_ttl_days` therefore only governs OUR storage. Deleting an image after it
    | lapses does not touch any email already sent; it only means the composer and our own
    | thread view can no longer show it. Existing files (task attachments, inbound email
    | attachments) have a null expires_at and are never pruned.
    |
    */

    'blocks' => [
        'image_ttl_days' => (int) env('INBOX_BLOCK_IMAGE_TTL_DAYS', 180),

        // Per-image cap. Gmail rejects messages over 25MB total, and every embedded image
        // is base64'd on the way out — roughly a third larger than the file on disk.
        'max_image_mb' => (int) env('INBOX_BLOCK_MAX_IMAGE_MB', 5),

        // Refused outright. GD cannot thumbnail webp/svg here (HandlesImageUploads throws),
        // and SVG in an email is both unsupported and a script vector.
        'image_mimes' => ['image/jpeg', 'image/png', 'image/gif'],

        // Belt and braces against a runaway message: the total embedded payload we will
        // put in one email, before base64 expansion.
        'max_total_mb' => (int) env('INBOX_BLOCK_MAX_TOTAL_MB', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Listing
    |--------------------------------------------------------------------------
    */

    'per_page' => (int) env('INBOX_PER_PAGE', 25),

];
