<?php

namespace App\Notifications;

use App\Models\Room;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Une chambre vient d'être nettoyée et attend le contrôle qualité.
 * Destinée à qui peut valider : chef d'équipe et chef de service. Sans ce
 * signal, la chambre reste bloquée avant d'être remise à la vente.
 */
class HousekeepingRoomToInspect extends Notification
{
    use Queueable;

    public function __construct(
        public Room $room,
        public ?string $teamName = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    private function url(): string
    {
        return rtrim((string) config('app.url'), '/') . '/housekeeping';
    }

    public function toArray(object $notifiable): array
    {
        return [
            'room_id' => $this->room->id,
            'title'   => 'Chambre à contrôler',
            'message' => "Chambre {$this->room->number} nettoyée"
                . ($this->teamName ? " par {$this->teamName}" : '')
                . ' — en attente de contrôle avant remise à disposition.',
            'url'     => $this->url(),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Chambre à contrôler',
            'body'  => "Chambre {$this->room->number} nettoyée, à valider.",
            'url'   => $this->url(),
            'tag'   => 'hk-inspect-' . $this->room->id,
        ];
    }
}
