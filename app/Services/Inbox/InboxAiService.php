<?php

namespace App\Services\Inbox;

use App\Models\Conversation;
use App\Models\Email;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The three AI calls the redesigned inbox makes: check an outbound draft, summarise a
 * thread, and draft a reply to an inbound message.
 *
 * Why this exists next to the five other places that already call Gemini
 * (EmailAiAnalysisService, LeadReplyHandlerService, AIGenerationService,
 * GenerateLeadFollowUpJob, GenerateLeadOutreachJob — all hand-rolling their own HTTP
 * payload): consolidating them is a separate job with its own blast radius, and doing it
 * as part of an inbox rebuild would put unrelated regressions in the same change. This
 * class is at least the *one* place the inbox talks to a model, and is written so those
 * others can be folded into it later.
 *
 * Every method returns null rather than throwing. An AI feature that can take a page down
 * is worse than one that quietly doesn't appear, and each caller stores a Failed status
 * so the outage is visible in the data rather than only in the log.
 */
class InboxAiService
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s';

    public function __construct(private readonly EmailBodyRenderer $bodies) {}

    public function enabled(): bool
    {
        return (bool) config('inbox.ai.enabled') && ! empty(config('services.gemini.key'));
    }

    /**
     * Does this outbound draft need a person to look at it before it goes to a client?
     *
     * @return array{approved:bool,reason:string}|null
     */
    public function checkOutbound(Email $email): ?array
    {
        $result = $this->json(
            <<<'PROMPT'
            You are the final check on an email a web agency is about to send a client. You
            are not a writing coach — approve anything that is merely plain or terse.

            Hold the email (approved: false) only if it does one of these:
            - promises a specific result, ranking, revenue figure or performance outcome
            - commits to a date, price or scope that reads as contractual
            - discloses another client's information
            - is hostile, sarcastic or blames the client
            - contains an obvious placeholder that was never filled in (e.g. [NAME], TBC, XXX)

            Otherwise approve it.

            Reply as JSON: {"approved": boolean, "reason": string}. The reason is read by
            the person who wrote the email, so address them directly, say which sentence is
            the problem, and keep it under 40 words. When approving, the reason may be "".
            PROMPT,
            $this->emailText($email),
            ['approved' => 'bool', 'reason' => 'string']
        );

        if ($result === null) {
            return null;
        }

        return [
            'approved' => (bool) $result['approved'],
            'reason' => trim((string) ($result['reason'] ?? '')),
        ];
    }

    /**
     * Summarise a whole thread, and suggest the task it implies (or none).
     *
     * @return array{summary:string,task:?array{title:string,priority:string,reason:string}}|null
     */
    public function summariseThread(Conversation $conversation): ?array
    {
        $text = $this->threadText($conversation);

        if ($text === '') {
            return null;
        }

        $result = $this->json(
            <<<'PROMPT'
            Summarise this email thread between a web agency and its client for a staff
            member who is opening it for the first time.

            The summary: 2-3 sentences, plain Australian English, no bullet points, no
            preamble like "This thread is about". Lead with what the client currently wants
            or is waiting on. Mention anything time-sensitive and who it is blocked on.
            Never invent detail that is not in the messages.

            Then decide whether the thread implies one concrete piece of work the agency
            owes. If it does, describe it as a task; if the thread is purely informational,
            or the work is already done in a later message, return null for the task.

            Reply as JSON:
            {"summary": string, "task": {"title": string, "priority": "low"|"medium"|"high",
            "reason": string} | null}
            The task title is an imperative under 12 words. The reason cites the message it
            came from, under 20 words.
            PROMPT,
            $text,
            ['summary' => 'string']
        );

        if ($result === null) {
            return null;
        }

        $task = $result['task'] ?? null;

        return [
            'summary' => trim((string) $result['summary']),
            'task' => is_array($task) && ! empty($task['title'])
                ? [
                    'title' => (string) $task['title'],
                    'priority' => in_array($task['priority'] ?? '', ['low', 'medium', 'high'], true)
                        ? $task['priority']
                        : 'medium',
                    'reason' => (string) ($task['reason'] ?? ''),
                ]
                : null,
        ];
    }

    /** One-line summary of a single message, shown when the message is collapsed. */
    public function summariseMessage(Email $email): ?string
    {
        $result = $this->json(
            <<<'PROMPT'
            Summarise this single email in one sentence, under 25 words, plain Australian
            English. Say what the sender wants or is telling us. Do not start with "The
            sender" or "This email". Do not invent detail.

            Reply as JSON: {"summary": string}
            PROMPT,
            $this->emailText($email),
            ['summary' => 'string']
        );

        return $result ? trim((string) $result['summary']) : null;
    }

    /**
     * A first-draft reply to an inbound message. Always shown as an editable draft —
     * nothing generated here is ever sent without a person pressing send.
     */
    public function draftReply(Email $email, ?string $signOffName = null): ?string
    {
        $name = $signOffName ?: 'the team';

        $result = $this->json(
            <<<PROMPT
            Draft a reply to this client email on behalf of an Australian web agency.

            Rules:
            - Answer what they actually asked. If you cannot answer it from the thread, say
              when you will come back to them instead of guessing.
            - Plain Australian English, warm but brief. No marketing language.
            - Never promise a result, ranking, or a date that is not already in the thread.
            - Do not invent prices, names or deadlines.
            - Open with "Hi <their first name)," and sign off with "Thanks,\\n{$name}".
            - 4-8 short lines. No subject line, no signature block, no placeholders.

            Reply as JSON: {"reply": string} with real newlines inside the string.
            PROMPT,
            $this->emailText($email),
            ['reply' => 'string']
        );

        return $result ? trim((string) $result['reply']) : null;
    }

    // ---------------------------------------------------------------- internals

    /**
     * One Gemini call, forced to JSON, validated against the keys the caller needs.
     *
     * @param  array<string,string>  $requiredKeys  keys that must be present in the reply
     */
    private function json(string $systemPrompt, string $userText, array $requiredKeys): ?array
    {
        $key = config('services.gemini.key');

        if (empty($key)) {
            Log::warning('inbox.ai: no Gemini key configured; skipping.');

            return null;
        }

        $model = config('services.gemini.model', 'gemini-2.5-flash-preview-05-20');

        try {
            $response = Http::timeout(45)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post(sprintf(self::ENDPOINT, $model, $key), [
                    'contents' => [['parts' => [['text' => $userText]]]],
                    'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        // Low but not zero: deterministic enough to be reviewable, not so
                        // rigid that every draft reply opens with the same sentence.
                        'temperature' => 0.3,
                    ],
                ]);

            if ($response->failed()) {
                Log::error('inbox.ai: Gemini request failed.', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return null;
            }

            $raw = data_get($response->json(), 'candidates.0.content.parts.0.text');

            if (! is_string($raw) || $raw === '') {
                Log::error('inbox.ai: empty Gemini response.');

                return null;
            }

            $decoded = json_decode($raw, true);

            if (! is_array($decoded)) {
                Log::error('inbox.ai: Gemini reply was not JSON.', ['raw' => mb_substr($raw, 0, 500)]);

                return null;
            }

            foreach (array_keys($requiredKeys) as $required) {
                if (! array_key_exists($required, $decoded)) {
                    Log::error('inbox.ai: Gemini reply missing key.', ['key' => $required]);

                    return null;
                }
            }

            return $decoded;
        } catch (Throwable $e) {
            Log::error('inbox.ai: exception talking to Gemini.', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * One email as plain text, trimmed to the configured budget.
     *
     * Through EmailBodyRenderer, not the raw column: a templated email stores body = null
     * and keeps its text in the template. Reading the column directly handed the checker
     * an empty message, and the prompt says to approve anything merely terse — so every
     * templated email auto-approved without ever being read.
     */
    private function emailText(Email $email): string
    {
        $limit = (int) config('inbox.ai.max_chars_per_message', 4000);
        $body = $this->plain($this->bodies->body($email));

        return trim('Subject: '.$this->bodies->subject($email)."\n\n".mb_substr($body, 0, $limit));
    }

    /**
     * A thread as plain text, newest-last, capped at the configured message count.
     *
     * The cap takes the MOST RECENT messages, not the first — on a long thread the tail is
     * what the summary is about, and the opening pleasantries are the least useful part of
     * the prompt.
     */
    private function threadText(Conversation $conversation): string
    {
        $limit = (int) config('inbox.ai.max_chars_per_message', 4000);
        $maxMessages = (int) config('inbox.ai.max_messages_per_thread', 12);

        return $conversation->emails
            ->sortBy(fn (Email $e) => ($e->sent_at ?? $e->created_at)?->timestamp ?? 0)
            // Withheld messages never reach the model, for the same reason in both cases:
            // the summary is shown to people who are not allowed to read them, so
            // including them would launder the content straight back in.
            //   - is_private: a manager deliberately kept it from the project team.
            //   - pending_approval_received: inbound mail still held for screening.
            // The count the summary is stamped with (SummariseConversation) covers the
            // whole thread, so releasing a screened message changes nothing until the
            // message count moves — which it does, because release is a status change on
            // an existing row. Regeneration is therefore driven by hasCurrentAiSummary
            // going stale on the next new message, not by the release itself.
            ->reject(fn (Email $e) => (bool) $e->is_private
                || $this->isScreened($e))
            ->take(-$maxMessages)
            ->map(function (Email $e) use ($limit) {
                $who = $e->sender?->name ?? ($e->type?->value === 'received' ? 'Client' : 'Agency');
                $when = ($e->sent_at ?? $e->created_at)?->toDayDateTimeString() ?? '';
                // Rendered, for the same reason as emailText() above.
                $body = mb_substr($this->plain($this->bodies->body($e)), 0, $limit);

                return "--- {$who} ({$when}) ---\n{$body}";
            })
            ->implode("\n\n");
    }

    /** Inbound mail still held for screening — not readable by the whole team. */
    private function isScreened(Email $email): bool
    {
        $status = $email->status instanceof \App\Enums\EmailStatus
            ? $email->status->value
            : strtolower((string) $email->status);

        return $status === \App\Enums\EmailStatus::PendingApprovalReceived->value;
    }

    /**
     * HTML down to the text a model should actually read.
     *
     * Three deliberate steps, in order:
     *
     * 1. `<script>`, `<style>` and `<head>` are removed WITH their contents. strip_tags
     *    only removes the tags, so a mail template with an embedded stylesheet would
     *    otherwise hand the model several kilobytes of CSS as if it were prose — billed
     *    per token, on every check, and actively misleading about what the email says.
     *
     * 2. `<img>` is removed explicitly. It has no text content, so strip_tags would drop
     *    it anyway; this is here so that intent survives a future change. Images must
     *    never reach the model — that is the entire reason the block builder embeds them
     *    as CID parts rather than inlining them as data URIs. A single screenshot as
     *    base64 costs more tokens than every email in a long thread put together.
     *
     * 3. Block separators, so the collapsed text still reads as separate paragraphs
     *    rather than one run-on line.
     */
    private function plain(?string $html): string
    {
        $html = (string) $html;

        $html = preg_replace('#<(script|style|head)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $html = preg_replace('#<img\b[^>]*>#i', ' ', $html) ?? $html;
        $html = preg_replace('#<(br|/p|/div|/li|/tr|/h[1-6])\b[^>]*>#i', "\n", $html) ?? $html;

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? '');
    }
}
