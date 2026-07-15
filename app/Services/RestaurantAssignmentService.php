<?php

namespace App\Services;

use App\Models\RestaurantCustomerOrder;
use App\Models\RestaurantShift;
use App\Models\User;
use App\Notifications\RestaurantOrderAssigned;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Répartit les commandes du portail entre les serveurs en salle.
 *
 * La cuisine et la salle ne communiquent pas directement : chaque commande passe
 * par un serveur. Quand un client valide sa commande depuis le portail, elle est
 * automatiquement confiée à l'un des serveurs en service, à charge de lui de la
 * transmettre en cuisine puis d'apporter le plat.
 *
 * Règle de répartition : au moins chargé. La commande va au serveur en service
 * qui a le moins de commandes actives ; à égalité, à celui qui a reçu sa dernière
 * affectation il y a le plus longtemps. Sur la durée, la charge s'égalise d'elle-
 * même — un serveur dont les tables traînent n'est pas noyé sous de nouvelles
 * commandes.
 */
class RestaurantAssignmentService
{
    private const SERVER_ROLE = 'restaurant_staff';

    /**
     * Serveurs actuellement en service (prise de service ouverte), actifs et
     * réellement porteurs du rôle serveur.
     */
    public function onDutyServers(): Collection
    {
        $onDutyIds = RestaurantShift::query()->open()->pluck('user_id')->unique();

        if ($onDutyIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->havingRole([self::SERVER_ROLE])
            ->active()
            ->whereIn('id', $onDutyIds)
            ->get();
    }

    /**
     * Affecte une commande du portail à un serveur en service et le notifie.
     *
     * Si personne n'est en service, la commande reste sans serveur : tous les
     * serveurs (et le chef) sont prévenus qu'une commande est à prendre en charge.
     */
    public function assignPortalOrder(RestaurantCustomerOrder $order): ?User
    {
        $server = $this->pickLeastLoadedServer();

        if (!$server) {
            $this->notifyUnassigned($order);

            return null;
        }

        $order->update([
            'assigned_server_id' => $server->id,
            'assigned_at' => now(),
        ]);

        $this->safeNotify(
            fn () => $server->notify(new RestaurantOrderAssigned($order->fresh())),
            $order,
        );

        return $server;
    }

    /**
     * Le serveur en service le moins chargé, ou null si aucun n'est en service.
     */
    public function pickLeastLoadedServer(): ?User
    {
        $servers = $this->onDutyServers();

        if ($servers->isEmpty()) {
            return null;
        }

        $ids = $servers->pluck('id')->all();

        // Nombre de commandes actives par serveur (celles qui l'occupent encore).
        $activeCounts = RestaurantCustomerOrder::query()
            ->whereIn('assigned_server_id', $ids)
            ->whereIn('status', RestaurantCustomerOrder::ACTIVE_STATUSES)
            ->selectRaw('assigned_server_id, count(*) as total')
            ->groupBy('assigned_server_id')
            ->pluck('total', 'assigned_server_id');

        // Date de la dernière affectation par serveur (pour départager les égalités).
        $lastAssigned = RestaurantCustomerOrder::query()
            ->whereIn('assigned_server_id', $ids)
            ->selectRaw('assigned_server_id, max(assigned_at) as last_at')
            ->groupBy('assigned_server_id')
            ->pluck('last_at', 'assigned_server_id');

        return $servers
            ->sortBy(function (User $server) use ($activeCounts, $lastAssigned) {
                $count = (int) ($activeCounts[$server->id] ?? 0);
                // Jamais affecté = priorité maximale (timestamp 0).
                $last = $lastAssigned[$server->id] ?? null;
                $lastTs = $last ? strtotime((string) $last) : 0;

                // Tri composite : d'abord la charge, puis l'ancienneté d'affectation.
                return sprintf('%010d-%012d', $count, $lastTs);
            })
            ->first();
    }

    /**
     * Nombre de commandes actives portées par un serveur.
     */
    public function activeOrderCountFor(int $userId): int
    {
        return RestaurantCustomerOrder::query()
            ->assignedTo($userId)
            ->active()
            ->count();
    }

    /**
     * Prévient les serveurs et le chef qu'une commande attend un preneur.
     */
    private function notifyUnassigned(RestaurantCustomerOrder $order): void
    {
        $recipients = User::query()
            ->havingRole([self::SERVER_ROLE, 'restaurant_chief'])
            ->active()
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $this->safeNotify(
            fn () => Notification::send($recipients, new RestaurantOrderAssigned($order->fresh(), needsPickup: true)),
            $order,
        );
    }

    /**
     * Les notifications ne doivent jamais faire échouer la prise de commande :
     * une panne de push ne doit pas perdre la commande du client.
     */
    private function safeNotify(callable $callback, RestaurantCustomerOrder $order): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::error("Notification commande restaurant #{$order->id} : " . $e->getMessage());
        }
    }
}
