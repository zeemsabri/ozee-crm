<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\LeadIntakeSubmitted;
use App\Models\Lead;
use App\Models\Presentation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PublicLeadIntakeController extends Controller
{
    /**
     * Public endpoint to accept lead submissions from the public presenter.
     * - Upserts a Lead by email
     * - Known columns mapped to Lead fields; unknown keys merged into metadata JSON
     * - Notifies all users who have permission 'receive_lead_emails'
     */
    public function store(Request $request)
    {
        $data = $request->all();

        // Basic validation – require email coming from the form
        $validated = $request->validate([
            'email' => 'required|email',
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'message' => 'nullable|string',
        ]);

        // Map incoming payload to Lead columns
        $email = $validated['email'];
        $fullName = trim((string)($validated['name'] ?? ''));
        $firstName = '';
        $lastName = '';
        if ($fullName !== '') {
            $parts = preg_split('/\s+/', $fullName, -1, PREG_SPLIT_NO_EMPTY);
            if ($parts) {
                $firstName = array_shift($parts);
                $lastName = trim(implode(' ', $parts));
            }
        }

        // Find existing by email (including soft-deleted)
        $lead = Lead::withTrashed()->where('email', $email)->first();
        if ($lead && $lead->trashed()) {
            $lead->restore();
        }

        $knownUpdates = array_filter([
            'first_name' => $firstName ?: ($lead->first_name ?? null),
            'last_name'  => $lastName ?: ($lead->last_name ?? null),
            'email'      => $email,
            'phone'      => $validated['phone'] ?? ($lead->phone ?? null),
            'company'    => $validated['company_name'] ?? ($lead->company ?? null),
            'address'    => $validated['address'] ?? ($lead->address ?? null),
            'notes'      => $validated['message'] ?? ($lead->notes ?? null),
            'source'     => 'public_presenter',
            'status'     => Presentation::QUALIFIED
        ], fn($v) => $v !== null && $v !== '');

        // Build metadata from extra keys not mapped
        $reserved = ['name','email','phone','company_name','address','message'];
        $extras = collect($data)
            ->filter(fn($v, $k) => !in_array($k, $reserved, true))
            ->all();

        if ($lead) {
            $lead->fill($knownUpdates);
            // Merge metadata
            $meta = is_array($lead->metadata) ? $lead->metadata : [];
            $lead->metadata = array_replace($meta, $extras);
            $lead->save();
        } else {
            $lead = Lead::create(array_merge($knownUpdates, [
                'metadata' => $extras,
            ]));
        }

        // Notify recipients with permission 'receive_lead_emails'
        try {
            $recipients = User::permission('receive_lead_emails')->get();
        } catch (\Throwable $e) {
            // If spatie/permission is not installed or trait not applied, fallback to admins
            Log::info('User::permission not available or failed: '.$e->getMessage());
            $recipients = User::where('id', 1)->get();
        }

        if ($recipients && $recipients->count() > 0) {
            foreach ($recipients as $user) {
                try {
                    Mail::to($user->email)->send(new LeadIntakeSubmitted($lead, $data));
                } catch (\Throwable $e) {
                    Log::warning('Lead intake mail failed for '.$user->email.': '.$e->getMessage());
                }
            }
        }

        return response()->json([
            'message' => 'Thanks! Your details have been received. We will contact you shortly.',
            'lead' => $lead->fresh(),
        ], $lead->wasRecentlyCreated ? 201 : 200);
    }
}
