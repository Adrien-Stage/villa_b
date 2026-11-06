<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail de réinitialisation de mot de passe déclenché par un responsable.
 * Transmet le mot de passe temporaire valable 5 heures et rappelle
 * l'obligation de choisir un mot de passe personnel dès la connexion.
 */
class UserPasswordResetMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $temporaryPassword,
        public int $expiresInHours = 5,
        public ?string $loginUrl = null
    ) {
    }

    public function envelope(): Envelope
    {
        $appName = config('app.name', 'Wetchah PMS');

        return new Envelope(
            from: new Address(
                config('mail.from.address', 'notifications@wetchah.com'),
                config('mail.from.name', $appName)
            ),
            subject: "Réinitialisation de votre mot de passe — Accès temporaire (5h)",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.users.password-reset',
            with: [
                'user' => $this->user,
                'temporaryPassword' => $this->temporaryPassword,
                'expiresInHours' => $this->expiresInHours,
                'loginUrl' => $this->loginUrl ?? route('login'),
                'appName' => config('app.name', 'Wetchah PMS'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
