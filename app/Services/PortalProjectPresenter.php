<?php

namespace App\Services;

use App\Enums\BillStatus;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectExpendableStatus;
use App\Models\Bill;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectExpendable;
use App\Models\User;
use App\Models\UserInteraction;
use Illuminate\Support\Carbon;

/**
 * Shapes the payloads the portal pages render.
 *
 * Extracted so the public share-link entry point and the signed-in portal produce
 * byte-identical project data — the two render the same React page, and drift between
 * them would show up as fields quietly missing depending on how you arrived.
 */
class PortalProjectPresenter
{
    /** Currencies a proposal or bill may be denominated in. */
    public const CURRENCIES = ['PKR', 'AUD', 'USD', 'EUR', 'GBP', 'INR'];

    /**
     * Milestone states that are finished one way or another. The portal only ever shows
     * work that is still open, so nobody is invited to quote for it.
     */
    public const CLOSED_MILESTONE_STATUSES = [
        MilestoneStatus::Approved,
        MilestoneStatus::Rejected,
        MilestoneStatus::Completed,
        MilestoneStatus::Canceled,
        MilestoneStatus::Expired,
    ];

    public function __construct(private CurrencyConversionService $currencyConversion) {}

    /**
     * @return array<int, string>
     */
    public function closedMilestoneStatusValues(): array
    {
        return array_map(fn (MilestoneStatus $s) => $s->value, self::CLOSED_MILESTONE_STATUSES);
    }

    /**
     * The full project payload: brief, open phases, open deliverables.
     *
     * @param  string|null  $shareCode  the 12-character share code, when the project
     *                                   has one and public sharing is on. Null for a
     *                                   project reached purely through team membership.
     *                                   Never the full `public_share_token`: that is the
     *                                   credential the whole share link rests on, and it
     *                                   has no business reaching the browser.
     * @return array<string, mixed>
     */
    public function project(Project $project, ?string $shareCode): array
    {
        $project->load([
            'manager:id,name',
            'admin:id,name',
            'milestones' => function ($q) {
                $q->select('id', 'project_id', 'name', 'description', 'status', 'completion_date')
                    ->whereNotIn('status', $this->closedMilestoneStatusValues())
                    ->orderBy('completion_date')
                    ->orderBy('created_at');
            },
            'projectDeliverables' => function ($q) {
                $q->select('id', 'project_id', 'milestone_id', 'name', 'description', 'status', 'due_date', 'details')
                    ->whereNotIn('status', ['completed', 'approved', 'canceled'])
                    ->orderBy('due_date')
                    ->orderBy('created_at');
            },
        ]);

        $milestones = $project->milestones->map(fn ($m) => [
            'id'              => $m->id,
            'name'            => (string) $m->name,
            'description'     => $m->description ? (string) $m->description : null,
            'status'          => $m->status instanceof \BackedEnum ? $m->status->value : (string) $m->status,
            'completion_date' => $m->completion_date?->format('d M Y'),
        ])->values();

        $activeMilestoneIds = $milestones->pluck('id')->all();

        $deliverables = $project->projectDeliverables
            ->filter(fn ($d) => is_null($d->milestone_id) || in_array($d->milestone_id, $activeMilestoneIds, true))
            ->map(function ($d) {
                $checklist = $this->sanitizeChecklist($d->details);

                return [
                    'id'              => $d->id,
                    'milestone_id'    => $d->milestone_id,
                    'name'            => (string) $d->name,
                    'description'     => $d->description ? (string) $d->description : null,
                    'status'          => $d->status instanceof \BackedEnum ? $d->status->value : (string) $d->status,
                    'due_date'        => $d->due_date?->format('d M Y'),
                    'checklist'       => $checklist,
                    'checklist_total' => count($checklist),
                    'checklist_done'  => count(array_filter($checklist, fn ($c) => $c['completed'])),
                ];
            })
            ->values();

        // `projects` has no due-date column, so target completion is the last milestone
        // to land. Uses every milestone, not just the open ones.
        $targetCompletion = $project->milestones()->max('completion_date');

        return [
            'id'                => $project->id,
            'name'              => $project->name,
            'description'       => $project->description,
            'status'            => $project->status instanceof \BackedEnum
                ? $project->status->value
                : (string) $project->status,
            'share_code'        => $shareCode,
            // Escape hatch to the pre-redesign Vue page while the portal is being
            // proven. Null once PORTAL_CLASSIC is off, and null for a project reached
            // through team membership rather than a share link — the classic page has
            // no way to render one, since it identifies a project by its token alone.
            'classic_url'       => $shareCode && config('portal.classic')
                ? url('/projects/classic/'.$shareCode)
                : null,
            'target_completion' => $targetCompletion ? Carbon::parse($targetCompletion)->format('d M Y') : null,
            'contact'           => $project->manager?->name ?? $project->admin?->name,
            'phase_count'       => $milestones->count(),
            'total_phase_count' => $project->milestones()->count(),
            'deliverable_count' => $deliverables->count(),
            'milestones'        => $milestones,
            'deliverables'      => $deliverables,
        ];
    }

