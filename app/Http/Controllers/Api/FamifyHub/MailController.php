<?php

namespace App\Http\Controllers\Api\FamifyHub;

use App\Http\Controllers\Controller;
use App\Mail\FamifyContactMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MailController extends Controller
{
    /**
     * Public endpoint: Receive contact form data from Famify website
     * and forward it to hello@famifyhub.com.au using the famify_smtp mailer.
     */
    public function submit(Request $request)
    {
        // Validate incoming data with conditional rules for the two user types
        $validator = Validator::make($request->all(), [
            // Existing relaxed fields (kept for compatibility)
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'subject' => 'sometimes|string|max:255',
            'message' => 'sometimes|string',
            'phone' => 'sometimes|string|max:50',
            'company' => 'sometimes|string|max:255',

            // New fields for Famify website dynamic form
            'userType' => 'required|string|in:Parent,Content Creator',
            'parentGoal' => 'nullable|string|max:255|required_if:userType,Parent',
            'childAge' => 'nullable|string|max:255|required_if:userType,Parent',
            'creatorGoal' => 'nullable|string|max:255|required_if:userType,Content Creator',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $toEmail = 'hello@famifyhub.com.au';
        $subject = 'New ' . ($data['userType'] ?? 'Website') . ' form submission — Famify Website';

        // Build a contextual message based on user type
        $summaryMessage = null;
        if (($data['userType'] ?? null) === 'Parent') {
            $pg = trim((string)($data['parentGoal'] ?? ''));
            $ca = trim((string)($data['childAge'] ?? ''));
            $parts = [];
            if ($pg !== '') { $parts[] = "Goal: $pg"; }
            if ($ca !== '') { $parts[] = "Child age: $ca"; }
            $summaryMessage = 'A Parent submitted the form' . (count($parts) ? ' — ' . implode('; ', $parts) : '.') ;
        } elseif (($data['userType'] ?? null) === 'Content Creator') {
            $cg = trim((string)($data['creatorGoal'] ?? ''));
            $summaryMessage = 'A Content Creator submitted the form' . ($cg !== '' ? ' — Goal: ' . $cg : '.') ;
        }

        // Prefer existing message if provided, otherwise use our contextual summary
        if (empty($data['message']) && !empty($summaryMessage)) {
            $data['message'] = $summaryMessage;
        }

        // Prepare key/value collection from all submitted fields, filtering empty values
        $allFields = collect($request->all())
            ->filter(function ($value) {
                if (is_null($value)) return false;
                if (is_string($value)) return trim($value) !== '';
                if (is_array($value)) return count($value) > 0;
                if (is_object($value)) return count((array)$value) > 0;
                return true;
            })
            ->map(function ($value, $key) {
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }
                return [
                    'key' => Str::title(e((string) $key)),
                    'value' => e((string) $value),
                ];
            })
            ->values()
            ->all();

        try {
            // Use the dedicated Famify SMTP mailer with a Mailable + Blade view
            $mailer = Mail::mailer('famify_smtp')->to($toEmail);
            if (!empty($data['email'])) {

                // Send a thank-you email to the submitter
                try {
                    Mail::mailer('famify_smtp')
                        ->to($data['email'])
                        ->send(new \App\Mail\FamifyThankYouMail(
                            $data['userType'] ?? 'Website',
                            $data['name'] ?? null,
                            $data['parentGoal'] ?? null,
                            $data['childAge'] ?? null,
                            $data['creatorGoal'] ?? null
                        ));
                } catch (\Throwable $te) {
                    // Don't fail the main flow if the thank-you email fails
                    Log::warning('Failed to send Famify thank-you email: ' . $te->getMessage(), [
                        'email' => $data['email'],
                    ]);
                }
            }

            $mailer->send(new FamifyContactMail($subject, $data, $allFields));

            return response()->json([
                'success' => true,
                'message' => 'Your message has been sent successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Famify contact form email failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send your message. Please try again later.',
            ], 500);
        }
    }

    /**
     * Public endpoint: Simple contact form
     * Accepts: name, email, subject, message, source (optional)
     * Sends to hello@famifyhub.com.au using famify_smtp without userType logic.
     */
    public function contactForm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'source' => 'sometimes|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $toEmail = 'hello@famifyhub.com.au';

        // Subject: prefer provided subject, add suffix for clarity
        $subject = ($data['subject'] ?? 'New contact message') . ' — Famify Website';

        // Build fields list, include only non-empty values
        $allFields = collect($request->all())
            ->filter(function ($value) {
                if (is_null($value)) return false;
                if (is_string($value)) return trim($value) !== '';
                if (is_array($value)) return count($value) > 0;
                if (is_object($value)) return count((array)$value) > 0;
                return true;
            })
            ->map(function ($value, $key) {
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }
                return [
                    'key' => Str::title(e((string) $key)),
                    'value' => e((string) $value),
                ];
            })
            ->values()
            ->all();

        try {
            $mailer = Mail::mailer('famify_smtp')->to($toEmail);

            $mailer->send(new FamifyContactMail($subject, $data, $allFields));

            return response()->json([
                'success' => true,
                'message' => 'Your message has been sent successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Famify simple contact form email failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send your message. Please try again later.',
            ], 500);
        }
    }

}
