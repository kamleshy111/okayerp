<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get recent notifications and unread count for header bell component.
     */
    public function headerList(Request $request)
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['notifications' => [], 'unreadCount' => 0]);
        }

        $unreadCount = Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();

        $notifications = Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'type' => $item->type,
                    'title' => $item->title,
                    'message' => $item->message,
                    'action_url' => $item->action_url,
                    'icon' => $item->icon ?: 'bi-bell',
                    'is_read' => (bool)$item->is_read,
                    'created_at_human' => Carbon::parse($item->created_at)->diffForHumans(),
                ];
            });

        return response()->json([
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markRead($id)
    {
        $userId = Auth::id();
        $notification = Notification::where('user_id', $userId)->where('id', $id)->first();

        if ($notification) {
            $notification->update([
                'is_read' => true,
                'read_at' => Carbon::now(),
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read for current user.
     */
    public function markAllRead()
    {
        $userId = Auth::id();
        Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => Carbon::now(),
            ]);

        return response()->json(['success' => true]);
    }

    /**
     * Clear / delete a notification.
     */
    public function destroy($id)
    {
        $userId = Auth::id();
        Notification::where('user_id', $userId)->where('id', $id)->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Register / Store FCM Device Token for Web Push Notifications.
     */
    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
            'device_type' => 'nullable|string',
        ]);

        $user = Auth::user();
        if ($user) {
            \App\Models\UserFcmToken::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'fcm_token' => $request->fcm_token,
                ],
                [
                    'device_type' => $request->device_type ?? 'web',
                    'user_agent' => $request->userAgent(),
                ]
            );

            return response()->json(['success' => true, 'message' => 'FCM Device Token registered successfully.']);
        }

        return response()->json(['success' => false, 'message' => 'User not authenticated.'], 401);
    }
}
