<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Models\UserInteraction;
use App\Services\OtpService;
use App\Services\PortalAccessService;
use App\Services\PortalProfileService;
use App\Services\PortalProjectPresenter;
use App\Services\PortalSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The front door of the supplier portal: the link people receive by email.
 *
 * This controller's whole job is to get someone identified. It renders the project
 * brief with a sign-in panel, sends and checks the emailed code, and then hands over to
 * PortalController — which owns every signed-in URL.
 *
 * Once verified, the session token goes into an HttpOnly cookie (see
 * PortalSessionService) rather than staying in the browser's localStorage, which is
 * what allows the portal to have real server-rendered URLs instead of one page
 * switching views client-side.
 */
class PublicProjectController extends Controller
{
    public function __construct(
        private OtpService $otpService,
        private PortalSessionService $portalSession,
        private PortalAccessService $access,
        private PortalProjectPresenter $presenter,
        private PortalProfileService $profiles,
    ) {}

    /**
     * The share code is the first 12 characters of `projects.public_share_token`
     * (see ProjectShareController::buildPublicShareUrl). Anything shorter is refused —
     * a 1-character prefix would otherwise match the first enabled project and let
     * anyone walk the alphabet to enumerate live projects.
     */
    private const MIN_SHARE_CODE_LENGTH = 12;

    /**
     * Raw-token form of the share link.
     */
    public function show(Request $request, string $token): Response|RedirectResponse
    {
        return $this->entryPoint($request, $this->resolveProjectByShareCode($token));
    }

    /**
     * Friendly form of the share link — this is what the invite emails contain.
     */
    public function showPretty(Request $request, string $slug, string $code): Response|RedirectResponse
    {
        return $this->entryPoint($request, $this->resolveProjectByShareCode($code));
    }

    /**
     * Already identified and allowed in? Go straight to the real project URL. Otherwise
     * show the brief with a sign-in panel.
     */
    private function entryPoint(Request $request, Project $project): Response|RedirectResponse
    {
        $user = $this->portalSession->resolve($request);

        if ($user && $this->access->canAccess($user, $project)) {
            return redirect()->route('portal.projects.show', $project);
        }

        return Inertia::render('React/Portal/Project', [
            // The 12-char code, not the token — enough for the sign-in calls below,
            // and useless for anything else.
            'project'        => $this->presenter->project($project, $this->shareCodeFor($project)),
            // Signed out: no personal data, and the page renders its sign-in panel.
            'account'        => null,
            'proposals'      => [],
            'paymentMethods' => [],
            'branding'       => $this->presenter->branding(),
            'currencies'     => PortalProjectPresenter::CURRENCIES,
        ]);
    }

    /**
     * Resolve a project from either the full 64-character share token or the 12-character
     * code the invite emails use.
     *
     * Two guards, both load-bearing:
     *  - a minimum length, so a short prefix can't match an arbitrary project;
     *  - escaped LIKE wildcards, so `%` can't match everything.
     */
    private function resolveProjectByShareCode(string $code): Project
    {
        abort_if(strlen($code) < self::MIN_SHARE_CODE_LENGTH, 404);

        $query = Project::query()->where('public_share_enabled', true);

        if (strlen($code) === 64) {
            $query->where('public_share_token', $code);
        } else {
            $query->where('public_share_token', 'like', addcslashes($code, '%_\\').'%');
        }

        return $query->firstOrFail();
    }

    private function findSharedProject(string $code): ?Project
    {
        if (strlen($code) < self::MIN_SHARE_CODE_LENGTH) {
            return null;
        }

        $query = Project::query()->where('public_share_enabled', true);

        if (strlen($code) === 64) {
            $query->where('public_share_token', $code);
        } else {
            $query->where('public_share_token', 'like', addcslashes($code, '%_\\').'%');
        }

        return $query->first();
    }

    private function shareCodeFor(Project $project): ?string
    {
        return $project->public_share_token
            ? substr((string) $project->public_share_token, 0, self::MIN_SHARE_CODE_LENGTH)
            : null;
    }

    /**
     * Email a 6-digit code for this project's share link.
     */
    public function sendOtp(Request $request, string $token): JsonResponse
    {
        $validator = Validator::make($request->all(), ['email' => 'required|email|max:255']);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $project = $this->findSharedProject($token);

        if (! $project) {
            return response()->json(['message' => 'This project link is not active.'], 404);
        }

        // Store the canonical 64-character token, not whatever length of it was in the
        // URL. `otp_verifications.project_token` is what PortalAccessService matches a
        // project by, and the invite links only carry the first 12 characters.
        $this->otpService->generate($request->email, (string) $project->public_share_token);

        return response()->json(['message' => 'Verification code sent to your email.']);
    }

    /**
     * Check the code, start a portal session, and say where to go next.
     *
     * The email is matched against `users`, so someone who already has an account here
     * is recognised as themselves rather than as a new guest — that is what makes the
     * All projects page able to show their team projects too.
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

        $project = $this->findSharedProject($token);

        if (! $project) {
            return response()->json(['message' => 'This project link is not active.'], 404);
        }

        // OtpService keeps no attempt counter, and the per-route throttle is per IP —
        // so a 6-digit code with a 10-minute life is brute-forceable from a handful of
        // addresses. Count attempts against the target instead of the caller.
        $canonical = (string) $project->public_share_token;
        $attemptKey = 'otp-verify:'.sha1(mb_strtolower($request->email).'|'.$canonical);

        if (RateLimiter::tooManyAttempts($attemptKey, 5)) {
            return response()->json([
                'message' => 'Too many attempts on that code. Request a new one in a few minutes.',
            ], 429);
        }

        // Codes issued before the token was canonicalised are keyed by the short code
        // that was in the URL, so fall back to it — otherwise deploying this would
        // invalidate every code already sitting in someone's inbox.
        $result = $this->otpService->verify($request->email, $request->otp, $canonical);

        if (! $result && $token !== $canonical) {
            $result = $this->otpService->verify($request->email, $request->otp, $token);
        }

        if (! $result) {
            RateLimiter::hit($attemptKey, 600);

            return response()->json(['message' => 'Invalid or expired verification code.'], 422);
        }

        RateLimiter::clear($attemptKey);

        // Not withTrashed: a soft-deleted account must not be able to mint a fresh
        // 30-day session, which would otherwise outlive the offboarding entirely.
        $user = User::query()->whereKey($result['guest_user_id'])->first();

        if (! $user) {
            return response()->json(['message' => 'That account is no longer active.'], 403);
        }

        $this->trackInteraction((int) $user->getKey(), $project->id, 'link_open');

        return response()->json([
            'message'     => 'Verified.',
            'needs_profile' => $result['needs_profile'],
            'account'     => $this->profiles->present($user),
            // Where the browser should go now that a session exists.
            'redirect_to' => route('portal.projects.show', $project),
        ])->withCookie($this->portalSession->cookieFor($result['session_token']));
    }

    private function trackInteraction(int $userId, int $projectId, string $interactionType): void
    {
        UserInteraction::firstOrCreate([
            'user_id'           => $userId,
            'interactable_id'   => $projectId,
            'interactable_type' => Project::class,
            'interaction_type'  => $interactionType,
        ])->touch();
    }
}
