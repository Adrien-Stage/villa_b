<?php

namespace App\Notifications;

use App\Models\StockRequisition;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Un département demande du matériel au magasin central. Destinée à l'économe :
 * rien ne sort du stock tant qu'il n'a pas validé. Tant que la demande attend
 * le visa du chef de service, c'est à lui qu'elle est destinée.
 */
class StockRequisitionSubmitted extends Notification
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

    public function toArray(object $notifiable): array
    {
        if ($this->requisition->canBeEndorsed()) {
            return [
                'requisition_id' => $this->requisition->id,
                'title'   => 'Demande à viser',
                'message' => "Demande {$this->requisition->number} de {$this->requisition->requestedBy?->name} "
                    . "({$this->requisition->departmentLabel()}) — elle attend votre visa avant l'économat.",
                'url'     => $this->url(),
            ];
        }

        return [
            'requisition_id' => $this->requisition->id,
            'title'   => 'Nouvelle demande à l\'économat',
            'message' => "Demande {$this->requisition->number} du service "
                . "{$this->requisition->departmentLabel()} — en attente de votre validation.",
            'url'     => $this->url(),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->requisition->canBeEndorsed() ? 'Demande à viser' : 'Demande à valider',
            'body'  => "{$this->requisition->departmentLabel()} · {$this->requisition->number}",
            'url'   => $this->url(),
            'tag'   => 'req-new-' . $this->requisition->id,
        ];
    }
}
