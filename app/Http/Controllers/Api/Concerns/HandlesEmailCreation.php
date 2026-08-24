<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Enums\EmailStatus;
use App\Jobs\ProcessDraftEmailJob;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Email;
use App\Models\Lead;
use App\Models\Project;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

trait HandlesEmailCreation
{
    /**
     * Create a custom email to project clients (non-template).
     */
    protected function handleCustomClientEmail(Authenticatable $user, array $validated): Email
    {
        $project = Project::with('clients')->findOrFail($validated['project_id']);

        $this->authorize('create', [Email::class, $project]);

        // Normalize client IDs
        $clientIds = array_map(fn ($c) => $c['id'], $validated['client_ids'] ?? []);
        if (empty($clientIds)) {
            throw ValidationException::withMessages(['client_ids' => 'At least one client is required.']);
        }

        // Ensure clients belong to project
        $projectClientIds = $project->clients->pluck('id')->all();
        foreach ($clientIds as $cid) {
            if (! in_array($cid, $projectClientIds, true)) {
                throw ValidationException::withMessages([
                    'client_ids' => "Client ID {$cid} is not assigned to this project.",
                ]);
            }
        }

        // Create conversation with first client as conversable
        $primaryClientId = $clientIds[0];
        $conversation = Conversation::create([
            'project_id' => $project->id,
            'subject' => $validated['subject'],
            'contractor_id' => $user->id,
            'conversable_type' => Client::class,
            'conversable_id' => $primaryClientId,
            'last_activity_at' => now(),
        ]);

        $emails = Client::whereIn('id', $clientIds)->pluck('email')->toArray();

        $greeting = $validated['custom_greeting_name'] ?? ($validated['greeting_name'] ?? 'Hi there');

        /*
         * Block-built body, from the redesigned inbox's "Project update" composer.
         *
         * Additive and fully guarded: `blocks` is a key the classic composer has never
         * sent and never will, so when it is absent every line below is skipped and this
         * method behaves exactly as it did. When it is present the typed `body` is
         * ignored — there isn't one; the content IS the blocks.
         *
         * sanitise() is scoped to the project this email is being sent on, so an image
         * block naming a file id from somewhere else is dropped rather than embedded.
         * MODE_SEND means the HTML carries `cid:` references: a few tokens each for the AI
         * to read, and the actual bytes are attached as MIME parts at send time. See
         * App\Services\Inbox\BlockComposition.
         */
        $composition = app(\App\Services\Inbox\BlockComposition::class);
        $blocks = is_array($validated['blocks'] ?? null)
            ? $composition->sanitise($validated['blocks'], [$project->id])
            : [];

        if (($validated['composition_type'] ?? null) === 'blocks' && $blocks === []) {
            throw ValidationException::withMessages([
                'blocks' => 'There is nothing in this update yet — add a block before sending it.',
            ]);
        }

        if ($blocks !== []) {
            /*
             * The greeting becomes the first block, rather than a string prefixed onto the
             * rendered HTML.
             *
             * It has to be inside the blocks because the send path re-renders from them —
             * see BlockComposition::renderForSend and why it does not trust the stored
             * body. A greeting glued on outside would survive being stored and then vanish
             * on the way out, so the client would receive an update that opens mid-
             * sentence. Inside the blocks it is rendered by the same code every time.
             */
            array_unshift($blocks, ['type' => 'text', 'text' => rtrim($greeting, ',').',']);

            $body = app(\App\Services\Inbox\BlockRenderer::class)
                ->render($blocks, \App\Services\Inbox\BlockRenderer::MODE_SEND);
        } else {
            $body = $greeting.'<br/>'.$validated['body'];
        }

        $email = Email::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'to' => $emails,
            'subject' => $validated['subject'],
            'body' => $body,
            // template_data carries the block JSON so the send path can rebuild the CID
            // map. template_id stays null, so every reader that keys off template_id
            // treats this as the custom email it is.
            'template_data' => $blocks !== []
                ? \App\Support\TemplateData::encode([\App\Services\Inbox\BlockComposition::KEY => $blocks])
                : null,
            'status' => $validated['status'] ?? EmailStatus::Draft,
            'type' => 'sent',
            // Compose-time privacy. Permission-checked in EmailController::store before
            // it reaches here; absent for every classic-composer request.
            'is_private' => (bool) ($validated['is_private'] ?? false),
        ]);

        // Re-point the images from the project to the email now that one exists.
        if ($blocks !== []) {
            app(\App\Services\Inbox\EmailImageStore::class)
                ->attachTo($email, $composition->imageIds($blocks));
        }

        $conversation->update(['last_activity_at' => now()]);

        return $email;
    }

    /**
     * Create a templated email to project clients.
     */
    protected function handleTemplatedEmail(Authenticatable $user, array $validated): Email
    {
        $project = Project::with('clients')->findOrFail($validated['project_id']);
        if (! $user->isSuperAdmin() &&
            ! $user->hasPermission('view_all_projects') &&
            ! $user->hasPermission('view_all_emails') &&
            ! $user->projects->contains($project->id) &&
            $project->admin?->id !== $user->id &&
            $project->manager?->id !== $user->id
        ) {
            abort(403, 'Unauthorized: You are not assigned to this project.');
        }

        $clientIds = $validated['client_ids'] ?? [];
        // In templated case, client_ids is array of raw IDs (not objects) from ComposeEmailContent
        if (! empty($clientIds) && is_array($clientIds) && isset($clientIds[0]['id'])) {
            $clientIds = array_map(fn ($c) => $c['id'], $clientIds);
        }
        if (empty($clientIds)) {
            throw ValidationException::withMessages(['client_ids' => 'At least one client is required.']);
        }

        $projectClientIds = $project->clients->pluck('id')->toArray();
        foreach ($clientIds as $cid) {
            if (! in_array($cid, $projectClientIds, true)) {
                throw ValidationException::withMessages([
                    'client_ids' => "Client ID {$cid} is not assigned to this project.",
                ]);
            }
        }

        $primaryClientId = $clientIds[0];
        $conversation = Conversation::firstOrCreate(
            [
                'project_id' => $project->id,
                'subject' => $validated['subject'],
            ],
            [
                'contractor_id' => $user->id,
                'conversable_type' => Client::class,
                'conversable_id' => $primaryClientId,
                'last_activity_at' => now(),
            ]
        );

        $emails = Client::whereIn('id', $clientIds)->pluck('email')->toArray();

        $email = Email::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'to' => $emails,
            'subject' => $validated['subject'],
            'template_id' => $validated['template_id'],
            'template_data' => json_encode($validated['template_data'] ?? []),
            'status' => $validated['status'] ?? EmailStatus::Draft,
            'type' => 'sent',
            // Compose-time privacy. Permission-checked in EmailController::store before
            // it reaches here; absent for every classic-composer request.
            'is_private' => (bool) ($validated['is_private'] ?? false),
        ]);

        //        ProcessDraftEmailJob::dispatch($email);

        $conversation->update(['last_activity_at' => now()]);

        return $email;
    }

    /**
     * Create a custom email to leads (project optional; no client conversion).
     */
    protected function handleLeadEmail(Authenticatable $user, array $validated): Email
    {
        $leadIds = array_map(fn ($l) => $l['id'], $validated['lead_ids'] ?? []);
        if (empty($leadIds)) {
            throw ValidationException::withMessages(['lead_ids' => 'At least one lead is required.']);
        }

        $projectId = $validated['project_id'] ?? null; // nullable by design

        // Pick first lead for conversable binding (supports lists by keeping recipients in Email.to)
        $firstLeadId = $leadIds[0];
        $conversation = Conversation::create([
            'project_id' => $projectId,
            'subject' => $validated['subject'],
            'contractor_id' => $user->id,
            'conversable_type' => Lead::class,
            'conversable_id' => $firstLeadId,
            'last_activity_at' => now(),
        ]);

        $emails = Lead::whereIn('id', $leadIds)->pluck('email')->filter()->values()->toArray();

        $greeting = $validated['custom_greeting_name'] ?? ($validated['greeting_name'] ?? 'Hi there');

        $email = Email::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'to' => $emails,
            'subject' => $validated['subject'],
            'body' => $greeting.'<br/>'.$validated['body'],
            'status' => $validated['status'] ?? EmailStatus::Draft,
            'type' => 'sent',
            // Compose-time privacy. Permission-checked in EmailController::store before
            // it reaches here; absent for every classic-composer request.
            'is_private' => (bool) ($validated['is_private'] ?? false),
        ]);

        //        ProcessDraftEmailJob::dispatch($email);

        $conversation->update(['last_activity_at' => now()]);

        return $email;
    }
}
