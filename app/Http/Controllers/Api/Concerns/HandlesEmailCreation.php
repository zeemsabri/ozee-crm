<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Jobs\ProcessDraftEmailJob;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Email;
use App\Models\EmailTemplate;
use App\Models\Lead;
use App\Models\Project;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Contracts\Auth\Authenticatable;

trait HandlesEmailCreation
{
    /**
     * Create a custom email to project clients (non-template).
     */
    protected function handleCustomClientEmail(Authenticatable $user, array $validated): Email
    {
        $project = Project::with('clients')->findOrFail($validated['project_id']);

        if (!$user->projects->contains($project->id)) {
            abort(403, 'Unauthorized: You are not assigned to this project.');
        }

        // Normalize client IDs
        $clientIds = array_map(fn ($c) => $c['id'], $validated['client_ids'] ?? []);
        if (empty($clientIds)) {
            throw ValidationException::withMessages(['client_ids' => 'At least one client is required.']);
        }

        // Ensure clients belong to project
        $projectClientIds = $project->clients->pluck('id')->all();
        foreach ($clientIds as $cid) {
            if (!in_array($cid, $projectClientIds, true)) {
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

        $email = Email::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'to' => $emails,
            'subject' => $validated['subject'],
            'body' => $greeting . '<br/>' . $validated['body'],
            'status' => $validated['status'] ?? 'pending_approval',
            'type' => 'sent',
        ]);

        $conversation->update(['last_activity_at' => now()]);
        return $email;
    }

    /**
     * Create a templated email to project clients.
     */
    protected function handleTemplatedEmail(Authenticatable $user, array $validated): Email
    {
        $project = Project::with('clients')->findOrFail($validated['project_id']);
        if (!$user->projects->contains($project->id)) {
            abort(403, 'Unauthorized: You are not assigned to this project.');
        }

        $clientIds = $validated['client_ids'] ?? [];
        // In templated case, client_ids is array of raw IDs (not objects) from ComposeEmailContent
        if (!empty($clientIds) && is_array($clientIds) && isset($clientIds[0]['id'])) {
            $clientIds = array_map(fn ($c) => $c['id'], $clientIds);
        }
        if (empty($clientIds)) {
            throw ValidationException::withMessages(['client_ids' => 'At least one client is required.']);
        }

        $projectClientIds = $project->clients->pluck('id')->toArray();
        foreach ($clientIds as $cid) {
            if (!in_array($cid, $projectClientIds, true)) {
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
            'status' => $validated['status'] ?? Email::STATUS_DRAFT,
            'type' => 'sent',
        ]);

        ProcessDraftEmailJob::dispatch($email);

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
            'body' => $greeting . '<br/>' . $validated['body'],
            'status' => $validated['status'] ?? Email::STATUS_DRAFT,
            'type' => 'sent',
        ]);

        ProcessDraftEmailJob::dispatch($email);

        $conversation->update(['last_activity_at' => now()]);
        return $email;
    }
}
