<?php

namespace App\Notifications;

use App\Models\MinibarCheck;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Pour la réception : le mini-bar d'une chambre vient d'être relevé. Ce que le
 * client a consommé est au folio de son séjour, ou reste à lui facturer s'il
 * est parti sans attendre.
 */
class MinibarCheckRecorded extends Notification
{
    use Queueable;

    public function __construct(public MinibarCheck $check)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    private function url(): string
    {
        $base = rtrim((string) config('app.url'), '/');

        return $this->check->booking_id ? "{$base}/bookings/{$this->check->booking_id}" : "{$base}/rooms";
    }

    private function message(): string
    {
        $check = $this->check->loadMissing('room', 'user', 'lines');
        $montant = number_format($check->total_amount / 100, 0, ',', ' ') . ' F';
        $debut = "Mini-bar de la chambre {$check->room?->number} relevé"
            . ($check->user ? " par {$check->user->name}" : '') . ' : ' . $check->resume();

        return match ($check->billing) {
            MinibarCheck::BILLING_FOLIO      => "{$debut} — {$montant} ajoutés au folio.",
            MinibarCheck::BILLING_A_FACTURER => "{$debut} — {$montant} consommés après le départ du client, à lui facturer.",
            default                          => "{$debut}.",
        };
    }

    public function toArray(object $notifiable): array
    {
        return [
            'room_id' => $this->check->room_id,
            'title'   => 'Relevé du mini-bar',
            'message' => $this->message(),
            'url'     => $this->url(),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Relevé du mini-bar',
            'body'  => $this->message(),
            'url'   => $this->url(),
            'tag'   => 'minibar-check-' . $this->check->id,
        ];
    }
}
