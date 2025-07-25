<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\MagicLinkMail;
use App\Models\MagicLink;
use App\Models\Project;
use App\Services\GmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class MagicLinkController extends Controller
{
    protected GmailService $gmailService;

    /**
     * Create a new controller instance.
     *
     * @param GmailService $gmailService
     */
    public function __construct(GmailService $gmailService)
    {
        $this->gmailService = $gmailService;
    }
    /**
     * Generate and send a magic link to the client.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $projectId
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendMagicLink(Request $request, $projectId)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find the project
            $project = Project::findOrFail($projectId);

            // Check if the project has clients
            if (!$project->clients || !count($project->clients)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This project has no clients associated with it.'
                ], 400);
            }

            // Check if the email belongs to a client of the project
            $clientEmails = collect($project->clients)->pluck('email')->toArray();
            if (!in_array($request->email, $clientEmails)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The provided email does not belong to any client associated with this project.'
                ], 400);
            }

            // Generate a unique token
            $token = Str::random(64);

            // Set expiration time (24 hours from now)
            $expiresAt = now()->addHours(24);

            // Create a new magic link
            $magicLink = MagicLink::create([
                'email' => $request->email,
                'token' => $token,
                'project_id' => $projectId,
                'expires_at' => $expiresAt,
                'used' => false,
            ]);

            // Generate the magic link URL
            $url = URL::temporarySignedRoute(
                'client.magic-link',
                $expiresAt,
                ['token' => $token]
            );

            // Render the email template
            $emailContent = View::make('emails.magic-link', [
                'magicLink' => $magicLink,
                'project' => $project,
                'url' => $url
            ])->render();

            // Send the magic link email using GmailService
            $subject = "Magic Link for {$project->name} Project";
            $this->gmailService->sendEmail($request->email, $subject, $emailContent);

            return response()->json([
                'success' => true,
                'message' => 'Magic link sent successfully to ' . $request->email
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending magic link: ' . $e->getMessage(), [
                'project_id' => $projectId,
                'email' => $request->email ?? 'not provided',
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send magic link: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle the magic link when clicked.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function handleMagicLink(Request $request)
    {
        try {
            // Check if the URL signature is valid
            if (!$request->hasValidSignature()) {
                return response()->view('errors.magic-link', [
                    'message' => 'Invalid or expired magic link.'
                ], 403);
            }

            // Find the magic link by token
            $magicLink = MagicLink::where('token', $request->token)->first();

            if (!$magicLink) {
                return response()->view('errors.magic-link', [
                    'message' => 'Magic link not found.'
                ], 404);
            }

            // Check if the magic link has expired
            if ($magicLink->hasExpired()) {
                return response()->view('errors.magic-link', [
                    'message' => 'This magic link has expired.'
                ], 403);
            }

            // Check if the magic link has been used
            if ($magicLink->hasBeenUsed()) {
                return response()->view('errors.magic-link', [
                    'message' => 'This magic link has already been used.'
                ], 403);
            }

            // Mark the magic link as used
           // $magicLink->markAsUsed();

            // Get the project
            $project = $magicLink->project;

            // Redirect to the client dashboard with the token
            return redirect()->route('client.dashboard', ['token' => $magicLink->token]);
        } catch (\Exception $e) {
            Log::error('Error handling magic link: ' . $e->getMessage(), [
                'token' => $request->token ?? 'not provided',
                'error' => $e->getTraceAsString(),
            ]);

            return response()->view('errors.magic-link', [
                'message' => 'An error occurred while processing your magic link.'
            ], 500);
        }
    }
}
