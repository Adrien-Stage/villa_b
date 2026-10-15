<?php

namespace App\Notifications;

use App\Models\Room;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Pour la réception : une chambre libérée vient d'être nettoyée. Elle sera
 * vendable dès que la gouvernante l'aura contrôlée.
 */
class HousekeepingRoomCleaned extends Notification
{
    use Queueable;

    public function __construct(
        public Room $room,
        public ?string $cleanedBy = null,
        public ?string $complement = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    private function url(): string
    {
        return rtrim((string) config('app.url'), '/') . '/rooms';
    }

    private function message(): string
    {
        return "Chambre {$this->room->number} nettoyée"
            . ($this->cleanedBy ? " par {$this->cleanedBy}" : '')
            . ' — en attente du contrôle de la gouvernante.'
            . ($this->complement ? ' ' . $this->complement : '');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'room_id' => $this->room->id,
            'title'   => 'Chambre nettoyée',
            'message' => $this->message(),
            'url'     => $this->url(),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Chambre nettoyée',
            'body'  => $this->message(),
            'url'   => $this->url(),
            'tag'   => 'hk-cleaned-' . $this->room->id,
        ];
    }
}
