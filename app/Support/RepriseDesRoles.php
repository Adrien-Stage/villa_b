<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Reprise des comptes existants dans le référentiel hiérarchique des rôles.
 *
 * Lancée par une migration, donc une fois par établissement, au démarrage
 * qui suit la mise à jour. Idempotente : relancée, elle ne trouve plus rien à
 * reprendre. Elle ne retire aucun droit à personne : elle rend explicite ce
 * que la colonne héritée users.role accordait, et préserve ce que le chef de
 * cuisine faisait avant que la salle ne passe au responsable de restaurant.
 *
 * Travaille sur les tables, pas sur les modèles : une migration doit rester
 * valable quand les modèles évolueront.
 */
class RepriseDesRoles
{
    /** Ancien rôle hérité => rôle du référentiel aux droits identiques. */
    private const EQUIVALENTS = [
        'housekeeping' => 'housekeeping_staff',
    ];

    /**
     * @return array{affectes: int, ignores: list<string>, realignes: int, dedoubles: int}
     */
    public static function executer(): array
    {
        [$affectes, $ignores] = self::affecterLesRolesHerites();

        return [
            'affectes' => $affectes,
            'ignores' => $ignores,
            'realignes' => self::realignerLaColonne(),
            'dedoubles' => self::dedoublerLesChefsDeCuisine(),
        ];
    }

    /**
     * Un compte sans affectation n'existait que par sa colonne users.role :
     * elle devient une vraie affectation, sans niveau — donc en écriture,
     * comme la colonne l'accordait.
     *
     * @return array{0: int, 1: list<string>} affectations créées, rôles inconnus ignorés
     */
    public static function affecterLesRolesHerites(): array
    {
        $comptes = DB::table('users')
            ->whereNotNull('role')
            ->where('role', '!=', '')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('role_user')->whereColumn('role_user.user_id', 'users.id'))
            ->get(['id', 'role']);

        $affectes = 0;
        $ignores = [];

        foreach ($comptes as $compte) {
            $roleId = self::idDuRole(self::EQUIVALENTS[$compte->role] ?? $compte->role);

            // Un rôle que ni la base ni le référentiel ne connaissent ne donnait
            // aucun droit : il n'y a rien à rendre explicite.
            if ($roleId === null) {
                $ignores[] = $compte->role;

                continue;
            }

            DB::table('role_user')->insert([
                'user_id' => $compte->id, 'role_id' => $roleId, 'level' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $affectes++;
        }

        return [$affectes, array_values(array_unique($ignores))];
    }

    /**
     * La console remplace les affectations sans toucher la colonne : elle
     * garde parfois un rôle retiré. La colonne est réalignée sur le rôle
     * principal des affectations — le plus haut dans la hiérarchie.
     */
    public static function realignerLaColonne(): int
    {
        $affectations = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->orderBy('roles.sort_order')
            ->get(['role_user.user_id', 'roles.slug'])
            ->groupBy('user_id');

        $realignes = 0;

        foreach ($affectations as $userId => $lignes) {
            $slugs = $lignes->pluck('slug')->all();
            $colonne = DB::table('users')->where('id', $userId)->value('role');

            if (! in_array($colonne, $slugs, true)) {
                DB::table('users')->where('id', $userId)->update(['role' => $slugs[0], 'updated_at' => now()]);
                $realignes++;
            }
        }

        return $realignes;
    }

    /**
     * La salle et la caisse passent du chef de cuisine au responsable de
     * restaurant. Qui tenait le rôle de chef de cuisine reçoit aussi celui de
     * responsable, au même niveau : il garde exactement ce qu'il faisait.
     * L'administrateur retire ensuite le rôle qui ne correspond pas — la
     * revue des comptes signale ces cumuls (RoleReview).
     */
    public static function dedoublerLesChefsDeCuisine(): int
    {
        $chef = DB::table('roles')->where('slug', 'restaurant_chief')->value('id');

        if (! $chef) {
            return 0;
        }

        $affectations = DB::table('role_user')->where('role_id', $chef)->get(['user_id', 'level']);

        if ($affectations->isEmpty()) {
            return 0;
        }

        $responsable = self::idDuRole('restaurant_manager');
        $dedoubles = 0;

        foreach ($affectations as $affectation) {
            $dejaResponsable = DB::table('role_user')
                ->where('user_id', $affectation->user_id)
                ->where('role_id', $responsable)
                ->exists();

            if (! $dejaResponsable) {
                DB::table('role_user')->insert([
                    'user_id' => $affectation->user_id, 'role_id' => $responsable, 'level' => $affectation->level,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $dedoubles++;
            }
        }

        return $dedoubles;
    }

    /**
     * Identifiant d'un rôle en base. Un rôle du référentiel qui n'y est pas
     * encore est créé : la synchronisation des rôles ne passe qu'après les
     * migrations, au démarrage.
     */
    private static function idDuRole(string $slug): ?int
    {
        $id = DB::table('roles')->where('slug', $slug)->value('id');

        if ($id) {
            return (int) $id;
        }

        $enregistrement = RoleCatalog::enregistrement($slug);

        if ($enregistrement === null) {
            return null;
        }

        return (int) DB::table('roles')->insertGetId($enregistrement + ['created_at' => now(), 'updated_at' => now()]);
    }
}
