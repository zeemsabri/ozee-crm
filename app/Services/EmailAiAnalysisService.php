<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmailAiAnalysisService
{
    protected string|null $apiKey;
    protected string|null $model;
    protected string|null $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
        $this->model = config('services.gemini.model', 'gemini-2.5-flash-preview-05-20');
        $this->apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";
    }

    /**
     * Analyzes email text via Gemini AI and returns a structured array with the
     * analysis, including an approval decision and a context summary.
     *
     * @param string $plainTextContent The plain text content of the email (subject + body).
     * @param bool $isIncoming Flag to indicate if the email is incoming or outgoing.
     * @return array|null The analysis array or null on failure.
     */
    public function analyzeAndSummarize(string $plainTextContent, bool $isIncoming = false): ?array
    {
        if (empty($this->apiKey)) {
            Log::error('Gemini API key is not configured.');
            return null;
        }

        try {
            $systemPrompt = $this->buildSystemPrompt($isIncoming);

            $payload = [
                'contents' => [
                    ['parts' => [['text' => $plainTextContent]]]
                ],
                'systemInstruction' => [
                    'parts' => [['text' => $systemPrompt]]
                ],
                'generationConfig' => [
                    'responseMimeType' => "application/json",
                ],
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, $payload);

            if ($response->failed()) {
                Log::error('Failed to communicate with the Gemini AI service.', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return null;
            }

            $generatedJsonString = $response->json('candidates.0.content.parts.0.text', '');
            if (empty($generatedJsonString)) {
                Log::warning('Gemini AI returned an empty or invalid response.', [
                    'response' => $response->json()
                ]);
                return null;
            }

            $content = json_decode($generatedJsonString, true);

            // Validate that the response contains all the keys we expect.
            if (isset($content['approval_required'], $content['reason'], $content['context_summary'])) {
                return $content;
            }

            Log::warning('Gemini analysis response was malformed.', [
                'response_string' => $generatedJsonString,
            ]);

            return null;

        } catch (Throwable $e) {
            Log::error('Exception during Gemini analysis process.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Builds the detailed system prompt to instruct the AI.
     * This version is more objective to prevent false positives.
     *
     * @param bool $isIncoming
     * @return string
     */
    private function buildSystemPrompt(bool $isIncoming): string
    {
        $companyDomain = config('services.gemini.company_domain', 'ozeeweb.com.au');

        // --- NEW MISSION STATEMENT ---
        // This is the most important change. We tell the AI its primary goal is to APPROVE emails
        // unless a clear violation is found.
        $prompt = "You are a CRM compliance assistant. Your primary goal is to auto-approve routine, professional business communications. You must only flag an email for manual review if it contains a clear, objective violation. Return a single JSON object with three keys: `approval_required` (boolean), `reason` (string), and `context_summary` (string).\n\n";

        $prompt .= "---\n\n";

        $prompt .= "**Rules for `approval_required` and `reason`:**\n";

        // --- REFINED RULES ---
        // We replaced subjective terms with objective ones.
        $prompt .= "1. **FLAG** if the email contains personal contact details (phone numbers, non-company emails like @gmail.com). Ignore contact details from `{$companyDomain}` in quoted replies.\n";
        $prompt .= "2. **FLAG** if the email contains rude, offensive, or overly casual language (e.g., slang, insults).\n";
        $prompt .= "3. **FLAG** if the email's primary purpose or call to action is impossible to understand.\n";
        if ($isIncoming) {
            $prompt .= "4. **FLAG** if an INCOMING email contains sensitive financial terms ('price', 'quote', '$', 'invoice', 'payment').\n";
        }
        $prompt .= "\n**Crucially, DO NOT FLAG standard business communications.** An email that is a clear project update, a meeting confirmation, or a simple follow-up should always be approved.\n";
        $prompt .= "5. If no violations are found, set `approval_required` to `false` and provide a positive reason.\n";


        $prompt .= "\n---\n\n";

        $prompt .= "**Rules for `context_summary`:**\n";
        // ... (The context summary rules remain the same as before)
        $prompt .= "1. The `context_summary` must be a concise, one to two sentences summary of the key action and information related to email, question, or event in the email.\n";

        if ($isIncoming) {
            $prompt .= "2. This is an INCOMING email from the client. Start the summary with '**Client**'.\n";
            $prompt .= "   - Example: 'Thanks for the wireframes. They look great, but can we change the header?' should result in 'Client provided positive feedback on the wireframes and requested a revision to the header.'\n";
        } else {
            $prompt .= "2. This is an OUTGOING email from us. Start the summary with '**User**'.\n";
            $prompt .= "   - Example: 'Hi John, attaching the wireframes for the homepage' should result in 'User sent the homepage wireframes for review.'\n";
        }

        return $prompt;
    }
}

