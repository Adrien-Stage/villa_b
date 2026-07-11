<?php

namespace App\Notifications\Channels;

use App\Models\PushSubscription;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Canal de notification Web Push : envoie une notification système au(x)
 * navigateur(s) abonné(s) de l'utilisateur, via le protocole Web Push
 * (VAPID). Fonctionne même quand l'application n'est pas ouverte — c'est le
 * service worker (public/sw.js) qui reçoit le message et affiche la
 * notification à l'écran, accompagnée du son système.
 *
 * Une notification déclenche ce canal si elle expose toWebPush($notifiable)
 * et inclut 'webpush' dans son via().
 */
class WebPushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toWebPush')) {
            return;
        }

        $publicKey  = (string) config('webpush.vapid.public_key');
        $privateKey = (string) config('webpush.vapid.private_key');
        if ($publicKey === '' || $privateKey === '') {
            Log::warning('[WebPush] Clés VAPID non configurées — notification push ignorée.');
            return;
        }

        $subscriptions = PushSubscription::where('user_id', $notifiable->getKey())->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = json_encode($notification->toWebPush($notifiable));

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject'    => (string) config('webpush.vapid.subject'),
                    'publicKey'  => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ]);

            foreach ($subscriptions as $sub) {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint'        => $sub->endpoint,
                        'publicKey'       => $sub->public_key,
                        'authToken'       => $sub->auth_token,
                        'contentEncoding' => $sub->content_encoding,
                    ]),
                    $payload
                );
            }

            // Envoi + nettoyage des abonnements expirés/invalides (410/404)
            foreach ($webPush->flush() as $report) {
                if (!$report->isSuccess()) {
                    $endpoint = $report->getEndpoint();
                    if ($report->isSubscriptionExpired()) {
                        PushSubscription::where('endpoint_hash', hash('sha256', $endpoint))->delete();
                    } else {
                        Log::info('[WebPush] Échec envoi : ' . $report->getReason());
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('[WebPush] Exception : ' . $e->getMessage());
        }
    }
}
