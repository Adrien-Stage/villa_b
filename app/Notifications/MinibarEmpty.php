<?php

namespace App\Notifications;

use App\Models\Room;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Pour les gouvernantes : le client a vidé le mini-bar de sa chambre pendant
 * son séjour. C'est le seul cas où il se recharge avant le départ.
 */
class MinibarEmpty extends Notification
{
    use Queueable;

    public function __construct(public Room $room)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    private function url(): string
    {
        return rtrim((string) config('app.url'), '/') . "/housekeeping/{$this->room->id}/mini-bar";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'room_id' => $this->room->id,
            'title'   => 'Mini-bar vide',
            'message' => "Le mini-bar de la chambre {$this->room->number} est vide, client en séjour : à réapprovisionner.",
            'url'     => $this->url(),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Mini-bar vide',
            'body'  => "Chambre {$this->room->number} : mini-bar vide, à réapprovisionner.",
            'url'   => $this->url(),
            'tag'   => 'minibar-empty-' . $this->room->id,
        ];
    }
}
