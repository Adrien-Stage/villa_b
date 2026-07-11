<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notification d'une demande de réservation reçue depuis le site vitrine,
 * destinée aux managers et à la réception. Canaux :
 *  - database : centre de notifications in-app (cloche)
 *  - webpush  : notification système, même application fermée (WebPushChannel)
 */
class WebsiteBookingReceived extends Notification
{
    use Queueable;

    public function __construct(public Booking $booking)
    {
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
        $dates = ($this->booking->check_in?->format('d/m') ?? '?') . ' → ' . ($this->booking->check_out?->format('d/m') ?? '?');

        return [
            'room' => $room,
            'customer' => $customer !== '' ? $customer : 'Client',
            'dates' => $dates,
        ];
    }

    public function toArray(object $notifiable): array
    {
        ['room' => $room, 'customer' => $customer, 'dates' => $dates] = $this->context();

        return [
            'booking_id'     => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'title'          => 'Réservation en ligne',
            'message'        => "Demande web {$this->booking->booking_number} — Chambre {$room} pour {$customer} ({$dates}). À confirmer.",
            'url'            => route('bookings.show', $this->booking->id),
        ];
    }

    /**
     * Charge utile envoyée au service worker (public/sw.js).
     */
    public function toWebPush(object $notifiable): array
    {
        ['room' => $room, 'customer' => $customer, 'dates' => $dates] = $this->context();

        return [
            'title' => '🌐 Réservation en ligne',
            'body'  => "Chambre {$room} — {$customer} ({$dates})",
            'url'   => route('bookings.show', $this->booking->id),
            'tag'   => 'web-booking-' . $this->booking->id,
            'requireInteraction' => true,
            'icon'  => asset('favicon.ico'),
        ];
    }
}
