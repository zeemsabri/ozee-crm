<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\Context;
use App\Models\Email;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateLeadFollowUpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Control queue behavior to reduce timeouts and add retries.
     */
    public int $tries = 3;
    public int $timeout = 120; // seconds
    public bool $failOnTimeout = true;
    public $backoff = [60, 300, 900]; // 1m, 5m, 15m

    public function __construct(public Lead $lead, public Campaign $campaign)
    {
        //
    }

    public function handle()
    {
        try {
            $apiKey = config('services.gemini.key');
            if (!$apiKey) {
                throw new \Exception('Gemini API key is not configured.');
            }

            // 1. Gather the lead's email history from the Context records.
            // The prompt relies on the count of previous emails to determine the correct follow-up.
            $emailHistory = $this->lead->contexts()
                ->with('referencable')
                ->where('referencable_type', \App\Models\Email::class) // Ensure context is from an email
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(fn ($context) => [
                    'subject'   =>  $context->referencable?->subject,
                    'summary' => $context->summary,
                    'sent_at' => $context->created_at->toIso8601String(),
                ])->toArray();

            // Safety check: If for some reason there's no history, we can't proceed.
            if (empty($emailHistory)) {
                $this->lead->update(['status' => Lead::STATUS_NEW]); // Revert status
                Log::warning('GenerateLeadFollowUpJob stopped: No email history found for lead.', ['lead_id' => $this->lead->id]);
                return;
            }

            // 2. Load the specific prompt for follow-ups.
            $systemPrompt = file_get_contents(public_path('prompts/follow_up_prompt.txt'));

            // 3. Prepare the payload for the AI.
            $leadDetails = $this->lead->toArray();
            $leadDetails['email_history'] = $emailHistory; // Add the history for the AI

            $payload = [
                'contents' => [
                    [
                        'parts' => [[
                            'text' => json_encode([
                                'campaign_details' => $this->campaign->toArray(),
                                'lead_details' => $leadDetails,
                                // The prompt expects 'resources'. We'll use 'value_adds' from the campaign.
                                'resources' => $this->campaign->shareableResources ?? [],
                            ])
                        ]]
                    ]
                ],
                'systemInstruction' => [
                    'parts' => [['text' => $systemPrompt]]
                ],
                'generationConfig' => [
                    'responseMimeType' => "application/json",
                ],
            ];

            $response = Http::timeout(20)
                ->retry(2, 500)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-05-20:generateContent?key={$apiKey}", $payload);

            if ($response->failed()) {
                throw new \Exception('Failed to communicate with Gemini API for follow-up. Status: ' . $response->status());
            }

            $aiResponse = json_decode($response->json('candidates.0.content.parts.0.text', ''), true);

            if (!isset($aiResponse['subject'], $aiResponse['ai_content'], $aiResponse['context_summary'])) {
                throw new \Exception('Invalid JSON response from AI for follow-up: ' . json_encode($aiResponse));
            }

            // 4. Create the new email and context.
            // We find the first conversation to keep the entire thread together.
            $conversation = $this->lead->conversations()->firstOrCreate(
                ['conversable_id' => $this->lead->id, 'conversable_type' => get_class($this->lead)],
                ['subject' => $aiResponse['subject'], 'contractor_id' => $this->lead->assigned_to_id ?? $this->lead->created_by_id]
            );

            $email = $conversation->emails()->create([
                'sender_id' => $this->lead->assigned_to_id ?? $this->lead->created_by_id,
                'to' => [$this->lead->email],
                'subject' => $aiResponse['subject'],
                'body' => json_encode($aiResponse['ai_content']),
                'template_data' => json_encode($aiResponse),
                'status' => Email::STATUS_DRAFT,
                'type' => 'sent',
            ]);

            $context = new Context([
                'summary' => $aiResponse['context_summary'],
                'user_id' => $this->lead->assigned_to_id ?? $this->lead->created_by_id,
            ]);
            $context->referencable()->associate($email);
            $context->linkable()->associate($this->lead);
            $context->save();

            // 5. Update the lead's status based on the AI's decision.
            $nextFollowUpDate = $aiResponse['next_follow_up_date'] ?? null;
            if ($nextFollowUpDate) {
                $this->lead->update([
                    'status' => Lead::STATUS_OUTREACH_SENT, // Set back to 'contacted' to allow for the next follow-up
                    'next_follow_up_date' => $nextFollowUpDate,
                ]);
            } else {
                // This is the "breakup" email. The sequence is complete.
                $this->lead->update([
                    'status' => Lead::STATUS_SEQUENCE_COMPLETED, // A new status to take it out of the queue
                    'next_follow_up_date' => null,
                ]);
            }

            // 6. Dispatch the job to send the email.
            ProcessDraftEmailJob::dispatch($email);

            Log::info('Successfully generated AI follow-up for lead.', ['lead_id' => $this->lead->id]);
        } catch (Throwable $e) {
            // If anything fails, revert the status so it can be picked up again on the next run.
            $this->lead->update(['status' => Lead::STATUS_OUTREACH_SENT]);
            Log::error('Failed to generate lead follow-up.', [
                'lead_id' => $this->lead->id,
                'error' => $e->getMessage()
            ]);
            $this->fail($e);
        }
    }

    /**
     * Called when the job has failed, including on timeouts when $failOnTimeout = true.
     */
    public function failed(Throwable $e): void
    {
        try {
            if ($this->lead && $this->lead->exists) {
                $this->lead->update(['status' => Lead::STATUS_OUTREACH_SENT]);
            }
        } catch (Throwable $inner) {
            // swallow to ensure failed() doesn't throw
        }

        Log::error('GenerateLeadFollowUpJob failed.', [
            'lead_id' => $this->lead->id ?? null,
            'error' => $e->getMessage(),
        ]);
    }
}
