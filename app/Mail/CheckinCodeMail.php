<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CheckinCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public Booking $booking;

    /**
     * Create a new message instance.
     */
    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');

        // Si l'application tourne localement et que l'adresse d'expédition par défaut utilise
        // un domaine non vérifiable comme gmail.com ou example.com, on force le bac à sable de Resend.
        if (app()->environment('local') && 
            (str_ends_with($fromAddress, '@gmail.com') || 
             str_ends_with($fromAddress, '@example.com') || 
             $fromAddress === 'hello@example.com')) {
            $fromAddress = 'onboarding@resend.dev';
        }

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address($fromAddress, $fromName),
            subject: "Votre code de check-in - Villa Boutanga ({$this->booking->booking_number})",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.bookings.checkin-code',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
