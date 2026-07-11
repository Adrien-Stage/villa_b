<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Gestion des abonnements Web Push côté navigateur : le client enregistre
 * son abonnement (endpoint + clés) après avoir accordé la permission, et le
 * retire à la désinscription. La clé publique VAPID nécessaire à
 * l'abonnement est exposée via vapidKey().
 */
class PushSubscriptionController extends Controller
{
    public function vapidKey()
    {
        return response()->json([
            'key' => (string) config('webpush.vapid.public_key'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'endpoint'          => ['required', 'string'],
            'keys.p256dh'       => ['required', 'string'],
            'keys.auth'         => ['required', 'string'],
            'content_encoding'  => ['nullable', 'string'],
        ]);

        $endpoint = $validated['endpoint'];

        PushSubscription::updateOrCreate(
            [
                'user_id'       => Auth::id(),
                'endpoint_hash' => hash('sha256', $endpoint),
            ],
            [
                'endpoint'         => $endpoint,
                'public_key'       => $validated['keys']['p256dh'],
                'auth_token'       => $validated['keys']['auth'],
                'content_encoding' => $validated['content_encoding'] ?? 'aesgcm',
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string'],
        ]);

        PushSubscription::where('user_id', Auth::id())
            ->where('endpoint_hash', hash('sha256', $validated['endpoint']))
            ->delete();

        return response()->json(['ok' => true]);
    }
}
