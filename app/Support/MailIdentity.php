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

    /**
     * Charte de l'établissement, telle que la reprend un courriel.
     *
     * Un message qui arrive aux couleurs d'un autre hôtel n'inspire rien de bon
     * au client. Les teintes viennent du même thème que l'interface — le
     * document et l'écran doivent se ressembler — avec les défauts de la charte
     * d'origine quand rien n'est configuré.
     *
     * @return array{name:string,logo:?string,address:?string,phone:?string,email:?string,primary:string,secondary:string,accent:string}
     */
    public static function brand(?Tenant $tenant = null): array
    {
        $tenant ??= self::tenant();
        $theme = is_array($tenant?->settings['theme'] ?? null) ? $tenant->settings['theme'] : [];

        $logo = self::propre($tenant?->settings['logo'] ?? null);

        return [
            'name'      => self::establishment($tenant),
            // URL absolue : une image relative ne s'affiche dans aucune messagerie.
            'logo'      => $logo ? asset('storage/' . ltrim($logo, '/')) : null,
            'address'   => self::propre($tenant?->address),
            'phone'     => self::propre($tenant?->phone),
            'email'     => self::propre($tenant?->email),
            'primary'   => self::couleur($theme['primary'] ?? null, '#391F0E'),
            'secondary' => self::couleur($theme['secondary'] ?? null, '#CCAB87'),
            'accent'    => self::couleur($theme['accent'] ?? null, '#EED4A3'),
        ];
    }

    /** Couleur hexadécimale sûre pour du style en ligne, repli compris. */
    private static function couleur(?string $valeur, string $defaut): string
    {
        $propre = strtoupper(ltrim(trim((string) $valeur), '#'));

        if (strlen($propre) === 3) {
            $propre = $propre[0] . $propre[0] . $propre[1] . $propre[1] . $propre[2] . $propre[2];
        }

        return preg_match('/^[0-9A-F]{6}$/', $propre) ? '#' . $propre : $defaut;
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
