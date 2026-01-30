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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
use App\Models\LoginAttempt;
use Carbon\Carbon;
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
            $genericSuccessResponse = response()->json([
                'success' => true,
                'message' => 'Magic link and temporary PIN sent to your email. You can login with the temporary PIN or click the magic link in your email.',
            ]);

            // Find projects associated with this client email
            $client = Client::where('email', $clientEmail)->first();

            // If no client, return generic success to prevent email enumeration
            if (! $client) {
                Log::info('Magic link request: email not found in clients table.', ['email' => $clientEmail]);
                return $genericSuccessResponse;
            }

            // Get projects through direct client_id OR many-to-many relationship
            $projects = Project::where(function ($query) use ($client) {
                $query->where('client_id', $client->id)
                    ->orWhereHas('clients', function ($q) use ($client) {
                        $q->where('clients.id', $client->id);
                    });
            })->get();

            // If no projects, return generic success to prevent email enumeration
            if ($projects->isEmpty()) {
                Log::info('Magic link request: no projects associated with client.', ['email' => $clientEmail, 'client_id' => $client->id]);
                return $genericSuccessResponse;
            }

            // Use the first project found
            $project = $projects->first();

            // Generate a unique token
            $token = Str::random(64);

            // Generate a 6-digit temporary PIN
            $temporaryPin = (string) rand(100000, 999999);
            
            // Set expiration time (24 hours for token, 15 mins for PIN)
            $expiresAt = now()->addDays(7);
            $tempPinExpiresAt = now()->addMinutes(15);

            // Create a new magic link
            $magicLink = MagicLink::create([
                'email' => $clientEmail,
                'token' => $token,
                'temporary_pin' => Hash::make($temporaryPin),
                'temp_pin_expires_at' => $tempPinExpiresAt,
                'project_id' => $project->id,
                'expires_at' => $expiresAt,
                'used' => false,
            ]);

            // --- The Core Change: Conditional URL Generation ---
            if ($request->has('url')) {
                $url = URL::temporarySignedRoute(
                    'client.magic-link-login',
                    $expiresAt,
                    ['token' => $token]
                );

                $signedParameters = parse_url($url, PHP_URL_QUERY);
                $baseUrl = $request->input('url');
                $finalUrl = $baseUrl.$token.'&'.$signedParameters;

            } else {
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
                'temporaryPin' => $temporaryPin, // Passing plain text PIN to email
            ])->render();

            // Send the magic link email using GmailService
            $subject = "Magic Link for {$project->name} Project";
            $this->gmailService->sendEmail($clientEmail, $subject, $emailContent);

            return $genericSuccessResponse;
        } catch (\Exception $e) {
            Log::error('Error sending client magic link: '.$e->getMessage(), [
                'email' => $request->email ?? 'not provided',
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while sending the magic link.',
            ], 500);
        }
    }

    public function handleMagicLink(Request $request)
    {
        try {
            $token = $request->token;
            $magicLink = MagicLink::where('token', $token)->first();

            // Check if the URL signature is valid
            if (! $request->hasValidSignature()) {
                // If signature is invalid, we check if we can identify the client via the token
                $hasPin = false;
                $email = null;

                if ($magicLink) {
                    $email = $magicLink->email;
                    $client = Client::where('email', $email)->first();
                    $hasPin = $client && ! empty($client->pin);
                }

                return Inertia::render('Errors/MagicLinkError', [
                    'message' => 'Invalid or expired magic link.',
                    'token' => $token,
                    'email' => $email,
                    'hasPin' => $hasPin,
                ]);
            }

            if (! $magicLink) {
                return Inertia::render('Errors/MagicLinkError', [
                    'message' => 'Magic link not found.',
                ]);
            }

            // Check if the magic link has expired
            if ($magicLink->hasExpired()) {
                $email = $magicLink->email;
                $client = Client::where('email', $email)->first();
                $hasPin = $client && ! empty($client->pin);

                return Inertia::render('Errors/MagicLinkError', [
                    'message' => 'This magic link has expired.',
                    'token' => $token,
                    'email' => $email,
                    'hasPin' => $hasPin,
                ]);
            }

            // Check if the magic link has been used
            if ($magicLink->hasBeenUsed()) {
                return Inertia::render('Errors/MagicLinkError', [
                    'message' => 'This magic link has already been used.',
                ]);
            }

            // Fetch all projects associated with this email
            $email = $magicLink->email;
            $client = Client::where('email', $email)->first();

            $clientProjects = Project::whereHas('clients', function ($query) use ($email) {
                $query->where('email', $email);
            })->orWhereHas('client', function ($query) use ($email) {
                $query->where('email', $email);
            })->select('id', 'name')->get();

            // Render the ClientDashboard Vue component
            return Inertia::render('ClientDashboard', [
                'initialAuthToken' => $magicLink->token,
                'projectId' => $magicLink->project_id,
                'clientProjects' => $clientProjects,
                'clientHasPin' => $client && ! empty($client->pin),
            ]);

        } catch (\Exception $e) {
            Log::error('Error handling magic link: '.$e->getMessage(), [
                'token' => $request->token ?? 'not provided',
                'error' => $e->getTraceAsString(),
            ]);

            return Inertia::render('Errors/MagicLinkError', [
                'message' => 'An error occurred while processing your magic link.',
            ]);
        }
    }

    /**
     * Set up a PIN for the client.
     */
    public function setupPin(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'token' => 'required|string',
                'pin' => 'required|string|min:4|max:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $magicLink = MagicLink::where('token', $request->token)->first();
            if (! $magicLink) {
                return response()->json(['success' => false, 'message' => 'Invalid session.'], 403);
            }

            $client = Client::where('email', $magicLink->email)->first();
            if (! $client) {
                return response()->json(['success' => false, 'message' => 'Client not found.'], 404);
            }

            $client->update(['pin' => Hash::make($request->pin)]);

            return response()->json([
                'success' => true,
                'message' => 'PIN set up successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error setting up PIN: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to set up PIN.'], 500);
        }
    }

    /**
     * Check if a client exists and has a PIN setup.
     */
    public function checkStatus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Valid email is required.'], 422);
            }

            // For security, we always return the same interface even if client doesn't exist
            // This prevents email enumeration. The frontend will show PIN input or magic link option
            // without confirming if the user has a PIN until they actually try to use it.
            return response()->json([
                'success' => true,
                'message' => 'Profile status retrieved.',
                // We'll tell the frontend to show PIN input for everyone to hide who has a PIN
                'showPin' => true,
                'email' => $request->email,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'An error occurred.'], 500);
        }
    }

    /**
     * Verify client via PIN and return token data.
     */
    public function verifyPin(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'token' => 'nullable|string',
                'email' => 'nullable|email',
                'pin' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                ], 422);
            }

            $email = $request->email;
            $ip = $request->ip();

            // If token is provided, get email from magic link
            if ($request->filled('token')) {
                $magicLink = MagicLink::where('token', $request->token)->first();
                if ($magicLink) {
                    $email = $magicLink->email;
                }
            }

            if (! $email) {
                return response()->json(['success' => false, 'message' => 'Valid session identifier required.'], 422);
            }

            // Check rate limiting
            $lockoutRemaining = $this->getLockoutDuration($email, $ip, 'pin');
            if ($lockoutRemaining > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many failed attempts.',
                    'lockout_seconds' => $lockoutRemaining,
                ], 429);
            }

            $client = Client::where('email', $email)->first();
            $isPermanentPinValid = $client && ! empty($client->pin) && Hash::check($request->pin, $client->pin);
            
            // Check for temporary PIN if permanent one is invalid or doesn't exist
            $isTemporaryPinValid = false;
            $activeMagicLink = null;
            
            if (! $isPermanentPinValid) {
                $activeMagicLinks = MagicLink::where('email', $email)
                    ->where('temp_pin_expires_at', '>', now())
                    ->whereNotNull('temporary_pin')
                    ->get();

                foreach ($activeMagicLinks as $ml) {
                    if (Hash::check($request->pin, $ml->temporary_pin)) {
                        $isTemporaryPinValid = true;
                        $activeMagicLink = $ml;
                        break;
                    }
                }
            }

            $successful = $isPermanentPinValid || $isTemporaryPinValid;
            $this->recordLoginAttempt($email, $ip, 'pin', $successful);

            if (! $successful) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Invalid PIN. Please try again.',
                ], 403);
            }

            // Client exists if we got here (either valid permanent or temp PIN)
            if (! $client) {
                 $client = Client::where('email', $email)->first();
            }

            // Find or create a magic link token if we used permanent PIN
            if ($isPermanentPinValid) {
                $project = Project::whereHas('clients', function ($query) use ($client) {
                    $query->where('clients.id', $client->id);
                })->first();

                if (! $project) {
                    return response()->json(['success' => false, 'message' => 'No projects associated with this account.'], 403);
                }

                $activeMagicLink = MagicLink::where('email', $email)
                    ->where('expires_at', '>', now())
                    ->first();

                if (! $activeMagicLink) {
                    $activeMagicLink = MagicLink::create([
                        'email' => $email,
                        'token' => Str::random(64),
                        'project_id' => $project->id,
                        'expires_at' => now()->addDays(7),
                        'used' => false,
                    ]);
                }
            } else {
                // If we used a temporary PIN, clear it so it's one-time use
                $activeMagicLink->update([
                    'temporary_pin' => null,
                    'temp_pin_expires_at' => null
                ]);
            }

            // Generate a signed URL for the dashboard
            $signedUrl = URL::temporarySignedRoute(
                'client.magic-link-login',
                now()->addDays(7),
                ['token' => $activeMagicLink->token]
            );

            return response()->json([
                'success' => true,
                'auth_token' => $activeMagicLink->token,
                'project_id' => $activeMagicLink->project_id,
                'redirect_url' => $signedUrl,
                'used_temporary_pin' => $isTemporaryPinValid, // Flag to force PIN reset
            ]);
        } catch (\Exception $e) {
            Log::error('Error verifying PIN: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'An error occurred.'], 500);
        }
    }

    /**
     * Resend a new magic link.
     */
    public function resendMagicLink(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Valid email is required.'], 422);
            }

            // Reuse the existing sendClientMagicLink logic
            return $this->sendClientMagicLink($request);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to resend link.'], 500);
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

    /**
     * Record a login attempt.
     */
    protected function recordLoginAttempt(string $email, string $ip, string $type, bool $successful)
    {
        LoginAttempt::create([
            'email' => $email,
            'ip_address' => $ip,
            'attempt_type' => $type,
            'successful' => $successful,
            'attempted_at' => now(),
        ]);
    }

    /**
     * Check if the user is rate limited and return the lockout duration in seconds.
     */
    protected function getLockoutDuration(string $email, string $ip, string $type): int
    {
        $maxAttempts = 5;
        $lockoutMinutes = 15;

        $failedAttempts = LoginAttempt::forEmail($email)
            ->forIp($ip)
            ->ofType($type)
            ->failed()
            ->recent($lockoutMinutes)
            ->count();

        if ($failedAttempts >= $maxAttempts) {
            $lastAttempt = LoginAttempt::forEmail($email)
                ->forIp($ip)
                ->ofType($type)
                ->failed()
                ->latest('attempted_at')
                ->first();

            if ($lastAttempt) {
                $lockoutExpires = $lastAttempt->attempted_at->addMinutes($lockoutMinutes);
                $remaining = now()->diffInSeconds($lockoutExpires, false);
                return $remaining > 0 ? (int) $remaining : 0;
            }
        }

        return 0;
    }
}
