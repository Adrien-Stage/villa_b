<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Clés VAPID
    |--------------------------------------------------------------------------
    | Identifient le serveur applicatif auprès des services de push des
    | navigateurs (FCM, Mozilla, WNS...). La clé publique est aussi exposée
    | côté client pour l'abonnement (PushManager.subscribe). Générées une
    | fois via `php artisan webpush:vapid`, puis figées dans le .env.
    |
    | Communes à tous les établissements : elles identifient l'éditeur de
    | l'application, pas le tenant. Injectées dans les containers au
    | provisioning si l'on veut des notifications push par établissement.
    */
    'vapid' => [
        'subject'     => env('VAPID_SUBJECT', config('app.url')),
        'public_key'  => env('VAPID_PUBLIC_KEY', ''),
        'private_key' => env('VAPID_PRIVATE_KEY', ''),
    ],

];
