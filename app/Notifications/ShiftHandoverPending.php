<?php

namespace App\Notifications;

use App\Models\ShiftHandover;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Un réceptionniste a terminé son quart et remis le tiroir : la passation
 * attend celui qui prend le service.
 */
class ShiftHandoverPending extends Notification
{
    use Queueable;

    public function __construct(public ShiftHandover $passation)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    private function url(): string
    {
        return rtrim((string) config('app.url'), '/') . "/bookings/passations/{$this->passation->id}/reprendre";
    }

    private function message(): string
    {
        return ($this->passation->fromUser?->name ?? 'Un réceptionniste') . ' a terminé son service à '
            . $this->passation->handed_at->format('H:i') . ' et remis le tiroir de la réception ('
            . number_format($this->passation->handed_amount / 100, 0, ',', ' ') . ' FCFA). '
            . 'Prenez connaissance de la passation avant d’ouvrir votre caisse.';
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'   => 'Passation de service à reprendre',
            'message' => $this->message(),
            'url'     => $this->url(),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Passation de service',
            'body'  => $this->message(),
            'url'   => $this->url(),
            'tag'   => 'passation-' . $this->passation->id,
        ];
    }
}