    /**
     * Cards for the All projects list.
     *
     * Takes the whole collection rather than one project at a time: signal 1 in
     * PortalAccessService can legitimately return hundreds of projects for a busy
     * project manager, and a per-card query would make this page unusable. Milestone
     * counts and dates come pre-aggregated on the models (see
     * PortalAccessService::accessibleProjects); proposals and interactions are fetched
     * here in one query each.
     *
     * @param  \Illuminate\Support\Collection<int, Project>  $projects
     * @return array<int, array<string, mixed>>
     */
    public function projectCards($projects, User $user): array
    {
        $projectIds = $projects->pluck('id')->all();

        $proposalsByProject = ProjectExpendable::query()
            ->whereIn('project_id', $projectIds)
            ->where('user_id', $user->getKey())
            ->get(['id', 'project_id', 'status', 'created_at'])
            ->groupBy('project_id');

        $sharedAtByProject = UserInteraction::query()
            ->whereIn('interactable_id', $projectIds)
            ->where('user_id', $user->getKey())
            ->where('interactable_type', Project::class)
            ->selectRaw('interactable_id, MAX(updated_at) as last_at')
            ->groupBy('interactable_id')
            ->pluck('last_at', 'interactable_id');

        return $projects->map(function (Project $project) use ($proposalsByProject, $sharedAtByProject) {
            $proposals = $proposalsByProject->get($project->id) ?? collect();
            $latestProposalAt = $proposals->max('created_at');
            $sharedAt = $sharedAtByProject[$project->id] ?? null;

            return [
                'id'             => $project->id,
                'name'           => $project->name,
                'client'         => $project->client?->name,
                'status'         => $project->status instanceof \BackedEnum
                    ? $project->status->value
                    : (string) $project->status,
                'summary'        => $this->summarise($project->description),
                'target'         => $project->last_milestone_date
                    ? Carbon::parse($project->last_milestone_date)->format('d M Y')
                    : null,
                'phase_count'    => (int) ($project->active_milestone_count ?? 0),
                'proposal_count' => $proposals->count(),
                'accepted_count' => $proposals
                    ->filter(fn ($p) => ($p->status instanceof \BackedEnum ? $p->status->value : $p->status)
                        === ProjectExpendableStatus::Accepted->value)
                    ->count(),
                'activity'       => $latestProposalAt
                    ? 'You last quoted '.Carbon::parse($latestProposalAt)->format('d M Y')
                    : ($sharedAt
                        ? 'Shared with you '.Carbon::parse($sharedAt)->format('d M Y')
                        : 'No proposals from you yet'),
            ];
        })->values()->all();
    }

