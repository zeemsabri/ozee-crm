<?php

namespace App\Http\Controllers;

use App\Models\MagicLink; // <-- Import the MagicLink model
use Illuminate\Http\Request;
use Inertia\Inertia; // <-- Import Inertia

class ClientDashboardController extends Controller
{
    /**
     * Display the client dashboard.
     *
     * @return \Inertia\Response|\Illuminate\Http\RedirectResponse
     */
    public function index(Request $request)
    {
        $token = $request->query('token');

        // 1. If no token is provided, it's an unauthorized access attempt.
        if (! $token) {
            // Redirect to the home page or show an error.
            return redirect('/');
        }

        // 2. Find the magic link by the token and load its associated project.
        $magicLink = MagicLink::where('token', $token)->with('project')->first();

        // 3. Validate the magic link.
        // The link must exist, not be expired, and have been marked as 'used'.
        // Your `handleMagicLink` method marks it as used, so this check confirms
        // the user came from the valid, signed URL.
        if (! $magicLink || $magicLink->hasExpired() || $magicLink->hasBeenUsed()) {
            // Log this attempt for security monitoring.
            \Illuminate\Support\Facades\Log::warning('Invalid client dashboard access attempt.', [
                'token' => $token,
                'ip' => $request->ip(),
            ]);
            // Show a generic error to the user.
            abort(403, 'This dashboard link is invalid or has expired.');
        }

        // 4. If validation passes, render the Inertia Vue component.
        // We pass the project data and the token to the frontend.
        return Inertia::render('Clients/Dashboard', [
            'magicToken' => $magicLink->token,
            'project' => $magicLink->project,
            'clientEmail' => $magicLink->email,
        ]);
    }
}
