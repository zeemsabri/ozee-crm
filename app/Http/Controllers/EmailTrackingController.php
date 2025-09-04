<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\NoticeBoard;
use App\Models\User;
use App\Models\UserInteraction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class EmailTrackingController extends Controller
{
    /**
     * Handles the email tracking pixel request.
     *
     * @param int $id The ID of the email to track.
     * @return \Illuminate\Http\Response
     */
    public function track(int $id)
    {
        try {
            // Find the email by its ID
            $email = Email::find($id);

            // If the email exists and hasn't been read yet, update the timestamp
            if ($email && !$email->read_at) {
                $email->read_at = Carbon::now();
                $email->save();
            }

            // Return a 1x1 transparent GIF to the client
            $pixel = base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAQUAAAALAAAAAABAAEAAAICRAEAOw==');

            return new Response($pixel, 200, [
                'Content-Type' => 'image/gif',
                'Content-Length' => strlen($pixel),
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);

        } catch (\Exception $e) {
            // Log any errors but still return a pixel to prevent errors on the client side
            Log::error('Error tracking email open event: ' . $e->getMessage(), ['email_id' => $id]);
            $pixel = base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAQUAAAALAAAAAABAAEAAAICRAEAOw==');
            return new Response($pixel, 200, ['Content-Type' => 'image/gif']);
        }
    }

    public function notice(int $id, string|null $email)
    {

        try {
            // Find the email by its ID
            $notice = NoticeBoard::find($id);
            $user = User::where('email', $email)->first();
            // If the email exists and hasn't been read yet, update the timestamp
            if ($notice && $user) {

                UserInteraction::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'interactable_id' => $notice->id,
                        'interactable_type' => NoticeBoard::class,
                        'interaction_type' => 'email_open'
                    ], [
                        'interaction_type' => 'email_open',
                        'updated_at'    =>  NOW()
                    ]
                );
            }

            // Return a 1x1 transparent GIF to the client
            $pixel = base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAQUAAAALAAAAAABAAEAAAICRAEAOw==');

            return new Response($pixel, 200, [
                'Content-Type' => 'image/gif',
                'Content-Length' => strlen($pixel),
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);

        } catch (\Exception $e) {
            // Log any errors but still return a pixel to prevent errors on the client side
            Log::error('Error tracking email open event: ' . $e->getMessage(), ['email_id' => $id]);
            $pixel = base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAQUAAAALAAAAAABAAEAAAICRAEAOw==');
            return new Response($pixel, 200, ['Content-Type' => 'image/gif']);
        }
    }
}
