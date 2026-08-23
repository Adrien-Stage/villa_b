<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Mail\Mailables\Address;

/**
 * L'identité sous laquelle l'établissement écrit à ses clients et à leurs
 * mandataires.
 *
 * Le fichier .env porte l'adresse technique du serveur d'envoi — celle que
 * l'hébergeur a validée. Mais l'adresse que le client lit, et surtout celle à
 * laquelle il répond, relèvent de la gestion quotidienne : elles changent quand
 * l'établissement ouvre une boîte « reservations@ », pas quand on redéploie.
 * Elles vivent donc dans les réglages, avec le .env pour filet.
 *
 * L'adresse de réponse compte autant que l'expéditeur : un client qui répond à
 * un « noreply@ » écrit dans le vide, et l'établissement ne le sait jamais.
 */
class MailIdentity
{
    /** Établissement résolu une fois par requête. */
    private static ?Tenant $tenant = null;

    private static bool $resolu = false;

    /** Expéditeur affiché au destinataire. */
    public static function from(?Tenant $tenant = null): Address
    {
        $tenant ??= self::tenant();
        $reglages = self::reglages($tenant);

        $adresse = self::propre($reglages['mail_from_address'] ?? null)
            ?? config('mail.from.address');

        $nom = self::propre($reglages['mail_from_name'] ?? null)
            ?? $tenant?->name
            ?? config('mail.from.name');

        return new Address($adresse, $nom);
    }

    /**
     * Adresse de réponse, si elle diffère de l'expéditeur.
     *
     * Renvoie un tableau, directement consommable par l'enveloppe d'un
     * Mailable : vide quand il n'y a rien à ajouter.
     *
     * @return array<int, Address>
     */
    public static function replyTo(?Tenant $tenant = null): array
    {
        $tenant ??= self::tenant();
        $reglages = self::reglages($tenant);

        $adresse = self::propre($reglages['mail_reply_to'] ?? null)
            ?? self::propre($tenant?->email);

        if ($adresse === null || $adresse === self::from($tenant)->address) {
            return [];
        }

        return [new Address($adresse, $tenant?->name ?: null)];
    }

    /** Nom de l'établissement, pour les objets de message. */
    public static function establishment(?Tenant $tenant = null): string
    {
        $tenant ??= self::tenant();

        return $tenant?->name ?: config('app.name');
    }

    /** Vide le cache de requête — utile aux tests qui modifient les réglages. */
    public static function forget(): void
    {
        self::$tenant = null;
        self::$resolu = false;
    }

    /**
     * Une seule base = un seul établissement, comme partout ailleurs dans
     * l'application. On mémorise : un envoi groupé ne doit pas relire la table
     * à chaque destinataire.
     */
    private static function tenant(): ?Tenant
    {
        if (!self::$resolu) {
            self::$tenant = Tenant::query()->first();
            self::$resolu = true;
        }

        return self::$tenant;
    }

    /** @return array<string, mixed> */
    private static function reglages(?Tenant $tenant): array
    {
        $general = $tenant?->settings['general'] ?? null;

        return is_array($general) ? $general : [];
    }

    /** Une chaîne vide n'est pas un réglage : elle doit laisser jouer le repli. */
    private static function propre(mixed $valeur): ?string
    {
        $propre = trim((string) $valeur);

        return $propre === '' ? null : $propre;
    }
}
