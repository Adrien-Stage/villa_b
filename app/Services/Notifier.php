<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Point d'envoi unique des notifications métier.
 *
 * Deux garanties, valables pour tous les modules :
 *  - une notification qui échoue (push HS, table indisponible…) ne fait jamais
 *    échouer l'action métier : l'incident est journalisé, le travail continue ;
 *  - les destinataires sont résolus par rôle de façon homogène, en tenant
 *    compte des deux systèmes de rôles (colonne héritée et table pivot).
 */
class Notifier
{
    /**
     * Notifie tous les utilisateurs actifs portant l'un des rôles donnés.
     *
     * @param  array<int, string>  $roles
     * @param  int|null  $exceptUserId  À exclure : typiquement l'auteur de l'action,
     *                                  qui n'a pas besoin d'être averti de son propre geste.
     */
    public function toRoles(array $roles, Notification $notification, ?int $exceptUserId = null): void
    {
        $recipients = User::query()
            ->havingRole($roles)
            ->active()
            ->when($exceptUserId, fn ($q) => $q->where('id', '!=', $exceptUserId))
            ->get();

        $this->send($recipients, $notification);
    }

    /**
     * Notifie une liste d'utilisateurs (collection, tableau ou modèle unique).
     * Les comptes désactivés et les doublons sont écartés.
     */
    public function send(Collection|array|User|null $recipients, Notification $notification): void
    {
        $collection = match (true) {
            $recipients instanceof User       => collect([$recipients]),
            $recipients instanceof Collection => $recipients,
            is_array($recipients)             => collect($recipients),
            default                           => collect(),
        };

        $collection = $collection
            ->filter(fn ($u) => $u instanceof User && $u->is_active)
            ->unique('id')
            ->values();

        if ($collection->isEmpty()) {
            return;
        }

        try {
            NotificationFacade::send($collection, $notification);
        } catch (\Throwable $e) {
            // Jamais bloquant : l'action métier a déjà eu lieu.
            Log::error(sprintf(
                'Envoi de notification %s échoué (%d destinataire(s)) : %s',
                class_basename($notification),
                $collection->count(),
                $e->getMessage()
            ));
        }
    }
}
