<?php

namespace App\Services;

use App\Models\PermissionGrant;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\PermissionScope;
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
    /** @var array<int, array<string, array{effect: string, scope: ?string}>> Surcharges par utilisateur, mémorisées le temps de la requête. */
    private array $cache = [];

    public function allows(?User $user, string $permission): bool
    {
        if ($user === null || $user->is_active === false) {
            return false;
        }

        $surcharge = $this->surchargesPour($user)[$permission] ?? null;

        if (($surcharge['effect'] ?? null) === PermissionGrant::EFFET_DENY) {
            return false;
        }

        if (($surcharge['effect'] ?? null) === PermissionGrant::EFFET_ALLOW) {
            return true;
        }

        return $user->hasAnyRole(PermissionCatalog::roles($permission));
    }

    /** Droits refusés à cette personne alors que ses rôles les lui donnaient. */
    public function deniedPermissions(User $user): array
    {
        return array_keys(array_filter(
            $this->surchargesPour($user),
            static fn (array $s): bool => $s['effect'] === PermissionGrant::EFFET_DENY
        ));
    }

    /**
     * Étendue des données que ce droit laisse voir à cette personne.
     *
     * Sans portée déclarée : tout l'établissement, comme avant. Plusieurs
     * portées héritées de plusieurs rôles se résolvent par la plus étroite —
     * une restriction ne se lève pas en ajoutant un rôle.
     */
    public function scopeFor(?User $user, string $permission): string
    {
        if ($user === null) {
            return PermissionScope::PROPRE;
        }

        return $this->surchargesPour($user)[$permission]['scope'] ?? PermissionScope::DEFAUT;
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
                ->get(['permission', 'effect', 'scope']);

            foreach ($lignes as $ligne) {
                $connu = $effets[$ligne->permission] ?? null;

                // Un refus déjà posé par un autre rôle ne se lève pas ; la
                // portée, elle, se resserre toujours vers la plus étroite.
                $effets[$ligne->permission] = [
                    'effect' => ($connu['effect'] ?? null) === PermissionGrant::EFFET_DENY
                        ? PermissionGrant::EFFET_DENY
                        : $ligne->effect,
                    'scope'  => PermissionScope::laPlusEtroite($connu['scope'] ?? null, $ligne->scope),
                ];
            }
        }

        $nominatives = PermissionGrant::query()
            ->where('subject_type', PermissionGrant::SUJET_USER)
            ->where('subject_id', (string) $user->id)
            ->get(['permission', 'effect', 'scope']);

        foreach ($nominatives as $ligne) {
            $effets[$ligne->permission] = ['effect' => $ligne->effect, 'scope' => $ligne->scope];
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
