<?php

namespace App\Notifications;

use App\Models\ShiftHandover;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * La passation est reprise : celui qui a remis le tiroir sait qui l'a pris et
 * combien il a compté ; le chef de réception est prévenu d'un écart.
 */
class ShiftHandoverTaken extends Notification
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
        return rtrim((string) config('app.url'), '/') . "/bookings/passations/{$this->passation->id}";
    }

    private function message(): string
    {
        $p = $this->passation;
        $ecart = (int) $p->discrepancy_amount;

        return ($p->toUser?->name ?? 'Un réceptionniste') . ' a repris le service à ' . $p->taken_at?->format('H:i')
            . ' : ' . number_format((int) $p->received_amount / 100, 0, ',', ' ') . ' FCFA comptés dans le tiroir remis par '
            . ($p->fromUser?->name ?? '—')
            . ($ecart === 0 ? ', sans écart.' : ', écart de ' . number_format($ecart / 100, 0, ',', ' ') . ' FCFA.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'   => $this->passation->discrepancy_amount ? 'Passation reprise avec un écart' : 'Passation reprise',
            'message' => $this->message(),
            'url'     => $this->url(),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Passation reprise',
            'body'  => $this->message(),
            'url'   => $this->url(),
            'tag'   => 'passation-reprise-' . $this->passation->id,
        ];
    }
}
