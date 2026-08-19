<?php

namespace App\Http\Controllers\Portal;

use App\Enums\BillStatus;
use App\Enums\ProjectExpendableStatus;
use App\Exceptions\BillExceedsContractException;
use App\Http\Controllers\Controller;
use App\Mail\GuestBillSubmittedMail;
use App\Models\Bill;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectExpendable;
use App\Models\User;
use App\Models\UserInteraction;
use App\Services\BillApprovalFlowService;
use App\Services\CurrencyConversionService;
use App\Services\GuestPaymentMethodService;
use App\Services\PortalAccessService;
use App\Services\PortalProfileService;
use App\Services\PortalProjectPresenter;
use App\Services\PortalSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The supplier portal: real URLs for someone who has proved who they are.
 *
 * Everything here sits behind `EnsurePortalUser`, which resolves the visitor either
 * from an existing app login or from a verified emailed-code session and puts them on
 * the request as `portal_user`. Access to any individual project is decided by
 * PortalAccessService, never by knowing a share token — that is what lets a project
 * reached through team membership work here at all.
 */
class PortalController extends Controller
{
    public function __construct(
        private PortalAccessService $access,
        private PortalProjectPresenter $presenter,
        private PortalProfileService $profiles,
        private PortalSessionService $portalSession,
        private GuestPaymentMethodService $paymentMethods,
        private BillApprovalFlowService $billApprovalFlow,
        private CurrencyConversionService $currencyConversion,
    ) {}

    private function user(Request $request): User
    {
        return $request->attributes->get('portal_user');
    }

    /**
     * The account payload, plus how this person got here.
     *
     * `via` matters to the UI: signing out only ends an emailed-code session. Someone
     * identified by their main-app login has no portal cookie to clear, so offering
     * them a "Sign out" button here would do nothing.
     */
    private function account(Request $request, User $user): array
    {
        return $this->profiles->present($user) + [
            // How they were actually identified, not merely whether a cookie is present:
            // a stale portal cookie alongside a real app login resolves to `login`, and
            // the page then hides a Sign out button that would clear nothing.
            'via' => $this->portalSession->resolvedVia($request) ?? 'login',
        ];
    }

    /**
     * Resolve a project the caller is allowed to open, or abort.
     */
    private function authorizedProject(Request $request, Project $project): Project
    {
        $user = $this->user($request);

        if (! $this->access->canAccess($user, $project)) {
            // A bare 404 here is deliberately indistinguishable from a project that
            // doesn't exist, which makes a misconfigured share genuinely hard to tell
            // apart from a hostile probe. Leave a breadcrumb for the former.
            Log::info('Portal access denied', [
                'user_id'    => $user->getKey(),
                'project_id' => $project->getKey(),
                'shared'     => (bool) $project->public_share_enabled,
            ]);

            abort(404);
        }

        return $project;
    }

    // ---------------------------------------------------------------- pages

    /**
     * All projects shared with this person.
     */
    public function index(Request $request): Response
    {
        $user = $this->user($request);

        return Inertia::render('React/Portal/Projects', [
            'projects' => $this->presenter->projectCards($this->access->accessibleProjects($user), $user),
            'account'  => $this->account($request, $user),
            'branding' => $this->presenter->branding(),
        ]);
    }

    /**
     * One project: brief, phases, the caller's own proposals and bills.
     */
    public function show(Request $request, Project $project): Response
    {
        $this->authorizedProject($request, $project);
        $user = $this->user($request);

        $this->trackInteraction($user->getKey(), $project->id, 'page_view');

        return Inertia::render('React/Portal/Project', [
            // The 12-character share CODE, never the full token: the page only needs it
            // to address the sign-in endpoints, and a token in an Inertia prop would be
            // readable in page source by anyone the browser is left open in front of.
            'project'         => $this->presenter->project($project, $this->shareCodeFor($project)),
            'proposals'       => $this->presenter->proposals($project, (int) $user->getKey()),
            'account'         => $this->account($request, $user),
            'paymentMethods'  => $this->paymentMethods->maskedList($user),
            'branding'        => $this->presenter->branding(),
            'currencies'      => PortalProjectPresenter::CURRENCIES,
        ]);
    }

