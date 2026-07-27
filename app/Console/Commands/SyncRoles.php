<?php

namespace App\Console\Commands;

use App\Support\RoleCatalog;
use Illuminate\Console\Command;

/**
 * Aligne la table des rôles sur le référentiel applicatif.
 *
 * Lancée par l'entrypoint du conteneur après les migrations, à chaque
 * démarrage : c'est ce qui fait qu'un rôle ajouté au catalogue arrive
 * automatiquement en production, sans intervention manuelle.
 */
class SyncRoles extends Command
{
    protected $signature = 'roles:sync';

    protected $description = 'Synchronise les rôles de l\'application avec le référentiel (idempotent)';

    public function handle(): int
    {
        $result = RoleCatalog::sync();

        $this->info("Rôles synchronisés : {$result['created']} créé(s), {$result['updated']} mis à jour.");

        return self::SUCCESS;
    }
}
