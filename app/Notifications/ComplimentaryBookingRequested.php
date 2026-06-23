<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ComplimentaryBookingRequested extends Notification
{
    use Queueable;

    public Booking $booking;
    protected string $receptionistName;

    /**
     * Create a new notification instance.
     */
    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
        $this->receptionistName = auth()->user()->name ?? 'Réceptionniste';
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $this->booking->load(['customer', 'room.roomType']);

        $roomNumber = $this->booking->room->number ?? 'N/A';
        $roomType = $this->booking->room->roomType->name ?? '';
        $customerName = $this->booking->customer
            ? ($this->booking->customer->first_name . ' ' . $this->booking->customer->last_name)
            : 'Client inconnu';

        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');

        return (new MailMessage)
            ->from($fromAddress, $fromName)
            ->subject("⚠ Chambre Offerte en Attente — Réservation {$this->booking->booking_number}")
            ->view('emails.bookings.complimentary-request', [
                'managerName' => $notifiable->name ?? 'Manager',
                'receptionistName' => $this->receptionistName,
                'bookingNumber' => $this->booking->booking_number,
                'customerName' => $customerName,
                'roomNumber' => $roomNumber,
                'roomType' => $roomType,
                'checkIn' => $this->booking->check_in ? $this->booking->check_in->format('d/m/Y') : '',
                'checkOut' => $this->booking->check_out ? $this->booking->check_out->format('d/m/Y') : '',
                'notes' => $this->booking->notes,
                'actionUrl' => route('bookings.show', $this->booking->id),
            ]);
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
            'receptionist_name' => $this->receptionistName,
            'check_in' => $this->booking->check_in ? $this->booking->check_in->format('d/m/Y') : '',
            'check_out' => $this->booking->check_out ? $this->booking->check_out->format('d/m/Y') : '',
            'notes' => $this->booking->notes,
            'title' => 'Chambre Offerte - Validation Requise',
            'message' => "La réservation {$this->booking->booking_number} (Chambre {$roomNumber}) créée par {$this->receptionistName} pour {$customerName} est en attente de validation.",
            'url' => route('bookings.show', $this->booking->id),
        ];
    }
}