    public function profile(Request $request): Response
    {
        $user = $this->user($request);

        return Inertia::render('React/Portal/Profile', [
            'account'        => $this->account($request, $user),
            'paymentMethods' => $this->paymentMethods->maskedList($user),
            'idTypes'        => collect(PortalProfileService::ID_TYPES)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'currencies'     => PortalProjectPresenter::CURRENCIES,
            'branding'       => $this->presenter->branding(),
        ]);
    }

    /**
     * Ends the emailed-code session server-side, not just in the browser.
     *
     * Deliberately does not touch Laravel's own session: a team member signed into the
     * main app stays signed in there.
     */
    public function signOut(Request $request): SymfonyResponse
    {
        $this->portalSession->invalidate($request->cookie(PortalSessionService::COOKIE));

        // Queued rather than attached: Inertia::location is typed as a plain Symfony
        // response, and the queue reaches whichever concrete response comes back.
        Cookie::queue($this->portalSession->forgetCookie());

        // Not redirect('/'): an Inertia POST would follow the 302 and try to mount the
        // Vue welcome page inside the React runtime, which throws. Inertia::location
        // answers 409 + X-Inertia-Location so the browser does a real page load, and
        // falls back to an ordinary redirect for a non-Inertia POST.
        return Inertia::location('/');
    }

    /**
     * The public share link only ever exposes the first 12 characters of the token
     * (see PublicProjectController), and only while sharing is switched on. A project
     * reached through team membership legitimately has no code at all.
     */
    private function shareCodeFor(Project $project): ?string
    {
        if (! $project->public_share_enabled || ! $project->public_share_token) {
            return null;
        }

        return substr((string) $project->public_share_token, 0, 12);
    }

    // ------------------------------------------------------------- profile

    public function updateProfile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'          => 'required|string|max:255',
            'phone'         => 'required|string|max:30',
            'business_name' => 'nullable|string|max:255',
            'verification'  => 'nullable|array',
            'verification.dob' => 'nullable|date|before:today',
            'verification.id_type' => 'nullable|string|in:'.implode(',', array_keys(PortalProfileService::ID_TYPES)),
            'verification.id_number' => 'nullable|string|max:100',
            'verification.city' => 'nullable|string|max:120',
            'verification.address' => 'nullable|string|max:255',
            'verification.postcode' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $this->user($request);

