<?php

namespace App\Services;

use App\Models\PermissionGrant;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Schema;

/**
 * Décide si une personne détient un droit.
 *
 * Trois sources, dans cet ordre :
 *
 *   1. un refus explicite, sur la personne ou sur l'un de ses rôles ;
 *   2. une autorisation explicite, sur la personne ou sur l'un de ses rôles ;
 *   3. le catalogue, qui donne le gabarit par défaut.
 *
 * Le refus explicite l'emporte toujours. C'est la règle des systèmes de droits
 * éprouvés, et la seule qui reste prévisible quand une personne cumule
 * plusieurs rôles : sans elle, il suffirait d'un second rôle pour rendre un
 * refus inopérant, et « le comptable ne crée pas d'article » ne tiendrait plus
 * dès lors qu'il porte aussi le rôle d'économe.
 *
 * Sans aucune surcharge en base, la décision est exactement celle du
 * catalogue : la table ne porte que les écarts.
 */
class PermissionResolver
{
    /** @var array<int, array<string, string>> Surcharges par utilisateur, mémorisées le temps de la requête. */
    private array $cache = [];

    public function allows(?User $user, string $permission): bool
    {
        if ($user === null || $user->is_active === false) {
            return false;
        }

        $surcharges = $this->surchargesPour($user);

        if (($surcharges[$permission] ?? null) === PermissionGrant::EFFET_DENY) {
            return false;
        }

        if (($surcharges[$permission] ?? null) === PermissionGrant::EFFET_ALLOW) {
            return true;
        }

        return $user->hasAnyRole(PermissionCatalog::roles($permission));
    }

    /** Droits refusés à cette personne alors que ses rôles les lui donnaient. */
    public function deniedPermissions(User $user): array
    {
        return array_keys(array_filter(
            $this->surchargesPour($user),
            static fn (string $effet): bool => $effet === PermissionGrant::EFFET_DENY
        ));
    }

    /** Vide la mémoire : à appeler après avoir modifié des surcharges. */
    public function forget(?User $user = null): void
    {
        if ($user === null) {
            $this->cache = [];

            return;
        }

        unset($this->cache[$user->id]);
    }

    /**
     * Surcharges applicables, droit => effet.
     *
     * Un refus porté par un rôle l'emporte sur une autorisation portée par un
     * autre rôle : on retient donc le refus dès qu'il apparaît. La surcharge
     * nominative, elle, est lue en dernier et écrase celles des rôles — c'est
     * l'exception que le directeur accorde à une personne précise.
     *
     * @return array<string, string>
     */
    private function surchargesPour(User $user): array
    {
        if (isset($this->cache[$user->id])) {
            return $this->cache[$user->id];
        }

        // La table peut manquer : migrations non jouées, ou base d'un
        // établissement installé avant cette version.
        if (!Schema::hasTable('permission_grants')) {
            return $this->cache[$user->id] = [];
        }

        $roles = $this->rolesDe($user);
        $effets = [];

        if ($roles !== []) {
            $lignes = PermissionGrant::query()
                ->where('subject_type', PermissionGrant::SUJET_ROLE)
                ->whereIn('subject_id', $roles)
                ->get(['permission', 'effect']);

            foreach ($lignes as $ligne) {
                if (($effets[$ligne->permission] ?? null) === PermissionGrant::EFFET_DENY) {
                    continue;   // un refus déjà posé par un autre rôle ne se lève pas
                }

                $effets[$ligne->permission] = $ligne->effect;
            }
        }

        $nominatives = PermissionGrant::query()
            ->where('subject_type', PermissionGrant::SUJET_USER)
            ->where('subject_id', (string) $user->id)
            ->get(['permission', 'effect']);

        foreach ($nominatives as $ligne) {
            $effets[$ligne->permission] = $ligne->effect;
        }

        return $this->cache[$user->id] = $effets;
    }

    /** @return list<string> rôles détenus, pivot et colonne héritée confondus */
    private function rolesDe(User $user): array
    {
        $roles = $user->relationLoaded('roles')
            ? $user->roles->pluck('slug')->all()
            : $user->roles()->pluck('slug')->all();

        if ($user->role) {
            $roles[] = $user->role;
        }

        return array_values(array_unique($roles));
    }
}
