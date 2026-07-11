<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notification d'une nouvelle réservation, destinée aux managers et à la
 * réception. Deux canaux :
 *  - database : centre de notifications in-app (cloche)
 *  - webpush  : notification système, même application fermée (WebPushChannel)
 */
class NewBookingCreated extends Notification
{
    use Queueable;

    public function __construct(
        public Booking $booking,
        protected string $creatorName
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    private function context(): array
    {
        $this->booking->loadMissing(['customer', 'room']);

        $room     = $this->booking->room->number ?? 'N/A';
        $customer = $this->booking->customer
            ? trim($this->booking->customer->first_name . ' ' . $this->booking->customer->last_name)
            : 'Client';

        return [
            'room' => $room,
            'customer' => $customer !== '' ? $customer : 'Client',
        ];
    }

    public function toArray(object $notifiable): array
    {
        ['room' => $room, 'customer' => $customer] = $this->context();

        return [
            'booking_id'     => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'title'          => 'Nouvelle réservation',
            'message'        => "Réservation {$this->booking->booking_number} — Chambre {$room} pour {$customer} (par {$this->creatorName}).",
            'url'            => route('bookings.show', $this->booking->id),
        ];
    }

    /**
     * Charge utile envoyée au service worker (public/sw.js).
     */
    public function toWebPush(object $notifiable): array
    {
        ['room' => $room, 'customer' => $customer] = $this->context();

        return [
            'title' => 'Nouvelle réservation',
            'body'  => "Chambre {$room} — {$customer} · {$this->booking->booking_number}",
            'url'   => route('bookings.show', $this->booking->id),
            'tag'   => 'booking-' . $this->booking->id,
            'icon'  => asset('favicon.ico'),
        ];
    }
}
