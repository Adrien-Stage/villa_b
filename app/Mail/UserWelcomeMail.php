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
 * E-mail de bienvenue envoyé lors de la création d'un utilisateur.
 * Contient ses identifiants et l'instruction de changer son mot de passe
 * à sa première connexion.
 */
class UserWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $defaultPassword,
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
            subject: "Bienvenue sur {$appName} — Vos accès et première connexion",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.users.welcome',
            with: [
                'user' => $this->user,
                'defaultPassword' => $this->defaultPassword,
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
