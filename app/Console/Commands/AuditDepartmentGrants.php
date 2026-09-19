<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\DepartmentRoles;
use App\Support\PermissionCatalog;
use Illuminate\Console\Command;

/**
 * Liste les comptes dont l'accès reposait sur l'octroi implicite du
 * département, désormais retiré.
 *
 * Cet octroi ne pouvait pas être converti automatiquement en rôles explicites :
 * il accordait le bloc entier du département, escalade comprise. Rattacher un
 * réceptionniste à « Direction Générale » lui donnait manager et admin. Les
 * transcrire tels quels aurait gravé cette escalade dans les rôles au lieu de
 * la supprimer.
 *
 * La commande rapporte donc, et n'écrit rien. Le directeur accorde ensuite les
 * rôles qu'il juge nécessaires, un par un.
 */
class AuditDepartmentGrants extends Command
{
    protected $signature = 'roles:audit-departements';

    protected $description = "Liste les comptes qui perdaient l'accès conféré implicitement par leur département";

    public function handle(): int
    {
        $comptes = User::with(['roles', 'department'])->get()
            ->filter(fn (User $u) => $u->department?->slug !== null);

        if ($comptes->isEmpty()) {
            $this->info('Aucun compte rattaché à un département.');

            return self::SUCCESS;
        }

        $lignes = [];

        foreach ($comptes as $utilisateur) {
            $detenus  = $this->rolesDetenus($utilisateur);
            $conferes = DepartmentRoles::for($utilisateur->department->slug);

            // Ce que le département donnait en plus de ce que la personne
            // détient réellement : c'est exactement ce qui vient d'être perdu.
            $perdus = array_values(array_diff($conferes, $detenus));

            if ($perdus === []) {
                continue;
            }

            $lignes[] = [
                $utilisateur->email,
                $utilisateur->department->name,
                implode(', ', $detenus) ?: '—',
                implode(', ', $perdus),
                $this->comptePermissions($perdus, $detenus),
            ];
        }

        if ($lignes === []) {
            $this->info("Aucun compte ne dépendait de l'octroi implicite du département.");

            return self::SUCCESS;
        }

        $this->warn(count($lignes) . " compte(s) dépendaient de l'octroi implicite de leur département.");
        $this->newLine();
        $this->table(
            ['Compte', 'Département', 'Rôles détenus', 'Rôles perdus', 'Droits perdus'],
            $lignes
        );
        $this->newLine();
        $this->line("Accordez les rôles nécessaires explicitement. Ne recopiez pas la colonne");
        $this->line("« Rôles perdus » telle quelle : elle contient le bloc entier du département,");
        $this->line("escalade comprise — c'est précisément ce qui vient d'être supprimé.");

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function rolesDetenus(User $utilisateur): array
    {
        $roles = $utilisateur->roles->pluck('slug')->all();

        if ($utilisateur->role) {
            $roles[] = $utilisateur->role;
        }

        $roles = array_values(array_unique($roles));
        sort($roles);

        return $roles;
    }

    /** Nombre de droits que les rôles perdus ouvraient et que les rôles détenus n'ouvrent pas. */
    private function comptePermissions(array $perdus, array $detenus): int
    {
        $ouverts = [];
        foreach ($perdus as $role) {
            $ouverts = array_merge($ouverts, PermissionCatalog::forRole($role));
        }

        $conserves = [];
        foreach ($detenus as $role) {
            $conserves = array_merge($conserves, PermissionCatalog::forRole($role));
        }

        return count(array_unique(array_diff($ouverts, $conserves)));
    }
}
