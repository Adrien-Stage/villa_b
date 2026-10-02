<?php

namespace App\Support;

use App\Models\User;

/**
 * Qui règle quel onglet des paramètres : la direction, et le chef du service
 * concerné. Un membre de service — un simple réceptionniste — ne règle rien.
 *
 * L'écran et le contrôleur posent la même question ici : un onglet affiché est
 * un onglet qu'on peut enregistrer, et un onglet qu'on ne voit pas ne
 * s'enregistre pas davantage en forgeant la requête.
 */
class SettingsTabs
{
    /**
     * Onglet (ou clé de stockage) => fonctions qui le règlent. Les horaires
     * de séjour se stockent sous « reception » mais s'affichent dans l'onglet
     * Hébergement : les deux clés suivent la même règle.
     */
    private const ONGLETS = [
        // L'administrateur règle l'identité et le fonctionnement général ;
        // tarifs, prestations et services restent à la direction et aux chefs.
        'general' => ['manager', 'admin'],
        'hebergement' => ['manager', 'reception_chief'],
        'reception' => ['manager', 'reception_chief'],
        'taxes' => ['manager', 'reception_chief'],
        'housekeeping' => ['manager', 'housekeeping_leader'],
        'restaurant' => ['manager', 'restaurant_chief', 'restaurant_manager'],
        'shop' => ['manager', 'shop_manager'],
        'services' => ['manager'],
        'partners' => ['manager'],
    ];

    /** Ordre de préférence de l'onglet ouvert par défaut. */
    private const PAR_DEFAUT = ['general', 'hebergement', 'housekeeping', 'restaurant', 'shop'];

    public static function peutRegler(?User $user, string $onglet): bool
    {
        // Un onglet inconnu relève de la direction seule.
        return $user !== null && $user->exerce(self::ONGLETS[$onglet] ?? ['manager']);
    }

    /** @return list<string> onglets que cette personne règle */
    public static function reglables(?User $user): array
    {
        return array_values(array_filter(
            array_keys(self::ONGLETS),
            static fn (string $onglet): bool => self::peutRegler($user, $onglet)
        ));
    }

    /** Onglet ouvert quand aucun n'est demandé, ou null si aucun ne lui revient. */
    public static function parDefaut(?User $user): ?string
    {
        foreach (self::PAR_DEFAUT as $onglet) {
            if (self::peutRegler($user, $onglet)) {
                return $onglet;
            }
        }

        return null;
    }
}
