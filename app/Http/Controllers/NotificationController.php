<?php

namespace App\Http\Controllers;

use App\Http\Resources\Notifications;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated user.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        // ordered by creation date (latest first). Filter out empty notifications (garbage from grouping).
        $notifications = Auth::user()->notifications()
            ->whereNotNull('data->view_id')
            ->orderBy('created_at', 'desc')
            ->get();

        return Notifications::collection($notifications);
    }

    /**
     * Mark a specific notification as read.
     *
     * @param  string  $notificationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead($notificationId)
    {
        $user = Auth::user();
        $notification = $user->notifications()->where('id', $notificationId)->first();

        if ($notification) {
            $notification->markAsRead();

            return Response::json(['message' => 'Notification marked as read.']);
        }

        return Response::json(['error' => 'Notification not found.'], 404);
    }

    /**
     * Delete a specific notification.
     *
     * @param  string  $notificationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($notificationId)
    {
        $user = Auth::user();
        $notification = $user->notifications()->where('id', $notificationId)->first();

        if ($notification) {
            $notification->delete();

            return Response::json(['message' => 'Notification deleted successfully.']);
        }

        return Response::json(['error' => 'Notification not found.'], 404);
    }

    /**
     * Mark a specific notification as read by its view_id.
     *
     * @param  string  $viewId
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsReadByViewId($viewId)
    {
        $user = Auth::user();

        // Find the notification where the 'data' column contains the matching 'view_id'
        // First try unread
        $notification = $user->unreadNotifications()->where('data->view_id', $viewId)->first();

        if (!$notification) {
            // Fallback to searching all (maybe already read)
            $notification = $user->notifications()->where('data->view_id', $viewId)->first();
            
            if ($notification && $notification->read_at) {
                return Response::json(['message' => 'Notification already read.']);
            }
        }

        if ($notification) {
            $notification->markAsRead();
            return Response::json(['message' => 'Notification marked as read.']);
        }

        return Response::json(['error' => 'Notification not found.'], 404);
    }

    /**
     * Mark all notifications as read for the authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAllAsRead()
    {
        Auth::user()->unreadNotifications->markAsRead();

        return Response::json(['message' => 'All notifications marked as read.']);
    }
}
