<?php

namespace App\Http\Controllers;

use App\Models\NoticeBoard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NoticeBoardController extends Controller
{
    /**
     * Accept a notice ID, log user interactions (read + click), and redirect to the final URL.
     */
    public function redirect(NoticeBoard $notice, Request $request)
    {
        // If there is no URL saved for this notice, return 400
        if (empty($notice->url)) {
            return response()->json(['message' => 'No URL for this notice'], 400);
        }

        $user = $request->user();
        $now = now();

        // Log both 'read' and 'click' interactions. Unique index prevents duplicates.
        foreach (['read', 'click'] as $type) {
            try {
                DB::table('user_interactions')->insert([
                    'user_id' => $user->id,
                    'interactable_id' => $notice->id,
                    'interactable_type' => NoticeBoard::class,
                    'interaction_type' => $type,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Ignore duplicates due to unique constraint
            }
        }

        return redirect()->away($notice->url);
    }
}
