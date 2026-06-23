<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ComplimentaryBookingApproved extends Notification
{
    use Queueable;

    public Booking $booking;

    /**
     * Create a new notification instance.
     */
    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $roomNumber = $this->booking->room->number ?? 'N/A';
        
        return [
            'booking_id' => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'room_number' => $roomNumber,
            'title' => 'Réservation Offerte Validée',
            'message' => "La réservation offerte {$this->booking->booking_number} (Chambre {$roomNumber}) a été validée par le manager.",
            'url' => route('bookings.show', $this->booking->id),
        ];
    }
}
