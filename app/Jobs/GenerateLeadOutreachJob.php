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

class GenerateLeadOutreachJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Lead $lead, public Campaign $campaign)
    {
        //
    }

    public function handle(): void
    {
        try {
            $apiKey = config('services.gemini.key');
            if (!$apiKey) {
                throw new \Exception('Gemini API key is not configured.');
            }

            $systemPrompt = file_get_contents('public/prompts/oz_e_email_composer_gem.txt');

            $payload = [
                'contents' => [
                    [
                        'parts' => [[
                            'text' => json_encode([
                                'campaign_details' => $this->campaign->toArray(),
                                'lead_details' => $this->lead->toArray()
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

            $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-05-20:generateContent?key={$apiKey}", $payload);

            if ($response->failed()) {
                throw new \Exception('Failed to communicate with Gemini API. Status: ' . $response->status());
            }

            $aiResponse = json_decode($response->json('candidates.0.content.parts.0.text', ''), true);

            if (!isset($aiResponse['subject'], $aiResponse['ai_content'], $aiResponse['context_summary'])) {
                throw new \Exception('Invalid JSON response from AI: ' . json_encode($aiResponse));
            }

            // 1. Create the Conversation and Email
            // Note: We no longer save a 'body'. The AI content is stored in 'template_data'.
            $conversation = $this->lead->conversations()->create([
                'subject' => $aiResponse['subject'],
                'contractor_id' => $this->lead->assigned_to_id ?? $this->lead->created_by_id,
            ]);

            $email = $conversation->emails()->create([
                'sender_id' => $this->lead->assigned_to_id ?? $this->lead->created_by_id,
                'to' => [$this->lead->email],
                'subject' => $aiResponse['subject'],
                'body' => json_encode($aiResponse['ai_content']),
                'template_id' => null, // Or you can assign a specific ID if you store the template in the DB
                'template_data' => json_encode($aiResponse), // Store the entire structured response
                'status' => Email::STATUS_DRAFT,
                'type' => 'sent',
            ]);

            // 2. Create the Context record from the AI's summary
            $context = new Context([
                'summary' => $aiResponse['context_summary'],
                'user_id' => $this->lead->assigned_to_id ?? $this->lead->created_by_id,
            ]);
            $context->referencable()->associate($email);
            $context->linkable()->associate($this->lead);
            $context->save();

            // 3. Update the lead's status and follow-up date
            $this->lead->update([
                'status' => Lead::STATUS_OUTREACH_SENT,
                'next_follow_up_date' => $aiResponse['next_follow_up_date'] ?? now()->addDays(3),
                'contacted_at' => now(),
            ]);

            // 4. Trigger the standard approval/sending process
            // This service will now know how to render the 'ai_lead_outreach_template'
            \App\Jobs\ProcessDraftEmailJob::dispatch($email);

            Log::info('Successfully generated AI outreach for lead.', ['lead_id' => $this->lead->id]);

        } catch (Throwable $e) {
            Log::error('Failed to generate lead outreach.', [
                'lead_id' => $this->lead->id,
                'error' => $e->getMessage()
            ]);
//            $this->lead->update(['status' => Lead::STATUS_GENERATION_FAILED]);
            $this->fail($e);
        }
    }
}

