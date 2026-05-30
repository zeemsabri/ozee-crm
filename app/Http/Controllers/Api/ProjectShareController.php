<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ProjectInviteMail;
use App\Models\Project;
use App\Models\ProjectExpendable;
use App\Models\User;
use App\Models\UserInteraction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProjectShareController extends Controller
{
    /**
     * Generate (or regenerate / disable) the public share token for a project.
     */
    public function generateToken(Request $request, Project $project): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $enabled = (bool) $request->enabled;

        if (! $enabled) {
            $project->update(['public_share_enabled' => false]);
            return response()->json(['message' => 'Public sharing disabled.', 'enabled' => false, 'share_url' => null]);
        }

        // Generate a new token (or reuse existing)
        $token = $project->public_share_token ?? Str::random(64);

        $project->update([
            'public_share_token'    => $token,
            'public_share_enabled'  => true,
        ]);

        return response()->json([
            'message'   => 'Share link generated.',
            'enabled'   => true,
            'share_url' => $this->buildPublicShareUrl($project),
        ]);
    }

    /**
     * Regenerate the share token (invalidates previous links).
     */
    public function regenerateToken(Project $project): JsonResponse
    {
        $token = Str::random(64);

        $project->update([
            'public_share_token'   => $token,
            'public_share_enabled' => true,
        ]);

        return response()->json([
            'message'   => 'Share link regenerated.',
            'share_url' => $this->buildPublicShareUrl($project),
        ]);
    }

    /**
     * Get the current share status for a project.
     */
    public function getShareInfo(Project $project): JsonResponse
    {
        return response()->json([
            'enabled'   => (bool) $project->public_share_enabled,
            'share_url' => $project->public_share_enabled && $project->public_share_token
                ? $this->buildPublicShareUrl($project)
                : null,
        ]);
    }

    /**
     * Get suggested recipients for sharing:
     * - Current project team users
     * - Saved contacts from proposal and tracking history (including guests)
     */
    public function recipients(Project $project): JsonResponse
    {
        $teamUsers = $project->users()
            ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.user_type',
                'users.role_id',
                'roles.slug as role_slug',
                'roles.name as role_name'
            )
            ->whereNull('users.deleted_at')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name ?: 'Unknown',
                'email' => $u->email,
                'user_type' => $u->user_type,
                'role_id' => $u->role_id,
                'role_slug' => $u->role_slug,
                'role_name' => $u->role_name,
                'source' => 'project_team',
            ]);

        $proposalUserIds = ProjectExpendable::query()
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        $trackedUserIds = UserInteraction::query()
            ->where('interactable_type', Project::class)
            ->whereIn('interaction_type', ['email_sent', 'email_open', 'link_open', 'proposal_submitted'])
            ->distinct()
            ->pluck('user_id');

        $savedContactIds = $proposalUserIds->merge($trackedUserIds)->unique()->values();

        $savedUsers = User::query()
            ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
            ->whereIn('users.id', $savedContactIds)
            ->whereNull('users.deleted_at')
            ->whereNotNull('users.email')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.user_type',
                'users.role_id',
                'roles.slug as role_slug',
                'roles.name as role_name'
            )
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name ?: 'Unknown',
                'email' => $u->email,
                'user_type' => $u->user_type,
                'role_id' => $u->role_id,
                'role_slug' => $u->role_slug,
                'role_name' => $u->role_name,
                'source' => 'saved_contact',
            ]);

        $recipients = $teamUsers
            ->merge($savedUsers)
            ->unique('id')
            ->sortBy([
                ['source', 'asc'],
                ['name', 'asc'],
            ])
            ->values();

        return response()->json([
            'recipients' => $recipients,
        ]);
    }

    /**
     * Get tracking summary for project sharing and public proposals.
     */
    public function tracking(Project $project): JsonResponse
    {
        $interactions = UserInteraction::query()
            ->with(['user:id,name,email'])
            ->where('interactable_type', Project::class)
            ->where('interactable_id', $project->id)
            ->whereIn('interaction_type', ['email_sent', 'email_open', 'link_open', 'proposal_submitted'])
            ->get();

        $summary = [
            'email_sent' => $interactions->where('interaction_type', 'email_sent')->count(),
            'email_open' => $interactions->where('interaction_type', 'email_open')->count(),
            'link_open' => $interactions->where('interaction_type', 'link_open')->count(),
            'proposal_submitted' => $interactions->where('interaction_type', 'proposal_submitted')->count(),
        ];

        $rows = $interactions
            ->groupBy('user_id')
            ->map(function ($items, $userId) {
                $user = optional($items->first())->user;

                return [
                    'user_id' => (int) $userId,
                    'name' => $user?->name ?: 'Unknown',
                    'email' => $user?->email,
                    'email_sent' => $items->where('interaction_type', 'email_sent')->isNotEmpty(),
                    'email_open' => $items->where('interaction_type', 'email_open')->isNotEmpty(),
                    'link_open' => $items->where('interaction_type', 'link_open')->isNotEmpty(),
                    'proposal_submitted' => $items->where('interaction_type', 'proposal_submitted')->isNotEmpty(),
                    'last_interaction_at' => optional($items->sortByDesc('updated_at')->first())->updated_at,
                ];
            })
            ->values();

        return response()->json([
            'summary' => $summary,
            'rows' => $rows,
        ]);
    }

    /**
     * Send project invite emails to platform users and/or external email addresses.
     */
    public function sendEmail(Request $request, Project $project): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_ids'       => 'sometimes|array',
            'user_ids.*'     => 'integer|exists:users,id',
            'external_emails' => 'sometimes|array',
            'external_emails.*' => 'email|max:255',
            'message'        => 'sometimes|nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (! $project->public_share_enabled || ! $project->public_share_token) {
            return response()->json(['message' => 'Public sharing is not enabled for this project. Please generate a share link first.'], 422);
        }

        $sentCount = 0;
        $customMessage = $request->message;

        // Send to existing platform users
        if (! empty($request->user_ids)) {
            $users = User::whereIn('id', $request->user_ids)->get();
            foreach ($users as $user) {
                Mail::to($user->email)->queue(
                    new ProjectInviteMail($project, $user->name, $user->email, $customMessage)
                );
                // Track the invitation
                $this->recordEmailInvite($user->id, $project->id);
                $sentCount++;
            }
        }

        // Send to external email addresses
        if (! empty($request->external_emails)) {
            foreach ($request->external_emails as $email) {
                // Look up or create a minimal guest user for tracking
                $guestUser = User::withTrashed()->where('email', $email)->first();

                if (! $guestUser) {
                    $guestUser = User::create([
                        'name'      => '',
                        'email'     => $email,
                        'password'  => Hash::make(Str::random(32)),
                        'user_type' => 'guest',
                    ]);
                }

                Mail::to($email)->queue(
                    new ProjectInviteMail($project, $guestUser->name ?: 'there', $email, $customMessage)
                );
                $this->recordEmailInvite($guestUser->id, $project->id);
                $sentCount++;
            }
        }

        if ($sentCount === 0) {
            return response()->json(['message' => 'No recipients specified.'], 422);
        }

        return response()->json(['message' => "Invite emails queued for {$sentCount} recipient(s)."]);
    }

    private function recordEmailInvite(int $userId, int $projectId): void
    {
        UserInteraction::updateOrCreate(
            [
                'user_id'          => $userId,
                'interactable_id'  => $projectId,
                'interactable_type' => Project::class,
                'interaction_type' => 'email_sent',
            ],
            ['updated_at' => now()]
        );
    }

    private function buildPublicShareUrl(Project $project): string
    {
        $slug = Str::slug($project->name ?: 'project');
        $code = substr((string) $project->public_share_token, 0, 12);

        return route('public.projects.pretty', [
            'slug' => $slug,
            'code' => $code,
        ]);
    }
}
