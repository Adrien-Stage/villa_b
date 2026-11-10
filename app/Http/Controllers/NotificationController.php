<?php

namespace App\Http\Controllers;

use App\Support\NotificationUrl;
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

        $notificationsData = $unreadNotifications->map(function ($notification) use ($request) {
            $data = is_array($notification->data) ? $notification->data : [];

            if (array_key_exists('url', $data)) {
                $data['url'] = NotificationUrl::toAppPath($data['url'], $request);
            }

            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'data' => $data,
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

        $url = NotificationUrl::toAppPath($notification->data['url'] ?? null, $request)
            ?? route('dashboard', [], false);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'url' => $url,
            ]);
        }

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
