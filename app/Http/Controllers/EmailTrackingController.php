<?php

namespace App\Http\Controllers;

use App\Models\Email;
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

            Log::info('Email tracking event', ['email_id' => $id]);;
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
}
