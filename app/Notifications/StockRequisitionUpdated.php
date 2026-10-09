<?php

namespace App\Notifications;

use App\Models\StockRequisition;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Suite donnée par l'économe à une demande : validée, refusée ou livrée.
 * Destinée au demandeur, qui attend son matériel pour travailler.
 */
class StockRequisitionUpdated extends Notification
{
    use Queueable;

    public function __construct(public StockRequisition $requisition)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    private function url(): string
    {
        return route('economat.requisitions.show', $this->requisition);
    }

    private function headline(): string
    {
        return match ($this->requisition->status) {
            StockRequisition::STATUS_PENDING   => 'Demande visée',
            StockRequisition::STATUS_APPROVED  => 'Demande validée',
            StockRequisition::STATUS_REJECTED  => $this->requisition->refuseeAuVisa() ? 'Demande refusée au visa' : 'Demande refusée',
            StockRequisition::STATUS_DELIVERED => 'Matériel livré',
            default                            => 'Demande mise à jour',
        };
    }

    private function detail(): string
    {
        $notes = trim((string) ($this->requisition->refuseeAuVisa()
            ? $this->requisition->endorsement_notes
            : $this->requisition->review_notes));

        return match ($this->requisition->status) {
            StockRequisition::STATUS_PENDING   => "Votre demande {$this->requisition->number} est visée par votre chef de service :"
                . " elle part à l'économat.",
            StockRequisition::STATUS_APPROVED  => "Votre demande {$this->requisition->number} est validée."
                . ($notes ? " Note : {$notes}" : ' Le matériel sera préparé.'),
            StockRequisition::STATUS_REJECTED  => "Votre demande {$this->requisition->number} a été refusée."
                . ($notes ? " Motif : {$notes}" : ''),
            StockRequisition::STATUS_DELIVERED => "Le matériel de la demande {$this->requisition->number} est disponible.",
            default                            => "Demande {$this->requisition->number} mise à jour.",
        };
    }

    public function toArray(object $notifiable): array
    {
        return [
            'requisition_id' => $this->requisition->id,
            'title'   => $this->headline(),
            'message' => $this->detail(),
            'url'     => $this->url(),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->headline(),
            'body'  => $this->detail(),
            'url'   => $this->url(),
            // Même tag pour toute la vie de la demande : le demandeur voit
            // toujours l'état le plus récent, sans pile d'anciennes alertes.
            'tag'   => 'req-' . $this->requisition->id,
        ];
    }
}
