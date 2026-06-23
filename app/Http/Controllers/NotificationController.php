<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function unread(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Unauthenticated'], 401);
        }

        $unreadNotifications = $user->unreadNotifications()->get();

        $notificationsData = $unreadNotifications->map(function ($notification) {
            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'data' => $notification->data,
                'created_at' => $notification->created_at->diffForHumans(),
            ];
        });

        return response()->json([
            'ok' => true,
            'has_unread' => $unreadNotifications->isNotEmpty(),
            'total_unread' => $unreadNotifications->count(),
            'notifications' => $notificationsData,
        ]);
    }

    public function markAsRead(Request $request, $id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        $url = $notification->data['url'] ?? route('dashboard');
        return redirect($url);
    }

    public function markAllAsRead(Request $request)
    {
        Auth::user()->unreadNotifications->markAsRead();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Toutes les notifications ont été marquées comme lues.');
    }
}