    /**
     * Every proposal this user has on this project, newest first, each with its bills.
     *
     * @return array<int, array<string, mixed>>
     */
    public function proposals(Project $project, int $userId): array
    {
        $proposals = ProjectExpendable::query()
            ->where('project_id', $project->id)
            ->where('user_id', $userId)
            ->with([
                'bills' => fn ($q) => $q->with('transactions')->orderBy('created_at'),
                'files' => fn ($q) => $q->latest(),
            ])
            ->latest()
            ->get();

        // One query for the milestone names rather than a morph load per row.
        $milestoneNames = $project->milestones()
            ->whereIn('id', $proposals
                ->where('expendable_type', Milestone::class)
                ->pluck('expendable_id')
                ->filter()
                ->all())
            ->pluck('name', 'id');

        return $proposals->map(function (ProjectExpendable $p) use ($milestoneNames) {
            $isMilestone = $p->expendable_type === Milestone::class;
            $status = $p->status instanceof \BackedEnum ? $p->status->value : (string) $p->status;
            $document = $p->files->first();

            $remaining = $status === ProjectExpendableStatus::Accepted->value
                ? $this->billableRemaining($p, $p->bills)
                : 0.0;

            return [
                'id'                 => $p->id,
                'number'             => $p->expendable_number,
                'scope'              => $isMilestone ? 'milestone' : 'project',
                'milestone_id'       => $isMilestone ? (int) $p->expendable_id : null,
                'milestone_name'     => $isMilestone ? ($milestoneNames[$p->expendable_id] ?? null) : null,
                'description'        => $p->description,
                'amount'             => (float) $p->amount,
                'currency'           => $p->currency,
                'payment_terms'      => $p->payment_terms,
                'status'             => $status,
                // Mirrors the upsert rule: anything past 'pending' is a decision the
                // proposer must not be able to silently overwrite.
                'can_edit'           => $status === ProjectExpendableStatus::PendingApproval->value,
                'can_bill'           => $remaining > 0,
                'billable_remaining' => round($remaining, 2),
                'submitted_at'       => $p->created_at?->format('d M Y'),
                'updated_at'         => $p->updated_at?->format('d M Y'),
                // Name only: file downloads would need a signed public route, which
                // doesn't exist yet.
                'document'           => $document ? ['name' => $document->filename, 'url' => null] : null,
                'bills'              => $p->bills->map(function (Bill $bill) {
                    // Payment progress comes from the bank transactions the accounts
                    // team links to the bill, the same source the admin transactions
                    // screen reads. `paid_amount` handles cross-currency payments.
                    $paid = (float) $bill->paid_amount;
                    $lastPaidAt = $bill->transactions
                        ->where('is_paid', true)
                        ->pluck('payment_date')
                        ->filter()
                        ->max();

                    return [
                        'id'               => $bill->id,
                        'number'           => $bill->bill_number,
                        'reference_number' => $bill->reference_number,
                        'amount'           => (float) $bill->amount,
                        'currency'         => $bill->currency,
                        'status'           => $bill->status instanceof \BackedEnum
                            ? $bill->status->value
                            : (string) $bill->status,
                        'due_date'         => $bill->due_date?->format('d M Y'),
                        'raised_at'        => $bill->created_at?->format('d M Y'),
                        'paid_amount'      => round($paid, 2),
                        'remaining_amount' => round(max(0, (float) $bill->amount - $paid), 2),
                        'last_paid_at'     => $lastPaidAt ? Carbon::parse($lastPaidAt)->format('d M Y') : null,
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * What is still billable on an accepted contract, in the contract's own currency.
     *
     * `balance` is already reduced for approved bills, so only bills still awaiting
     * approval need subtracting — the same arithmetic BillController::store performs.
     *
     * @param  \Illuminate\Support\Collection<int, Bill>|null  $bills  already-loaded bills,
     *                                                                to avoid re-querying
     */
    public function billableRemaining(ProjectExpendable $expendable, $bills = null): float
    {
        $contractCurrency = $expendable->currency ?? 'AUD';

        $pending = ($bills ?? $expendable->bills()->get())
            ->filter(fn (Bill $bill) => $bill->status === BillStatus::PendingApproval)
            ->reduce(function (float $carry, Bill $bill) use ($contractCurrency) {
                try {
                    return $carry + $this->currencyConversion->convert(
                        (float) $bill->amount,
                        $bill->currency ?? 'AUD',
                        $contractCurrency,
                    );
                } catch (\Throwable $e) {
                    // Matches the admin path, which also ignores conversion failures for
                    // already-pending bills rather than blocking a new one.
                    return $carry;
                }
            }, 0.0);

        return max(0, (float) $expendable->balance - $pending);
    }

    /**
     * @return array<string, mixed>
     */
    public function branding(): array
    {
        $config = config('branding');

        return [
            'company' => [
                'name'     => $config['company']['name'] ?? null,
                'website'  => $config['company']['website'] ?? null,
                'phone'    => $config['company']['phone'] ?? null,
                'address'  => $config['company']['address'] ?? null,
                'logo_url' => ! empty($config['company']['logo_url']) ? asset($config['company']['logo_url']) : null,
            ],
            // The signature tagline is stored with a <br> for emails; the page wants one
            // line of plain text.
            'tagline' => isset($config['signature']['tagline'])
                ? trim(preg_replace('/\s+/', ' ', strip_tags((string) $config['signature']['tagline'])))
                : null,
            'support_email' => config('mail.from.address'),
        ];
    }

    /**
     * @return array<int, array{name: string, completed: bool}>
     */
    public function sanitizeChecklist($details): array
    {
        if (! is_array($details)) {
            return [];
        }

        $items = $details['checklist'] ?? [];

        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(function ($item) {
                if (! is_array($item)) {
                    return null;
                }

                $name = trim((string) ($item['name'] ?? ''));

                return $name === '' ? null : ['name' => $name, 'completed' => (bool) ($item['completed'] ?? false)];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function summarise(?string $description): ?string
    {
        if (! $description) {
            return null;
        }

        $clean = trim(preg_replace('/\s+/', ' ', strip_tags($description)));

        return mb_strlen($clean) > 180 ? mb_substr($clean, 0, 177).'…' : $clean;
    }
}
