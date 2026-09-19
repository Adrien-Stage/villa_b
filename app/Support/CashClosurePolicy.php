<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\User;

/**
 * Politique de clôture des caisses, propre à chaque établissement.
 *
 * Par défaut, celui qui a ouvert la caisse la ferme : c'est praticable pour
 * un petit hôtel où personne d'autre n'est présent à la fermeture du service.
 * Mais compter soi-même l'argent qu'on a encaissé, sans témoin, prive l'écart
 * de caisse de la valeur qu'on lui prête — il devient une déclaration.
 *
 * L'établissement choisit donc s'il exige un comptage contradictoire, et par
 * qui. Le réglage vit dans les paramètres (clé « caisse ») plutôt que dans le
 * code : une chaîne hôtelière et une maison d'hôtes n'ont pas la même
 * organisation de nuit.
 */
class CashClosurePolicy
{
    /** Le déclarant ferme lui-même — comportement par défaut. */
    public const WITNESS_NONE = 'aucun';

    /** Un responsable d'établissement contresigne. */
    public const WITNESS_MANAGER = 'manager';

    /** Un membre de la comptabilité contresigne — le plus exigeant. */
    public const WITNESS_ACCOUNTANT = 'comptabilite';

    public const WITNESS_CHOICES = [
        self::WITNESS_NONE,
        self::WITNESS_MANAGER,
        self::WITNESS_ACCOUNTANT,
    ];

    /** Modules de caisse auxquels la règle peut s'appliquer. */
    public const MODULES = ['reception', 'shop'];

    /** Rôles habilités à contresigner, selon le témoin exigé. */
    private const ROLES_BY_WITNESS = [
        self::WITNESS_MANAGER    => ['manager'],
        self::WITNESS_ACCOUNTANT => ['accountant', 'manager'],
    ];

    /** Statut d'une caisse comptée mais pas encore contrôlée. */
    public const STATUS_PENDING_REVIEW = 'pending_review';

    private static function settings(): array
    {
        $tenant = Tenant::current() ?? Tenant::query()->first();

        return (array) (($tenant?->settings ?? [])['caisse'] ?? []);
    }

    /** Témoin exigé par l'établissement, ou « aucun ». */
    public static function witness(): string
    {
        $choix = (string) (self::settings()['closure_witness'] ?? self::WITNESS_NONE);

        return in_array($choix, self::WITNESS_CHOICES, true) ? $choix : self::WITNESS_NONE;
    }

    /** Caisses soumises à la règle. Par défaut, toutes. */
    public static function modules(): array
    {
        $modules = self::settings()['closure_witness_modules'] ?? self::MODULES;

        return array_values(array_intersect((array) $modules, self::MODULES));
    }

    public static function requiresWitness(string $module): bool
    {
        return self::witness() !== self::WITNESS_NONE
            && in_array($module, self::modules(), true);
    }

    /**
     * Cet utilisateur peut-il contresigner le comptage de cette caisse ?
     *
     * Le déclarant est exclu quel que soit son rôle : un manager qui a tenu
     * la caisse ne peut pas se contrôler lui-même, sinon la règle ne fait
     * que déplacer le problème.
     */
    public static function canWitness(User $user, ?int $countedByUserId = null): bool
    {
        if ($countedByUserId !== null && $user->id === $countedByUserId) {
            return false;
        }

        return $user->hasAnyRole(self::ROLES_BY_WITNESS[self::witness()] ?? []);
    }

    /** Libellé lisible du témoin exigé, pour les écrans et les messages. */
    public static function witnessLabel(): string
    {
        return match (self::witness()) {
            self::WITNESS_MANAGER    => 'un responsable',
            self::WITNESS_ACCOUNTANT => 'la comptabilité',
            default                  => 'personne',
        };
    }
}
