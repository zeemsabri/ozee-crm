<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use App\Http\Resources\Notifications;
class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated user.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        // Get all unread and read notifications for the current user,
        // ordered by creation date (latest first).
        $notifications = Auth::user()->notifications()->orderBy('created_at', 'desc')->get();

        return Notifications::collection($notifications);
    }

    /**
     * Mark a specific notification as read.
     *
     * @param string $notificationId
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
     * @param string $notificationId
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
     * @param string $viewId
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsReadByViewId($viewId)
    {
        $user = Auth::user();

        // Find the notification where the 'data' column contains the matching 'view_id'
        $notification = $user->unreadNotifications()->where('data->view_id', $viewId)->first();

        if ($notification) {
            $notification->markAsRead();
            return Response::json(['message' => 'Notification marked as read.']);
        }

        return Response::json(['error' => 'Notification not found or already read.'], 404);
    }
}

