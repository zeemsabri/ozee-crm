<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\MagicLink;
use App\Models\Project;
use App\Services\GmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Inertia\Inertia; // Import Inertia

class MagicLinkController extends Controller
{
    protected GmailService $gmailService;

    /**
     * Create a new controller instance.
     */
    public function __construct(GmailService $gmailService)
    {
        $this->gmailService = $gmailService;
    }

    /**
     * Generate and send a magic link to the client.
     *
     * @param  int  $projectId
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendMagicLink(Request $request, $projectId)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'client_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Find the project
            $project = Project::findOrFail($projectId);

            // Check if the project has clients
            if (! $project->clients || ! count($project->clients)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This project has no clients associated with it.',
                ], 400);
            }

            // Find the client by ID and check if it belongs to the project
            $client = collect($project->clients)->firstWhere('id', $request->client_id);

            if (! $client) {
                return response()->json([
                    'success' => false,
                    'message' => 'The provided client ID does not belong to any client associated with this project.',
                ], 400);
            }

            // Get the client's email
            $clientEmail = $client->email;

            if (! $clientEmail) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected client does not have a valid email address.',
                ], 400);
            }

            // Generate a unique token
            $token = Str::random(64);

            // Set expiration time (24 hours from now)
            $expiresAt = now()->addHours(24);

            // Create a new magic link
            $magicLink = MagicLink::create([
                'email' => $clientEmail,
                'token' => $token,
                'project_id' => $projectId,
                'expires_at' => $expiresAt,
                'used' => false,
            ]);

            // Generate the magic link URL
            // Ensure this route name matches the one defined in web.php for the Vue client dashboard
            $url = URL::temporarySignedRoute(
                'client.magic-link-login', // Changed route name for clarity
                $expiresAt,
                ['token' => $token]
            );

            // Render the email template
            $emailContent = View::make('emails.magic-link', [
                'magicLink' => $magicLink,
                'project' => $project,
                'url' => $url,
            ])->render();

            // Send the magic link email using GmailService
            $subject = "Magic Link for {$project->name} Project";
            $this->gmailService->sendEmail($clientEmail, $subject, $emailContent);

            return response()->json([
                'success' => true,
                'message' => 'Magic link sent successfully to '.$client->name,
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending magic link: '.$e->getMessage(), [
                'project_id' => $projectId,
                'client_id' => $request->client_id ?? 'not provided',
                'email' => isset($clientEmail) ? $clientEmail : 'not found',
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send magic link: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send a magic link to a client email without requiring a specific project.
     * This is used for the client login on the welcome page.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendClientMagicLink(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'url' => 'nullable|url',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please provide a valid email address',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $clientEmail = $request->email;

            // Find projects associated with this client email
            $client = Client::where('email', $clientEmail)->first();

            if (! $client) {
                return response()->json([
                    'success' => false,
                    'message' => 'No client found with this email address.',
                ], 404);
            }

            // Get projects through the many-to-many relationship
            $projects = Project::whereHas('clients', function ($query) use ($client) {
                $query->where('clients.id', $client->id);
            })->get();

            if ($projects->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No projects found associated with this email address.',
                ], 404);
            }

            // Use the first project found (we could potentially send links for all projects)
            $project = $projects->first();

            // Generate a unique token
            $token = Str::random(64);

            // Set expiration time (24 hours from now)
            $expiresAt = now()->addDays(7);

            // Create a new magic link
            $magicLink = MagicLink::create([
                'email' => $clientEmail,
                'token' => $token,
                'project_id' => $project->id,
                'expires_at' => $expiresAt,
                'used' => false,
            ]);

            // --- The Core Change: Conditional URL Generation ---
            if ($request->has('url')) {
                // If a URL is provided in the request, append the token to it
                // We'll generate a temporary signed route for validation,
                // then extract the signature and expires parameter and add it to the user's provided URL.
                $url = URL::temporarySignedRoute(
                    'client.magic-link-login',
                    $expiresAt,
                    ['token' => $token]
                );

                // Extract the signed parameters and append them to the provided URL
                $signedParameters = parse_url($url, PHP_URL_QUERY);
                $baseUrl = $request->input('url');
                $finalUrl = $baseUrl.$token.'&'.$signedParameters;

            } else {
                // Fallback to the default, named route
                $finalUrl = URL::temporarySignedRoute(
                    'client.magic-link-login',
                    $expiresAt,
                    ['token' => $token]
                );
            }

            // Render the email template
            $emailContent = View::make('emails.magic-link', [
                'magicLink' => $magicLink,
                'project' => $project,
                'url' => $finalUrl,
            ])->render();

            // Send the magic link email using GmailService
            $subject = "Magic Link for {$project->name} Project";
            $this->gmailService->sendEmail($clientEmail, $subject, $emailContent);

            return response()->json([
                'success' => true,
                'message' => 'Magic link sent successfully to your email address. Please check your inbox.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending client magic link: '.$e->getMessage(), [
                'email' => $request->email ?? 'not provided',
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send magic link: '.$e->getMessage(),
            ], 500);
        }
    }

    public function handleMagicLink(Request $request)
    {
        try {
            // Check if the URL signature is valid
            if (! $request->hasValidSignature()) {
                return Inertia::render('Errors/MagicLinkError', [ // Use Inertia for error page
                    'message' => 'Invalid or expired magic link.',
                ])->toResponse($request)->setStatusCode(403);
            }

            // Find the magic link by token
            $magicLink = MagicLink::where('token', $request->token)->first();

            if (! $magicLink) {
                return Inertia::render('Errors/MagicLinkError', [ // Use Inertia for error page
                    'message' => 'Magic link not found.',
                ])->toResponse($request)->setStatusCode(404);
            }

            // Check if the magic link has expired
            if ($magicLink->hasExpired()) {
                return Inertia::render('Errors/MagicLinkError', [ // Use Inertia for error page
                    'message' => 'This magic link has expired.',
                ])->toResponse($request)->setStatusCode(403);
            }

            // Check if the magic link has been used
            if ($magicLink->hasBeenUsed()) {
                return Inertia::render('Errors/MagicLinkError', [ // Use Inertia for error page
                    'message' => 'This magic link has already been used.',
                ])->toResponse($request)->setStatusCode(403);
            }

            // Mark the magic link as used (uncomment if you want to use it only once)
            // $magicLink->markAsUsed();

            // Get the project (optional, might be needed for internal checks)
            // $project = $magicLink->project;

            // Fetch all projects associated with this email via project_client or project->client_id
            $email = $magicLink->email;
            $clientProjects = Project::whereHas('clients', function ($query) use ($email) {
                $query->where('email', $email);
            })->orWhereHas('client', function ($query) use ($email) {
                $query->where('email', $email);
            })->select('id', 'name')->get();

            // Render the ClientDashboard Vue component and pass the token and Firebase config as props
            return Inertia::render('ClientDashboard', [
                'initialAuthToken' => $magicLink->token, // Pass the token to the Vue component
                'projectId' => $magicLink->project_id,
                'clientProjects' => $clientProjects,
            ]);

        } catch (\Exception $e) {
            Log::error('Error handling magic link: '.$e->getMessage(), [
                'token' => $request->token ?? 'not provided',
                'error' => $e->getTraceAsString(),
            ]);

            return Inertia::render('Errors/MagicLinkError', [ // Use Inertia for error page
                'message' => 'An error occurred while processing your magic link.',
            ])->toResponse($request)->setStatusCode(500);
        }
    }

    /**
     * Verify a client magic link token and return token and project id
     */
    public function verifyClient(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'token' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $rawToken = $request->input('token');
            // Extract token before query string if present
            $tokenOnly = explode('?', $rawToken)[0];

            $magicLink = MagicLink::where('token', $tokenOnly)->first();
            if (! $magicLink) {
                return response()->json(['message' => 'Invalid magic link token.'], 404);
            }

            if ($magicLink->hasExpired()) {
                return response()->json(['message' => 'Magic link token has expired.'], 403);
            }

            if ($magicLink->hasBeenUsed()) {
                return response()->json(['message' => 'Magic link token has already been used.'], 403);
            }

            return response()->json([
                'auth_token' => $magicLink->token,
                'project_id' => $magicLink->project_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error verifying magic link: '.$e->getMessage(), [
                'token' => $request->input('token') ?? 'not provided',
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Failed to verify magic link.'], 500);
        }
    }

    /**
     * Switch the project for a magic link.
     */
    public function switchProject(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'token' => 'required|string',
                'project_id' => 'required|exists:projects,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $token = $request->input('token');
            $projectId = $request->input('project_id');

            $magicLink = MagicLink::where('token', $token)->first();
            if (! $magicLink) {
                return response()->json(['message' => 'Invalid magic link token.'], 404);
            }

            if ($magicLink->hasExpired()) {
                return response()->json(['message' => 'Magic link token has expired.'], 403);
            }

            // Verify user has access to this project
            $email = $magicLink->email;
            $hasAccess = Project::where('id', $projectId)
                ->where(function ($query) use ($email) {
                    $query->whereHas('clients', function ($q) use ($email) {
                        $q->where('email', $email);
                    })->orWhereHas('client', function ($q) use ($email) {
                        $q->where('email', $email);
                    });
                })->exists();

            if (! $hasAccess) {
                return response()->json(['message' => 'You do not have access to this project.'], 403);
            }

            $magicLink->update(['project_id' => $projectId]);

            return response()->json([
                'success' => true,
                'message' => 'Project switched successfully.',
                'project_id' => $projectId,
            ]);
        } catch (\Exception $e) {
            Log::error('Error switching project: '.$e->getMessage(), [
                'token' => $request->input('token'),
                'project_id' => $request->input('project_id'),
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Failed to switch project.'], 500);
        }
    }
}
