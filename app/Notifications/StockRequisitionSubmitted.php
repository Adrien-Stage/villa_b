<?php

namespace App\Notifications;

use App\Models\StockRequisition;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Un département demande du matériel au magasin central. Destinée à l'économe :
 * rien ne sort du stock tant qu'il n'a pas validé.
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
            'title' => 'Demande à valider',
            'body'  => "{$this->requisition->departmentLabel()} · {$this->requisition->number}",
            'url'   => $this->url(),
            'tag'   => 'req-new-' . $this->requisition->id,
        ];
    }
}
