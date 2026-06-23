<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ComplimentaryBookingRequested extends Notification
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
        $customerName = $this->booking->customer 
            ? ($this->booking->customer->first_name . ' ' . $this->booking->customer->last_name)
            : 'Client inconnu';

        return [
            'booking_id' => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'room_number' => $roomNumber,
            'customer_name' => $customerName,
            'receptionist_name' => auth()->user()->name ?? 'Réceptionniste',
            'check_in' => $this->booking->check_in ? $this->booking->check_in->format('d/m/Y') : '',
            'check_out' => $this->booking->check_out ? $this->booking->check_out->format('d/m/Y') : '',
            'notes' => $this->booking->notes,
            'title' => 'Chambre Offerte - Validation Requise',
            'message' => "La réservation {$this->booking->booking_number} (Chambre {$roomNumber}) créée par " . (auth()->user()->name ?? 'un réceptionniste') . " pour {$customerName} est en attente de validation.",
            'url' => route('bookings.show', $this->booking->id),
        ];
    }
}
