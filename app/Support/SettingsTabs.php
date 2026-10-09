<?php

namespace App\Support;

use App\Models\User;
use App\Services\PermissionResolver;

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
        // Le calendrier des inventaires généraux engage tous les services :
        // c'est une décision de la direction.
        'inventaire' => ['manager'],
        // Les quarts de travail engagent tout l'hôtel : la direction les
        // définit, l'administrateur les règle aussi (configuration).
        'quarts' => ['manager', 'admin'],
        'partners' => ['manager'],
        // Les unités de stockage des articles : l'économe les tient, la
        // direction les consulte (les écrire reste un droit de l'économat).
        'economat' => ['manager', 'econome'],
    ];

    /** Onglets d'un module : sans le module, l'onglet n'existe pas. */
    private const MODULES = [
        'economat' => 'economat',
    ];

    /**
     * Onglets qui montrent aussi ce qu'un droit ouvre à la consultation,
     * quand le module est activé : l'onglet Restaurant porte les restaurants
     * de l'hôtel, que la direction crée et que le contrôle consulte sans
     * rien régler.
     */
    private const CONSULTATION = [
        'restaurant' => ['droit' => 'restaurant.restaurants.voir', 'module' => 'restaurant'],
    ];

    /** Ordre de préférence de l'onglet ouvert par défaut. */
    private const PAR_DEFAUT = ['general', 'hebergement', 'housekeeping', 'restaurant', 'shop', 'economat'];

    public static function peutRegler(?User $user, string $onglet): bool
    {
        if (isset(self::MODULES[$onglet]) && !TenantModules::has(self::MODULES[$onglet])) {
            return false;
        }

        // Un onglet inconnu relève de la direction seule.
        return $user !== null && $user->exerce(self::ONGLETS[$onglet] ?? ['manager']);
    }

    /** Cette personne ouvre-t-elle l'onglet, pour le régler ou pour consulter ce qu'il porte ? */
    public static function peutOuvrir(?User $user, string $onglet): bool
    {
        return self::peutRegler($user, $onglet) || self::consulte($user, $onglet);
    }

    /**
     * L'onglet montre-t-il à cette personne ce qu'il porte en consultation ?
     * Ni le droit seul ni le module seul n'y suffisent.
     */
    public static function consulte(?User $user, string $onglet): bool
    {
        $consultation = self::CONSULTATION[$onglet] ?? null;

        return $consultation !== null
            && TenantModules::has($consultation['module'])
            && app(PermissionResolver::class)->allows($user, $consultation['droit']);
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
            if (self::peutOuvrir($user, $onglet)) {
                return $onglet;
            }
        }

        return null;
    }
}
