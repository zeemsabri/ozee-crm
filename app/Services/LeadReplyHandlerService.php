<?php

namespace App\Services;

use App\Jobs\ProcessDraftEmailJob;
use App\Models\Context;
use App\Models\Email;
use App\Models\Lead;
use App\Models\ManualReviewTask;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class LeadReplyHandlerService
{
    protected string $apiKey;
    protected string $model;
    protected string $apiUrl;

    /**
     * The system prompt for analyzing incoming lead replies.
     */
    protected string $systemPrompt;


    public function __construct()
    {
        // IMPROVEMENT: Added error handling for when the prompt file is missing.
        $promptPath = public_path('prompts/reply_handling.txt');
        if (!file_exists($promptPath)) {
            Log::critical('CRITICAL: Reply handling prompt file is missing.', ['path' => $promptPath]);
            // Throwing an exception is better here to halt execution if the core prompt is missing.
            throw new \Exception("Reply handling prompt file not found at: {$promptPath}");
        }
        $this->systemPrompt = file_get_contents($promptPath);

        $this->apiKey = config('services.gemini.key');
        $this->model = config('services.gemini.model', 'gemini-2.5-flash-preview-05-20');
        $this->apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";
    }

    /**
     * Handles an incoming email reply from a lead.
     *
     * @param Lead $lead The lead who replied.
     * @param Email $email The incoming email model, already created by the controller.
     * @return void
     */
    public function handleIncomingReply(Lead $lead, Email $email): void
    {
        $emailContent = $email->body;

        if (empty($this->apiKey)) {
            Log::error('Gemini API key is not configured. Cannot handle lead reply.');
            $this->createManualReviewTask($lead, $emailContent, 'Failsafe: AI system is not configured.');
            return;
        }

        // The incoming email is now created in the controller, so we proceed directly to analysis.
        $aiAnalysis = $this->analyzeReply($lead, $emailContent);

        if (!$aiAnalysis) {
            Log::error('Failed to get AI analysis for lead reply.', ['lead_id' => $lead->id]);
            $this->createManualReviewTask($lead, $emailContent, 'Failsafe: AI analysis returned an error.');
            return;
        }

        switch ($aiAnalysis['response_status'] ?? 'MANUAL_APPROVAL_REQUIRED') {
            case 'AUTO_SEND':
                $this->queueReply($lead, $aiAnalysis);
                Log::info('Automated reply queued for lead.', ['lead_id' => $lead->id, 'reason' => $aiAnalysis['context_summary']]);
                break;

            case 'MANUAL_APPROVAL_REQUIRED':
                $this->queueReply($lead, $aiAnalysis, Email::STATUS_PENDING_APPROVAL_SENT);
                $this->createManualReviewTask(
                    $lead,
                    $emailContent,
                    $aiAnalysis['context_summary'],
                    $aiAnalysis
                );
                Log::info('Manual review task created for lead.', ['lead_id' => $lead->id, 'reason' => $aiAnalysis['context_summary']]);
                break;

            case 'POSITIVE_CLOSE':
                // TODO: Consider creating a LeadStatus::UNSUBSCRIBED enum or constant.
                $lead->update(['status' => 'unsubscribed', 'next_follow_up_date' => null]);
                Log::info('Lead unsubscribed. Sequence halted.', ['lead_id' => $lead->id, 'reason' => $aiAnalysis['context_summary']]);
                break;

            default:
                Log::warning('Unknown AI action received.', ['action' => $aiAnalysis['response_status'], 'lead_id' => $lead->id]);
                $this->createManualReviewTask($lead, $emailContent, 'Failsafe: AI returned an unknown status.');
                break;
        }
    }

    /**
     * Analyzes the reply content using the Gemini AI, enriched with lead and campaign context.
     *
     * @param Lead $lead
     * @param string $incomingEmailContent
     * @return array|null
     */
    private function analyzeReply(Lead $lead, string $incomingEmailContent): ?array
    {
        try {
            $emailHistory = $lead->contexts()
                ->with('referencable')
                ->where('referencable_type', \App\Models\Email::class)
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($context) {
                    $email = $context->referencable;
                    return [
                        'type'    => $email?->type, // 'sent' or 'received'
                        'subject' => $email?->subject,
                        'summary' => $context->summary,
                        'sent_at' => $context->created_at->toIso8601String(),
                    ];
                })->toArray();

            $promptInput = [
                'incoming_email_text' => $incomingEmailContent,
                'email_history' => $emailHistory,
                'lead_details' => $lead->toArray(),
                'campaign_details' => $lead->campaign ? $lead->campaign->toArray() : [],
            ];

            $payload = [
                'contents' => [['parts' => [['text' => json_encode($promptInput)]]]],
                // BUG FIX: Changed self::SYSTEM_PROMPT to $this->systemPrompt to use the property loaded in the constructor.
                'systemInstruction' => ['parts' => [['text' => $this->systemPrompt]]],
                'generationConfig' => ['responseMimeType' => "application/json"],
            ];

            $response = Http::post($this->apiUrl, $payload);

            if ($response->failed()) {
                Log::error('Failed to communicate with the Gemini AI service for reply analysis.', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return null;
            }

            $generatedJsonString = $response->json('candidates.0.content.parts.0.text', '');
            if (empty($generatedJsonString)) {
                Log::warning('Gemini AI returned an empty response for reply analysis.', [
                    'response' => $response->json()
                ]);
                return null;
            }

            return json_decode($generatedJsonString, true);

        } catch (Throwable $e) {
            Log::error('Exception during Gemini reply analysis.', [
                'error' => $e->getMessage(),
                'lead_id' => $lead->id,
            ]);
            return null;
        }
    }

    /**
     * Creates and queues an automated email reply based on the AI's response.
     *
     * @param Lead $lead
     * @param array $aiResponse
     * @return void
     */
    private function queueReply(Lead $lead, array $aiResponse, $status = null): void
    {
        try {
            $conversation = $lead->conversations()->firstOrCreate(
                ['conversable_id' => $lead->id, 'conversable_type' => get_class($lead)],
                ['subject' => $aiResponse['subject'], 'contractor_id' => $lead->assigned_to_id ?? $lead->created_by_id]
            );

            $email = $conversation->emails()->create([
                'sender_id' => $lead->assigned_to_id ?? $lead->created_by_id,
                'to' => [$lead->email],
                'subject' => $aiResponse['subject'],
                'body' => json_encode($aiResponse['ai_content']),
                'template_data' => json_encode($aiResponse),
                'status' => $status ?? Email::STATUS_DRAFT,
                'type' => 'sent',
            ]);

            $context = new Context([
                'summary' => $aiResponse['context_summary'],
                'user_id' => $lead->assigned_to_id ?? $lead->created_by_id,
            ]);
            $context->referencable()->associate($email);
            $context->linkable()->associate($lead);
            $context->save();

            // The lead has replied, so we clear the next follow-up date.
            $lead->update(['next_follow_up_date' => null]);

            ProcessDraftEmailJob::dispatch($email);
        } catch (Throwable $e) {
            Log::error('Failed to queue automated reply.', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
            // Failsafe to ensure a human can see this.
            $this->createManualReviewTask($lead, json_encode($aiResponse), 'Failsafe: Error during auto-reply creation.');
        }
    }

    /**
     * Creates a task for a human to manually review the email.
     *
     * @param Lead $lead
     * @param string $emailContent
     * @param string $reason
     * @param array|null $aiResponse The full response from the AI, to be used as a template.
     * @return void
     */
    private function createManualReviewTask(Lead $lead, string $emailContent, string $reason, ?array $aiResponse = null): void
    {
        // FIX: Re-enabled the failsafe task creation. This is critical for error handling.
//        ManualReviewTask::create([
//            'lead_id' => $lead->id,
//            'original_email_content' => $emailContent,
//            'reason_for_review' => $reason,
//            'status' => 'pending',
//            // 'suggested_reply' => $aiResponse, // Keep this commented until migration is ready.
//        ]);
    }
}

