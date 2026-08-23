<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Customer;
use App\Support\MailIdentity;
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
     * Le destinataire réel du message.
     *
     * Il n'est pas toujours le client : une réservation tenue par un mandataire
     * lui envoie le code. Saluer le client dans un message adressé à un tiers
     * trahissait à la fois le ton et la confidentialité du dossier.
     */
    public ?Customer $recipient;

    public function __construct(Booking $booking, ?Customer $recipient = null)
    {
        $this->booking = $booking;
        $this->recipient = $recipient ?? $booking->customer;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: MailIdentity::from(),
            replyTo: MailIdentity::replyTo(),
            subject: 'Votre code de check-in - ' . MailIdentity::establishment()
                . " ({$this->booking->booking_number})",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.bookings.checkin-code',
            with: [
                'recipient' => $this->recipient,
                'brand'     => MailIdentity::brand(),
            ],
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
