<?php

namespace App\Http\Controllers\Api;

use App\Enums\EmailStatus;
use App\Http\Controllers\Controller;
use App\Models\Email;
use App\Services\Inbox\InboxAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Saved (unfinished) emails — the composer's "Save" button and its autosave.
 *
 * ## What a saved email is
 *
 * A real emails row with `status = 'saved'`, NO conversation, and the composer's resume
 * state in `draft_meta`. The "Saved" rail view lists them; Resume reopens the composer
 * from them. Submitting one does NOT transition it: the composer posts to the normal
 * create endpoint (a fresh row, so the automation fires exactly as it always has) and
 * deletes the saved row. There is deliberately no saved→draft path — the workflow
 * triggers on CREATION, and a status flip would produce an email the automation never
 * picks up, the same class of hole as the AI-resume gap.
 *
 * ## Why every write here is wrapped in Email::withoutEvents()
 *
 * GlobalModelEventSubscriber forwards EVERY eloquent created/updated/deleted event to the
 * workflow engine, and which workflows react is configuration living in the database —
 * not something this code can audit. A half-written email must never reach that engine,
 * so saved rows are written with events off entirely. Nothing that matters is lost: the
 * EmailObserver only acts on received mail and status transitions, and the status
 * regression guard protects rows that have been sent, which a saved row never was.
 *
 * ## Who sees what (the user's rule: same visibility as any other email)
 *
 * - The feature belongs to whoever may compose free-form: `InboxAccess::canComposeCustom`
 *   — saved emails are custom-tab drafts, and only someone who could finish one should
 *   see it.
 * - Scope: your own saved emails, plus those on projects you may compose on
 *   (`composableProjectIds`, the same membership rule the compose endpoints enforce).
 * - Private: `is_private` follows the standard rule — without `view_private_emails` you
 *   do not see a private saved email, EVEN your own. That mirrors sent mail exactly, and
 *   the composer already warns the author at the toggle.
 */
class InboxSavedController extends Controller
{
    public function __construct(private readonly InboxAccess $access) {}

    private const GREETING_MODES = ['full_name', 'first_name', 'last_name', 'custom', 'none'];

    /** GET /api/inbox/saved */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        abort_unless($this->access->canComposeCustom($user), 403, 'You do not have permission to compose custom emails.');

