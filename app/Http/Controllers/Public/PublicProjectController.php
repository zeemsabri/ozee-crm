<?php

namespace App\Http\Controllers\Public;

use App\Enums\MilestoneStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectExpendable;
use App\Models\UserInteraction;
use App\Services\OtpService;
use App\Enums\ProjectExpendableStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class PublicProjectController extends Controller
{
    public function __construct(private OtpService $otpService) {}

    /**
     * Render the public project view (no auth required).
     */
    public function show(string $token): Response
    {
        $project = $this->resolveProjectByToken($token);

        return $this->renderProjectPage($project, $token);
    }

    /**
     * Render public project page using a friendly URL structure.
     */
    public function showPretty(string $slug, string $code): Response
    {
        $project = $this->resolveProjectByCode($code);

        return $this->renderProjectPage($project, $project->public_share_token);
    }

    private function renderProjectPage(Project $project, string $token): Response
    {
        $brandingConfig = config('branding');

        $project->load([
            'milestones' => function ($q) {
                $q->select('id', 'project_id', 'name', 'description', 'status', 'completion_date')
                    // Public view should only show active/pending work.
                    ->whereNotIn('status', [
                        MilestoneStatus::Approved->value,
                        MilestoneStatus::Rejected->value,
                        MilestoneStatus::Completed->value,
                        MilestoneStatus::Canceled->value,
                        MilestoneStatus::Expired->value,
                    ])
                    ->orderBy('completion_date')
                    ->orderBy('created_at');
            },
            'projectDeliverables' => function ($q) {
                $q->select('id', 'project_id', 'milestone_id', 'name', 'description', 'status', 'due_date', 'details')
                    // Keep public deliverables focused on pending work.
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
            ->map(fn ($d) => [
                'id' => $d->id,
                'milestone_id' => $d->milestone_id,
                'name' => (string) $d->name,
                'description' => $d->description ? (string) $d->description : null,
                'status' => $d->status instanceof \BackedEnum ? $d->status->value : (string) $d->status,
                'due_date' => $d->due_date?->format('d M Y'),
                'checklist' => $this->sanitizeChecklist($d->details),
            ])
            ->values();

        $projectStatus = $project->status instanceof \BackedEnum
            ? $project->status->value
            : (string) $project->status;

        return Inertia::render('Public/ProjectView', [
            'project' => [
                'name'        => $project->name,
                'description' => $project->description,
                'status'      => $projectStatus,
                'token'       => $token,
                'milestones'  => $milestones,
                'deliverables' => $deliverables,
            ],
            'branding' => [
                'company' => [
                    'name' => $brandingConfig['company']['name'] ?? null,
                    'website' => $brandingConfig['company']['website'] ?? null,
                    'logo_url' => ! empty($brandingConfig['company']['logo_url'])
                        ? asset($brandingConfig['company']['logo_url'])
                        : null,
                ],
            ],
        ]);
    }

    private function sanitizeChecklist($details): array
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
                if ($name === '') {
                    return null;
                }

                return [
                    'name' => $name,
                    'completed' => (bool) ($item['completed'] ?? false),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function resolveProjectByToken(string $token): Project
    {
        return Project::query()
            ->where('public_share_token', $token)
            ->where('public_share_enabled', true)
            ->firstOrFail();
    }

    private function resolveProjectByCode(string $code): Project
    {
        return Project::query()
            ->where('public_share_enabled', true)
            ->where('public_share_token', 'like', $code.'%')
            ->firstOrFail();
    }

    /**
     * Send OTP to the provided email.
     */
    public function sendOtp(Request $request, string $token): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate the project token exists and is enabled
        $project = Project::where('public_share_token', $token)
            ->where('public_share_enabled', true)
            ->first();

        if (! $project) {
            return response()->json(['message' => 'This project link is not active.'], 404);
        }

        $this->otpService->generate($request->email, $token);

        return response()->json(['message' => 'Verification code sent to your email.']);
    }

    /**
     * Verify OTP and return a session token.
     */
    public function verifyOtp(Request $request, string $token): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp'   => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $result = $this->otpService->verify($request->email, $request->otp, $token);

        if (! $result) {
            return response()->json(['message' => 'Invalid or expired verification code.'], 422);
        }

        $project = Project::where('public_share_token', $token)
            ->where('public_share_enabled', true)
            ->first();

        if (! $project) {
            return response()->json(['message' => 'This project link is not active.'], 404);
        }

        $guestUser = \App\Models\User::withTrashed()->find($result['guest_user_id']);

        return response()->json([
            'session_token' => $result['session_token'],
            'needs_profile' => $result['needs_profile'],
            'user'          => [
                'name'  => $guestUser->name ?? '',
                'email' => $guestUser->email,
                'phone' => $guestUser->metadata['phone'] ?? '',
            ],
            'latest_proposal' => $this->latestProposalForGuest($project->id, (int) $guestUser->id),
        ]);
    }

    /**
     * Resolve current verified session details and latest proposal for form prefill.
     */
    public function session(Request $request, string $token): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $project = Project::where('public_share_token', $token)
            ->where('public_share_enabled', true)
            ->first();

        if (! $project) {
            return response()->json(['message' => 'This project link is not active.'], 404);
        }

        $guestUser = $this->otpService->resolveGuest($request->session_token, $token);

        if (! $guestUser) {
            return response()->json(['message' => 'Session expired. Please verify your email again.'], 401);
        }

        return response()->json([
            'user' => [
                'name'  => $guestUser->name ?? '',
                'email' => $guestUser->email,
                'phone' => $guestUser->metadata['phone'] ?? '',
            ],
            'latest_proposal' => $this->latestProposalForGuest($project->id, (int) $guestUser->id),
        ]);
    }

    /**
     * Update the guest user's profile (name + phone).
     */
    public function updateProfile(Request $request, string $token): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_token' => 'required|string',
            'name'          => 'required|string|max:255',
            'phone'         => 'required|string|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $guestUser = $this->otpService->resolveGuest($request->session_token, $token);

        if (! $guestUser) {
            return response()->json(['message' => 'Session expired. Please verify your email again.'], 401);
        }

        $metadata = $guestUser->metadata ?? [];
        $metadata['phone'] = $request->phone;

        $guestUser->update([
            'name'     => $request->name,
            'metadata' => $metadata,
        ]);

        return response()->json(['message' => 'Profile saved.', 'user' => ['name' => $guestUser->name, 'phone' => $request->phone]]);
    }

    /**
     * Submit a proposal (creates a ProjectExpendable linked to the guest user).
     */
    public function storeProposal(Request $request, string $token): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_token'  => 'required|string',
            'proposal_scope' => 'required|string|in:milestone,project',
            'milestone_id'   => 'required_if:proposal_scope,milestone|nullable|integer|exists:milestones,id',
            'description'    => 'required|string|min:20|max:5000',
            'amount'         => 'required|numeric|min:1',
            'currency'       => 'required|string|in:PKR,AUD,USD,EUR,GBP,INR',
            'payment_terms'  => 'nullable|string|max:10000',
            'document'       => 'nullable|file|mimes:pdf|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $project = Project::where('public_share_token', $token)
            ->where('public_share_enabled', true)
            ->first();

        if (! $project) {
            return response()->json(['message' => 'This project link is not active.'], 404);
        }

        $guestUser = $this->otpService->resolveGuest($request->session_token, $token);

        if (! $guestUser) {
            return response()->json(['message' => 'Session expired. Please verify your email again.'], 401);
        }

        $proposalScope = $request->string('proposal_scope')->toString();

        $expendableId = $project->id;
        $expendableType = \App\Models\Project::class;

        if ($proposalScope === 'milestone') {
            // Milestone proposals must belong to the shared project.
            $milestone = $project->milestones()->where('id', $request->milestone_id)->first();
            if (! $milestone) {
                return response()->json(['message' => 'Invalid milestone selected.'], 422);
            }

            $expendableId = $milestone->id;
            $expendableType = \App\Models\Milestone::class;
        }

        $expendable = ProjectExpendable::query()
            ->where('project_id', $project->id)
            ->where('user_id', $guestUser->id)
            ->where('status', '!=', \App\Enums\ProjectExpendableStatus::Accepted->value)
            ->latest()
            ->first();

        $attributes = [
            'name'             => $proposalScope === 'project'
                ? 'Whole Project Proposal from '.$guestUser->name
                : 'Milestone Proposal from '.$guestUser->name,
            'description'      => $request->description,
            'currency'         => $request->currency,
            'amount'           => $request->amount,
            'balance'          => $request->amount,
            'payment_terms'    => $request->payment_terms,
            'expendable_id'    => $expendableId,
            'expendable_type'  => $expendableType,
        ];

        if ($expendable) {
            // Temporarily set the auth user so Spatie Activity Log records the guest user as the causer
            // if they are an eloquent model. The OTP service returns a User model.
            $originalUser = auth()->user();
            auth()->setUser($guestUser);
            
            $expendable->update($attributes);
            
            if ($originalUser) {
                auth()->setUser($originalUser);
            } else {
                auth()->logout();
            }
        } else {
            $attributes['project_id'] = $project->id;
            $attributes['user_id'] = $guestUser->id;
            $attributes['status'] = 'Pending Approval';
            $expendable = ProjectExpendable::create($attributes);
        }

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $objectPath = \Illuminate\Support\Facades\Storage::disk('gcs')->putFile('proposals', $file);

            $expendable->files()->create([
                'project_id' => $project->id,
                'filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'path' => $objectPath,
            ]);
        }

        $this->trackInteraction($guestUser->id, $project->id, 'proposal_submitted');

        return response()->json(['message' => 'Your proposal has been submitted successfully. We will review it and get back to you.']);
    }

    /**
     * Track a verified guest opening the public project page or clicking the share link.
     */
    public function track(Request $request, string $token): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_token' => 'required|string',
            'event'         => 'required|string|in:link_open,page_view',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $project = Project::where('public_share_token', $token)
            ->where('public_share_enabled', true)
            ->first();

        if (! $project) {
            return response()->json(['message' => 'This project link is not active.'], 404);
        }

        $guestUser = $this->otpService->resolveGuest($request->session_token, $token);

        if (! $guestUser) {
            return response()->json(['message' => 'Session expired. Please verify your email again.'], 401);
        }

        $this->trackInteraction($guestUser->id, $project->id, $request->event);

        return response()->json(['message' => 'Tracked.']);
    }

    private function trackInteraction(int $userId, int $projectId, string $interactionType): void
    {
        UserInteraction::updateOrCreate(
            [
                'user_id'           => $userId,
                'interactable_id'   => $projectId,
                'interactable_type' => Project::class,
                'interaction_type'  => $interactionType,
            ],
            [
                'updated_at' => now(),
            ]
        );
    }

    private function latestProposalForGuest(int $projectId, int $guestUserId): ?array
    {
        $proposal = ProjectExpendable::query()
            ->where('project_id', $projectId)
            ->where('user_id', $guestUserId)
            // Do not reuse approved proposals as prefill; users should submit a fresh one.
            ->where('status', '!=', ProjectExpendableStatus::Accepted->value)
            ->latest()
            ->first();

        if (! $proposal) {
            return null;
        }

        $isMilestoneProposal = $proposal->expendable_type === \App\Models\Milestone::class;

        return [
            'proposal_scope' => $isMilestoneProposal ? 'milestone' : 'project',
            'milestone_id'   => $isMilestoneProposal ? $proposal->expendable_id : null,
            'description'    => $proposal->description,
            'amount'         => $proposal->amount,
            'currency'       => $proposal->currency,
            'payment_terms'  => $proposal->payment_terms,
        ];
    }
}
