<?php

namespace App\Mail;

use App\Models\PurchaseOrder;
use App\Support\MailIdentity;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Bon de commande envoyé au fournisseur. Le corps liste les articles et les
 * quantités, sans aucun prix ; c'est ce message qui déclenche la livraison.
 */
class PurchaseOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PurchaseOrder $order)
    {
    }

    public function envelope(): Envelope
    {
        $tenantName = MailIdentity::establishment();

        return new Envelope(
            from: MailIdentity::from(),
            replyTo: MailIdentity::replyTo(),
            subject: "Bon de commande {$this->order->number} — {$tenantName}",
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
