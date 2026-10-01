<?php

namespace App\Support;

use App\Models\User;

/**
 * Le personnel de l'établissement tel que la console d'orchestration le voit :
 * identité, rôles détenus, état du compte. Les clients du portail n'en font
 * pas partie.
 *
 * Les rôles sont ceux des affectations (User::rolesDetenus), seules à faire
 * foi : la colonne héritée users.role peut être périmée.
 */
class StaffDirectory
{
    /**
     * @return list<array{id: int, name: string, email: string, phone: ?string, roles: list<string>,
     *                    lecture_seule: list<string>, actif: bool, departement: ?string, derniere_connexion: ?string}>
     */
    public static function comptes(): array
    {
        return User::query()
            ->with(['roles', 'department'])
            ->orderBy('name')
            ->get()
            ->map(static fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'roles' => $u->rolesDetenus(),
                'lecture_seule' => $u->roles
                    ->filter(static fn ($role) => ($role->pivot->level ?? null) === 'read')
                    ->pluck('slug')->values()->all(),
                'actif' => (bool) $u->is_active,
                'departement' => $u->department?->name,
                'derniere_connexion' => $u->last_login_at?->toIso8601String(),
            ])
            ->reject(static fn (array $compte): bool => $compte['roles'] === ['customer_guest'])
            ->values()
            ->all();
    }
}
