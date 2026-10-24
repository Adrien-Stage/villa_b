<?php

namespace App\Notifications;

use App\Models\ManagementAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Une dérive de gestion vient d'être constatée (AlertesDeGestion) :
 * dépassement de budget, recettes en retard, coût matière, marge en baisse.
 * Pour le contrôle de gestion, le responsable financier et la direction.
 */
class AlerteDeGestion extends Notification
{
    use Queueable;

    public function __construct(public ManagementAlert $alerte)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'management_alert_id' => $this->alerte->id,
            'title' => $this->alerte->ruleLabel(),
            'message' => $this->alerte->message,
            'url' => route('pilotage.index', ['mois' => $this->alerte->period]),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->alerte->ruleLabel(),
            'body' => $this->alerte->message,
            'url' => route('pilotage.index', ['mois' => $this->alerte->period]),
            // Une alerte par règle, centre et mois : les suivantes la remplacent.
            'tag' => 'gestion-' . $this->alerte->rule . '-' . ($this->alerte->center ?? 'hotel') . '-' . $this->alerte->period,
        ];
    }
}
