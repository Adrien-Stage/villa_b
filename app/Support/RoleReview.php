<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Revue des comptes : ce qu'un administrateur doit trancher après la reprise
 * des rôles, et ce qui empêche l'établissement de fonctionner.
 *
 * Rien n'est corrigé ici : chaque constat nomme les comptes et dit quoi
 * décider. Une décision sur un compte est une décision humaine.
 */
class RoleReview
{
    public const A_CORRIGER = 'à corriger';

    public const A_CONFIRMER = 'à confirmer';

    public const INFORMATION = 'information';

    /**
     * @return list<array{code: string, gravite: string, constat: string, decision: string, comptes: list<string>}>
     */
    public static function constats(): array
    {
        $actifs = User::query()->where('is_active', true)->with('roles')->orderBy('name')->get();

        $constats = [];

        $cumulCuisineSalle = $actifs->filter(fn (User $u) => $u->hasRole('restaurant_chief') && $u->hasRole('restaurant_manager'));
        if ($cumulCuisineSalle->isNotEmpty()) {
            $constats[] = self::constat('cumul_cuisine_salle', self::A_CONFIRMER,
                'Chef de cuisine et responsable de restaurant sur le même compte.',
                'La salle et la caisse sont passées au responsable de restaurant ; le chef de cuisine qui les tenait a reçu les deux rôles pour ne rien perdre. Retirer celui qui ne correspond pas à la fonction réelle.',
                $cumulCuisineSalle);
        }

        $retires = array_column(array_filter(RoleCatalog::all(), fn (array $r) => $r['statut'] === RoleCatalog::RETIRE), 'slug');
        $porteursRetires = $actifs->filter(fn (User $u) => array_intersect($u->rolesDetenus(), $retires) !== []);
        if ($porteursRetires->isNotEmpty()) {
            $constats[] = self::constat('role_retire', self::A_CORRIGER,
                'Rôle retiré du référentiel (Technicien IT, Responsable RH).',
                'Ce rôle ne donne plus aucun droit. Le service informatique se crée en administrateur depuis la console ; les ressources humaines relèvent de leur plateforme. Retirer le rôle.',
                $porteursRetires);
        }

        $cumulsInterdits = $actifs->filter(fn (User $u) => ! DutySegregation::isCompatible($u->rolesDetenus()));
        if ($cumulsInterdits->isNotEmpty()) {
            $constats[] = self::constat('cumul_interdit', self::A_CORRIGER,
                'Cumul de rôles incompatibles (séparation des tâches).',
                "Retirer l'un des rôles, ou confirmer la dérogation motivée si elle a été accordée.",
                $cumulsInterdits);
        }

        $connus = array_column(RoleCatalog::all(), 'slug');
        $inconnus = $actifs->filter(fn (User $u) => array_diff($u->rolesDetenus(), $connus) !== []);
        if ($inconnus->isNotEmpty()) {
            $constats[] = self::constat('role_inconnu', self::A_CORRIGER,
                'Rôle absent du référentiel.',
                'Ce rôle ne donne aucun droit : attribuer le rôle du référentiel qui correspond à la fonction.',
                $inconnus);
        }

        $sansRole = $actifs->filter(fn (User $u) => $u->rolesDetenus() === []);
        if ($sansRole->isNotEmpty()) {
            $constats[] = self::constat('sans_role', self::A_CORRIGER,
                'Compte actif sans aucun rôle.',
                'Attribuer un rôle, ou désactiver le compte.',
                $sansRole);
        }

        if ($actifs->doesntContain(fn (User $u) => $u->exerce(['accountant']))) {
            $constats[] = self::constat('aucun_comptable', self::A_CORRIGER,
                'Aucun compte en comptabilité.',
                'La comptabilité contresigne toute caisse comptée : sans comptable ni responsable administratif et financier, les caisses resteront en attente de contrôle. Créer au moins un compte, même pour un comptable externe.',
                collect());
        }

        if ($actifs->doesntContain(fn (User $u) => $u->hasRole(RoleCatalog::ADMIN))) {
            $constats[] = self::constat('aucun_administrateur', self::INFORMATION,
                'Aucun administrateur.',
                "L'administrateur — le service informatique — se crée depuis la console. D'ici là, le manager gère les comptes.",
                collect());
        }

        return $constats;
    }

    /** @param  Collection<int, User>  $comptes */
    private static function constat(string $code, string $gravite, string $constat, string $decision, Collection $comptes): array
    {
        return [
            'code' => $code,
            'gravite' => $gravite,
            'constat' => $constat,
            'decision' => $decision,
            'comptes' => $comptes->map(fn (User $u) => "{$u->name} <{$u->email}>")->values()->all(),
        ];
    }
}
