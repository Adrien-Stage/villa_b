<?php

namespace App\Mail;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Bon de commande envoyé au fournisseur. Le corps liste les articles, les
 * quantités et le total ; c'est ce message qui déclenche la livraison.
 */
class PurchaseOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PurchaseOrder $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(
                config('mail.from.address'),
                config('mail.from.name'),
            ),
            subject: "Bon de commande {$this->order->number} — " . config('mail.from.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.economat.purchase-order',
            with: ['order' => $this->order],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
