<?php

namespace App\Notifications;

use App\Models\Room;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Le client de la chambre s'en va : la réception attend le relevé du mini-bar
 * pour clore sa note.
 */
class MinibarCheckRequested extends Notification
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
            'title'   => 'Contrôle du mini-bar demandé',
            'message' => "Le client de la chambre {$this->room->number} part : la réception attend le relevé du mini-bar pour clore sa note.",
            'url'     => $this->url(),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Contrôle du mini-bar',
            'body'  => "Chambre {$this->room->number} : relevé du mini-bar attendu par la réception.",
            'url'   => $this->url(),
            'tag'   => 'minibar-request-' . $this->room->id,
        ];
    }
}