        $rows = $this->visibleSaved()
            ->with('sender:id,name')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (Email $email) => $this->payload($email))->values(),
        ]);
    }

    /** POST /api/inbox/saved */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($this->access->canComposeCustom($user), 403, 'You do not have permission to compose custom emails.');

        $data = $this->validated($request);

        // An empty composer never creates a saved row — the frontend enforces the same.
        if (trim($data['subject'] ?? '') === '' && trim($data['body'] ?? '') === '') {
            return response()->json(['message' => 'Validation failed', 'errors' => ['body' => ['There is nothing to save yet.']]], 422);
        }

        $email = Email::withoutEvents(fn () => Email::create([
            'conversation_id' => null,
            'sender_id' => $user->id,
            'to' => [],
            'subject' => $data['subject'] ?? '',
            'body' => $data['body'] ?? '',
            'status' => EmailStatus::Saved,
            'type' => 'sent',
            // Same guard as the compose endpoints: the flag only sticks when the author
            // may set it. The composer already warned them about locking themselves out.
            'is_private' => $this->access->canMarkPrivate($user) && ($data['is_private'] ?? false),
            'draft_meta' => $this->meta($data),
        ]));

        return response()->json(['data' => $this->payload($email->fresh('sender'))], 201);
    }

    /** PUT /api/inbox/saved/{email} — autosave and explicit save both land here. */
    public function update(Request $request, Email $email): JsonResponse
    {
        $user = Auth::user();
        $this->authoriseRow($email, $user);

        $data = $this->validated($request);

        Email::withoutEvents(function () use ($email, $data, $user) {
            $email->fill([
                'subject' => $data['subject'] ?? '',
                'body' => $data['body'] ?? '',
                'is_private' => $this->access->canMarkPrivate($user) && ($data['is_private'] ?? false),
                'draft_meta' => $this->meta($data),
            ]);
            $email->save();
        });

        return response()->json(['data' => $this->payload($email->fresh('sender'))]);
    }

    /** DELETE /api/inbox/saved/{email} — discarded, or submitted through the real endpoint. */
    public function destroy(Email $email): JsonResponse
    {
        $user = Auth::user();
        $this->authoriseRow($email, $user);

        // Hard delete, not the soft delete the rest of the inbox uses: an unfinished email
        // was never part of any thread, and surfacing it in the bin next to real deleted
        // mail would suggest a "restore" that has nothing to restore into.
        Email::withoutEvents(fn () => $email->forceDelete());

        return response()->json(['ok' => true]);
    }

    /* ------------------------------------------------------------------ */

    private function validated(Request $request): array
    {
        return $request->validate([
            'project_id' => ['sometimes', 'nullable', 'integer'],
            'client_ids' => ['sometimes', 'array'],
            'client_ids.*' => ['integer'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string', 'max:65000'],
            'greeting_mode' => ['sometimes', 'nullable', 'string', Rule::in(self::GREETING_MODES)],
            'greeting_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'is_private' => ['sometimes', 'boolean'],
        ]);
    }

    private function meta(array $data): array
    {
        $user = Auth::user();
        $projectId = $data['project_id'] ?? null;

        // A project you may not compose on cannot be attached to a draft — otherwise the
        // draft would be visible to that project's composers as a side door.
        if ($projectId !== null && ! in_array((int) $projectId, $this->access->composableProjectIds($user), true)) {
            abort(422, 'You cannot compose on that project.');
        }

        return [
            'project_id' => $projectId !== null ? (int) $projectId : null,
            'client_ids' => array_values(array_map('intval', $data['client_ids'] ?? [])),
            'greeting_mode' => $data['greeting_mode'] ?? 'full_name',
            'greeting_name' => $data['greeting_name'] ?? '',
        ];
    }

    /** The rows this user may see. Author's own, plus composable projects; private gated. */
    private function visibleSaved()
    {
        $user = Auth::user();
        $projectIds = $this->access->composableProjectIds($user);

        return Email::query()
            ->where('status', EmailStatus::Saved)
            ->where(function ($q) use ($user, $projectIds) {
                $q->where('sender_id', $user->id);
                if ($projectIds !== []) {
                    // draft_meta is a JSON column; project_id lives inside it because a
                    // saved row has no conversation to carry one.
                    $q->orWhereIn(
                        // MySQL: ->> unquotes; whereJsonContains would match arrays, not scalars.
                        \Illuminate\Support\Facades\DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(draft_meta, '$.project_id')) AS UNSIGNED)"),
                        $projectIds
                    );
                }
            })
            ->when(
                ! $this->access->canSeePrivate($user),
                fn ($q) => $q->where('is_private', false)
            );
    }

    private function authoriseRow(Email $email, $user): void
    {
        // Not-found for anything that is not a saved row: this controller must never
        // become a way to edit or delete a real email.
        abort_unless($email->status === EmailStatus::Saved, 404);
        abort_unless($this->access->canComposeCustom($user), 403, 'You do not have permission to compose custom emails.');

        $projectId = $email->draft_meta['project_id'] ?? null;
        $mayTouch = $email->sender_id === $user->id
            || ($projectId !== null && in_array((int) $projectId, $this->access->composableProjectIds($user), true));
        abort_unless($mayTouch, 403, 'This saved email belongs to a project you cannot compose on.');

        // Privacy is the same rule as every other email — without view_private_emails a
        // private saved email is invisible, the author included (they were warned).
        if ($email->is_private && ! $this->access->canSeePrivate($user)) {
            abort(404);
        }
    }

    private function payload(Email $email): array
    {
        return [
            'id' => $email->id,
            'subject' => (string) $email->subject,
            'body' => (string) $email->body,
            'is_private' => (bool) $email->is_private,
            'updated_at' => optional($email->updated_at)->toIso8601String(),
            'sender' => [
                'id' => $email->sender?->id,
                'name' => $email->sender?->name,
            ],
            'meta' => [
                'project_id' => $email->draft_meta['project_id'] ?? null,
                'client_ids' => $email->draft_meta['client_ids'] ?? [],
                'greeting_mode' => $email->draft_meta['greeting_mode'] ?? 'full_name',
                'greeting_name' => $email->draft_meta['greeting_name'] ?? '',
            ],
        ];
    }
}