        try {
            $this->profiles->update($user, $validator->validated());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Details saved.',
            'account' => $this->profiles->present($user->refresh()),
        ]);
    }

    // ----------------------------------------------------- payment methods

    public function storePaymentMethod(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type'     => 'required|string|in:'.implode(',', GuestPaymentMethodService::TYPES),
            'label'    => 'required|string|max:100',
            'country'  => 'nullable|string|max:100',
            'currency' => 'nullable|string|in:'.implode(',', PortalProjectPresenter::CURRENCIES),
            'fields'   => 'required|array',
            'fields.*' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            [$methods] = $this->paymentMethods->add($this->user($request), $validator->validated());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Payment method saved.', 'paymentMethods' => $methods]);
    }

    public function destroyPaymentMethod(Request $request, string $methodId): JsonResponse
    {
        try {
            $methods = $this->paymentMethods->remove($this->user($request), $methodId);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Payment method removed.', 'paymentMethods' => $methods]);
    }

    public function defaultPaymentMethod(Request $request, string $methodId): JsonResponse
    {
        $user = $this->user($request);

        if (! $this->paymentMethods->find($user, $methodId)) {
            return response()->json(['message' => 'That payment method no longer exists.'], 422);
        }

        try {
            $methods = $this->paymentMethods->makeDefault($user, $methodId);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Default payment method updated.', 'paymentMethods' => $methods]);
    }

    // ------------------------------------------------------------ proposals

    /**
     * Submit or update proposals.
     *
     * Scope decides how many rows are written:
     *   'milestone'  — one proposal for one phase
     *   'milestones' — one proposal *per* selected phase, each with its own amount,
     *                  sharing the cover letter, payment terms and document
     *   'project'    — one proposal covering the whole project
     *
     * Within a scope, an existing still-pending proposal from this person is updated in
     * place rather than duplicated. Anything already decided on is left untouched.
     */
    public function storeProposal(Request $request, Project $project): JsonResponse
    {
        $this->authorizedProject($request, $project);

        $validator = Validator::make($request->all(), [
            'proposal_scope'  => 'required|string|in:milestone,milestones,project',
            'milestone_id'    => 'required_if:proposal_scope,milestone|nullable|integer|exists:milestones,id',
            'milestone_ids'   => 'required_if:proposal_scope,milestones|array|min:1',
            'milestone_ids.*' => 'integer|exists:milestones,id',
            'description'     => 'required|string|min:20|max:5000',
            'amount'          => 'required_unless:proposal_scope,milestones|nullable|numeric|min:1',
            'amounts'         => 'required_if:proposal_scope,milestones|array',
            'amounts.*'       => 'numeric|min:1',
            'currency'        => 'required|string|in:'.implode(',', PortalProjectPresenter::CURRENCIES),
            'payment_terms'   => 'nullable|string|max:10000',
            'document'        => 'nullable|file|mimes:pdf|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $this->user($request);
        $scope = $request->string('proposal_scope')->toString();
        $targets = [];

        if ($scope === 'project') {
            $targets[] = ['target' => $project, 'amount' => (float) $request->input('amount')];
        } else {
            $requestedIds = $scope === 'milestones'
                ? array_map('intval', $request->input('milestone_ids', []))
                : [(int) $request->input('milestone_id')];

            // Milestones must belong to this project, and be work that is still open.
            $milestones = $project->milestones()
                ->whereIn('id', $requestedIds)
                ->whereNotIn('status', $this->presenter->closedMilestoneStatusValues())
                ->get()
                ->keyBy('id');

            if ($milestones->count() !== count(array_unique($requestedIds))) {
                return response()->json([
                    'message' => 'One of those phases is no longer open for proposals. Reload the page and try again.',
                ], 422);
            }

            $amounts = $request->input('amounts', []);

            foreach ($requestedIds as $id) {
                $amount = $scope === 'milestones'
                    ? (float) ($amounts[$id] ?? 0)
                    : (float) $request->input('amount');

                if ($amount < 1) {
                    return response()->json(['message' => 'Every phase needs an amount of at least 1.'], 422);
                }

                $targets[] = ['target' => $milestones[$id], 'amount' => $amount];
            }
        }

        // Upload once and attach the same object to every proposal in the batch.
        $documentAttributes = null;
        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $documentAttributes = [
                'project_id' => $project->id,
                'filename'   => $file->getClientOriginalName(),
                'mime_type'  => $file->getMimeType(),
                'file_size'  => $file->getSize(),
                'path'       => Storage::disk('gcs')->putFile('proposals', $file),
            ];
        }

        // Spatie's activity log resolves the causer from the authenticated user. For an
        // emailed-code visitor there isn't one, so borrow the identity for the batch and
        // put things back exactly as they were.
        $originalUser = auth()->user();
        auth()->setUser($user);

        try {
            DB::transaction(function () use ($targets, $project, $user, $request, $documentAttributes) {
                foreach ($targets as ['target' => $target, 'amount' => $amount]) {
                    $expendable = $this->upsertProposal(
                        project: $project,
                        author: $user,
                        target: $target,
                        amount: $amount,
                        description: (string) $request->input('description'),
                        currency: (string) $request->input('currency'),
                        paymentTerms: $request->input('payment_terms'),
                    );

                    if ($documentAttributes) {
                        $expendable->files()->create($documentAttributes);
                    }
                }
            });
        } finally {
            if ($originalUser) {
                auth()->setUser($originalUser);
            } else {
                // setUser() never touched the session, so forgetUser() is the symmetric
                // undo. logout() would cycle the borrowed user's remember_token and fire
                // a Logout event.
                auth()->forgetUser();
            }
        }

        $this->trackInteraction($user->getKey(), $project->id, 'proposal_submitted');

        $count = count($targets);

        return response()->json([
            'message' => $count > 1
                ? "Your {$count} proposals have been submitted — one per phase. We'll review them and get back to you."
                : 'Your proposal has been submitted successfully. We will review it and get back to you.',
            'proposals' => $this->presenter->proposals($project, (int) $user->getKey()),
        ]);
    }

    /**
     * Create or update this person's proposal for one morph target (a Milestone, or the
     * Project itself for a whole-project quote).
     */
    private function upsertProposal(
        Project $project,
        User $author,
        Project|Milestone $target,
        float $amount,
        string $description,
        string $currency,
        ?string $paymentTerms,
    ): ProjectExpendable {
        $isMilestone = $target instanceof Milestone;

        $expendable = ProjectExpendable::query()
            ->where('project_id', $project->id)
            ->where('user_id', $author->getKey())
            ->where('expendable_type', $isMilestone ? Milestone::class : Project::class)
            ->where('expendable_id', $target->id)
            // Only a proposal still awaiting a decision may be replaced in place.
            // Accepted is a commitment, Completed has been billed and paid, Shortlisted
            // is under active consideration, and Rejected is a record of a decision.
            ->where('status', ProjectExpendableStatus::PendingApproval->value)
            ->latest()
            ->first();

        $attributes = [
            'name'            => $isMilestone
                ? $target->name.' — proposal from '.($author->name ?: $author->email)
                : 'Whole project proposal from '.($author->name ?: $author->email),
            'description'     => $description,
            'currency'        => $currency,
            'amount'          => $amount,
            'payment_terms'   => $paymentTerms,
            'expendable_id'   => $target->id,
            'expendable_type' => $isMilestone ? Milestone::class : Project::class,
            'status'          => ProjectExpendableStatus::PendingApproval->value,
        ];

        if ($expendable) {
            // Only re-baseline the balance while nothing has been billed against it.
            if ($expendable->bills()->doesntExist()) {
                $attributes['balance'] = $amount;
            }

            $expendable->update($attributes);

            return $expendable;
        }

        return ProjectExpendable::create($attributes + [
            'project_id' => $project->id,
            'user_id'    => $author->getKey(),
            'balance'    => $amount,
        ]);
    }

    // ----------------------------------------------------------------- bills

    /**
     * Upload a bill against one of the caller's accepted proposals.
     *
     * They supply only what they can know: their invoice, its number, the amount and
     * where to be paid. The Xero account code, tax type and transaction type are left
     * null for the accounts team — the bill cannot be approved until at least the
     * account code is set (BillController::performFinalBillApproval enforces that).
     */
    public function storeBill(Request $request, Project $project): JsonResponse
    {
        $this->authorizedProject($request, $project);

        $validator = Validator::make($request->all(), [
            'proposal_id'       => 'required|integer',
            'payment_method_id' => 'required|string',
            'reference_number'  => 'required|string|max:255',
            'amount'            => 'required|numeric|min:0.01',
            'currency'          => 'required|string|in:'.implode(',', PortalProjectPresenter::CURRENCIES),
            'due_date'          => 'nullable|date|after_or_equal:today',
            // The invoice itself is the point, so unlike the admin path it is required.
            'document'          => 'required|file|mimes:pdf|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $this->user($request);

        $expendable = ProjectExpendable::query()
            ->where('id', $request->integer('proposal_id'))
            ->where('project_id', $project->id)
            ->where('user_id', $user->getKey())
            ->first();

        if (! $expendable) {
            return response()->json(['message' => 'That proposal is not one of yours.'], 422);
        }

        $status = $expendable->status instanceof \BackedEnum
            ? $expendable->status->value
            : (string) $expendable->status;

        if ($status !== ProjectExpendableStatus::Accepted->value) {
            return response()->json([
                'message' => 'You can only bill a proposal once it has been accepted.',
                'errors'  => ['proposal_id' => ['This proposal has not been accepted yet.']],
            ], 422);
        }

        $method = $this->paymentMethods->find($user, (string) $request->input('payment_method_id'));

        if (! $method) {
            return response()->json([
                'message' => 'Choose a payment method to be paid to.',
                'errors'  => ['payment_method_id' => ['That payment method no longer exists.']],
            ], 422);
        }

        $amount = (float) $request->input('amount');

        try {
            $amountInContractCurrency = $this->currencyConversion->convert(
                $amount,
                (string) $request->input('currency'),
                $expendable->currency ?? 'AUD',
            );
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'We could not convert that amount to the contract currency. Bill in '
                    .($expendable->currency ?? 'AUD').' instead.',
                'errors'  => ['currency' => [$e->getMessage()]],
            ], 422);
        }

        // Upload before opening the transaction: a storage failure then aborts without
        // leaving a bill behind, which is the opposite of the admin path's behaviour.
        $file = $request->file('document');
        try {
            $objectPath = Storage::disk('gcs')->putFile('bills', $file);
        } catch (\Throwable $e) {
            Log::error('Portal bill upload failed to store document: '.$e->getMessage());

            return response()->json(['message' => 'We could not store that file. Try again in a moment.'], 500);
        }

        $paymentDetail = $this->paymentMethods->toBillPaymentDetail($method);

        try {
            $bill = DB::transaction(function () use ($project, $user, $expendable, $request, $amount, $amountInContractCurrency, $paymentDetail, $file, $objectPath) {
                // Re-read the contract under a row lock: two uploads racing for the last
                // of the balance would otherwise both pass an unlocked check and both be
                // created, leaving a duplicate that can never be approved.
                $locked = ProjectExpendable::whereKey($expendable->getKey())->lockForUpdate()->first();
                $remaining = $this->presenter->billableRemaining($locked);

                // Compared raw, not rounded, because performFinalBillApproval compares
                // raw floats. Rounding only here would let a bill through that could
                // never be approved.
                if ($amountInContractCurrency > $remaining) {
                    throw new BillExceedsContractException(
                        'You have '.number_format($remaining, 2).' '.($locked->currency ?? 'AUD')
                        .' left to bill, once bills already awaiting approval are counted.'
                    );
                }

                $bill = Bill::create([
                    'project_id'            => $project->id,
                    'contractor_id'         => $user->getKey(),
                    'project_expendable_id' => $expendable->id,
                    // Left for the accounts team — a supplier can't know these.
                    'transaction_type_id'   => null,
                    'xero_account_code'     => null,
                    'xero_tax_type'         => null,
                    'reference_number'      => $request->input('reference_number'),
                    'due_date'              => $request->input('due_date'),
                    'currency'              => $request->input('currency'),
                    'amount'                => $amount,
                    'status'                => BillStatus::PendingApproval,
                ]);

                $bill->paymentDetail()->create([
                    'contractor_id'  => $user->getKey(),
                    'payment_method' => $paymentDetail['payment_method'],
                    'details'        => $paymentDetail['details'],
                ]);

                $bill->files()->create([
                    'project_id' => $project->id,
                    'filename'   => $file->getClientOriginalName(),
                    'mime_type'  => $file->getMimeType(),
                    'file_size'  => $file->getSize(),
                    'path'       => $objectPath,
                ]);

                return $bill;
            });
        } catch (BillExceedsContractException $e) {
            return response()->json([
                'message' => 'That is more than is left to bill on this proposal.',
                'errors'  => ['amount' => [$e->getMessage()]],
            ], 422);
        }

        // Same approval flow an internally created bill enters.
        $this->billApprovalFlow->initialize($bill, $project->id);

        $this->notifyAccountsOfBill($bill);

        return response()->json([
            'message'   => 'Bill uploaded. The accounts team has been notified and will review it.',
            'proposals' => $this->presenter->proposals($project, (int) $user->getKey()),
        ]);
    }

    private function notifyAccountsOfBill(Bill $bill): void
    {
        $recipient = config('mail.mailers.smtp.username') ?: config('mail.from.address');

        if (! $recipient || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::to($recipient)->queue(new GuestBillSubmittedMail($bill));
        } catch (\Throwable $e) {
            // A mail failure must never lose someone their upload.
            Log::error('Failed to queue portal bill notification: '.$e->getMessage());
        }
    }

    private function trackInteraction(int $userId, int $projectId, string $interactionType): void
    {
        // `updated_at` is not fillable on UserInteraction, so passing it as an update
        // attribute is silently dropped and the model is never dirty — a repeat event
        // would leave the timestamp frozen at first contact. touch() is explicit.
        UserInteraction::firstOrCreate([
            'user_id'           => $userId,
            'interactable_id'   => $projectId,
            'interactable_type' => Project::class,
            'interaction_type'  => $interactionType,
        ])->touch();
    }
}
