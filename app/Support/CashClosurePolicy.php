<?php

namespace App\Support;

use App\Models\User;

/**
 * Politique de clôture des caisses : la comptabilité contresigne toujours.
 *
 * Compter soi-même l'argent qu'on a encaissé, sans témoin, prive l'écart de
 * caisse de la valeur qu'on lui prête — il devient une déclaration. Le
 * titulaire compte donc sa caisse, qui cesse aussitôt d'encaisser, puis la
 * comptabilité contresigne le comptage et constate l'écart : la caisse n'est
 * close qu'à ce moment.
 *
 * Le contrôle se fait après coup : le réceptionniste de nuit compte sa caisse
 * au petit matin, la comptabilité la contrôle à son arrivée.
 *
 * Ce n'est plus un réglage de l'établissement. Laisser le choix permettait de
 * confier le contrôle au manager, qui supervise les caisses qu'il ferait
 * contrôler, ou de n'en confier aucun : la direction a arrêté que seule la
 * comptabilité, qui n'encaisse jamais, contresigne.
 */
class CashClosurePolicy
{
    /** Le témoin des comptages, partout : la comptabilité. */
    public const WITNESS_ACCOUNTANT = 'comptabilite';

    /**
     * Caisses soumises au comptage contradictoire : toutes. La réception, la
     * boutique et chaque restaurant suivent le même circuit.
     */
    public const MODULES = ['reception', 'shop', 'restaurant'];

    /**
     * Rôles habilités à contresigner. Le responsable administratif et
     * financier est nommé : il dirige la comptabilité, et l'inclusion de ses
     * membres ne vaut que pour les droits, pas pour les rôles demandés ici.
     */
    private const ROLES_TEMOINS = ['accountant', 'finance_manager'];

    /** Statut d'une caisse comptée mais pas encore contrôlée. */
    public const STATUS_PENDING_REVIEW = 'pending_review';

    /** Témoin exigé : toujours la comptabilité. */
    public static function witness(): string
    {
        return self::WITNESS_ACCOUNTANT;
    }

    /** Caisses soumises à la règle. */
    public static function modules(): array
    {
        return self::MODULES;
    }

    public static function requiresWitness(string $module): bool
    {
        return in_array($module, self::MODULES, true);
    }

    /**
     * Cet utilisateur peut-il contresigner le comptage de cette caisse ?
     *
     * Le déclarant est exclu quel que soit son rôle : celui qui a tenu la
     * caisse ne peut pas se contrôler lui-même, sinon la règle ne fait que
     * déplacer le problème.
     */
    public static function canWitness(User $user, ?int $countedByUserId = null): bool
    {
        if ($countedByUserId !== null && $user->id === $countedByUserId) {
            return false;
        }

        return $user->hasAnyRole(self::ROLES_TEMOINS);
    }

    /** Libellé lisible du témoin, pour les écrans et les messages. */
    public static function witnessLabel(): string
    {
        return 'la comptabilité';
    }
}
